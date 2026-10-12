<?php
/**
 * FEI — Kadence Child. Presentation only; logic is in the fei-core plugin.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_style( 'fei-fonts', 'https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400;1,600&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( 'fei-kadence-child', get_stylesheet_uri(), array( 'kadence-global' ), wp_get_theme()->get( 'Version' ) );
	},
	20
);

add_action(
	'wp_head',
	static function (): void {
		echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
		echo '<meta name="theme-color" content="#1A202C">' . "\n";
	},
	0
);

/** Utility bar content (assign the "FEI Utility Bar" HTML widget area or use Kadence Header → Top Row → HTML element with [fei_utility_bar]). */
add_shortcode(
	'fei_utility_bar',
	static function (): string {
		$org  = function_exists( 'fei_org' ) ? fei_org() : array( 'phone' => '786-665-8310', 'phone_e164' => '+17866658310', 'portal' => 'https://starnet.neolms.com/' );
		$lang = function_exists( 'fei_lang' ) ? fei_lang() : 'en';
		$en   = home_url( '/' );
		$es   = home_url( '/es/' );
		if ( function_exists( 'pll_the_languages' ) ) {
			$langs = pll_the_languages( array( 'raw' => 1 ) );
			$en    = $langs['en']['url'] ?? $en;
			$es    = $langs['es']['url'] ?? $es;
		}
		ob_start();
		?>
		<div class="fei-utility">
			<a href="tel:<?php echo esc_attr( $org['phone_e164'] ); ?>"><?php echo esc_html( ( 'es' === $lang ? 'Llama hoy ' : 'Call Today ' ) . $org['phone'] ); ?></a>
			<span class="fei-utility__sep" aria-hidden="true">|</span>
			<a href="<?php echo esc_url( $org['portal'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( 'es' === $lang ? 'Portal del Estudiante' : 'Student Portal' ); ?></a>
			<span class="fei-utility__sep" aria-hidden="true">|</span>
			<span class="fei-lang" aria-label="Language selector">
				<a href="<?php echo esc_url( $en ); ?>" hreflang="en" class="<?php echo 'en' === $lang ? 'is-active' : ''; ?>" title="English">EN</a>
				<a href="<?php echo esc_url( $es ); ?>" hreflang="es" class="<?php echo 'es' === $lang ? 'is-active' : ''; ?>" title="Español">ES</a>
			</span>
		</div>
		<?php
		return (string) ob_get_clean();
	}
);

/** Register block patterns for the home page sections (Appearance → Editor → Patterns → FEI). */
add_action(
	'init',
	static function (): void {
		if ( ! function_exists( 'register_block_pattern_category' ) ) {
			return;
		}
		register_block_pattern_category( 'fei', array( 'label' => 'FEI' ) );
		foreach ( glob( get_stylesheet_directory() . '/patterns/*.php' ) ?: array() as $file ) {
			$slug = basename( $file, '.php' );
			$data = include $file;
			if ( is_array( $data ) && ! empty( $data['content'] ) ) {
				register_block_pattern( 'fei/' . $slug, array_merge( array( 'categories' => array( 'fei' ) ), $data ) );
			}
		}
	}
);

/** Kadence: add Apply Now button to the header via the "Button" element label/URL defaults. */
add_filter(
	'kadence_header_button_output',
	static function ( string $html ): string {
		return str_replace( 'class="button', 'class="button fei-apply-btn', $html );
	}
);

/** Mark header "Apply Now" button so fei-site.js opens the modal. */
add_filter(
	'kadence_header_button_args',
	static function ( array $args ): array {
		$args['attributes'] = ( $args['attributes'] ?? '' ) . ' data-apply';
		return $args;
	}
);
