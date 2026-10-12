<?php
/**
 * Programs: CPT `fei_program`, taxonomy `fei_area`, structured meta, JSON seeding,
 * Polylang pairing, and a catalog accessor used by forms/templates/schema.
 *
 * URL parity with the Astro site: /programs/{slug} (EN) and /es/programas/{slug} (ES).
 * Polylang handles the /es/ prefix; the ES archive slug is rewritten by FEI_Redirects.
 */

defined( 'ABSPATH' ) || exit;

final class FEI_Programs {

	const CPT      = 'fei_program';
	const TAX      = 'fei_area';
	const META_KEY = '_fei_program';

	/** Meta fields stored individually (for query/REST) — all strings. */
	const FIELDS = array( 'key', 'type', 'credential', 'lede', 'short', 'length', 'format', 'format_detail', 'campus', 'intro_title', 'intro', 'experience_title', 'experience', 'demand', 'schedule', 'quote', 'quote_by', 'seo_title', 'order', 'image' );

	/** Meta fields stored as JSON arrays. */
	const LIST_FIELDS = array( 'skills', 'careers', 'credentials', 'why', 'faq' );

	private static ?FEI_Programs $instance = null;

	public static function instance(): FEI_Programs {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ) );
		add_action( 'save_post_' . self::CPT, array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'pll_get_post_types', array( $this, 'pll_post_types' ), 10, 2 );
		add_filter( 'pll_get_taxonomies', array( $this, 'pll_taxonomies' ), 10, 2 );
		add_action( 'admin_post_fei_reseed_programs', array( $this, 'handle_reseed' ) );
		add_shortcode( 'fei_programs_grid', array( $this, 'shortcode_grid' ) );
	}

	public static function register_post_types(): void {
		register_post_type(
			self::CPT,
			array(
				'labels'       => array(
					'name'          => __( 'Programs', 'fei-core' ),
					'singular_name' => __( 'Program', 'fei-core' ),
					'add_new_item'  => __( 'Add Program', 'fei-core' ),
					'edit_item'     => __( 'Edit Program', 'fei-core' ),
				),
				'public'       => true,
				'has_archive'  => 'programs',
				'rewrite'      => array( 'slug' => 'programs', 'with_front' => false ),
				'menu_icon'    => 'dashicons-welcome-learn-more',
				'menu_position' => 5,
				'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'page-attributes' ),
				'show_in_rest' => true,
				'rest_base'    => 'programs',
			)
		);
		register_taxonomy(
			self::TAX,
			self::CPT,
			array(
				'labels'       => array( 'name' => __( 'Program Areas', 'fei-core' ), 'singular_name' => __( 'Program Area', 'fei-core' ) ),
				'public'       => true,
				'hierarchical' => true,
				'rewrite'      => array( 'slug' => 'programs/area', 'with_front' => false ),
				'show_in_rest' => true,
			)
		);
	}

	public function register_meta(): void {
		foreach ( array_merge( self::FIELDS, self::LIST_FIELDS ) as $f ) {
			register_post_meta(
				self::CPT,
				self::META_KEY . '_' . $f,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'wp_kses_post',
					'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
				)
			);
		}
	}

	public function pll_post_types( array $types, bool $is_settings ): array {
		$types[ self::CPT ] = self::CPT;
		return $types;
	}

	public function pll_taxonomies( array $taxes, bool $is_settings ): array {
		$taxes[ self::TAX ] = self::TAX;
		return $taxes;
	}

	/* ---------- Data access ---------- */

	public static function get_field( int $post_id, string $field ) {
		$v = get_post_meta( $post_id, self::META_KEY . '_' . $field, true );
		if ( in_array( $field, self::LIST_FIELDS, true ) ) {
			$d = json_decode( (string) $v, true );
			return is_array( $d ) ? $d : array();
		}
		return (string) $v;
	}

	/** Full structured program array for templates. */
	public static function get_program( int $post_id ): array {
		$out = array( 'id' => $post_id, 'title' => get_the_title( $post_id ), 'url' => get_permalink( $post_id ) );
		foreach ( array_merge( self::FIELDS, self::LIST_FIELDS ) as $f ) {
			$out[ $f ] = self::get_field( $post_id, $f );
		}
		$terms        = get_the_terms( $post_id, self::TAX );
		$out['area']  = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
		$out['area_slug'] = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->slug : '';
		$out['image_url'] = get_the_post_thumbnail_url( $post_id, 'large' ) ?: ( $out['image'] ?: '' );
		return $out;
	}

	/**
	 * Catalog for the form dropdown: [ ['key'=>'Medical Assistant','type'=>'Diploma','label'=>'Medical Assistant','url'=>...], ... ]
	 * Reads published programs in the current language; falls back to JSON seed if none exist yet.
	 */
	public static function catalog( ?string $lang = null ): array {
		$lang = $lang ?: fei_lang();
		$args = array(
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);
		if ( function_exists( 'pll_current_language' ) ) {
			$args['lang'] = $lang;
		}
		$posts = get_posts( $args );
		$out   = array();
		foreach ( $posts as $p ) {
			$key = self::get_field( $p->ID, 'key' ) ?: $p->post_title;
			$out[] = array(
				'key'   => $key,
				'type'  => self::get_field( $p->ID, 'type' ) ?: 'Diploma',
				'label' => $p->post_title . ( 'Associate Degree' === self::get_field( $p->ID, 'type' ) ? ' (A.A.S.)' : '' ),
				'url'   => get_permalink( $p ),
				'id'    => $p->ID,
			);
		}
		if ( ! $out ) {
			foreach ( self::seed_data()['programs'] ?? array() as $prog ) {
				$loc   = $prog[ $lang ] ?? $prog['en'];
				$out[] = array(
					'key'   => $prog['key'],
					'type'  => $prog['type'],
					'label' => $loc['title'] . ( 'Associate Degree' === $prog['type'] ? ' (A.A.S.)' : '' ),
					'url'   => home_url( ( 'es' === $lang ? '/es/programas/' : '/programs/' ) . $loc['slug'] ),
					'id'    => 0,
				);
			}
		}
		return $out;
	}

	/** Program keys accepted by the lead validator. */
	public static function valid_keys(): array {
		$keys = array_column( self::catalog( 'en' ), 'key' );
		foreach ( self::seed_data()['programs'] ?? array() as $prog ) {
			$keys[] = $prog['key'];
		}
		return array_values( array_unique( $keys ) );
	}

	public static function seed_data(): array {
		static $data = null;
		if ( null === $data ) {
			$raw  = file_get_contents( FEI_CORE_DIR . 'data/programs.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$data = json_decode( (string) $raw, true ) ?: array();
		}
		return $data;
	}

	/* ---------- Seeding ---------- */

	/**
	 * Create/refresh program posts from data/programs.json. Idempotent: matched by key+lang.
	 * Does not overwrite manually edited post_content; refreshes structured meta only.
	 */
	public static function seed_from_json( bool $force_meta = true ): array {
		$data   = self::seed_data();
		$report = array( 'created' => 0, 'updated' => 0 );
		// Only treat Polylang as present when both languages are actually configured; otherwise a
		// language-less ES copy would be created and never matched again by the `lang` query.
		$has_pll = function_exists( 'pll_set_post_language' ) && function_exists( 'pll_languages_list' )
			&& ! empty( $GLOBALS['polylang'] )
			&& ! array_diff( array( 'en', 'es' ), (array) pll_languages_list() );
		$langs   = $has_pll ? array( 'en', 'es' ) : array( 'en' );

		// Areas.
		$area_ids = array();
		foreach ( $data['areas'] ?? array() as $slug => $names ) {
			foreach ( $langs as $lang ) {
				$tslug = 'en' === $lang ? $slug : $slug . '-es';
				$term  = term_exists( $tslug, self::TAX );
				if ( ! $term ) {
					$term = wp_insert_term( $names[ $lang ] ?? $names['en'], self::TAX, array( 'slug' => $tslug ) );
				}
				if ( ! is_wp_error( $term ) ) {
					$area_ids[ $lang ][ $slug ] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
					if ( $has_pll ) {
						pll_set_term_language( $area_ids[ $lang ][ $slug ], $lang );
					}
				}
			}
			if ( $has_pll && isset( $area_ids['en'][ $slug ], $area_ids['es'][ $slug ] ) ) {
				pll_save_term_translations( array( 'en' => $area_ids['en'][ $slug ], 'es' => $area_ids['es'][ $slug ] ) );
			}
		}

		foreach ( $data['programs'] ?? array() as $prog ) {
			$ids = array();
			foreach ( $langs as $lang ) {
				$loc = $prog[ $lang ] ?? null;
				if ( ! $loc ) {
					continue;
				}
				$existing = get_posts(
					array(
						'post_type'      => self::CPT,
						'post_status'    => 'any',
						'posts_per_page' => 1,
						'meta_key'       => self::META_KEY . '_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_value'     => $prog['key'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
						'lang'           => $has_pll ? $lang : '',
						'fields'         => 'ids',
					)
				);
				$pending = ! empty( $loc['es_detail_pending'] );
				$postarr = array(
					'post_type'    => self::CPT,
					'post_title'   => $loc['title'],
					'post_name'    => $loc['slug'],
					'post_status'  => $pending ? 'draft' : 'publish',
					'post_excerpt' => $loc['short'] ?? '',
					'menu_order'   => (int) $prog['order'],
				);
				if ( $existing ) {
					$post_id = (int) $existing[0];
					unset( $postarr['post_status'] ); // never flip status of an existing post.
					$postarr['ID'] = $post_id;
					wp_update_post( $postarr );
					++$report['updated'];
				} else {
					$postarr['post_content'] = '<!-- wp:paragraph --><p>' . esc_html( $loc['lede'] ?? $loc['short'] ?? '' ) . '</p><!-- /wp:paragraph -->';
					$post_id = (int) wp_insert_post( $postarr );
					++$report['created'];
				}
				if ( ! $post_id ) {
					continue;
				}
				$ids[ $lang ] = $post_id;

				if ( $force_meta ) {
					$meta = array_merge(
						$loc,
						array(
							'key'        => $prog['key'],
							'type'       => $prog['type'],
							'credential' => $prog['credential'],
							'order'      => (string) $prog['order'],
							'image'      => '/img/programs/' . ( $prog['en']['slug'] ) . '.webp',
						)
					);
					foreach ( self::FIELDS as $f ) {
						if ( isset( $meta[ $f ] ) ) {
							update_post_meta( $post_id, self::META_KEY . '_' . $f, (string) $meta[ $f ] );
						}
					}
					foreach ( self::LIST_FIELDS as $f ) {
						if ( isset( $meta[ $f ] ) && is_array( $meta[ $f ] ) ) {
							update_post_meta( $post_id, self::META_KEY . '_' . $f, wp_json_encode( $meta[ $f ], JSON_UNESCAPED_UNICODE ) );
						}
					}
				}
				if ( isset( $area_ids[ $lang ][ $prog['area'] ] ) ) {
					wp_set_object_terms( $post_id, array( $area_ids[ $lang ][ $prog['area'] ] ), self::TAX );
				}
				if ( $has_pll ) {
					pll_set_post_language( $post_id, $lang );
				}
			}
			if ( $has_pll && count( $ids ) === 2 ) {
				pll_save_post_translations( $ids );
			}
		}
		fei_log( 'programs', 'seed complete', $report );
		return $report;
	}

	public function handle_reseed(): void {
		check_admin_referer( 'fei_reseed' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden' );
		}
		$r = self::seed_from_json();
		wp_safe_redirect( add_query_arg( array( 'post_type' => self::CPT, 'fei_seeded' => $r['created'] . '-' . $r['updated'] ), admin_url( 'edit.php' ) ) );
		exit;
	}

	/* ---------- Admin meta box ---------- */

	public function meta_boxes(): void {
		add_meta_box( 'fei_program_facts', __( 'Program facts', 'fei-core' ), array( $this, 'render_box' ), self::CPT, 'normal', 'high' );
	}

	public function render_box( WP_Post $post ): void {
		wp_nonce_field( 'fei_program_meta', 'fei_program_nonce' );
		$labels = array(
			'key'          => 'CRM key (English title — do not change once leads exist)',
			'type'         => 'Form program type (Diploma | Associate Degree)',
			'credential'   => 'Credential label (Diploma | A.A.S.)',
			'lede'         => 'Hero lede',
			'short'        => 'Card description',
			'length'       => 'Program length',
			'format'       => 'Format',
			'format_detail' => 'Format detail',
			'campus'       => 'Campus',
			'intro_title'  => 'Intro heading',
			'intro'        => 'Intro paragraph',
			'experience_title' => 'Experience heading (externship/lab) — optional',
			'experience'   => 'Experience paragraph — optional',
			'demand'       => 'Local demand paragraph',
			'schedule'     => 'Schedule paragraph',
			'quote'        => 'Graduate quote',
			'quote_by'     => 'Quote attribution',
			'seo_title'    => 'SEO title',
			'image'        => 'Fallback image path/URL',
		);
		echo '<table class="form-table">';
		foreach ( $labels as $f => $label ) {
			$v = self::get_field( $post->ID, $f );
			printf(
				'<tr><th><label for="fei_%1$s">%2$s</label></th><td>%3$s</td></tr>',
				esc_attr( $f ),
				esc_html( $label ),
				strlen( $v ) > 90 || in_array( $f, array( 'intro', 'experience', 'demand', 'schedule', 'quote' ), true )
					? sprintf( '<textarea id="fei_%1$s" name="fei[%1$s]" rows="3" class="large-text">%2$s</textarea>', esc_attr( $f ), esc_textarea( $v ) )
					: sprintf( '<input id="fei_%1$s" name="fei[%1$s]" value="%2$s" class="large-text">', esc_attr( $f ), esc_attr( $v ) )
			);
		}
		foreach ( self::LIST_FIELDS as $f ) {
			$v   = self::get_field( $post->ID, $f );
			$txt = 'faq' === $f
				? implode( "\n", array_map( static fn( $r ) => ( $r['q'] ?? '' ) . ' || ' . ( $r['a'] ?? '' ), $v ) )
				: implode( "\n", $v );
			printf(
				'<tr><th><label for="fei_%1$s">%2$s</label><p class="description">%3$s</p></th><td><textarea id="fei_%1$s" name="fei[%1$s]" rows="6" class="large-text">%4$s</textarea></td></tr>',
				esc_attr( $f ),
				esc_html( ucfirst( $f ) ),
				'faq' === $f ? 'One per line: Question || Answer' : 'One per line',
				esc_textarea( $txt )
			);
		}
		echo '</table>';
	}

	public function save_meta( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['fei_program_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['fei_program_nonce'] ), 'fei_program_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in = isset( $_POST['fei'] ) && is_array( $_POST['fei'] ) ? wp_unslash( $_POST['fei'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		foreach ( self::FIELDS as $f ) {
			if ( isset( $in[ $f ] ) ) {
				update_post_meta( $post_id, self::META_KEY . '_' . $f, sanitize_textarea_field( (string) $in[ $f ] ) );
			}
		}
		foreach ( self::LIST_FIELDS as $f ) {
			if ( ! isset( $in[ $f ] ) ) {
				continue;
			}
			$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $in[ $f ] ) ) ) );
			if ( 'faq' === $f ) {
				$lines = array_map(
					static function ( string $line ): array {
						$parts = array_map( 'trim', explode( '||', $line, 2 ) );
						return array( 'q' => sanitize_text_field( $parts[0] ?? '' ), 'a' => sanitize_textarea_field( $parts[1] ?? '' ) );
					},
					$lines
				);
			} else {
				$lines = array_map( 'sanitize_text_field', $lines );
			}
			update_post_meta( $post_id, self::META_KEY . '_' . $f, wp_json_encode( $lines, JSON_UNESCAPED_UNICODE ) );
		}
	}

	/* ---------- Shortcode: [fei_programs_grid] ---------- */

	public function shortcode_grid( $atts ): string {
		$atts = shortcode_atts( array( 'style' => 'tiles' ), $atts, 'fei_programs_grid' );
		ob_start();
		$programs = self::catalog();
		$view     = FEI_CORE_DIR . 'templates/parts/programs-grid.php';
		if ( file_exists( $view ) ) {
			include $view;
		}
		return (string) ob_get_clean();
	}
}
