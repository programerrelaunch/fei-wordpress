<?php
/**
 * WordPress Playground demo provisioning for FEI.
 *
 * Executed by blueprint.json via runPHP in two stages:
 *   FEI_SETUP_STAGE = 'languages' — right after Polylang is active, BEFORE fei-core activates
 *                                   (so the activation seed sees en/es and pairs translations).
 *   FEI_SETUP_STAGE = 'site'      — after fei-core + child theme: pages, menu, front page,
 *                                   permalinks, landing pages.
 * Idempotent. Mirrors migration/RUNBOOK.md §1–2. Not part of the production plugin/theme.
 */

$stage = defined( 'FEI_SETUP_STAGE' ) ? FEI_SETUP_STAGE : 'site';

if ( ! defined( 'PLL_ADMIN' ) ) {
	define( 'PLL_ADMIN', true ); // Polylang: load PLL_Admin_Model so languages can be created.
}
if ( ! defined( 'WP_ADMIN' ) ) {
	define( 'WP_ADMIN', true );
}
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

wp_set_current_user( 1 );

$log = static function ( string $m ): void {
	echo '[fei-setup] ' . $m . "\n";
};

/* ---------- 1. Polylang languages (en default, es in /es/ directory) ---------- */
if ( function_exists( 'PLL' ) && PLL() && isset( PLL()->model ) && method_exists( PLL()->model, 'add_language' ) ) {
	$have = wp_list_pluck( PLL()->model->get_languages_list(), 'slug' );
	$defs = array(
		array( 'name' => 'English', 'slug' => 'en', 'locale' => 'en_US', 'rtl' => 0, 'flag' => 'us', 'term_group' => 0 ),
		array( 'name' => 'Español', 'slug' => 'es', 'locale' => 'es_ES', 'rtl' => 0, 'flag' => 'es', 'term_group' => 1 ),
	);
	foreach ( $defs as $d ) {
		if ( ! in_array( $d['slug'], $have, true ) ) {
			$r = PLL()->model->add_language( $d );
			$log( 'language ' . $d['slug'] . ': ' . ( is_wp_error( $r ) ? $r->get_error_message() : 'added' ) );
		}
	}
	$opts = PLL()->options;
	$set  = array( 'default_lang' => 'en', 'force_lang' => 1, 'hide_default' => 1, 'rewrite' => 1, 'browser' => 0, 'media_support' => 0 );
	foreach ( $set as $k => $v ) {
		$opts[ $k ] = $v;
	}
	if ( is_array( $opts ) ) {
		update_option( 'polylang', $opts );
	} elseif ( is_object( $opts ) && method_exists( $opts, 'save' ) ) {
		$opts->save();
	}
	PLL()->model->clean_languages_cache();
	$log( 'polylang configured' );
} else {
	$log( 'polylang API unavailable — continuing in English only' );
}

if ( 'languages' === $stage ) {
	$log( 'stage languages done' );
	return;
}

$has_pll = function_exists( 'pll_set_post_language' ) && function_exists( 'pll_save_post_translations' )
	&& function_exists( 'pll_languages_list' ) && in_array( 'es', (array) pll_languages_list(), true );

/* ---------- 2. Ensure programs are seeded (activation normally did this; idempotent) ---------- */
if ( class_exists( 'FEI_Programs' ) ) {
	FEI_Programs::register_post_types();
	$rep = FEI_Programs::seed_from_json();
	$log( 'programs seeded: ' . wp_json_encode( $rep ) );
}

/* ---------- 3. Pages ---------- */
$pattern = static function ( string $slug ): string {
	$file = get_stylesheet_directory() . '/patterns/' . $slug . '.php';
	if ( ! file_exists( $file ) ) {
		return '';
	}
	$data = include $file;
	return is_array( $data ) ? (string) ( $data['content'] ?? '' ) : '';
};

$ensure_page = static function ( string $slug, string $title, string $content, string $lang, bool $fullwidth = false ) use ( $has_pll, $log ): int {
	$existing = get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'lang' => '' ) );
	if ( $existing ) {
		$id = (int) $existing[0];
	} else {
		$id = (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);
		$log( "page /$slug created (#$id)" );
	}
	if ( $id && $fullwidth ) {
		// Kadence "Page Settings" meta: full-width layout, no title, unboxed content.
		update_post_meta( $id, '_kad_post_layout', 'fullwidth' );
		update_post_meta( $id, '_kad_post_title', 'hide' );
		update_post_meta( $id, '_kad_post_content_style', 'unboxed' );
		update_post_meta( $id, '_kad_post_vertical_padding', 'hide' );
	}
	if ( $id && $has_pll ) {
		pll_set_post_language( $id, $lang );
	}
	return $id;
};

$es_programs_block = '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"4rem","bottom":"4rem"}}},"layout":{"type":"constrained","contentSize":"1200px"}} --><div class="wp-block-group alignfull" style="padding-top:4rem;padding-bottom:4rem">'
	. '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Programas</h1><!-- /wp:heading -->'
	. '<!-- wp:shortcode -->[fei_programs_grid]<!-- /wp:shortcode -->'
	. '<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">[fei_apply_button]</p><!-- /wp:paragraph -->'
	. '</div><!-- /wp:group -->';

$es_apply_block = '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"4rem","bottom":"5rem"}}},"layout":{"type":"constrained","contentSize":"900px"}} --><div class="wp-block-group alignfull" style="padding-top:4rem;padding-bottom:5rem">'
	. '<!-- wp:shortcode -->[fei_apply_form prefix="af" id="applyForm"]<!-- /wp:shortcode -->'
	. '</div><!-- /wp:group -->';

$home_en  = $ensure_page( 'home', 'Home', $pattern( 'home-en' ), 'en', true );
$apply_en = $ensure_page( 'apply', 'Apply Now', $pattern( 'apply-en' ), 'en' );
$home_es  = $ensure_page( 'inicio', 'Inicio', $es_programs_block, 'es', true );
$apply_es = $ensure_page( 'inscripcion', 'Inscríbete', $es_apply_block, 'es' );

if ( $has_pll ) {
	pll_save_post_translations( array( 'en' => $home_en, 'es' => $home_es ) );
	pll_save_post_translations( array( 'en' => $apply_en, 'es' => $apply_es ) );
}

/* ---------- 4. Reading settings + permalinks ---------- */
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home_en );
update_option( 'blogname', 'Florida Education Institute' );
update_option( 'blogdescription', 'Career education in Miami, FL' );
update_option( 'timezone_string', 'America/New_York' );
update_option( 'permalink_structure', '/%postname%/' );

/* ---------- 5. Primary menu ---------- */
$menu_name = 'FEI Primary';
$menu      = wp_get_nav_menu_object( $menu_name );
if ( ! $menu ) {
	$menu_id = (int) wp_create_nav_menu( $menu_name );
	$items   = array(
		array( 'menu-item-title' => 'Programs', 'menu-item-url' => home_url( '/programs/' ), 'menu-item-type' => 'custom' ),
		array( 'menu-item-title' => 'Apply Now', 'menu-item-object-id' => $apply_en, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type' ),
	);
	foreach ( $items as $i ) {
		wp_update_nav_menu_item( $menu_id, 0, array_merge( $i, array( 'menu-item-status' => 'publish' ) ) );
	}
	$locations            = (array) get_theme_mod( 'nav_menu_locations', array() );
	$locations['primary'] = $menu_id;
	$locations['mobile']  = $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );
	$log( 'menu created' );
}

/* ---------- 6. Kadence header: show the Apply button (opens the modal via data-apply) ---------- */
set_theme_mod( 'header_button_label', 'Apply Now' );
set_theme_mod( 'header_button_link', home_url( '/apply/' ) );
set_theme_mod( 'header_button_style', 'filled' );
$desktop = get_theme_mod( 'header_desktop_items' );
if ( ! is_array( $desktop ) ) {
	$desktop = array(
		'top'    => array( 'left' => array(), 'left_center' => array(), 'center' => array(), 'right_center' => array(), 'right' => array() ),
		'main'   => array( 'left' => array( 'logo' ), 'left_center' => array(), 'center' => array(), 'right_center' => array(), 'right' => array( 'navigation', 'button' ) ),
		'bottom' => array( 'left' => array(), 'left_center' => array(), 'center' => array(), 'right_center' => array(), 'right' => array() ),
	);
} elseif ( ! in_array( 'button', $desktop['main']['right'] ?? array(), true ) ) {
	$desktop['main']['right'][] = 'button';
}
set_theme_mod( 'header_desktop_items', $desktop );

/* ---------- 7. Landing pages for every program ---------- */
if ( class_exists( 'FEI_Landing' ) && method_exists( 'FEI_Landing', 'generate' ) ) {
	FEI_Landing::register_post_type();
	$n = FEI_Landing::generate();
	$log( 'landing pages created: ' . $n );
	// Publish one EN demo LP so /lp/<slug> is viewable without logging in.
	$lp = get_posts( array( 'post_type' => 'fei_landing', 'post_status' => 'draft', 'posts_per_page' => -1, 'fields' => 'ids', 'lang' => 'en' ) );
	foreach ( $lp as $id ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
	}
}

/* ---------- 8. Flush ---------- */
if ( class_exists( 'FEI_Leads' ) ) {
	FEI_Leads::register_post_type();
}
flush_rewrite_rules( true );
wp_cache_flush();
$log( 'done — front page #' . $home_en );
