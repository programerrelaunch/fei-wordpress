<?php
/**
 * Program detail — structure mirrors fei.edu/programs/{slug}:
 * hero + facts → intro → skills → experience (opt) → careers → schedule → aid → credentials
 * → why FEI → quote → steps → FAQ → inline apply form (pre-filled program).
 */
defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	$id   = get_the_ID();
	$pr   = FEI_Programs::get_program( $id );
	$org  = fei_org();
	$lang = fei_lang();
	$hero = fei_program_banner_url( $pr );
	?>
	<main id="top" class="fei-main fei-program-page" tabindex="-1">
		<section class="phero" <?php echo $hero ? 'style="--hero:url(' . esc_url( $hero ) . ')"' : ''; ?>>
			<?php if ( $hero ) : ?><img class="phero__img" src="<?php echo esc_url( $hero ); ?>" alt="" fetchpriority="high" width="1600" height="900"><?php endif; ?>
			<div class="phero__overlay" aria-hidden="true"></div>
			<div class="phero__inner">
				<nav class="crumbs" aria-label="Breadcrumb"><a href="<?php echo esc_url( home_url( 'es' === $lang ? '/es/' : '/' ) ); ?>"><?php echo esc_html( fei_t( 'home' ) ); ?></a> › <a href="<?php echo esc_url( home_url( 'es' === $lang ? '/es/programas/' : '/programs/' ) ); ?>"><?php echo esc_html( fei_t( 'programs' ) ); ?></a> › <span aria-current="page"><?php the_title(); ?></span></nav>
				<p class="eyebrow"><span class="eyebrow__dot"></span><?php echo esc_html( $pr['area'] ); ?> · <?php echo esc_html( $pr['credential'] ); ?></p>
				<h1 class="phero__title"><?php the_title(); ?></h1>
				<p class="hero__lede"><?php echo esc_html( $pr['lede'] ); ?></p>
				<div class="hero__cta">
					<a href="#apply" class="btn btn--solid btn--lg" data-apply><?php echo esc_html( fei_t( 'start_today' ) ); ?></a>
					<a href="tel:<?php echo esc_attr( $org['phone_e164'] ); ?>" class="btn btn--outline btn--outline-light btn--lg"><?php echo esc_html( sprintf( fei_t( 'call_now' ), $org['phone'] ) ); ?></a>
				</div>
			</div>
		</section>

		<ul class="facts">
			<li><strong><?php echo esc_html( $pr['length'] ); ?></strong><span><?php echo esc_html( fei_t( 'program_length' ) ); ?></span></li>
			<li><strong><?php echo esc_html( $pr['credential'] ); ?></strong><span><?php echo esc_html( fei_t( 'credential_earned' ) ); ?></span></li>
			<li><strong><?php echo esc_html( $pr['format'] ); ?></strong><span><?php echo esc_html( $pr['format_detail'] ?: fei_t( 'format' ) ); ?></span></li>
			<li><strong><?php echo esc_html( $pr['campus'] ); ?></strong><span><?php echo esc_html( fei_t( 'campus' ) ); ?></span></li>
		</ul>

		<section class="section pintro">
			<h2 class="section__title"><?php echo esc_html( $pr['intro_title'] ); ?></h2>
			<p class="section__intro"><?php echo esc_html( $pr['intro'] ); ?></p>
			<?php if ( get_the_content() && ! str_starts_with( trim( wp_strip_all_tags( get_the_content() ) ), $pr['lede'] ) ) : ?>
				<div class="entry-content"><?php the_content(); ?></div>
			<?php endif; ?>
		</section>

		<section class="section pskills band band--tint">
			<div class="two-col">
				<div>
					<p class="kicker"><?php echo esc_html( fei_t( 'what_you_master' ) ); ?></p>
					<ul class="checklist">
						<?php foreach ( $pr['skills'] as $s ) : ?><li><?php echo esc_html( $s ); ?></li><?php endforeach; ?>
					</ul>
				</div>
				<?php if ( $pr['experience'] ) : ?>
					<div class="callout">
						<h3><?php echo esc_html( $pr['experience_title'] ); ?></h3>
						<p><?php echo esc_html( $pr['experience'] ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<section class="section pcareers">
			<div class="two-col">
				<div>
					<p class="kicker"><?php echo esc_html( fei_t( 'where_this_takes_you' ) ); ?></p>
					<ul class="pill-list">
						<?php foreach ( $pr['careers'] as $c ) : ?><li><?php echo esc_html( $c ); ?></li><?php endforeach; ?>
					</ul>
					<p class="muted"><?php echo esc_html( $pr['demand'] ); ?></p>
				</div>
				<div>
					<h3><?php echo esc_html( 'es' === $lang ? 'Diseñado para tu vida real.' : 'Built to fit a real life.' ); ?></h3>
					<p><?php echo esc_html( $pr['schedule'] ); ?></p>
					<h3><?php echo esc_html( 'es' === $lang ? 'Te ayudaremos a encontrar la forma de pagar.' : 'We will help you find a way to pay.' ); ?></h3>
					<p><?php echo esc_html( 'es' === $lang ? 'La matrícula varía y se explica mejor de manera personalizada. Hay ayuda financiera disponible para quienes califican, incluidas becas y planes de pago.' : 'Tuition varies and is best explained one-on-one, with your specific costs and any funding you qualify for laid out clearly. Financial aid is available to those who qualify, including scholarships and payment plans.' ); ?></p>
					<ul class="badges">
						<?php foreach ( $pr['credentials'] as $c ) : ?><li><?php echo esc_html( $c ); ?></li><?php endforeach; ?>
					</ul>
				</div>
			</div>
		</section>

		<section class="section advantage">
			<div class="advantage__grid">
				<div class="advantage__intro">
					<p class="kicker kicker--light"><?php echo esc_html( 'es' === $lang ? 'Por qué estudiar ' . get_the_title() . ' con FEI' : 'Why study ' . get_the_title() . ' with FEI' ); ?></p>
					<h2 class="section__title section__title--light"><?php echo esc_html( 'es' === $lang ? 'Más atención. Mejores resultados.' : 'More attention. Better outcomes.' ); ?></h2>
					<a href="#apply" class="btn btn--coral btn--lg" data-apply><?php echo esc_html( fei_t( 'talk_advisor' ) ); ?></a>
				</div>
				<ol class="advantage__cards advantage__cards--list">
					<?php foreach ( $pr['why'] as $w ) :
						$parts = explode( '. ', $w, 2 ); ?>
						<li class="feature"><h3><?php echo esc_html( $parts[0] ); ?>.</h3><p><?php echo esc_html( $parts[1] ?? '' ); ?></p></li>
					<?php endforeach; ?>
				</ol>
			</div>
		</section>

		<?php if ( $pr['quote'] ) : ?>
			<div class="band band--tint"><section class="section support"><figure class="quote"><blockquote>“<?php echo esc_html( $pr['quote'] ); ?>”</blockquote><figcaption><span class="quote__avatar" aria-hidden="true">★</span><span><strong><?php echo esc_html( $pr['quote_by'] ); ?></strong></span></figcaption></figure></section></div>
		<?php endif; ?>

		<section class="section admissions">
			<div class="section__head section__head--center"><div><p class="kicker"><?php echo esc_html( fei_t( 'how_to_start' ) ); ?></p></div></div>
			<ol class="steps steps--3">
				<li class="step"><span class="step__no">1</span><h3><?php echo esc_html( fei_t( 'talk_advisor' ) ); ?></h3><p><?php echo esc_html( 'es' === $lang ? 'Llama o completa el formulario a continuación, sin presión, solo respuestas.' : 'Call or fill out the form below, no pressure, just answers.' ); ?></p></li>
				<li class="step"><span class="step__no">2</span><h3><?php echo esc_html( 'es' === $lang ? 'Confirma que es para ti' : 'Confirm your fit' ); ?></h3><p><?php echo esc_html( 'es' === $lang ? 'Revisen juntos los requisitos y tus opciones de ayuda financiera.' : 'Review requirements and your financial aid options together.' ); ?></p></li>
				<li class="step"><span class="step__no">3</span><h3><?php echo esc_html( 'es' === $lang ? 'Inscríbete' : 'Enroll' ); ?></h3><p><?php echo esc_html( 'es' === $lang ? 'Elige tu fecha de inicio y comienza tu formación.' : 'Pick your start date and begin your ' . get_the_title() . ' training.' ); ?></p></li>
			</ol>
		</section>

		<?php if ( $pr['faq'] ) : ?>
			<section class="section faq">
				<div class="section__head"><div><p class="kicker">FAQ</p><h2 class="section__title"><?php echo esc_html( fei_t( 'quick_answers' ) ); ?></h2></div></div>
				<?php echo FEI_Templates::part( 'faq', array( 'items' => $pr['faq'], 'schema' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</section>
		<?php endif; ?>

		<section class="section apply-inline" id="apply">
			<div class="apply-inline__grid">
				<div>
					<p class="kicker"><?php echo esc_html( fei_t( 'modal_eyebrow' ) ); ?></p>
					<h2 class="section__title"><?php echo esc_html( fei_t( 'apply_to' ) . ' ' . get_the_title() ); ?></h2>
					<p class="section__intro"><?php echo esc_html( fei_t( 'modal_sub' ) ); ?></p>
					<p class="muted"><?php echo esc_html( fei_t( 'aid_note' ) ); ?> · <?php echo esc_html( fei_t( 'coe' ) ); ?></p>
				</div>
				<div class="card">
					<?php echo FEI_Templates::part( 'apply-form', array( 'prefix' => 'af', 'form_id' => 'applyForm', 'stage' => '', 'title' => '', 'success_id' => 'applySuccess', 'programs' => FEI_Programs::catalog(), 'standalone' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</section>
	</main>
	<?php
endwhile;
get_footer();
