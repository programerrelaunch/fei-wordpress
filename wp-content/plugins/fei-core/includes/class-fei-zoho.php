<?php
/**
 * Zoho CRM v7 client: OAuth2 refresh-token grant, Leads search/upsert, Tasks, Notes.
 * Idempotent per lead (stores Zoho record ID on the WP lead). All HTTP via wp_remote_*.
 *
 * Setup (one-time, by someone with Zoho admin):
 *   1. api-console.zoho.com → Self Client → scopes:
 *      ZohoCRM.modules.leads.ALL, ZohoCRM.modules.tasks.ALL, ZohoCRM.modules.notes.ALL,
 *      ZohoCRM.settings.fields.READ, ZohoCRM.users.READ
 *   2. Generate grant code → exchange for refresh token (see migration/ZOHO-SETUP.md).
 *   3. Put client id/secret/refresh token in wp-config constants or Settings → Zoho.
 */

defined( 'ABSPATH' ) || exit;

final class FEI_Zoho {

	private static ?FEI_Zoho $instance = null;

	public static function instance(): FEI_Zoho {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'admin_post_fei_zoho_test', array( $this, 'admin_test' ) );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/* ---------- Endpoints ---------- */

	private function accounts_url(): string {
		return 'https://accounts.zoho.' . $this->dc() . '/oauth/v2/token';
	}

	private function api_url( string $path ): string {
		return 'https://www.zohoapis.' . $this->dc() . '/crm/v7/' . ltrim( $path, '/' );
	}

	private function dc(): string {
		$dc = (string) fei_opt( 'zoho_dc', 'com' );
		return in_array( $dc, array( 'com', 'eu', 'in', 'com.au', 'jp', 'ca', 'com.cn', 'sa' ), true ) ? $dc : 'com';
	}

	/* ---------- Auth ---------- */

	public function access_token( bool $force = false ): string {
		$cached = get_transient( 'fei_zoho_token' );
		if ( $cached && ! $force ) {
			return (string) $cached;
		}
		$res = wp_remote_post(
			$this->accounts_url(),
			array(
				'timeout' => 10,
				'body'    => array(
					'grant_type'    => 'refresh_token',
					'client_id'     => fei_opt( 'zoho_client_id' ),
					'client_secret' => fei_opt( 'zoho_client_secret' ),
					'refresh_token' => fei_opt( 'zoho_refresh_token' ),
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			throw new RuntimeException( 'Zoho token: ' . $res->get_error_message() );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( empty( $body['access_token'] ) ) {
			throw new RuntimeException( 'Zoho token: ' . ( $body['error'] ?? 'no access_token' ) );
		}
		$ttl = max( 60, (int) ( $body['expires_in'] ?? 3600 ) - 120 );
		set_transient( 'fei_zoho_token', $body['access_token'], $ttl );
		return (string) $body['access_token'];
	}

	/** @return array{code:int, body:array} */
	private function request( string $method, string $path, ?array $json = null, bool $retry = true ): array {
		$args = array(
			'method'  => $method,
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Zoho-oauthtoken ' . $this->access_token(),
				'Content-Type'  => 'application/json',
			),
		);
		if ( null !== $json ) {
			$args['body'] = wp_json_encode( $json );
		}
		$res = wp_remote_request( $this->api_url( $path ), $args );
		if ( is_wp_error( $res ) ) {
			throw new RuntimeException( 'Zoho HTTP: ' . $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true ) ?: array();
		if ( 401 === $code && $retry ) {
			$this->access_token( true );
			return $this->request( $method, $path, $json, false );
		}
		return array( 'code' => $code, 'body' => $body );
	}

	/* ---------- Leads ---------- */

	/** Find an existing Zoho lead by Web_Lead_ID, email, or phone. Returns record ID or ''. */
	public function find_lead( array $lead ): string {
		$module = fei_opt( 'zoho_module', 'Leads' );
		$map    = array_merge( FEI_Zoho_Mapper::default_map(), $this->override_map() );
		$checks = array();
		if ( ! empty( $lead['preRegId'] ) && ! empty( $map['preRegId'] ) ) {
			$checks[] = array( 'criteria', '(' . $map['preRegId'] . ':equals:' . $lead['preRegId'] . ')' );
		}
		if ( ! empty( $lead['email'] ) ) {
			$checks[] = array( 'email', $lead['email'] );
		}
		if ( ! empty( $lead['phone'] ) ) {
			$checks[] = array( 'phone', $lead['phone'] );
		}
		foreach ( $checks as [ $param, $value ] ) {
			$r = $this->request( 'GET', $module . '/search?' . http_build_query( array( $param => $value, 'per_page' => 1 ) ) );
			if ( 200 === $r['code'] && ! empty( $r['body']['data'][0]['id'] ) ) {
				return (string) $r['body']['data'][0]['id'];
			}
		}
		return '';
	}

	/**
	 * Upsert a WP lead into Zoho. Returns true on success; $err set on failure (retryable).
	 */
	public function sync_lead( int $lead_id, array $lead, string &$err ): bool {
		$module = fei_opt( 'zoho_module', 'Leads' );
		$extra  = array();
		if ( fei_opt( 'zoho_owner_id' ) ) {
			$extra['Owner'] = array( 'id' => (string) fei_opt( 'zoho_owner_id' ) );
		}
		if ( fei_opt( 'zoho_layout_id' ) ) {
			$extra['Layout'] = array( 'id' => (string) fei_opt( 'zoho_layout_id' ) );
		}
		$record = FEI_Zoho_Mapper::to_record( $lead, $this->override_map(), $extra );
		$record = apply_filters( 'fei_zoho_record', $record, $lead, $lead_id );

		$existing = (string) get_post_meta( $lead_id, '_fei_zoho_id', true ) ?: $this->find_lead( $lead );

		if ( $existing ) {
			$r = $this->request( 'PUT', $module . '/' . $existing, array( 'data' => array( $record ), 'trigger' => array( 'workflow', 'approval', 'blueprint' ) ) );
		} else {
			$r = $this->request( 'POST', $module, array( 'data' => array( $record ), 'trigger' => array( 'workflow', 'approval', 'blueprint' ) ) );
		}
		$row = $r['body']['data'][0] ?? array();
		if ( ( 200 === $r['code'] || 201 === $r['code'] ) && ( $row['code'] ?? '' ) === 'SUCCESS' ) {
			$zid = (string) ( $row['details']['id'] ?? $existing );
			update_post_meta( $lead_id, '_fei_zoho_id', $zid );
			update_post_meta( $lead_id, '_fei_zoho_synced_at', gmdate( 'c' ) );
			fei_log( 'zoho', $existing ? 'updated' : 'created', array( 'lead' => $lead_id, 'zoho' => $zid ) );

			if ( ! $existing && fei_opt( 'zoho_assign_task', 1 ) ) {
				$this->create_task( $zid, $lead );
			}
			$this->add_note( $zid, 'Web form submission (' . ( $lead['stage'] ?? 'full' ) . ")\n" . FEI_Zoho_Mapper::description( $lead ) );
			return true;
		}

		// Unknown field → strip the offending custom fields once and retry (lets the plugin work before the org adds custom fields).
		if ( ( $row['code'] ?? '' ) === 'INVALID_DATA' && ! empty( $row['details']['api_name'] ) && empty( $lead['_stripped'] ) ) {
			$bad = (string) $row['details']['api_name'];
			fei_log( 'zoho', 'field rejected, retrying without it', array( 'field' => $bad ) );
			$this->remember_bad_field( $bad );
			$lead['_stripped'] = true;
			return $this->sync_lead( $lead_id, $lead, $err );
		}
		$err = 'Zoho ' . $r['code'] . ' ' . wp_json_encode( $row ?: $r['body'] );
		fei_log( 'zoho', 'sync failed', array( 'lead' => $lead_id, 'err' => $err ) );
		return false;
	}

	public function create_task( string $zoho_lead_id, array $lead ): void {
		$lang = $lead['lang'] ?? 'en';
		$task = array(
			'Subject'     => ( 'es' === $lang ? 'Llamar nuevo lead web: ' : 'Call new web lead: ' ) . trim( $lead['firstName'] . ' ' . $lead['lastName'] ) . ' (' . ( $lead['program'] ?: 'no program' ) . ')',
			'Status'      => 'Not Started',
			'Priority'    => 'High',
			'Due_Date'    => gmdate( 'Y-m-d' ),
			// Leads/Contacts attach through Who_Id; $se_module tells Zoho which module the id belongs to.
			'Who_Id'      => array( 'id' => $zoho_lead_id ),
			'$se_module'  => 'Leads',
			'Description' => 'Speed-to-lead target: 5 minutes. Phone ' . $lead['phone'] . ', email ' . $lead['email'] . '. Preferred language: ' . strtoupper( $lang ) . '.',
		);
		if ( fei_opt( 'zoho_owner_id' ) ) {
			$task['Owner'] = array( 'id' => (string) fei_opt( 'zoho_owner_id' ) );
		}
		$r = $this->request( 'POST', 'Tasks', array( 'data' => array( $task ) ) );
		if ( 201 !== $r['code'] ) {
			fei_log( 'zoho', 'task failed', array( 'resp' => $r['body'] ) );
		}
	}

	public function add_note( string $zoho_lead_id, string $content ): void {
		$this->request(
			'POST',
			'Leads/' . $zoho_lead_id . '/Notes',
			array( 'data' => array( array( 'Note_Title' => 'Website', 'Note_Content' => mb_substr( $content, 0, 32000 ) ) ) )
		);
	}

	/** Update lead status in Zoho (used when WP lead status changes). */
	public function set_status( string $zoho_lead_id, string $status ): void {
		$this->request( 'PUT', 'Leads/' . $zoho_lead_id, array( 'data' => array( array( 'Lead_Status' => $status ) ) ) );
	}

	/* ---------- Field map helpers ---------- */

	private function override_map(): array {
		$json = (string) fei_opt( 'zoho_field_map_json', '' );
		$map  = $json ? ( json_decode( $json, true ) ?: array() ) : array();
		foreach ( (array) get_option( 'fei_zoho_bad_fields', array() ) as $api ) {
			$src = array_search( $api, FEI_Zoho_Mapper::default_map(), true );
			if ( $src && ! isset( $map[ $src ] ) ) {
				$map[ $src ] = '';
			}
		}
		return $map;
	}

	private function remember_bad_field( string $api ): void {
		$bad = (array) get_option( 'fei_zoho_bad_fields', array() );
		if ( ! in_array( $api, $bad, true ) ) {
			$bad[] = $api;
			update_option( 'fei_zoho_bad_fields', $bad, false );
		}
	}

	/** Fetch the org's Leads field API names (for the settings screen / docs). */
	public function list_fields(): array {
		$r = $this->request( 'GET', 'settings/fields?module=' . fei_opt( 'zoho_module', 'Leads' ) );
		return array_map( static fn( $f ) => array( 'api_name' => $f['api_name'], 'label' => $f['field_label'], 'type' => $f['data_type'], 'custom' => (bool) $f['custom_field'] ), $r['body']['fields'] ?? array() );
	}

	/* ---------- Admin / REST (status change hooks from Zoho → WP) ---------- */

	public function admin_test(): void {
		check_admin_referer( 'fei_zoho_test' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden' );
		}
		try {
			$fields = $this->list_fields();
			$msg    = 'OK — ' . count( $fields ) . ' fields. Custom: ' . implode( ', ', array_column( array_filter( $fields, static fn( $f ) => $f['custom'] ), 'api_name' ) );
		} catch ( Throwable $e ) {
			$msg = 'FAILED — ' . $e->getMessage();
		}
		fei_log( 'zoho', 'connection test', array( 'result' => $msg ) );
		wp_safe_redirect( admin_url( 'admin.php?page=fei-log' ) );
		exit;
	}

	/**
	 * POST /wp-json/fei/v1/zoho-status  { "web_lead_id": "...", "status": "Contacted|Enrolled|Closed", "token": "<shared secret>" }
	 * Call from a Zoho Workflow webhook so automation stops when an advisor updates the lead.
	 */
	public function routes(): void {
		register_rest_route(
			'fei/v1',
			'/zoho-status',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => static function ( WP_REST_Request $req ) {
					$in     = (array) $req->get_json_params();
					$secret = defined( 'FEI_ZOHO_WEBHOOK_SECRET' ) ? FEI_ZOHO_WEBHOOK_SECRET : '';
					if ( ! $secret || ! hash_equals( $secret, (string) ( $in['token'] ?? '' ) ) ) {
						return new WP_Error( 'fei_auth', 'unauthorised', array( 'status' => 401 ) );
					}
					$found = get_posts( array( 'post_type' => FEI_Leads::CPT, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_key' => '_fei_pre_reg_id', 'meta_value' => sanitize_text_field( (string) ( $in['web_lead_id'] ?? '' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
					if ( ! $found ) {
						return new WP_REST_Response( array( 'ok' => false, 'reason' => 'not found' ), 404 );
					}
					$map    = array( 'contacted' => 'fei_contacted', 'enrolled' => 'fei_enrolled', 'closed' => 'fei_closed', 'new' => 'fei_new' );
					$status = $map[ strtolower( (string) ( $in['status'] ?? '' ) ) ] ?? 'fei_contacted';
					wp_update_post( array( 'ID' => (int) $found[0], 'post_status' => $status ) );
					do_action( 'fei_lead_status_changed', (int) $found[0], $status );
					return new WP_REST_Response( array( 'ok' => true ), 200 );
				},
			)
		);
	}
}
