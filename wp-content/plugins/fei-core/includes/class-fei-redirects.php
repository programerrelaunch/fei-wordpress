<?php
/**
 * Legacy URL handling.
 *  - 301 map from FEI_URL_Map::redirects() (runs before WP's own 404 so no soft-404s).
 *  - Spanish archive alias: /es/programas → programs archive in ES (Polylang translates
 *    the CPT slug only with Pro; we add a rewrite so the live URL keeps working).
 *  - Trailing-slash normalisation is left to WordPress (canonical redirect).
 */

defined( 'ABSPATH' ) || exit;

final class FEI_Redirects {

	private static ?FEI_Redirects $instance = null;

	public static function instance(): FEI_Redirects {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'template_redirect', array( $this, 'legacy' ), 1 );
		add_action( 'init', array( $this, 'rewrites' ) );
		add_filter( 'post_type_link', array( $this, 'es_program_link' ), 10, 2 );
		add_filter( 'post_type_archive_link', array( $this, 'es_archive_link' ), 10, 2 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
	}

	public function legacy(): void {
		if ( ! fei_opt( 'redirects_enabled', 1 ) || is_admin() ) {
			return;
		}
		$uri    = (string) ( $_SERVER['REQUEST_URI'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$path   = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$target = FEI_URL_Map::resolve( $path );
		if ( null === $target ) {
			return;
		}
		$qs = (string) wp_parse_url( $uri, PHP_URL_QUERY );
		if ( $qs && ! str_contains( $target, '?' ) ) {
			$target .= '?' . $qs;
		}
		wp_safe_redirect( home_url( $target ), 301, 'FEI legacy map' );
		exit;
	}

	public function query_vars( array $vars ): array {
		$vars[] = 'fei_es_programs';
		return $vars;
	}

	/** /es/programas and /es/programas/{slug} → fei_program in Spanish. */
	public function rewrites(): void {
		add_rewrite_rule( '^es/programas/?$', 'index.php?post_type=' . FEI_Programs::CPT . '&lang=es', 'top' );
		add_rewrite_rule( '^es/programas/([^/]+)/?$', 'index.php?post_type=' . FEI_Programs::CPT . '&name=$matches[1]&lang=es', 'top' );
		add_rewrite_rule( '^es/lp/([^/]+)/?$', 'index.php?post_type=' . FEI_Landing::CPT . '&name=$matches[1]&lang=es', 'top' );
	}

	public function es_program_link( string $link, WP_Post $post ): string {
		if ( FEI_Programs::CPT !== $post->post_type || ! function_exists( 'pll_get_post_language' ) ) {
			return $link;
		}
		if ( 'es' === pll_get_post_language( $post->ID ) ) {
			return home_url( '/es/programas/' . $post->post_name . '/' );
		}
		return $link;
	}

	public function es_archive_link( string $link, string $post_type ): string {
		if ( FEI_Programs::CPT === $post_type && 'es' === fei_lang() ) {
			return home_url( '/es/programas/' );
		}
		return $link;
	}
}
