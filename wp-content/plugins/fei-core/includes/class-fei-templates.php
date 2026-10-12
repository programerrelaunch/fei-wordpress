<?php
/**
 * Front-end rendering: assets, apply form (shortcode + modal), program/landing templates.
 *
 * Shortcodes
 *   [fei_apply_form]                 full form (used on /apply and /es/inscripcion)
 *   [fei_apply_form prefix="lp"]     LP step-2 form
 *   [fei_prereg_form program="…"]    2-step step-1 form
 *   [fei_apply_button label="…"]     button that opens the modal
 *   [fei_programs_grid]              (registered in FEI_Programs)
 *   [fei_faq id="…"]                 accordion from a program's FAQ or inline items
 *   [fei_net_price_calculator]       full-bleed NPC iframe
 */

defined( 'ABSPATH' ) || exit;

final class FEI_Templates {

	private static ?FEI_Templates $instance = null;

	public static function instance(): FEI_Templates {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'template_include', array( $this, 'template' ) );
		add_action( 'wp_footer', array( $this, 'modal' ), 5 );
		add_action( 'wp_body_open', array( $this, 'program_meta' ) );
		add_shortcode( 'fei_apply_form', array( $this, 'sc_apply_form' ) );
		add_shortcode( 'fei_prereg_form', array( $this, 'sc_prereg_form' ) );
		add_shortcode( 'fei_apply_button', array( $this, 'sc_apply_button' ) );
		add_shortcode( 'fei_faq', array( $this, 'sc_faq' ) );
		add_shortcode( 'fei_net_price_calculator', array( $this, 'sc_npc' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	public function assets(): void {
		wp_enqueue_style( 'fei-core', FEI_CORE_URL . 'assets/css/fei.css', array(), FEI_CORE_VERSION );
		wp_enqueue_style( 'fei-intl-tel', 'https://cdn.jsdelivr.net/npm/intl-tel-input@24.6.0/build/css/intlTelInput.css', array(), '24.6.0' );
		wp_enqueue_script( 'fei-intl-tel', 'https://cdn.jsdelivr.net/npm/intl-tel-input@24.6.0/build/js/intlTelInputWithUtils.min.js', array(), '24.6.0', array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'fei-site', FEI_CORE_URL . 'assets/js/fei-site.js', array( 'fei-intl-tel' ), FEI_CORE_VERSION, array( 'strategy' => 'defer' ) );
		wp_localize_script(
			'fei-site',
			'FEI',
			array(
				'endpoint'      => esc_url_raw( rest_url( 'fei/v1/lead' ) ),
				'errorEndpoint' => esc_url_raw( rest_url( 'fei/v1/lead-error' ) ),
				'lang'          => fei_lang(),
				'applyUrl'      => home_url( 'es' === fei_lang() ? '/es/inscripcion/' : '/apply/' ),
				'turnstileKey'  => (string) fei_opt( 'turnstile_site_key', '' ),
				'i18n'          => array(
					'sending'  => fei_t( 'sending' ),
					'there'    => fei_t( 'there' ),
					'applyTo'  => fei_t( 'apply_to' ),
				),
			)
		);
		if ( fei_opt( 'turnstile_site_key' ) ) {
			wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, array( 'strategy' => 'defer' ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
	}

	public function body_class( array $classes ): array {
		if ( is_singular( FEI_Landing::CPT ) ) {
			$classes[] = 'fei-lp';
		}
		if ( is_singular( FEI_Programs::CPT ) ) {
			$classes[] = 'fei-program';
		}
		return $classes;
	}

	/** Theme can override by placing fei-core/{template}.php in the (child) theme. */
	public function template( string $template ): string {
		$map = array();
		if ( is_singular( FEI_Programs::CPT ) ) {
			$map = array( 'single-fei_program.php' );
		} elseif ( is_post_type_archive( FEI_Programs::CPT ) || is_tax( FEI_Programs::TAX ) ) {
			$map = array( 'archive-fei_program.php' );
		} elseif ( is_singular( FEI_Landing::CPT ) ) {
			$map = array( 'single-fei_landing.php' );
		}
		if ( ! $map ) {
			return $template;
		}
		$theme = locate_template( array_map( static fn( $f ) => 'fei-core/' . $f, $map ) );
		if ( $theme ) {
			return $theme;
		}
		// If the active theme already ships the CPT template, respect it.
		if ( str_contains( basename( $template ), 'fei_' ) ) {
			return $template;
		}
		return FEI_CORE_DIR . 'templates/' . $map[0];
	}

	/** Program pages: hidden meta the JS reads to pre-fill the form (same contract as Astro). */
	public function program_meta(): void {
		if ( is_singular( FEI_Programs::CPT ) ) {
			$id = get_queried_object_id();
			printf(
				'<div id="feiProgramMeta" data-program="%s" data-program-type="%s" data-program-label="%s" hidden></div>',
				esc_attr( FEI_Programs::get_field( $id, 'key' ) ),
				esc_attr( FEI_Programs::get_field( $id, 'type' ) ),
				esc_attr( get_the_title( $id ) )
			);
		} elseif ( is_singular( FEI_Landing::CPT ) ) {
			$lp = FEI_Landing::get( get_queried_object_id() );
			if ( $lp['program'] ) {
				printf(
					'<div id="feiProgramMeta" data-program="%s" data-program-type="%s" data-program-label="%s" hidden></div>',
					esc_attr( $lp['program']['key'] ),
					esc_attr( $lp['program']['type'] ),
					esc_attr( $lp['program']['title'] )
				);
			}
		}
	}

	/* ---------- Partials ---------- */

	public static function part( string $name, array $vars = array() ): string {
		$file = locate_template( 'fei-core/parts/' . $name . '.php' ) ?: FEI_CORE_DIR . 'templates/parts/' . $name . '.php';
		if ( ! file_exists( $file ) ) {
			return '';
		}
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		ob_start();
		include $file;
		return (string) ob_get_clean();
	}

	public function modal(): void {
		if ( is_admin() || is_singular( FEI_Landing::CPT ) ) {
			return; // LPs keep a single conversion path (no modal competing with the hero form).
		}
		echo self::part( 'modal', array( 'programs' => FEI_Programs::catalog() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function sc_apply_form( $atts ): string {
		$a = shortcode_atts( array( 'prefix' => 'af', 'id' => 'applyForm', 'stage' => '', 'title' => '', 'success_id' => 'applySuccess' ), $atts, 'fei_apply_form' );
		return self::part(
			'apply-form',
			array(
				'prefix'     => preg_replace( '/[^a-z0-9]/', '', strtolower( $a['prefix'] ) ) ?: 'af',
				'form_id'    => sanitize_html_class( $a['id'] ),
				'stage'      => $a['stage'],
				'title'      => $a['title'],
				'success_id' => sanitize_html_class( $a['success_id'] ),
				'programs'   => FEI_Programs::catalog(),
				'standalone' => true,
			)
		);
	}

	public function sc_prereg_form( $atts ): string {
		$a = shortcode_atts( array( 'program' => '', 'program_type' => '', 'next' => '', 'title' => '', 'cta' => '' ), $atts, 'fei_prereg_form' );
		return self::part( 'prereg-form', $a );
	}

	public function sc_apply_button( $atts, $content = '' ): string {
		$a = shortcode_atts( array( 'label' => fei_t( 'apply_now' ), 'class' => 'btn btn--solid' ), $atts, 'fei_apply_button' );
		return sprintf( '<a href="%s" class="%s" data-apply>%s</a>', esc_url( home_url( 'es' === fei_lang() ? '/es/inscripcion/' : '/apply/' ) ), esc_attr( $a['class'] ), esc_html( $content ?: $a['label'] ) );
	}

	public function sc_faq( $atts, $content = '' ): string {
		$a     = shortcode_atts( array( 'program' => '', 'schema' => '1' ), $atts, 'fei_faq' );
		$items = array();
		if ( $a['program'] ) {
			$items = FEI_Programs::get_field( (int) $a['program'], 'faq' );
		} elseif ( $content ) {
			foreach ( preg_split( '/\r\n|\r|\n/', strip_tags( $content ) ) as $line ) {
				$p = array_map( 'trim', explode( '||', $line, 2 ) );
				if ( count( $p ) === 2 ) {
					$items[] = array( 'q' => $p[0], 'a' => $p[1] );
				}
			}
		}
		return self::part( 'faq', array( 'items' => $items, 'schema' => '1' === $a['schema'] ) );
	}

	public function sc_npc( $atts ): string {
		return sprintf(
			'<div class="npc-fullbleed"><iframe src="%s" title="Net Price Calculator" loading="lazy" scrolling="yes"></iframe></div>',
			esc_url( fei_org()['npc_url'] )
		);
	}
}
