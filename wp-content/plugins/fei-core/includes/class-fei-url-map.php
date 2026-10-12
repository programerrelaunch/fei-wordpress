<?php
/**
 * Legacy (Astro) URL → WordPress URL map. Pure; shared by the redirect handler,
 * the hreflang emitter and the migration docs/test-suite.
 *
 * Design: keep every public URL identical where WordPress can serve it natively,
 * so the map is mostly identity. Only paths WordPress cannot produce 1:1 are
 * rewritten. ES pages keep their Spanish slugs under /es/ (Polylang directory mode).
 */

defined( 'ABSPATH' ) || defined( 'FEI_TESTING' ) || exit;

final class FEI_URL_Map {

	/** EN path => ES path (both as served by the Astro site). */
	public static function pairs(): array {
		return array(
			'/'                      => '/es',
			'/programs'              => '/es/programas',
			'/about'                 => '/es/sobre',
			'/admissions'            => '/es/admisiones',
			'/apply'                 => '/es/inscripcion',
			'/employers'             => '/es/empleadores',
			'/financial-aid'         => '/es/ayuda-financiera',
			'/support'               => '/es/apoyo',
			'/blog'                  => '/blog',
			'/sitemap'               => '/es/sitemap',
			'/legal/privacy'         => '/es/legal/privacidad',
			'/legal/terms'           => '/es/legal/terminos',
			'/consumer-information'  => '/es/informacion-al-consumidor',
			'/net-price-calculator'  => '/net-price-calculator',
			'/programs/medical-assistant'              => '/es/programas/asistente-medico',
			'/programs/medical-billing-coding'         => '/es/programas/facturacion-y-codificacion-medica',
			'/programs/pharmacy-technician'            => '/es/programas/tecnico-de-farmacia',
			'/programs/medical-office-administrator'   => '/es/programas/administrador-de-oficina-medica',
			'/programs/culinary-arts'                  => '/es/programas/artes-culinarias',
			'/programs/culinary-hospitality-management' => '/es/programas/gestion-culinaria-y-hospitalidad',
			'/programs/pastry-baking-arts'             => '/es/programas/reposteria-y-panaderia',
			'/programs/pastry-baking-management'       => '/es/programas/gestion-de-reposteria-y-panaderia',
			'/programs/hvac'                           => '/es/programas/climatizacion-y-refrigeracion',
			'/programs/business-management'            => '/es/programas/gestion-empresarial',
			'/programs/business-administration'        => '/es/programas/administracion-de-empresas',
		);
	}

	/**
	 * Legacy path => WordPress path. Identity for everything WordPress serves at the same
	 * path; explicit entries for the few that differ. Trailing slashes are normalised away
	 * on lookup so both /apply and /apply/ resolve.
	 */
	public static function redirects(): array {
		return array(
			// Astro served legal pages under /legal/*; WP pages live at the root with the same slug
			// unless a "legal" parent page is created. We keep /legal/* by creating a parent page,
			// so these stay identity. Listed explicitly for documentation.
			'/legal/privacy'      => '/legal/privacy/',
			'/legal/terms'        => '/legal/terms/',
			'/es/legal/privacidad' => '/es/legal/privacidad/',
			'/es/legal/terminos'  => '/es/legal/terminos/',
			// Astro blog search → WP native search scoped to posts.
			'/blog/search'        => '/?s=&post_type=post',
			// Astro had no /contact; the WP vendor build created one. Keep it, but legacy 404
			// lookups for /contact-us etc. go to admissions.
			'/contact-us'         => '/admissions/',
			'/es/contacto'        => '/es/admisiones/',
			// Old headless-blog hostname paths that leaked into the wild.
			'/home'               => '/',
			'/about-fei'          => '/about/',
			'/es/about-fei-espanol' => '/es/sobre/',
			// Vercel/Astro artefacts.
			'/404'                => '/',
			'/sitemap-pages.xml'  => '/sitemap_index.xml',
			'/sitemap-blog.xml'   => '/sitemap_index.xml',
			'/sitemap.xml'        => '/sitemap_index.xml',
		);
	}

	public static function normalize( string $path ): string {
		$path = '/' . ltrim( (string) parse_url( $path, PHP_URL_PATH ), '/' );
		$path = strtolower( $path );
		return ( strlen( $path ) > 1 ) ? rtrim( $path, '/' ) : $path;
	}

	/** Returns the target path or null if no redirect applies. */
	public static function resolve( string $path ): ?string {
		$n = self::normalize( $path );
		$r = self::redirects();
		if ( isset( $r[ $n ] ) && self::normalize( $r[ $n ] ) !== $n ) {
			return $r[ $n ];
		}
		return null;
	}

	/** Translation counterpart of a path, or null. */
	public static function counterpart( string $path ): ?string {
		$n = self::normalize( $path );
		foreach ( self::pairs() as $en => $es ) {
			if ( self::normalize( $en ) === $n ) {
				return $es;
			}
			if ( self::normalize( $es ) === $n ) {
				return $en;
			}
		}
		return null;
	}

	public static function is_spanish( string $path ): bool {
		$n = self::normalize( $path );
		return '/es' === $n || str_starts_with( $n, '/es/' );
	}
}
