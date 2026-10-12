<?php
/**
 * Playground / demo bootstrap: front page, apply page, publish program LPs.
 * Loaded by the WordPress Playground blueprint (runPHP).
 */
require_once '/wordpress/wp-load.php';

update_option( 'blogname', 'Florida Education Institute' );
update_option( 'blogdescription', 'Career-focused education in Miami' );
update_option( 'permalink_structure', '/%postname%/' );
update_option( 'timezone_string', 'America/New_York' );
update_option( 'blog_public', '0' ); // demo: discourage indexing of the sandbox
update_option( 'show_on_front', 'page' );

$home_pat  = array();
$apply_pat = array();
$child     = get_theme_root() . '/fei-kadence-child/patterns';
if ( is_readable( $child . '/home-en.php' ) ) {
	$home_pat = include $child . '/home-en.php';
}
if ( is_readable( $child . '/apply-en.php' ) ) {
	$apply_pat = include $child . '/apply-en.php';
}

$home_id = wp_insert_post(
	array(
		'post_title'   => 'Home',
		'post_name'    => 'home',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => is_array( $home_pat ) && ! empty( $home_pat['content'] ) ? $home_pat['content'] : '<!-- wp:shortcode -->[fei_programs_grid]<!-- /wp:shortcode -->',
	)
);
$apply_id = wp_insert_post(
	array(
		'post_title'   => 'Apply Now',
		'post_name'    => 'apply',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => is_array( $apply_pat ) && ! empty( $apply_pat['content'] ) ? $apply_pat['content'] : '<!-- wp:shortcode -->[fei_apply_form]<!-- /wp:shortcode -->',
	)
);

if ( $home_id && ! is_wp_error( $home_id ) ) {
	update_option( 'page_on_front', (int) $home_id );
}

if ( class_exists( 'FEI_Landing' ) ) {
	FEI_Landing::generate();
	$lps = get_posts(
		array(
			'post_type'   => 'fei_landing',
			'post_status' => 'draft',
			'numberposts' => -1,
		)
	);
	foreach ( $lps as $lp ) {
		wp_update_post( array( 'ID' => $lp->ID, 'post_status' => 'publish' ) );
	}
}

if ( class_exists( 'FEI_Programs' ) ) {
	FEI_Programs::register_post_types();
}

$menu_id = wp_create_nav_menu( 'Primary' );
if ( $menu_id && ! is_wp_error( $menu_id ) ) {
	wp_update_nav_menu_item(
		$menu_id,
		0,
		array(
			'menu-item-title'  => 'Programs',
			'menu-item-url'    => home_url( '/programs/' ),
			'menu-item-status' => 'publish',
		)
	);
	wp_update_nav_menu_item(
		$menu_id,
		0,
		array(
			'menu-item-title'     => 'Apply Now',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => (int) $apply_id,
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
		)
	);
	$locations           = get_theme_mod( 'nav_menu_locations', array() );
	$locations['primary'] = (int) $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );
}

flush_rewrite_rules( false );
echo 'FEI demo ready';
