<?php
/**
 * Follow-up automation (WordPress-native, provider-agnostic).
 *
 *  On new lead:      instant confirmation email to the lead (EN/ES) + internal alert.
 *  Drip:             D+1, D+3, D+7 (configurable) nurture emails, hourly cron.
 *  SMS:              optional outbound webhook (Twilio/Zoho/other) — only when the lead
 *                    ticked the TRANSACTIONAL consent box; marketing SMS only with the
 *                    marketing consent. Nothing is sent without consent.
 *  Stop rules:       status != fei_new (advisor contacted/enrolled/closed), unsubscribe
 *                    link, or lead upgraded (pre → full restarts sequence once).
 *
 * All copy lives in templates/emails/*.php so Admissions can edit without touching logic.
 */

defined( 'ABSPATH' ) || exit;

final class FEI_Automation {

	private static ?FEI_Automation $instance = null;

	public static function instance(): FEI_Automation {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'fei_run_automation', array( $this, 'run_drips' ) );
		add_action( 'fei_lead_status_changed', array( $this, 'on_status_changed' ), 10, 2 );
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );
		add_action( 'init', array( $this, 'handle_unsubscribe' ) );
		add_filter( 'wp_mail_from', array( $this, 'mail_from' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'mail_from_name' ) );
	}

	public function mail_from( string $from ): string {
		$f = (string) fei_opt( 'from_email' );
		return $f && is_email( $f ) ? $f : $from;
	}

	public function mail_from_name( string $name ): string {
		return (string) fei_opt( 'from_name' ) ?: $name;
	}

	/* ---------- Entry points ---------- */

	/** Queue job: runs right after a lead is stored. */
	public function on_new_lead( int $lead_id, array $lead, string &$err ): bool {
		if ( ! fei_opt( 'automation_enabled', 1 ) ) {
			return true;
		}
		$log = $this->log( $lead_id );

		// Internal alert — always, including pre-registrations (speed-to-lead).
		if ( empty( $log['alert'] ) ) {
			$this->send_internal_alert( $lead_id, $lead );
			$log['alert'] = time();
		}
		// Lead confirmation — once per stage.
		$stage_key = 'confirm_' . ( $lead['stage'] ?? 'full' );
		if ( empty( $log[ $stage_key ] ) && ! empty( $lead['email'] ) ) {
			$this->send_template( $lead_id, $lead, 'confirmation' );
			$log[ $stage_key ] = time();
			$log['sequence_started'] = time(); // (re)start the drip clock on each stage.
			$log['drips_sent'] = array();
		}
		// Transactional SMS acknowledgement (consent-gated).
		if ( empty( $log['sms_ack'] ) && ! empty( $lead['smsTransactionalConsent'] ) ) {
			$this->send_sms( $lead_id, $lead, $this->sms_copy( 'ack', $lead ) );
			$log['sms_ack'] = time();
		}
		$this->save_log( $lead_id, $log );
		return true;
	}

	/** Hourly cron: send due drips. */
	public function run_drips(): void {
		if ( ! fei_opt( 'automation_enabled', 1 ) ) {
			return;
		}
		$days = array_values( array_filter( array_map( 'intval', explode( ',', (string) fei_opt( 'drip_days', '1,3,7' ) ) ) ) );
		sort( $days );
		if ( ! $days ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'      => FEI_Leads::CPT,
				'post_status'    => 'fei_new',
				'fields'         => 'ids',
				'posts_per_page' => 200,
				'date_query'     => array( array( 'after' => ( max( $days ) + 2 ) . ' days ago' ) ),
			)
		);
		foreach ( $ids as $id ) {
			$lead = FEI_Leads::get_lead( (int) $id );
			$log  = $this->log( (int) $id );
			if ( empty( $lead['email'] ) || ! empty( $log['unsubscribed'] ) || empty( $log['sequence_started'] ) ) {
				continue;
			}
			$elapsed_days = ( time() - (int) $log['sequence_started'] ) / DAY_IN_SECONDS;
			foreach ( $days as $i => $day ) {
				$step = 'drip_' . ( $i + 1 );
				if ( $elapsed_days >= $day && empty( $log['drips_sent'][ $step ] ) ) {
					$this->send_template( (int) $id, $lead, $step );
					$log['drips_sent'][ $step ] = time();
					if ( 1 === $i && ! empty( $lead['smsMarketingConsent'] ) && empty( $log['sms_drip'] ) ) {
						$this->send_sms( (int) $id, $lead, $this->sms_copy( 'nudge', $lead ) );
						$log['sms_drip'] = time();
					}
					$this->save_log( (int) $id, $log );
					break; // one step per run per lead.
				}
			}
		}
	}

	public function on_status_changed( int $lead_id, string $status ): void {
		$log = $this->log( $lead_id );
		$log['stopped'] = array( 'at' => time(), 'reason' => $status );
		$this->save_log( $lead_id, $log );
		// Mirror to Zoho when the change originated in WP.
		$zid = (string) get_post_meta( $lead_id, '_fei_zoho_id', true );
		if ( $zid && fei_opt( 'zoho_enabled' ) && ! did_action( 'rest_api_init' ) ) {
			$map = array( 'fei_contacted' => 'Contacted', 'fei_enrolled' => 'Converted', 'fei_closed' => 'Lost Lead', 'fei_new' => 'Not Contacted' );
			try {
				FEI_Zoho::instance()->set_status( $zid, $map[ $status ] ?? 'Contacted' );
			} catch ( Throwable $e ) {
				fei_log( 'zoho', 'status mirror failed', array( 'err' => $e->getMessage() ) );
			}
		}
	}

	public function on_transition( string $new, string $old, WP_Post $post ): void {
		if ( FEI_Leads::CPT === $post->post_type && $new !== $old && str_starts_with( $new, 'fei_' ) ) {
			do_action( 'fei_lead_status_changed', $post->ID, $new );
		}
	}

	/* ---------- Senders ---------- */

	private function send_internal_alert( int $lead_id, array $lead ): void {
		$to = (string) fei_opt( 'notify_email' );
		if ( ! $to ) {
			return;
		}
		$subject = sprintf( '[FEI lead] %s %s — %s (%s)', $lead['firstName'], $lead['lastName'], $lead['program'] ?: 'no program', strtoupper( $lead['lang'] ?? 'en' ) . '/' . ( $lead['stage'] ?? 'full' ) );
		$body    = $this->render( 'internal-alert', array( 'lead' => $lead, 'lead_id' => $lead_id, 'edit_url' => admin_url( 'post.php?post=' . $lead_id . '&action=edit' ) ) );
		wp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8', 'Reply-To: ' . $lead['firstName'] . ' ' . $lead['lastName'] . ' <' . $lead['email'] . '>' ) );
	}

	private function send_template( int $lead_id, array $lead, string $step ): void {
		$lang = in_array( $lead['lang'] ?? 'en', array( 'en', 'es' ), true ) ? $lead['lang'] : 'en';
		$tpl  = $step . '-' . $lang;
		$vars = array(
			'lead'        => $lead,
			'lead_id'     => $lead_id,
			'org'         => fei_org(),
			'program'     => $this->program_context( $lead ),
			'unsubscribe' => $this->unsubscribe_url( $lead_id ),
			'apply_url'   => home_url( 'es' === $lang ? '/es/inscripcion/' : '/apply/' ),
			'aid_url'     => home_url( 'es' === $lang ? '/es/ayuda-financiera/' : '/financial-aid/' ),
		);
		$subject = $this->subject( $step, $lang, $lead );
		$html    = $this->render( $tpl, $vars );
		if ( ! $html ) {
			return;
		}
		$sent = wp_mail( $lead['email'], $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
		fei_log( 'automation', $step, array( 'lead' => $lead_id, 'sent' => $sent ) );
		do_action( 'fei_automation_email_sent', $lead_id, $step, $sent );
	}

	private function send_sms( int $lead_id, array $lead, string $body ): void {
		$url = (string) fei_opt( 'sms_webhook_url' );
		if ( ! $url || empty( $lead['phone'] ) ) {
			return;
		}
		$res = wp_remote_post( $url, array( 'timeout' => 8, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'to' => $lead['phone'], 'body' => $body, 'lead_id' => $lead_id, 'web_lead_id' => $lead['preRegId'] ?? '' ) ) ) );
		fei_log( 'sms', 'sent', array( 'lead' => $lead_id, 'ok' => ! is_wp_error( $res ) && wp_remote_retrieve_response_code( $res ) < 300 ) );
	}

	/* ---------- Copy ---------- */

	private function subject( string $step, string $lang, array $lead ): string {
		$p = $lead['program'] ?: '';
		$s = array(
			'en' => array(
				'confirmation' => $p ? "We got your request about $p — here's what happens next" : 'We got your request — here is what happens next',
				'drip_1'       => "Quick question, {$lead['firstName']} — what's holding you back?",
				'drip_2'       => 'Paying for school at FEI: aid, scholarships, payment plans',
				'drip_3'       => 'Classes start every 3–4 weeks — want a campus tour?',
			),
			'es' => array(
				'confirmation' => $p ? "Recibimos tu solicitud sobre $p — esto es lo que sigue" : 'Recibimos tu solicitud — esto es lo que sigue',
				'drip_1'       => "Una pregunta rápida, {$lead['firstName']}: ¿qué te detiene?",
				'drip_2'       => 'Cómo pagar tus estudios en FEI: ayuda, becas y planes de pago',
				'drip_3'       => 'Las clases empiezan cada 3–4 semanas — ¿quieres visitar el campus?',
			),
		);
		return $s[ $lang ][ $step ] ?? $s['en'][ $step ] ?? 'Florida Education Institute';
	}

	private function sms_copy( string $kind, array $lead ): string {
		$es = 'es' === ( $lead['lang'] ?? 'en' );
		if ( 'ack' === $kind ) {
			return $es
				? "FEI: Hola {$lead['firstName']}, recibimos tu solicitud. Un asesor te llamará pronto desde el 786-665-8310. Responde STOP para cancelar."
				: "FEI: Hi {$lead['firstName']}, we received your request. An advisor will call you shortly from 786-665-8310. Reply STOP to opt out.";
		}
		return $es
			? "FEI: {$lead['firstName']}, ¿aún te interesa " . ( $lead['program'] ?: 'estudiar en FEI' ) . '? Las clases empiezan cada 3–4 semanas. Llámanos: 786-665-8310. STOP para cancelar.'
			: "FEI: {$lead['firstName']}, still interested in " . ( $lead['program'] ?: 'FEI' ) . '? Classes start every 3–4 weeks. Call 786-665-8310. Reply STOP to opt out.';
	}

	private function program_context( array $lead ): array {
		if ( empty( $lead['program'] ) ) {
			return array();
		}
		foreach ( FEI_Programs::catalog( $lead['lang'] ?? 'en' ) as $p ) {
			if ( $p['key'] === $lead['program'] ) {
				return $p['id'] ? FEI_Programs::get_program( (int) $p['id'] ) : $p;
			}
		}
		return array();
	}

	/* ---------- Rendering & state ---------- */

	private function render( string $template, array $vars ): string {
		$file = locate_template( 'fei-core/emails/' . $template . '.php' ) ?: FEI_CORE_DIR . 'templates/emails/' . $template . '.php';
		if ( ! file_exists( $file ) ) {
			return '';
		}
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		ob_start();
		include $file;
		return (string) ob_get_clean();
	}

	private function log( int $lead_id ): array {
		$l = get_post_meta( $lead_id, '_fei_automation_log', true );
		return is_array( $l ) ? $l : array();
	}

	private function save_log( int $lead_id, array $log ): void {
		update_post_meta( $lead_id, '_fei_automation_log', $log );
	}

	public function unsubscribe_url( int $lead_id ): string {
		$token = hash_hmac( 'sha256', 'unsub|' . $lead_id, wp_salt( 'auth' ) );
		return add_query_arg( array( 'fei_unsub' => $lead_id, 't' => substr( $token, 0, 32 ) ), home_url( '/' ) );
	}

	public function handle_unsubscribe(): void {
		if ( empty( $_GET['fei_unsub'] ) || empty( $_GET['t'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$id    = (int) $_GET['fei_unsub']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = sanitize_text_field( wp_unslash( $_GET['t'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$want  = substr( hash_hmac( 'sha256', 'unsub|' . $id, wp_salt( 'auth' ) ), 0, 32 );
		if ( hash_equals( $want, $token ) ) {
			$log                 = $this->log( $id );
			$log['unsubscribed'] = time();
			$this->save_log( $id, $log );
		}
		$lang = (string) get_post_meta( $id, '_fei_lang', true );
		wp_die(
			esc_html( 'es' === $lang ? 'Listo. No recibirás más correos de seguimiento de FEI. Si cambias de opinión, llámanos al 786-665-8310.' : 'Done. You will not receive further follow-up emails from FEI. If you change your mind, call us at 786-665-8310.' ),
			'FEI',
			array( 'response' => 200 )
		);
	}
}
