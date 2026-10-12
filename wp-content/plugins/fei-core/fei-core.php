<?php
/**
 * Plugin Name:       FEI Core — Programs, Leads, Zoho & Automation
 * Plugin URI:        https://fei.edu/
 * Description:       Florida Education Institute site engine: Programs CPT, landing pages, lead intake (n8n + Zoho CRM), attribution, follow-up automation, SEO/schema parity with the Astro site, redirects and hardening.
 * Version:           1.3.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            FEI Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       fei-core
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'FEI_CORE_VERSION', '1.3.0' );
define( 'FEI_CORE_FILE', __FILE__ );
define( 'FEI_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'FEI_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once FEI_CORE_DIR . 'includes/fei-helpers.php';
require_once FEI_CORE_DIR . 'includes/class-fei-lead-validator.php';
require_once FEI_CORE_DIR . 'includes/class-fei-zoho-mapper.php';
require_once FEI_CORE_DIR . 'includes/class-fei-url-map.php';
require_once FEI_CORE_DIR . 'includes/class-fei-settings.php';
require_once FEI_CORE_DIR . 'includes/class-fei-programs.php';
require_once FEI_CORE_DIR . 'includes/class-fei-leads.php';
require_once FEI_CORE_DIR . 'includes/class-fei-zoho.php';
require_once FEI_CORE_DIR . 'includes/class-fei-automation.php';
require_once FEI_CORE_DIR . 'includes/class-fei-landing.php';
require_once FEI_CORE_DIR . 'includes/class-fei-templates.php';
require_once FEI_CORE_DIR . 'includes/class-fei-seo.php';
require_once FEI_CORE_DIR . 'includes/class-fei-tracking.php';
require_once FEI_CORE_DIR . 'includes/class-fei-redirects.php';
require_once FEI_CORE_DIR . 'includes/class-fei-security.php';

/**
 * Boot all modules once plugins are loaded.
 */
function fei_core_boot(): void {
	load_plugin_textdomain( 'fei-core', false, dirname( plugin_basename( FEI_CORE_FILE ) ) . '/languages' );

	FEI_Settings::instance();
	FEI_Programs::instance();
	FEI_Leads::instance();
	FEI_Zoho::instance();
	FEI_Automation::instance();
	FEI_Landing::instance();
	FEI_Templates::instance();
	FEI_SEO::instance();
	FEI_Tracking::instance();
	FEI_Redirects::instance();
	FEI_Security::instance();
}
add_action( 'plugins_loaded', 'fei_core_boot' );

/**
 * Activation: register CPTs, seed programs, flush rewrites, schedule cron.
 */
function fei_core_activate(): void {
	require_once FEI_CORE_DIR . 'includes/fei-helpers.php';
	require_once FEI_CORE_DIR . 'includes/class-fei-settings.php';
	require_once FEI_CORE_DIR . 'includes/class-fei-programs.php';
	require_once FEI_CORE_DIR . 'includes/class-fei-leads.php';
	require_once FEI_CORE_DIR . 'includes/class-fei-landing.php';

	FEI_Programs::register_post_types();
	FEI_Leads::register_post_type();
	FEI_Landing::register_post_type();
	FEI_Programs::seed_from_json();

	if ( ! wp_next_scheduled( 'fei_process_queue' ) ) {
		wp_schedule_event( time() + 60, 'fei_five_minutes', 'fei_process_queue' );
	}
	if ( ! wp_next_scheduled( 'fei_run_automation' ) ) {
		wp_schedule_event( time() + 120, 'hourly', 'fei_run_automation' );
	}
	flush_rewrite_rules();
	update_option( 'fei_core_version', FEI_CORE_VERSION );
}
register_activation_hook( __FILE__, 'fei_core_activate' );

function fei_core_deactivate(): void {
	wp_clear_scheduled_hook( 'fei_process_queue' );
	wp_clear_scheduled_hook( 'fei_run_automation' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'fei_core_deactivate' );

add_filter(
	'cron_schedules',
	static function ( array $schedules ): array {
		$schedules['fei_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Every five minutes (FEI)', 'fei-core' ),
		);
		return $schedules;
	}
);
