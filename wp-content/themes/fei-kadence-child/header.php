<?php
/**
 * Custom FEI chrome — replaces the unconfigured Kadence header on Playground/staging.
 */
defined( 'ABSPATH' ) || exit;
$org  = function_exists( 'fei_org' ) ? fei_org() : array( 'name' => 'Florida Education Institute', 'phone' => '786-665-8310', 'phone_e164' => '+17866658310', 'portal' => 'https://starnet.neolms.com/' );
$lang = function_exists( 'fei_lang' ) ? fei_lang() : 'en';
$home = home_url( '/' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="wrapper" class="site fei-shell">
<header class="fei-chrome">
	<div class="fei-chrome__util">
		<div class="fei-chrome__wrap">
			<span class="fei-chrome__place">Miami, Florida</span>
			<div class="fei-utility"><?php echo do_shortcode( '[fei_utility_bar]' ); ?></div>
		</div>
	</div>
	<div class="fei-chrome__main">
		<div class="fei-chrome__wrap">
			<a class="fei-logo" href="<?php echo esc_url( $home ); ?>">
				<strong>FEI</strong>
				<span><?php echo esc_html( $org['name'] ); ?></span>
			</a>
			<input type="checkbox" id="fei-nav-toggle" class="fei-nav-toggle" hidden>
			<label for="fei-nav-toggle" class="fei-nav-burger" aria-label="Menu"><span></span></label>
			<nav class="fei-nav" aria-label="Primary">
				<a href="<?php echo esc_url( home_url( '/programs/' ) ); ?>"><?php echo esc_html( 'es' === $lang ? 'Programas' : 'Programs' ); ?></a>
				<a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php echo esc_html( 'es' === $lang ? 'Nosotros' : 'About FEI' ); ?></a>
				<a href="<?php echo esc_url( home_url( '/admissions/' ) ); ?>"><?php echo esc_html( 'es' === $lang ? 'Admisiones' : 'Admissions' ); ?></a>
				<a href="<?php echo esc_url( home_url( '/financial-aid/' ) ); ?>"><?php echo esc_html( 'es' === $lang ? 'Ayuda financiera' : 'Financial Aid' ); ?></a>
				<a href="<?php echo esc_url( home_url( '/support/' ) ); ?>"><?php echo esc_html( 'es' === $lang ? 'Apoyo' : 'Support' ); ?></a>
				<a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">Blog</a>
				<a class="fei-nav__cta" href="<?php echo esc_url( home_url( 'es' === $lang ? '/es/inscripcion/' : '/apply/' ) ); ?>" data-apply><?php echo esc_html( 'es' === $lang ? 'Inscríbete' : 'Apply Now' ); ?></a>
			</nav>
		</div>
	</div>
</header>
<div id="inner-wrap" class="wrap">
