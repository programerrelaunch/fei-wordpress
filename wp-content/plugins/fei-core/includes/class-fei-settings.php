<?php
/**
 * Settings: single option array `fei_core_settings` + admin screen.
 * Secrets may also be provided as constants in wp-config.php (preferred):
 *   FEI_ZOHO_CLIENT_ID, FEI_ZOHO_CLIENT_SECRET, FEI_ZOHO_REFRESH_TOKEN,
 *   FEI_TURNSTILE_SECRET, FEI_LEAD_WEBHOOK_URL
 */

defined( 'ABSPATH' ) || exit;

final class FEI_Settings {

	const OPTION = 'fei_core_settings';

	private static ?FEI_Settings $instance = null;

	public static function instance(): FEI_Settings {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	public static function defaults(): array {
		return array(
			// Lead routing.
			'lead_webhook_url'      => 'https://flow.fei.edu/webhook/lead-conversion',
			'error_webhook_url'     => 'https://flow.fei.edu/webhook/error',
			'forward_to_n8n'        => 1,
			'notify_email'          => 'admissions@fei.edu',
			'from_email'            => 'admissions@fei.edu',
			'from_name'             => 'Florida Education Institute',
			// Tracking.
			'gtm_id'                => 'GTM-TR7MCFQ',
			'capture_client_ip'     => 1,
			// Anti-spam.
			'turnstile_site_key'    => '',
			'turnstile_secret'      => '',
			'rate_limit_per_hour'   => 8,
			// Zoho.
			'zoho_enabled'          => 0,
			'zoho_dc'               => 'com',
			'zoho_client_id'        => '',
			'zoho_client_secret'    => '',
			'zoho_refresh_token'    => '',
			'zoho_module'           => 'Leads',
			'zoho_owner_id'         => '',
			'zoho_layout_id'        => '',
			'zoho_assign_task'      => 1,
			'zoho_field_map_json'   => '',
			// Automation.
			'automation_enabled'    => 1,
			'drip_days'             => '1,3,7',
			'sms_webhook_url'       => '',
			// Legacy redirects.
			'redirects_enabled'     => 1,
		);
	}

	public static function all(): array {
		$saved = get_option( self::OPTION, array() );
		$all   = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );

		// Constants override saved values for secrets.
		$const_map = array(
			'zoho_client_id'     => 'FEI_ZOHO_CLIENT_ID',
			'zoho_client_secret' => 'FEI_ZOHO_CLIENT_SECRET',
			'zoho_refresh_token' => 'FEI_ZOHO_REFRESH_TOKEN',
			'turnstile_secret'   => 'FEI_TURNSTILE_SECRET',
			'lead_webhook_url'   => 'FEI_LEAD_WEBHOOK_URL',
		);
		foreach ( $const_map as $key => $const ) {
			if ( defined( $const ) && constant( $const ) ) {
				$all[ $key ] = constant( $const );
			}
		}
		return $all;
	}

	public static function get( string $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	public function menu(): void {
		add_menu_page( 'FEI', 'FEI', 'manage_options', 'fei-core', array( $this, 'render' ), 'dashicons-welcome-learn-more', 3 );
		add_submenu_page( 'fei-core', __( 'Settings', 'fei-core' ), __( 'Settings', 'fei-core' ), 'manage_options', 'fei-core', array( $this, 'render' ) );
		add_submenu_page( 'fei-core', __( 'Integration log', 'fei-core' ), __( 'Integration log', 'fei-core' ), 'manage_options', 'fei-log', array( $this, 'render_log' ) );
	}

	public function register(): void {
		register_setting(
			'fei_core',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$out   = array();
		foreach ( self::defaults() as $key => $default ) {
			if ( ! array_key_exists( $key, $input ) ) {
				$out[ $key ] = is_int( $default ) ? 0 : $default;
				continue;
			}
			$v = $input[ $key ];
			if ( str_ends_with( $key, '_url' ) ) {
				$out[ $key ] = esc_url_raw( trim( (string) $v ) );
			} elseif ( str_contains( $key, 'email' ) ) {
				$out[ $key ] = sanitize_email( (string) $v );
			} elseif ( 'zoho_field_map_json' === $key ) {
				$decoded     = json_decode( (string) $v, true );
				$out[ $key ] = ( is_array( $decoded ) || '' === trim( (string) $v ) ) ? trim( (string) $v ) : '';
				if ( '' !== trim( (string) $v ) && ! is_array( $decoded ) ) {
					add_settings_error( self::OPTION, 'fei_map', __( 'Zoho field map must be valid JSON.', 'fei-core' ) );
				}
			} elseif ( is_int( $default ) ) {
				$out[ $key ] = (int) $v;
			} else {
				$out[ $key ] = sanitize_text_field( (string) $v );
			}
		}
		return $out;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o      = self::all();
		$fields = array(
			'Lead routing'  => array(
				'lead_webhook_url'  => array( 'url', 'n8n lead webhook URL' ),
				'error_webhook_url' => array( 'url', 'n8n error webhook URL' ),
				'forward_to_n8n'    => array( 'bool', 'Forward every lead to n8n (keeps the existing Zoho/Sheets/CAPI workflow alive during transition)' ),
				'notify_email'      => array( 'email', 'Internal lead alert email' ),
				'from_email'        => array( 'email', 'From email' ),
				'from_name'         => array( 'text', 'From name' ),
			),
			'Tracking'      => array(
				'gtm_id'            => array( 'text', 'Google Tag Manager container ID' ),
				'capture_client_ip' => array( 'bool', 'Store visitor IP with the lead (server-side; replaces api.ipify.org call)' ),
			),
			'Anti-spam'     => array(
				'turnstile_site_key'  => array( 'text', 'Cloudflare Turnstile site key (optional)' ),
				'turnstile_secret'    => array( 'password', 'Cloudflare Turnstile secret' ),
				'rate_limit_per_hour' => array( 'number', 'Max submissions per IP per hour' ),
			),
			'Zoho CRM'      => array(
				'zoho_enabled'        => array( 'bool', 'Push leads to Zoho CRM directly from WordPress' ),
				'zoho_dc'             => array( 'text', 'Data centre TLD (com, eu, in, com.au, jp, ca)' ),
				'zoho_client_id'      => array( 'text', 'OAuth client ID' ),
				'zoho_client_secret'  => array( 'password', 'OAuth client secret' ),
				'zoho_refresh_token'  => array( 'password', 'OAuth refresh token (scope ZohoCRM.modules.ALL, ZohoCRM.settings.fields.READ)' ),
				'zoho_module'         => array( 'text', 'Module (Leads)' ),
				'zoho_owner_id'       => array( 'text', 'Default owner user ID (optional)' ),
				'zoho_layout_id'      => array( 'text', 'Layout ID (optional)' ),
				'zoho_assign_task'    => array( 'bool', 'Create a follow-up Task for the owner on new leads' ),
				'zoho_field_map_json' => array( 'textarea', 'Field map override (JSON: {"leadField":"Zoho_API_Name"})' ),
			),
			'Automation'    => array(
				'automation_enabled' => array( 'bool', 'Send confirmation + drip emails from WordPress' ),
				'drip_days'          => array( 'text', 'Drip schedule in days after lead (comma separated)' ),
				'sms_webhook_url'    => array( 'url', 'SMS provider webhook (optional; receives {to, body, lead_id})' ),
			),
			'Migration'     => array(
				'redirects_enabled' => array( 'bool', 'Enable legacy Astro URL → WordPress 301 map' ),
			),
		);
		echo '<div class="wrap"><h1>FEI Core</h1>';
		settings_errors( self::OPTION );
		echo '<form method="post" action="options.php">';
		settings_fields( 'fei_core' );
		foreach ( $fields as $section => $items ) {
			echo '<h2>' . esc_html( $section ) . '</h2><table class="form-table" role="presentation">';
			foreach ( $items as $key => [ $type, $label ] ) {
				$name = self::OPTION . '[' . $key . ']';
				$val  = $o[ $key ] ?? '';
				echo '<tr><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
				switch ( $type ) {
					case 'bool':
						printf( '<input type="hidden" name="%1$s" value="0"><input type="checkbox" id="%2$s" name="%1$s" value="1" %3$s>', esc_attr( $name ), esc_attr( $key ), checked( 1, (int) $val, false ) );
						break;
					case 'textarea':
						printf( '<textarea id="%1$s" name="%2$s" rows="6" class="large-text code">%3$s</textarea>', esc_attr( $key ), esc_attr( $name ), esc_textarea( (string) $val ) );
						break;
					default:
						printf( '<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text">', esc_attr( 'password' === $type ? 'password' : ( 'number' === $type ? 'number' : 'text' ) ), esc_attr( $key ), esc_attr( $name ), esc_attr( (string) $val ) );
				}
				echo '</td></tr>';
			}
			echo '</table>';
		}
		submit_button();
		echo '</form>';
		echo '<p class="description">' . esc_html__( 'Secrets can be defined as constants in wp-config.php (FEI_ZOHO_CLIENT_ID, FEI_ZOHO_CLIENT_SECRET, FEI_ZOHO_REFRESH_TOKEN, FEI_TURNSTILE_SECRET). Constants override saved values.', 'fei-core' ) . '</p>';
		echo '</div>';
	}

	public function render_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$buf = array_reverse( (array) get_transient( 'fei_log_ring' ) );
		echo '<div class="wrap"><h1>' . esc_html__( 'FEI integration log', 'fei-core' ) . '</h1>';
		echo '<p>' . esc_html__( 'Last 200 events (24h). Queue size: ', 'fei-core' ) . (int) FEI_Leads::queue_size() . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>Time</th><th>Channel</th><th>Message</th><th>Context</th></tr></thead><tbody>';
		foreach ( $buf as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td><code>%s</code></td></tr>',
				esc_html( wp_date( 'Y-m-d H:i:s', (int) ( $row['t'] ?? 0 ) ) ),
				esc_html( (string) ( $row['c'] ?? '' ) ),
				esc_html( (string) ( $row['m'] ?? '' ) ),
				esc_html( wp_json_encode_safe( $row['x'] ?? array() ) )
			);
		}
		echo '</tbody></table></div>';
	}
}
