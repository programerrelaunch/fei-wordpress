<?php
/**
 * SEO parity with the Astro site + enrichment.
 *  - Organization / WebSite / Course / BreadcrumbList JSON-LD (Rank Math handles titles,
 *    canonical, OG; we add what Rank Math does not know about our CPTs).
 *  - Course schema enriched with timeRequired / educationalCredentialAwarded / offers /
 *    hasCourseInstance (courseMode, location) — fields the Astro build lacked.
 *  - hreflang: Polylang emits it for paired posts; a fallback uses FEI_URL_Map pairs.
 *  - robots.txt: same AI-crawler allow list as the live site.
 *  - Rank Math: exclude landing pages from sitemap; set program SEO title default.
 */

defined( 'ABSPATH' ) || exit;

final class FEI_SEO {

	private static ?FEI_SEO $instance = null;

	public static function instance(): FEI_SEO {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'wp_head', array( $this, 'jsonld' ), 20 );
		add_action( 'wp_head', array( $this, 'hreflang_fallback' ), 2 );
		add_filter( 'robots_txt', array( $this, 'robots_txt' ), 10, 2 );
		add_filter( 'rank_math/sitemap/exclude_post_type', array( $this, 'rm_exclude' ), 10, 2 );
		add_filter( 'rank_math/frontend/title', array( $this, 'rm_title' ) );
		add_filter( 'rank_math/frontend/description', array( $this, 'rm_description' ) );
		add_filter( 'rank_math/json_ld', array( $this, 'rm_strip_duplicates' ), 99 );
		add_filter( 'wp_sitemaps_post_types', array( $this, 'core_sitemap_types' ) );
	}

	private function org_id(): string {
		return home_url( '/#organization' );
	}

	public function organization(): array {
		$o = fei_org();
		return array(
			'@type'         => array( 'CollegeOrUniversity', 'EducationalOrganization' ),
			'@id'           => $this->org_id(),
			'name'          => $o['name'],
			'alternateName' => $o['short'],
			'url'           => home_url( '/' ),
			'logo'          => home_url( '/wp-content/uploads/fei-logo.png' ),
			'image'         => home_url( '/wp-content/uploads/fei-logo.png' ),
			'telephone'     => '+1-786-665-8310',
			'email'         => $o['email'],
			'address'       => array( '@type' => 'PostalAddress', 'streetAddress' => $o['street'], 'addressLocality' => $o['city'], 'addressRegion' => $o['region'], 'postalCode' => $o['zip'], 'addressCountry' => 'US' ),
			'areaServed'    => 'Miami, Florida',
			'sameAs'        => array( $o['facebook'], $o['instagram'], $o['x'] ),
			'accreditation' => array( '@type' => 'Organization', 'name' => $o['accreditor'], 'alternateName' => 'COE', 'url' => $o['accreditor_url'] ),
		);
	}

	public function jsonld(): void {
		$graph = array();
		if ( is_front_page() ) {
			$graph[] = array_merge( array( '@context' => 'https://schema.org' ), $this->organization() );
			$graph[] = array(
				'@context'        => 'https://schema.org',
				'@type'           => 'WebSite',
				'@id'             => home_url( '/#website' ),
				'url'             => home_url( '/' ),
				'name'            => fei_org()['name'],
				'publisher'       => array( '@id' => $this->org_id() ),
				'potentialAction' => array( '@type' => 'SearchAction', 'target' => array( '@type' => 'EntryPoint', 'urlTemplate' => home_url( '/?s={search_term_string}&post_type=post' ) ), 'query-input' => 'required name=search_term_string' ),
			);
		}
		if ( is_singular( FEI_Programs::CPT ) ) {
			$id   = get_queried_object_id();
			$pr   = FEI_Programs::get_program( $id );
			$lang = fei_lang();
			$mode = $this->course_mode( $pr['format'] . ' ' . $pr['format_detail'] );
			$course = array(
				'@context'    => 'https://schema.org',
				'@type'       => 'Course',
				'name'        => get_the_title( $id ),
				'description' => $pr['short'] ?: $pr['lede'],
				'url'         => get_permalink( $id ),
				'inLanguage'  => $lang,
				'provider'    => array( '@type' => 'CollegeOrUniversity', '@id' => $this->org_id(), 'name' => fei_org()['name'], 'sameAs' => home_url( '/' ) ),
				'educationalCredentialAwarded' => 'A.A.S.' === $pr['credential'] ? 'Associate of Applied Science' : 'Diploma',
				'occupationalCategory' => $pr['careers'] ?: null,
				'teaches'     => $pr['skills'] ?: null,
				'offers'      => array( '@type' => 'Offer', 'category' => 'Paid', 'availability' => 'https://schema.org/InStock', 'url' => home_url( 'es' === $lang ? '/es/ayuda-financiera/' : '/financial-aid/' ), 'description' => 'Tuition varies; financial aid available to those who qualify.' ),
				'hasCourseInstance' => array(
					array(
						'@type'      => 'CourseInstance',
						'courseMode' => $mode,
						'courseWorkload' => $this->iso_duration( $pr['length'] ),
						'location'   => 'online' === $mode ? null : array( '@type' => 'Place', 'name' => fei_org()['name'] . ' — ' . ( $pr['campus'] ?: 'Miami' ), 'address' => array( '@type' => 'PostalAddress', 'streetAddress' => fei_org()['street'], 'addressLocality' => fei_org()['city'], 'addressRegion' => 'FL', 'postalCode' => fei_org()['zip'], 'addressCountry' => 'US' ) ),
						'courseSchedule' => array( '@type' => 'Schedule', 'repeatFrequency' => 'P4W', 'description' => 'New classes start every 3–4 weeks' ),
					),
				),
			);
			if ( $this->iso_duration( $pr['length'] ) ) {
				$course['timeRequired'] = $this->iso_duration( $pr['length'] );
			}
			$graph[] = array_filter( $course, static fn( $v ) => null !== $v );
			$graph[] = array(
				'@context'        => 'https://schema.org',
				'@type'           => 'BreadcrumbList',
				'itemListElement' => array(
					array( '@type' => 'ListItem', 'position' => 1, 'name' => fei_t( 'home' ), 'item' => home_url( 'es' === $lang ? '/es/' : '/' ) ),
					array( '@type' => 'ListItem', 'position' => 2, 'name' => fei_t( 'programs' ), 'item' => home_url( 'es' === $lang ? '/es/programas/' : '/programs/' ) ),
					array( '@type' => 'ListItem', 'position' => 3, 'name' => get_the_title( $id ), 'item' => get_permalink( $id ) ),
				),
			);
		}
		if ( is_post_type_archive( FEI_Programs::CPT ) ) {
			$lang    = fei_lang();
			$graph[] = array(
				'@context'        => 'https://schema.org',
				'@type'           => 'BreadcrumbList',
				'itemListElement' => array(
					array( '@type' => 'ListItem', 'position' => 1, 'name' => fei_t( 'home' ), 'item' => home_url( 'es' === $lang ? '/es/' : '/' ) ),
					array( '@type' => 'ListItem', 'position' => 2, 'name' => fei_t( 'programs' ), 'item' => home_url( 'es' === $lang ? '/es/programas/' : '/programs/' ) ),
				),
			);
			$graph[] = array(
				'@context'        => 'https://schema.org',
				'@type'           => 'ItemList',
				'itemListElement' => array_map( static fn( $p, $i ) => array( '@type' => 'ListItem', 'position' => $i + 1, 'url' => $p['url'], 'name' => $p['label'] ), FEI_Programs::catalog(), array_keys( FEI_Programs::catalog() ) ),
			);
		}
		foreach ( $graph as $node ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
		}
	}

	public function course_mode( string $format ): string {
		$f = strtolower( $format );
		if ( str_contains( $f, '100% online' ) || ( str_contains( $f, 'online' ) && ! str_contains( $f, 'hybrid' ) && ! str_contains( $f, 'extern' ) && ! str_contains( $f, 'campus' ) ) ) {
			return 'online';
		}
		if ( str_contains( $f, 'hybrid' ) || str_contains( $f, 'híbrido' ) || str_contains( $f, 'flexible' ) || str_contains( $f, 'extern' ) ) {
			return 'blended';
		}
		return 'onsite';
	}

	/** "45 Weeks" → "P45W"; "In Under a Year" → "P12M" (upper bound); unknown → ''. */
	public function iso_duration( string $length ): string {
		if ( preg_match( '/(\d+)\s*(week|semana)/i', $length, $m ) ) {
			return 'P' . (int) $m[1] . 'W';
		}
		if ( preg_match( '/(\d+)\s*(month|mes)/i', $length, $m ) ) {
			return 'P' . (int) $m[1] . 'M';
		}
		if ( preg_match( '/under a year|menos de un año/i', $length ) ) {
			return 'P12M';
		}
		return '';
	}

	/** Only when Polylang is absent (otherwise it prints hreflang itself). */
	public function hreflang_fallback(): void {
		if ( function_exists( 'pll_the_languages' ) || is_admin() ) {
			return;
		}
		$path = (string) wp_parse_url( home_url( add_query_arg( array() ) ), PHP_URL_PATH );
		$alt  = FEI_URL_Map::counterpart( $path );
		if ( null === $alt ) {
			return;
		}
		$is_es = FEI_URL_Map::is_spanish( $path );
		$en    = $is_es ? $alt : $path;
		$es    = $is_es ? $path : $alt;
		printf( '<link rel="alternate" hreflang="en" href="%s">' . "\n", esc_url( home_url( $en ) ) );
		printf( '<link rel="alternate" hreflang="es" href="%s">' . "\n", esc_url( home_url( $es ) ) );
		printf( '<link rel="alternate" hreflang="x-default" href="%s">' . "\n", esc_url( home_url( $en ) ) );
	}

	public function robots_txt( string $output, bool $public ): string {
		if ( ! $public ) {
			return $output;
		}
		$bots  = array( 'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-Web', 'anthropic-ai', 'PerplexityBot', 'Perplexity-User', 'Google-Extended', 'Applebot-Extended', 'Bingbot', 'CCBot', 'Amazonbot', 'Meta-ExternalAgent' );
		$lines = array( '# Florida Education Institute — ' . home_url( '/' ), '# Public marketing/admissions site. AI crawlers explicitly welcomed.', '', 'User-agent: *', 'Allow: /', 'Disallow: /wp-admin/', 'Allow: /wp-admin/admin-ajax.php', 'Disallow: /lp/', 'Disallow: /es/lp/', 'Disallow: /?s=', '' );
		foreach ( $bots as $b ) {
			$lines[] = 'User-agent: ' . $b;
			$lines[] = 'Allow: /';
			$lines[] = '';
		}
		$lines[] = 'Sitemap: ' . home_url( '/sitemap_index.xml' );
		return implode( "\n", $lines ) . "\n";
	}

	public function rm_exclude( bool $exclude, string $type ): bool {
		return in_array( $type, array( FEI_Landing::CPT, FEI_Leads::CPT ), true ) ? true : $exclude;
	}

	public function core_sitemap_types( array $types ): array {
		unset( $types[ FEI_Landing::CPT ], $types[ FEI_Leads::CPT ] );
		return $types;
	}

	public function rm_title( string $title ): string {
		if ( is_singular( FEI_Programs::CPT ) ) {
			$seo = FEI_Programs::get_field( get_queried_object_id(), 'seo_title' );
			if ( $seo && ! get_post_meta( get_queried_object_id(), 'rank_math_title', true ) ) {
				return $seo;
			}
		}
		return $title;
	}

	public function rm_description( string $desc ): string {
		if ( is_singular( FEI_Programs::CPT ) && ! get_post_meta( get_queried_object_id(), 'rank_math_description', true ) ) {
			$pr = FEI_Programs::get_program( get_queried_object_id() );
			return $pr['lede'] ?: $pr['short'] ?: $desc;
		}
		return $desc;
	}

	/** Rank Math also prints Organization/WebSite on the front page; keep ours (richer) and drop theirs. */
	public function rm_strip_duplicates( array $data ): array {
		if ( is_front_page() ) {
			unset( $data['publisher'], $data['Organization'], $data['WebSite'], $data['website'] );
		}
		if ( is_singular( FEI_Programs::CPT ) ) {
			unset( $data['BreadcrumbList'] );
		}
		return $data;
	}
}
