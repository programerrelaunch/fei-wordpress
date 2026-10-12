<?php
/**
 * Hardening for findings in the audit of the Hostinger install:
 *  - user enumeration (?author=N and /wp-json/wp/v2/users) exposed an email-as-username admin
 *  - XML-RPC on, version strings, no security headers
 * Everything here is non-destructive and can be toggled off with filters.
 */

defined( 'ABSPATH' ) || exit;

final class FEI_Security {

	private static ?FEI_Security $instance = null;

	public static function instance(): FEI_Security {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_filter( 'rest_endpoints', array( $this, 'hide_users_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'block_author_enum' ) );
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array' );
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );
		add_action( 'send_headers', array( $this, 'headers' ) );
		add_filter( 'login_errors', static fn() => __( 'Invalid login.', 'fei-core' ) );
		add_filter( 'wp_sitemaps_add_provider', array( $this, 'no_user_sitemap' ), 10, 2 );
		add_filter( 'rank_math/sitemap/providers', array( $this, 'rm_no_author_sitemap' ) );
		add_filter( 'oembed_response_data', array( $this, 'strip_author_from_oembed' ) );
		add_filter( 'rest_authentication_errors', array( $this, 'rest_nonce_required_for_writes' ), 99 );
		add_filter( 'comments_open', '__return_false', 20 );
		add_filter( 'pings_open', '__return_false', 20 );
		add_action( 'admin_notices', array( $this, 'admin_notice_email_username' ) );
	}

	public function hide_users_endpoint( array $endpoints ): array {
		if ( is_user_logged_in() && current_user_can( 'list_users' ) ) {
			return $endpoints;
		}
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( str_starts_with( $route, '/wp/v2/users' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	public function block_author_enum(): void {
		if ( is_admin() ) {
			return;
		}
		$has_author_qs = isset( $_GET['author'] ) && ! is_user_logged_in(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $has_author_qs || ( is_author() && ! apply_filters( 'fei_allow_author_archives', false ) ) ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	public function headers(): void {
		if ( headers_sent() || is_admin() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
		}
	}

	public function no_user_sitemap( $provider, string $name ) {
		return 'users' === $name ? false : $provider;
	}

	public function rm_no_author_sitemap( array $providers ): array {
		unset( $providers['author'] );
		return $providers;
	}

	public function strip_author_from_oembed( array $data ): array {
		unset( $data['author_name'], $data['author_url'] );
		return $data;
	}

	/** Public REST: writes require a nonce or auth, except our explicitly public intake routes. */
	public function rest_nonce_required_for_writes( $result ) {
		if ( ! empty( $result ) || is_user_logged_in() ) {
			return $result;
		}
		$method = strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( 'GET' === $method || 'HEAD' === $method || 'OPTIONS' === $method ) {
			return $result;
		}
		$route = (string) ( $GLOBALS['wp']->query_vars['rest_route'] ?? ( $_SERVER['REQUEST_URI'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$allow = apply_filters( 'fei_public_write_routes', array( '/fei/v1/lead', '/fei/v1/lead-error', '/fei/v1/zoho-status', '/contact-form-7/', '/kadence-form/', '/jwt-auth/v1/token' ) );
		foreach ( $allow as $prefix ) {
			if ( str_contains( $route, $prefix ) ) {
				return $result;
			}
		}
		return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'fei-core' ), array( 'status' => 401 ) );
	}

	public function admin_notice_email_username(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$risky = get_users( array( 'role__in' => array( 'administrator' ), 'search' => '*@*', 'search_columns' => array( 'user_login' ), 'fields' => array( 'user_login' ), 'number' => 5 ) );
		if ( $risky ) {
			echo '<div class="notice notice-warning"><p><strong>FEI Security:</strong> ' . esc_html__( 'An administrator account uses an email address as its username (publicly discoverable before hardening). Create a new admin with a non-email username, reassign content, delete the old one, and enable 2FA.', 'fei-core' ) . ' (' . esc_html( implode( ', ', array_map( static fn( $u ) => $u->user_login, $risky ) ) ) . ')</p></div>';
		}
	}
}
