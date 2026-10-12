<?php
/**
 * Landing pages for paid traffic: CPT `fei_landing` at /lp/{slug} (EN) and /es/lp/{slug} (ES).
 *
 * CRO template (templates/single-fei_landing.php): no site nav, click-to-call sticky bar,
 * program-bound hero with the 2-step pre-registration form (step 1: name/email/phone →
 * step 2: /apply with zip + consents, same preRegId), trust strip (COE, aid, 1:1),
 * outcomes, FAQ, single CTA repeated. Always `noindex,nofollow`; canonical to itself.
 *
 * Variants: the `variant` meta (A/B) is pushed to the dataLayer so GTM/GA4 can split
 * conversion rates without a third-party testing tool.
 */

defined( 'ABSPATH' ) || exit;

final class FEI_Landing {

	const CPT = 'fei_landing';

	const META = array( 'program_id', 'headline', 'subheadline', 'cta_label', 'hero_image', 'variant', 'form_mode', 'next_url', 'phone_override', 'bullets', 'proof', 'show_faq', 'offer_strip' );

	private static ?FEI_Landing $instance = null;

	public static function instance(): FEI_Landing {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_box' ) );
		add_action( 'save_post_' . self::CPT, array( $this, 'save' ), 10, 2 );
		add_filter( 'pll_get_post_types', array( $this, 'pll' ), 10, 2 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_action( 'admin_post_fei_generate_lps', array( $this, 'generate_for_programs' ) );
	}

	public static function register_post_type(): void {
		register_post_type(
			self::CPT,
			array(
				'labels'        => array( 'name' => __( 'Landing Pages', 'fei-core' ), 'singular_name' => __( 'Landing Page', 'fei-core' ) ),
				'public'        => true,
				'has_archive'   => false,
				'rewrite'       => array( 'slug' => 'lp', 'with_front' => false ),
				'show_in_menu'  => 'fei-core',
				'supports'      => array( 'title', 'editor', 'thumbnail', 'revisions' ),
				'show_in_rest'  => true,
				'exclude_from_search' => true,
			)
		);
	}

	public function pll( array $types, bool $is_settings ): array {
		$types[ self::CPT ] = self::CPT;
		return $types;
	}

	public function robots( array $robots ): array {
		if ( is_singular( self::CPT ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}

	public static function get( int $post_id ): array {
		$out = array();
		foreach ( self::META as $k ) {
			$out[ $k ] = (string) get_post_meta( $post_id, '_fei_lp_' . $k, true );
		}
		$out['program'] = $out['program_id'] ? FEI_Programs::get_program( (int) $out['program_id'] ) : array();
		$out['bullets'] = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $out['bullets'] ) ) ) );
		$out['proof']   = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $out['proof'] ) ) ) );
		if ( ! $out['bullets'] && $out['program'] ) {
			$out['bullets'] = array_slice( (array) $out['program']['skills'], 0, 5 );
		}
		if ( ! $out['proof'] ) {
			$out['proof'] = 'es' === fei_lang()
				? array( 'Acreditado por COE', 'Ayuda financiera para quienes califiquen', 'Clases cada 3–4 semanas', 'Atención 1:1' )
				: array( 'COE accredited', 'Aid for those who qualify', 'Classes start every 3–4 weeks', '1:1 student attention' );
		}
		$out['form_mode'] = in_array( $out['form_mode'], array( 'two_step', 'full' ), true ) ? $out['form_mode'] : 'two_step';
		return $out;
	}

	public function meta_box(): void {
		add_meta_box( 'fei_lp', __( 'Landing page settings', 'fei-core' ), array( $this, 'render_box' ), self::CPT, 'normal', 'high' );
	}

	public function render_box( WP_Post $post ): void {
		wp_nonce_field( 'fei_lp_meta', 'fei_lp_nonce' );
		$m        = self::get( $post->ID );
		$programs = get_posts( array( 'post_type' => FEI_Programs::CPT, 'post_status' => 'any', 'posts_per_page' => 50, 'orderby' => 'menu_order', 'order' => 'ASC' ) );
		echo '<table class="form-table">';
		echo '<tr><th>' . esc_html__( 'Program', 'fei-core' ) . '</th><td><select name="fei_lp[program_id]"><option value="">—</option>';
		foreach ( $programs as $p ) {
			printf( '<option value="%d" %s>%s</option>', $p->ID, selected( (int) $m['program_id'], $p->ID, false ), esc_html( $p->post_title ) );
		}
		echo '</select></td></tr>';
		$text = array(
			'headline'       => 'Headline (H1) — default: program title',
			'subheadline'    => 'Sub-headline — default: program lede',
			'cta_label'      => 'CTA label — default: "Get program details"',
			'hero_image'     => 'Hero image URL — default: program image',
			'variant'        => 'Variant label for A/B reporting (e.g. A, B)',
			'next_url'       => 'Step-2 URL — default: /apply (or /es/inscripcion)',
			'phone_override' => 'Tracking phone number (for call tracking) — default: 786-665-8310',
			'offer_strip'    => 'Urgency strip text (e.g. "Next start date: Nov 3") — leave blank to hide',
		);
		foreach ( $text as $k => $label ) {
			printf( '<tr><th><label>%s</label></th><td><input class="large-text" name="fei_lp[%s]" value="%s"></td></tr>', esc_html( $label ), esc_attr( $k ), esc_attr( $m[ $k ] ) );
		}
		printf( '<tr><th>Form mode</th><td><select name="fei_lp[form_mode]"><option value="two_step" %s>2-step (name/email/phone → /apply)</option><option value="full" %s>Full form on page</option></select></td></tr>', selected( 'two_step', $m['form_mode'], false ), selected( 'full', $m['form_mode'], false ) );
		printf( '<tr><th>Bullets (one per line)</th><td><textarea class="large-text" rows="5" name="fei_lp[bullets]">%s</textarea></td></tr>', esc_textarea( implode( "\n", $m['bullets'] ) ) );
		printf( '<tr><th>Trust strip (one per line)</th><td><textarea class="large-text" rows="4" name="fei_lp[proof]">%s</textarea></td></tr>', esc_textarea( implode( "\n", $m['proof'] ) ) );
		printf( '<tr><th>Show FAQ</th><td><input type="hidden" name="fei_lp[show_faq]" value="0"><input type="checkbox" name="fei_lp[show_faq]" value="1" %s></td></tr>', checked( '1', $m['show_faq'] ?: '1', false ) );
		echo '</table>';
		printf( '<p><a class="button" href="%s">%s</a> <span class="description">%s</span></p>', esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fei_generate_lps' ), 'fei_generate_lps' ) ), esc_html__( 'Generate a draft LP for every program', 'fei-core' ), esc_html__( 'Creates /lp/{program-slug} drafts that do not exist yet.', 'fei-core' ) );
	}

	public function save( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['fei_lp_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['fei_lp_nonce'] ), 'fei_lp_meta' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in = isset( $_POST['fei_lp'] ) && is_array( $_POST['fei_lp'] ) ? wp_unslash( $_POST['fei_lp'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		foreach ( self::META as $k ) {
			if ( ! isset( $in[ $k ] ) ) {
				continue;
			}
			$v = in_array( $k, array( 'hero_image', 'next_url' ), true ) ? esc_url_raw( (string) $in[ $k ] ) : sanitize_textarea_field( (string) $in[ $k ] );
			update_post_meta( $post_id, '_fei_lp_' . $k, $v );
		}
	}

	/** Create one draft LP per program (per language) that lacks one. */
	/** Admin-post handler: Landing Pages → "Generate a draft LP for every program". */
	public function generate_for_programs(): void {
		check_admin_referer( 'fei_generate_lps' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden' );
		}
		$created = self::generate();
		wp_safe_redirect( add_query_arg( array( 'post_type' => self::CPT, 'fei_generated' => $created ), admin_url( 'edit.php' ) ) );
		exit;
	}

	/** Create one draft landing page per program that has none yet (EN/ES paired via Polylang). Returns count created. */
	public static function generate(): int {
		$programs = get_posts( array( 'post_type' => FEI_Programs::CPT, 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => 50 ) );
		$created  = 0;
		$pairs    = array();
		foreach ( $programs as $p ) {
			$exists = get_posts( array( 'post_type' => self::CPT, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_key' => '_fei_lp_program_id', 'meta_value' => $p->ID ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			if ( $exists ) {
				continue;
			}
			$lang = function_exists( 'pll_get_post_language' ) ? ( pll_get_post_language( $p->ID ) ?: 'en' ) : 'en';
			$id   = wp_insert_post( array( 'post_type' => self::CPT, 'post_status' => 'draft', 'post_title' => $p->post_title, 'post_name' => $p->post_name ) );
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_fei_lp_program_id', $p->ID );
				update_post_meta( $id, '_fei_lp_variant', 'A' );
				update_post_meta( $id, '_fei_lp_form_mode', 'two_step' );
				update_post_meta( $id, '_fei_lp_show_faq', '1' );
				if ( function_exists( 'pll_set_post_language' ) ) {
					pll_set_post_language( $id, $lang );
					$key = (string) FEI_Programs::get_field( $p->ID, 'key' );
					$pairs[ $key ][ $lang ] = $id;
				}
				++$created;
			}
		}
		if ( function_exists( 'pll_save_post_translations' ) ) {
			foreach ( $pairs as $ids ) {
				if ( count( $ids ) === 2 ) {
					pll_save_post_translations( $ids );
				}
			}
		}
		return $created;
	}
}
