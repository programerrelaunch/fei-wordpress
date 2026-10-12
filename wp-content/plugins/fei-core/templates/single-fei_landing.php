<?php
/**
 * Paid-traffic landing page. Deliberately minimal chrome: no site nav, one conversion
 * goal, phone always visible. Uses wp_head/wp_footer so GTM + assets still load.
 */
defined( 'ABSPATH' ) || exit;
the_post();
$id    = get_the_ID();
$lp    = FEI_Landing::get( $id );
$pr    = $lp['program'];
$org   = fei_org();
$lang  = fei_lang();
$phone = $lp['phone_override'] ?: $org['phone'];
$tel   = preg_replace( '/\D+/', '', $phone );
$tel   = ( 10 === strlen( $tel ) ? '+1' : '+' ) . $tel;
$h1    = $lp['headline'] ?: ( $pr['title'] ?? get_the_title() );
$sub   = $lp['subheadline'] ?: ( $pr['lede'] ?? '' );
$cta   = $lp['cta_label'] ?: ( 'es' === $lang ? 'Recibe información del programa' : 'Get program details' );
$hero  = $lp['hero_image'] ?: ( $pr['image_url'] ?? '' );
$next  = $lp['next_url'] ?: home_url( 'es' === $lang ? '/es/inscripcion/' : '/apply/' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
<script>window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:'lp_view',lp_id:<?php echo (int) $id; ?>,lp_variant:<?php echo wp_json_encode( $lp['variant'] ?: 'A' ); ?>,program:<?php echo wp_json_encode( $pr['key'] ?? '' ); ?>});</script>
</head>
<body <?php body_class( 'fei-lp-body' ); ?>>
<?php wp_body_open(); ?>
<a href="#lpForm" class="sr-only sr-only-focusable">Skip to form</a>
<header class="lp-bar">
	<a href="<?php echo esc_url( home_url( 'es' === $lang ? '/es/' : '/' ) ); ?>" class="brand" aria-label="<?php echo esc_attr( $org['name'] ); ?>"><strong>FEI</strong><em><?php echo esc_html( $org['name'] ); ?></em></a>
	<a href="tel:<?php echo esc_attr( $tel ); ?>" class="lp-bar__phone btn btn--solid"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6.1 6.1l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z" fill="none" stroke="currentColor" stroke-width="2"/></svg><?php echo esc_html( $phone ); ?></a>
</header>

<main class="lp-main">
	<section class="lp-hero" <?php echo $hero ? 'style="--hero:url(' . esc_url( $hero ) . ')"' : ''; ?>>
		<div class="lp-hero__overlay" aria-hidden="true"></div>
		<div class="lp-hero__inner">
			<div class="lp-hero__copy">
				<p class="eyebrow"><span class="eyebrow__dot"></span><?php echo esc_html( 'es' === $lang ? 'Miami, Florida · Educación Profesional' : 'Miami, Florida · Career Education' ); ?></p>
				<h1 class="lp-hero__title"><?php echo esc_html( $h1 ); ?></h1>
				<?php if ( $sub ) : ?><p class="hero__lede"><?php echo esc_html( $sub ); ?></p><?php endif; ?>
				<?php if ( $pr ) : ?>
					<ul class="hero__proof">
						<li><strong><?php echo esc_html( $pr['length'] ); ?></strong></li>
						<li><strong><?php echo esc_html( $pr['credential'] ); ?></strong></li>
						<li><strong><?php echo esc_html( $pr['format'] ); ?></strong></li>
					</ul>
				<?php endif; ?>
				<?php if ( $lp['bullets'] ) : ?>
					<ul class="checklist checklist--light">
						<?php foreach ( $lp['bullets'] as $b ) : ?><li><?php echo esc_html( $b ); ?></li><?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<p class="hero__chip"><span class="hero__chip-stars">★★★★★</span><em>“<?php echo esc_html( 'es' === $lang ? 'Realmente me conocían.' : 'They actually knew me.' ); ?>”</em></p>
			</div>
			<div class="lp-hero__form card" id="lpForm">
				<?php
				if ( 'two_step' === $lp['form_mode'] ) {
					echo FEI_Templates::part( 'prereg-form', array( 'program' => $pr['key'] ?? '', 'program_type' => $pr['type'] ?? '', 'next' => $next, 'title' => $cta, 'cta' => fei_t( 'next_step' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					echo FEI_Templates::part( 'apply-form', array( 'prefix' => 'lp', 'form_id' => 'lpApplyForm', 'stage' => 'full', 'title' => $cta, 'success_id' => 'lpSuccess', 'programs' => FEI_Programs::catalog(), 'standalone' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</div>
		</div>
	</section>

	<?php if ( $lp['offer_strip'] ) : ?>
		<div class="lp-strip"><?php echo esc_html( $lp['offer_strip'] ); ?></div>
	<?php endif; ?>

	<ul class="facts facts--trust">
		<?php foreach ( $lp['proof'] as $t ) : ?><li><strong>✓</strong><span><?php echo esc_html( $t ); ?></span></li><?php endforeach; ?>
	</ul>

	<?php if ( $pr ) : ?>
		<section class="section two-col lp-body">
			<div>
				<p class="kicker"><?php echo esc_html( fei_t( 'what_you_master' ) ); ?></p>
				<ul class="checklist"><?php foreach ( $pr['skills'] as $s ) : ?><li><?php echo esc_html( $s ); ?></li><?php endforeach; ?></ul>
			</div>
			<div>
				<p class="kicker"><?php echo esc_html( fei_t( 'where_this_takes_you' ) ); ?></p>
				<ul class="pill-list"><?php foreach ( $pr['careers'] as $c ) : ?><li><?php echo esc_html( $c ); ?></li><?php endforeach; ?></ul>
				<p class="muted"><?php echo esc_html( $pr['schedule'] ); ?></p>
			</div>
		</section>
		<?php if ( $pr['quote'] ) : ?>
			<div class="band band--tint"><section class="section support"><figure class="quote"><blockquote>“<?php echo esc_html( $pr['quote'] ); ?>”</blockquote><figcaption><span class="quote__avatar" aria-hidden="true">★</span><span><strong><?php echo esc_html( $pr['quote_by'] ); ?></strong></span></figcaption></figure></section></div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( get_the_content() ) : ?>
		<section class="section entry-content lp-content"><?php the_content(); ?></section>
	<?php endif; ?>

	<section class="section admissions">
		<div class="section__head section__head--center"><div><p class="kicker"><?php echo esc_html( fei_t( 'how_to_start' ) ); ?></p><h2 class="section__title"><?php echo esc_html( 'es' === $lang ? 'Empezar es sencillo.' : 'Getting started is simple.' ); ?></h2></div></div>
		<ol class="steps steps--3">
			<li class="step"><span class="step__no">1</span><h3><?php echo esc_html( fei_t( 'talk_advisor' ) ); ?></h3><p><?php echo esc_html( 'es' === $lang ? 'Completa el formulario y un asesor te llamará.' : 'Fill out the form and an advisor calls you back.' ); ?></p></li>
			<li class="step"><span class="step__no">2</span><h3><?php echo esc_html( 'es' === $lang ? 'Planifica tu ayuda' : 'Plan your aid' ); ?></h3><p><?php echo esc_html( 'es' === $lang ? 'Revisamos juntos la ayuda financiera y becas a las que califiques.' : 'We review financial aid and scholarships you may qualify for, together.' ); ?></p></li>
			<li class="step"><span class="step__no">3</span><h3><?php echo esc_html( 'es' === $lang ? 'Inscríbete' : 'Enroll' ); ?></h3><p><?php echo esc_html( 'es' === $lang ? 'Las clases empiezan cada 3–4 semanas.' : 'Classes start every 3–4 weeks.' ); ?></p></li>
		</ol>
	</section>

	<?php if ( '1' === ( $lp['show_faq'] ?: '1' ) && ! empty( $pr['faq'] ) ) : ?>
		<section class="section faq">
			<div class="section__head"><div><p class="kicker">FAQ</p><h2 class="section__title"><?php echo esc_html( fei_t( 'quick_answers' ) ); ?></h2></div></div>
			<?php echo FEI_Templates::part( 'faq', array( 'items' => $pr['faq'], 'schema' => false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</section>
	<?php endif; ?>

	<section class="cta">
		<div class="cta__inner">
			<h2 class="cta__title"><?php echo esc_html( 'es' === $lang ? '¡Tu nueva carrera te espera!' : 'Your new career awaits!' ); ?></h2>
			<div class="cta__actions">
				<a href="tel:<?php echo esc_attr( $tel ); ?>" class="btn btn--coral btn--lg"><?php echo esc_html( sprintf( fei_t( 'call_now' ), $phone ) ); ?></a>
				<a href="#lpForm" class="btn btn--outline btn--outline-light btn--lg"><?php echo esc_html( $cta ); ?></a>
			</div>
		</div>
	</section>
</main>

<footer class="lp-footer">
	<p><?php echo esc_html( $org['name'] . ' · ' . $org['street'] . ', ' . $org['city'] . ', ' . $org['region'] . ' ' . $org['zip'] ); ?> · <a href="tel:<?php echo esc_attr( $tel ); ?>"><?php echo esc_html( $phone ); ?></a></p>
	<p><?php echo esc_html( fei_t( 'coe' ) ); ?> · <?php echo esc_html( fei_t( 'aid_note' ) ); ?></p>
	<p><a href="<?php echo esc_url( home_url( 'es' === $lang ? '/es/legal/privacidad/' : '/legal/privacy/' ) ); ?>"><?php echo esc_html( fei_t( 'privacy' ) ); ?></a> · <a href="<?php echo esc_url( home_url( 'es' === $lang ? '/es/legal/terminos/' : '/legal/terms/' ) ); ?>"><?php echo esc_html( fei_t( 'terms' ) ); ?></a> · <a href="<?php echo esc_url( home_url( 'es' === $lang ? '/es/informacion-al-consumidor/' : '/consumer-information/' ) ); ?>"><?php echo esc_html( 'es' === $lang ? 'Información al consumidor' : 'Consumer Information' ); ?></a></p>
</footer>

<div class="lp-sticky">
	<a href="tel:<?php echo esc_attr( $tel ); ?>" class="btn btn--solid"><?php echo esc_html( sprintf( fei_t( 'call_now' ), $phone ) ); ?></a>
	<a href="#lpForm" class="btn btn--outline"><?php echo esc_html( $cta ); ?></a>
</div>
<?php wp_footer(); ?>
</body>
</html>
