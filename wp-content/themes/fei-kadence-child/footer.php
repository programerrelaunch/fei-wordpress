<?php
defined( 'ABSPATH' ) || exit;
$org  = function_exists( 'fei_org' ) ? fei_org() : array( 'name' => 'Florida Education Institute', 'street' => '5818 SW 8th Street', 'city' => 'Miami', 'region' => 'FL', 'zip' => '33144', 'phone' => '786-665-8310', 'phone_e164' => '+17866658310', 'email' => 'admissions@fei.edu', 'facebook' => 'https://www.facebook.com/FEIMiami', 'instagram' => 'https://www.instagram.com/feimiami/', 'x' => 'https://x.com/FEIMiami' );
$lang = function_exists( 'fei_lang' ) ? fei_lang() : 'en';
?>
</div><!-- #inner-wrap -->
<footer class="fei-foot">
	<div class="fei-foot__grid">
		<div>
			<p class="fei-logo fei-logo--light"><strong>FEI</strong><span><?php echo esc_html( $org['name'] ); ?></span></p>
			<p><?php echo esc_html( $org['street'] ); ?><br><?php echo esc_html( $org['city'] . ', ' . $org['region'] . ' ' . $org['zip'] ); ?></p>
			<p><a href="tel:<?php echo esc_attr( $org['phone_e164'] ); ?>"><?php echo esc_html( $org['phone'] ); ?></a><br>
			<a href="mailto:<?php echo esc_attr( $org['email'] ); ?>"><?php echo esc_html( $org['email'] ); ?></a></p>
		</div>
		<div>
			<h2><?php echo esc_html( 'es' === $lang ? 'Instituto' : 'Institute' ); ?></h2>
			<ul>
				<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php echo esc_html( 'es' === $lang ? 'Sobre FEI' : 'About FEI' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/admissions/' ) ); ?>"><?php echo esc_html( 'es' === $lang ? 'Admisiones' : 'Admissions' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/financial-aid/' ) ); ?>"><?php echo esc_html( 'es' === $lang ? 'Ayuda financiera' : 'Financial Aid' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/programs/' ) ); ?>"><?php echo esc_html( 'es' === $lang ? 'Programas' : 'Programs' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( 'es' === $lang ? '/es/inscripcion/' : '/apply/' ) ); ?>" data-apply><?php echo esc_html( 'es' === $lang ? 'Inscríbete' : 'Apply Now' ); ?></a></li>
			</ul>
		</div>
		<div>
			<h2><?php echo esc_html( 'es' === $lang ? 'Legal' : 'Legal' ); ?></h2>
			<ul>
				<li><a href="<?php echo esc_url( home_url( '/legal/privacy/' ) ); ?>"><?php echo esc_html( 'es' === $lang ? 'Privacidad' : 'Privacy' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/legal/terms/' ) ); ?>"><?php echo esc_html( 'es' === $lang ? 'Términos' : 'Terms' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/consumer-information/' ) ); ?>"><?php echo esc_html( 'es' === $lang ? 'Información al consumidor' : 'Consumer Information' ); ?></a></li>
			</ul>
			<p class="fei-foot__social">
				<a href="<?php echo esc_url( $org['facebook'] ); ?>">Facebook</a>
				<a href="<?php echo esc_url( $org['instagram'] ); ?>">Instagram</a>
				<a href="<?php echo esc_url( $org['x'] ); ?>">X</a>
			</p>
		</div>
	</div>
	<p class="fei-foot__copy">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $org['name'] ); ?></p>
</footer>
</div><!-- #wrapper -->
<?php wp_footer(); ?>
</body>
</html>
