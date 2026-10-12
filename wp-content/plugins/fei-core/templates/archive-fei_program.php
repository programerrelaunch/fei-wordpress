<?php
/** Programs index — mirrors fei.edu/programs with area filter chips. */
defined( 'ABSPATH' ) || exit;
get_header();
$lang  = fei_lang();
$org   = fei_org();
$areas = get_terms( array( 'taxonomy' => FEI_Programs::TAX, 'hide_empty' => true ) );
$all   = FEI_Programs::catalog();
?>
<main id="top" class="fei-main" tabindex="-1">
	<section class="section programs programs--index">
		<div class="section__head">
			<div>
				<p class="kicker"><?php echo esc_html( fei_t( 'programs' ) ); ?></p>
				<h1 class="section__title"><?php echo esc_html( 'es' === $lang ? 'Elige la carrera. Te enseñamos las habilidades.' : 'Pick the career. We will teach you the skills.' ); ?></h1>
			</div>
			<p class="section__intro"><?php echo esc_html( 'es' === $lang ? 'Once programas enfocados y orientados a la carrera en salud, artes culinarias, oficios especializados y negocios. Caminos cortos, habilidades reales e instructores que han hecho el trabajo.' : 'Eleven focused, career-driven programs across healthcare, culinary arts, skilled trades and business. Short paths, real skills, and instructors who have done the work.' ); ?></p>
		</div>
		<?php if ( $areas && ! is_wp_error( $areas ) ) : ?>
			<div class="chips" role="tablist" aria-label="Filter programs">
				<button class="chip is-active" type="button" data-filter="all" aria-pressed="true"><?php echo esc_html( 'es' === $lang ? 'Todos' : 'All' ); ?></button>
				<?php foreach ( $areas as $a ) : ?>
					<button class="chip" type="button" data-filter="<?php echo esc_attr( str_replace( '-es', '', $a->slug ) ); ?>" aria-pressed="false"><?php echo esc_html( $a->name ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php echo FEI_Templates::part( 'programs-grid', array( 'programs' => $all, 'atts' => array( 'style' => 'cards' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</section>
	<section class="cta" id="apply">
		<div class="cta__inner">
			<p class="kicker kicker--light"><?php echo esc_html( 'es' === $lang ? '¡Persigue tus sueños!' : 'Chase your dreams!' ); ?></p>
			<h2 class="cta__title"><?php echo esc_html( 'es' === $lang ? '¡Tu nueva carrera te espera!' : 'Your new career awaits!' ); ?></h2>
			<div class="cta__actions">
				<a href="tel:<?php echo esc_attr( $org['phone_e164'] ); ?>" class="btn btn--coral btn--lg"><?php echo esc_html( sprintf( fei_t( 'call_now' ), $org['phone'] ) ); ?></a>
				<a href="<?php echo esc_url( home_url( 'es' === $lang ? '/es/inscripcion/' : '/apply/' ) ); ?>" class="btn btn--outline btn--outline-light btn--lg" data-apply><?php echo esc_html( fei_t( 'apply_now' ) ); ?></a>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
