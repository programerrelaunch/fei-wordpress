<?php
/**
 * Lead intake.
 *
 *  POST /wp-json/fei/v1/lead         ← browser (same JSON contract as the Astro site)
 *  POST /wp-json/fei/v1/lead-error   ← browser failure telemetry (mirrors flow.fei.edu/webhook/error)
 *
 * Pipeline (persist-first so a downstream outage never loses a lead):
 *   1. validate + anti-spam + rate-limit
 *   2. upsert `fei_lead` post (dedupe by preRegId, then email+phone within 30 days)
 *   3. enqueue jobs: n8n forward, Zoho upsert, automation kick-off
 *   4. process queue inline (fast path) and again via cron with back-off
 */

defined( 'ABSPATH' ) || exit;

final class FEI_Leads {

	const CPT        = 'fei_lead';
	const QUEUE_OPT  = 'fei_job_queue';
	const MAX_TRIES  = 6;

	private static ?FEI_Leads $instance = null;

	public static function instance(): FEI_Leads {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'fei_process_queue', array( __CLASS__, 'process_queue' ) );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'add_meta_boxes', array( $this, 'meta_box' ) );
		add_action( 'admin_post_fei_export_leads', array( $this, 'export_csv' ) );
	}

	public static function register_post_type(): void {
		register_post_type(
			self::CPT,
			array(
				'labels'              => array( 'name' => __( 'Leads', 'fei-core' ), 'singular_name' => __( 'Lead', 'fei-core' ) ),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'fei-core',
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'show_in_rest'        => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'        => true,
			)
		);
		register_post_status( 'fei_new', array( 'label' => 'New', 'public' => false, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true, 'label_count' => _n_noop( 'New <span class="count">(%s)</span>', 'New <span class="count">(%s)</span>' ) ) );
		register_post_status( 'fei_contacted', array( 'label' => 'Contacted', 'public' => false, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true, 'label_count' => _n_noop( 'Contacted <span class="count">(%s)</span>', 'Contacted <span class="count">(%s)</span>' ) ) );
		register_post_status( 'fei_enrolled', array( 'label' => 'Enrolled', 'public' => false, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true, 'label_count' => _n_noop( 'Enrolled <span class="count">(%s)</span>', 'Enrolled <span class="count">(%s)</span>' ) ) );
		register_post_status( 'fei_closed', array( 'label' => 'Closed', 'public' => false, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true, 'label_count' => _n_noop( 'Closed <span class="count">(%s)</span>', 'Closed <span class="count">(%s)</span>' ) ) );
	}

	/* ---------- REST ---------- */

	public function routes(): void {
		register_rest_route(
			'fei/v1',
			'/lead',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_lead' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'fei/v1',
			'/lead-error',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_error' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function handle_lead( WP_REST_Request $req ) {
		$in   = $req->get_json_params();
		$in   = is_array( $in ) ? $in : $req->get_body_params();
		$lang = in_array( $in['lang'] ?? '', array( 'en', 'es' ), true ) ? $in['lang'] : fei_lang();
		$ip   = self::client_ip();

		// Same-origin guard (CSRF-lite for a public endpoint): Origin/Referer must be this host or a Vercel preview.
		$origin = (string) ( $req->get_header( 'origin' ) ?: $req->get_header( 'referer' ) );
		$host   = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $origin && wp_parse_url( $origin, PHP_URL_HOST ) !== $host && ! apply_filters( 'fei_allow_origin', false, $origin ) ) {
			return new WP_Error( 'fei_origin', 'Forbidden origin', array( 'status' => 403 ) );
		}

		// Rate limit per IP.
		$limit = (int) fei_opt( 'rate_limit_per_hour', 8 );
		$tkey  = 'fei_rl_' . md5( $ip );
		$count = (int) get_transient( $tkey );
		if ( $limit > 0 && $count >= $limit ) {
			return new WP_Error( 'fei_rate', 'Too many requests', array( 'status' => 429 ) );
		}
		set_transient( $tkey, $count + 1, HOUR_IN_SECONDS );

		$result = FEI_Lead_Validator::validate( $in, FEI_Programs::valid_keys(), $lang );
		if ( ! $result['ok'] ) {
			return new WP_REST_Response( array( 'ok' => false, 'errors' => $result['errors'] ), 422 );
		}
		$lead = $result['lead'];
		$lead['ip'] = fei_opt( 'capture_client_ip', 1 ) ? $ip : '';
		$lead['userAgent'] = mb_substr( (string) $req->get_header( 'user_agent' ), 0, 300 );
		$lead['receivedAt'] = gmdate( 'c' );

		$spam = FEI_Lead_Validator::spam_reason( $in, $lead );
		if ( '' !== $spam ) {
			fei_log( 'spam', 'dropped', array( 'reason' => $spam, 'ip' => $ip ) );
			// Pretend success so bots learn nothing; nothing is stored.
			return new WP_REST_Response( array( 'ok' => true, 'id' => fei_uuid4() ), 200 );
		}
		if ( ! self::turnstile_ok( (string) ( $in['cf-turnstile-response'] ?? '' ), $ip ) ) {
			return new WP_Error( 'fei_captcha', 'Verification failed', array( 'status' => 403 ) );
		}

		$post_id = self::upsert( $lead );
		if ( ! $post_id ) {
			return new WP_Error( 'fei_store', 'Could not store lead', array( 'status' => 500 ) );
		}
		$lead['wpLeadId'] = $post_id;
		if ( '' === $lead['preRegId'] ) {
			$lead['preRegId'] = (string) get_post_meta( $post_id, '_fei_pre_reg_id', true );
		}

		self::enqueue( 'n8n', $post_id );
		self::enqueue( 'zoho', $post_id );
		self::enqueue( 'automation', $post_id );
		do_action( 'fei_lead_received', $post_id, $lead );

		// Fast path: try the queue now (bounded), cron retries the rest.
		self::process_queue( 3 );

		return new WP_REST_Response(
			array(
				'ok'       => true,
				'id'       => $lead['preRegId'],
				'lead_id'  => $post_id,
				'stage'    => $lead['stage'],
			),
			200
		);
	}

	public function handle_error( WP_REST_Request $req ) {
		$in = $req->get_json_params();
		if ( ! is_array( $in ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 400 );
		}
		$safe = array_intersect_key( $in, array_flip( array( 'kind', 'at', 'reason', 'httpStatus', 'message', 'responseBody', 'formId', 'pageUrl', 'userAgent' ) ) );
		fei_log( 'client-error', 'lead_submit_error', array_map( static fn( $v ) => is_scalar( $v ) ? mb_substr( (string) $v, 0, 500 ) : '', $safe ) );
		$url = fei_opt( 'error_webhook_url' );
		if ( $url ) {
			wp_remote_post( $url, array( 'timeout' => 3, 'blocking' => false, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $in ) ) );
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/* ---------- Storage ---------- */

	/** Insert or update the fei_lead post. Returns post ID. */
	public static function upsert( array $lead ): int {
		$existing = 0;
		if ( $lead['preRegId'] ) {
			$found = get_posts( array( 'post_type' => self::CPT, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_key' => '_fei_pre_reg_id', 'meta_value' => $lead['preRegId'] ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			$existing = $found ? (int) $found[0] : 0;
		}
		if ( ! $existing && $lead['email'] ) {
			$found = get_posts(
				array(
					'post_type'      => self::CPT,
					'post_status'    => 'any',
					'fields'         => 'ids',
					'posts_per_page' => 1,
					'date_query'     => array( array( 'after' => '30 days ago' ) ),
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
						'relation' => 'AND',
						array( 'key' => '_fei_email', 'value' => $lead['email'] ),
						array( 'key' => '_fei_phone', 'value' => $lead['phone'] ),
					),
				)
			);
			$existing = $found ? (int) $found[0] : 0;
		}

		$title = trim( $lead['firstName'] . ' ' . $lead['lastName'] ) . ' — ' . ( $lead['program'] ?: 'No program' );
		if ( $existing ) {
			$prev = (array) get_post_meta( $existing, '_fei_lead', true );
			// Merge: new non-empty values win, but never downgrade stage full → pre.
			$merged = array_merge( $prev, array_filter( $lead, static fn( $v ) => '' !== $v && null !== $v ) );
			if ( ( $prev['stage'] ?? '' ) === 'full' ) {
				$merged['stage'] = 'full';
			}
			$lead = $merged;
			wp_update_post( array( 'ID' => $existing, 'post_title' => $title ) );
			$post_id = $existing;
			update_post_meta( $post_id, '_fei_updated_count', (int) get_post_meta( $post_id, '_fei_updated_count', true ) + 1 );
		} else {
			$post_id = (int) wp_insert_post(
				array(
					'post_type'   => self::CPT,
					'post_status' => 'fei_new',
					'post_title'  => $title,
				),
				true
			);
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				return 0;
			}
			if ( '' === $lead['preRegId'] ) {
				$lead['preRegId'] = fei_uuid4();
			}
			update_post_meta( $post_id, '_fei_created_gmt', gmdate( 'c' ) );
		}
		update_post_meta( $post_id, '_fei_lead', $lead );
		update_post_meta( $post_id, '_fei_pre_reg_id', $lead['preRegId'] );
		update_post_meta( $post_id, '_fei_email', $lead['email'] );
		update_post_meta( $post_id, '_fei_phone', $lead['phone'] );
		update_post_meta( $post_id, '_fei_program', $lead['program'] );
		update_post_meta( $post_id, '_fei_stage', $lead['stage'] );
		update_post_meta( $post_id, '_fei_lang', $lead['lang'] ?? 'en' );
		update_post_meta( $post_id, '_fei_source', FEI_Zoho_Mapper::lead_source( $lead ) );
		return $post_id;
	}

	public static function get_lead( int $post_id ): array {
		$l = get_post_meta( $post_id, '_fei_lead', true );
		return is_array( $l ) ? $l : array();
	}

	/** Payload shape the n8n webhook has always received (keeps the existing workflow working). */
	public static function n8n_payload( array $lead ): array {
		$keys = array_merge(
			array( 'stage', 'preRegId', 'firstName', 'lastName', 'email', 'phone', 'zip', 'program', 'programType', 'smsMarketingConsent', 'smsTransactionalConsent', 'referrer', 'documentReferrer', 'ip', 'fbc', 'fbp' ),
			fei_touch_keys()
		);
		$out = array();
		foreach ( $keys as $k ) {
			$out[ $k ] = $lead[ $k ] ?? '';
		}
		$out['source']   = 'wordpress';
		$out['lang']     = $lead['lang'] ?? 'en';
		$out['wpLeadId'] = $lead['wpLeadId'] ?? 0;
		return $out;
	}

	/* ---------- Job queue (option-backed, no extra tables) ---------- */

	public static function enqueue( string $job, int $lead_id, int $delay = 0 ): void {
		$q   = (array) get_option( self::QUEUE_OPT, array() );
		$key = $job . ':' . $lead_id;
		if ( isset( $q[ $key ] ) ) {
			return;
		}
		$q[ $key ] = array( 'job' => $job, 'lead_id' => $lead_id, 'tries' => 0, 'next' => time() + $delay, 'last_error' => '' );
		update_option( self::QUEUE_OPT, $q, false );
	}

	public static function queue_size(): int {
		return count( (array) get_option( self::QUEUE_OPT, array() ) );
	}

	public static function process_queue( int $max = 25 ): void {
		if ( get_transient( 'fei_queue_lock' ) ) {
			return;
		}
		set_transient( 'fei_queue_lock', 1, 55 );
		$q = (array) get_option( self::QUEUE_OPT, array() );
		$n = 0;
		foreach ( $q as $key => $job ) {
			if ( $n >= $max ) {
				break;
			}
			if ( $job['next'] > time() ) {
				continue;
			}
			++$n;
			$ok  = false;
			$err = '';
			try {
				$ok = self::run_job( $job['job'], (int) $job['lead_id'], $err );
			} catch ( Throwable $e ) {
				$err = $e->getMessage();
			}
			if ( $ok ) {
				unset( $q[ $key ] );
				continue;
			}
			$q[ $key ]['tries']++;
			$q[ $key ]['last_error'] = mb_substr( $err, 0, 300 );
			if ( $q[ $key ]['tries'] >= self::MAX_TRIES ) {
				fei_log( 'queue', 'job dead-lettered', array( 'job' => $key, 'error' => $err ) );
				update_post_meta( (int) $job['lead_id'], '_fei_failed_' . $job['job'], $err );
				unset( $q[ $key ] );
				continue;
			}
			$q[ $key ]['next'] = time() + min( 3600, 30 * ( 2 ** $q[ $key ]['tries'] ) );
		}
		update_option( self::QUEUE_OPT, $q, false );
		delete_transient( 'fei_queue_lock' );
	}

	private static function run_job( string $job, int $lead_id, string &$err ): bool {
		$lead = self::get_lead( $lead_id );
		if ( ! $lead ) {
			$err = 'lead missing';
			return true; // nothing to retry.
		}
		$lead['wpLeadId'] = $lead_id;
		switch ( $job ) {
			case 'n8n':
				if ( ! fei_opt( 'forward_to_n8n', 1 ) ) {
					return true;
				}
				$url = fei_opt( 'lead_webhook_url' );
				if ( ! $url ) {
					return true;
				}
				$res = wp_remote_post( $url, array( 'timeout' => 10, 'headers' => array( 'Content-Type' => 'application/json', 'X-FEI-Source' => 'wordpress' ), 'body' => wp_json_encode( self::n8n_payload( $lead ) ) ) );
				if ( is_wp_error( $res ) ) {
					$err = $res->get_error_message();
					return false;
				}
				$code = (int) wp_remote_retrieve_response_code( $res );
				if ( $code >= 200 && $code < 300 ) {
					update_post_meta( $lead_id, '_fei_n8n_at', gmdate( 'c' ) );
					return true;
				}
				$err = 'HTTP ' . $code . ' ' . mb_substr( (string) wp_remote_retrieve_body( $res ), 0, 200 );
				return $code >= 400 && $code < 500 && 429 !== $code ? true : false; // 4xx (not 429) will not succeed on retry.
			case 'zoho':
				if ( ! fei_opt( 'zoho_enabled', 0 ) ) {
					return true;
				}
				return FEI_Zoho::instance()->sync_lead( $lead_id, $lead, $err );
			case 'automation':
				return FEI_Automation::instance()->on_new_lead( $lead_id, $lead, $err );
			default:
				$ok = apply_filters( 'fei_run_custom_job', null, $job, $lead_id, $lead );
				return null === $ok ? true : (bool) $ok;
		}
	}

	/* ---------- Helpers ---------- */

	public static function client_ip(): string {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $k ) {
			if ( ! empty( $_SERVER[ $k ] ) ) {
				$ip = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $k ] ) ) )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}

	private static function turnstile_ok( string $token, string $ip ): bool {
		$secret = (string) fei_opt( 'turnstile_secret', '' );
		if ( '' === $secret ) {
			return true; // not configured.
		}
		if ( '' === $token ) {
			return false;
		}
		$res = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array( 'timeout' => 5, 'body' => array( 'secret' => $secret, 'response' => $token, 'remoteip' => $ip ) )
		);
		if ( is_wp_error( $res ) ) {
			return true; // fail-open: never block a real lead on a verifier outage.
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		return ! empty( $body['success'] );
	}

	/* ---------- Admin list & export ---------- */

	public function columns( array $cols ): array {
		return array(
			'cb'      => $cols['cb'] ?? '',
			'title'   => __( 'Lead', 'fei-core' ),
			'program' => __( 'Program', 'fei-core' ),
			'stage'   => __( 'Stage', 'fei-core' ),
			'source'  => __( 'Source', 'fei-core' ),
			'sync'    => __( 'Sync', 'fei-core' ),
			'date'    => __( 'Date', 'fei-core' ),
		);
	}

	public function column( string $col, int $post_id ): void {
		switch ( $col ) {
			case 'program':
				echo esc_html( (string) get_post_meta( $post_id, '_fei_program', true ) );
				break;
			case 'stage':
				echo esc_html( (string) get_post_meta( $post_id, '_fei_stage', true ) );
				break;
			case 'source':
				echo esc_html( (string) get_post_meta( $post_id, '_fei_source', true ) );
				break;
			case 'sync':
				$n = get_post_meta( $post_id, '_fei_n8n_at', true ) ? '✔ n8n' : ( get_post_meta( $post_id, '_fei_failed_n8n', true ) ? '✖ n8n' : '… n8n' );
				$z = get_post_meta( $post_id, '_fei_zoho_id', true ) ? '✔ Zoho' : ( get_post_meta( $post_id, '_fei_failed_zoho', true ) ? '✖ Zoho' : ( fei_opt( 'zoho_enabled' ) ? '… Zoho' : '– Zoho' ) );
				echo esc_html( $n . ' · ' . $z );
				break;
		}
	}

	public function meta_box(): void {
		add_meta_box(
			'fei_lead_data',
			__( 'Lead data', 'fei-core' ),
			static function ( WP_Post $post ): void {
				$lead = self::get_lead( $post->ID );
				echo '<table class="widefat striped"><tbody>';
				foreach ( $lead as $k => $v ) {
					if ( '' === $v || null === $v ) {
						continue;
					}
					printf( '<tr><th style="width:220px">%s</th><td>%s</td></tr>', esc_html( $k ), esc_html( is_bool( $v ) ? ( $v ? 'yes' : 'no' ) : (string) $v ) );
				}
				foreach ( array( '_fei_n8n_at' => 'Forwarded to n8n', '_fei_zoho_id' => 'Zoho record ID', '_fei_failed_n8n' => 'n8n failure', '_fei_failed_zoho' => 'Zoho failure', '_fei_automation_log' => 'Automation' ) as $mk => $label ) {
					$mv = get_post_meta( $post->ID, $mk, true );
					if ( $mv ) {
						printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html( $label ), esc_html( is_array( $mv ) ? wp_json_encode_safe( $mv ) : (string) $mv ) );
					}
				}
				echo '</tbody></table>';
				printf( '<p><a class="button" href="%s">%s</a></p>', esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fei_export_leads' ), 'fei_export' ) ), esc_html__( 'Export all leads (CSV)', 'fei-core' ) );
			},
			self::CPT,
			'normal',
			'high'
		);
	}

	public function export_csv(): void {
		check_admin_referer( 'fei_export' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden' );
		}
		$ids = get_posts( array( 'post_type' => self::CPT, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC' ) );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=fei-leads-' . gmdate( 'Ymd-His' ) . '.csv' );
		$out  = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$cols = array_merge( array( 'wpLeadId', 'receivedAt', 'stage', 'preRegId', 'firstName', 'lastName', 'email', 'phone', 'zip', 'program', 'programType', 'lang', 'smsMarketingConsent', 'smsTransactionalConsent', 'referrer', 'documentReferrer', 'ip' ), fei_touch_keys(), array( 'zohoId', 'status' ) );
		fputcsv( $out, $cols );
		foreach ( $ids as $id ) {
			$l   = self::get_lead( (int) $id );
			$row = array();
			foreach ( $cols as $c ) {
				if ( 'wpLeadId' === $c ) {
					$row[] = $id;
				} elseif ( 'zohoId' === $c ) {
					$row[] = get_post_meta( (int) $id, '_fei_zoho_id', true );
				} elseif ( 'status' === $c ) {
					$row[] = get_post_status( (int) $id );
				} else {
					$v     = $l[ $c ] ?? '';
					$row[] = is_bool( $v ) ? ( $v ? 'yes' : 'no' ) : $v;
				}
			}
			fputcsv( $out, $row );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
}
