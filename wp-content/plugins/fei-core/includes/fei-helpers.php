<?php
/**
 * Shared helpers. Pure functions here must not depend on WordPress being loaded
 * (they are exercised by the CLI test-suite), WP-dependent ones are guarded.
 */

defined( 'ABSPATH' ) || defined( 'FEI_TESTING' ) || exit;

/** Attribution keys — MUST stay identical to the Astro site.js contract. */
function fei_utm_keys(): array {
	return array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_id', 'utm_platform' );
}

function fei_click_id_keys(): array {
	return array( 'gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'ttclid', 'li_fat_id', 'twclid', 'sccid', 'epik', 'irclickid' );
}

function fei_touch_keys(): array {
	return array_merge( fei_utm_keys(), fei_click_id_keys() );
}

/** Institutional constants used in templates, schema and emails. */
function fei_org(): array {
	return array(
		'name'      => 'Florida Education Institute',
		'short'     => 'FEI',
		'street'    => '5818 SW 8th Street',
		'city'      => 'Miami',
		'region'    => 'FL',
		'zip'       => '33144',
		'phone'     => '786-665-8310',
		'phone_e164' => '+17866658310',
		'email'     => 'admissions@fei.edu',
		'portal'    => 'https://starnet.neolms.com/',
		'facebook'  => 'https://www.facebook.com/FEIMiami',
		'instagram' => 'https://www.instagram.com/feimiami/',
		'x'         => 'https://x.com/FEIMiami',
		'accreditor' => 'Council on Occupational Education',
		'accreditor_url' => 'https://council.org/',
		'npc_url'   => 'https://prospect.studentaidprocess.com/saintprospect/netpricecalculator?corpid=139',
	);
}

/** Current language slug ('en' | 'es') — Polylang aware, safe without it. */
function fei_lang(): string {
	if ( function_exists( 'pll_current_language' ) ) {
		$l = pll_current_language( 'slug' );
		if ( $l ) {
			return $l;
		}
	}
	if ( function_exists( 'get_locale' ) ) {
		return str_starts_with( (string) get_locale(), 'es' ) ? 'es' : 'en';
	}
	return 'en';
}

/** Tiny i18n table for strings that appear in both languages on the live site. */
function fei_t( string $key, ?string $lang = null ): string {
	$lang = $lang ?: fei_lang();
	static $table = null;
	if ( null === $table ) {
		$table = include FEI_CORE_DIR . 'data/strings.php';
	}
	return $table[ $lang ][ $key ] ?? $table['en'][ $key ] ?? $key;
}

/** Normalise a US phone to E.164 (+1XXXXXXXXXX). Returns '' when invalid. */
function fei_normalize_us_phone( string $raw ): string {
	$digits = preg_replace( '/\D+/', '', $raw );
	if ( strlen( $digits ) === 11 && $digits[0] === '1' ) {
		$digits = substr( $digits, 1 );
	}
	if ( strlen( $digits ) !== 10 ) {
		return '';
	}
	// NANP: area code and exchange cannot start with 0/1.
	if ( in_array( $digits[0], array( '0', '1' ), true ) || in_array( $digits[3], array( '0', '1' ), true ) ) {
		return '';
	}
	return '+1' . $digits;
}

function fei_is_valid_zip( string $zip ): bool {
	return (bool) preg_match( '/^\d{5}(-\d{4})?$/', trim( $zip ) );
}

/** Wrapper around get_option with plugin defaults (safe in tests). */
function fei_opt( string $key, $default = null ) {
	if ( class_exists( 'FEI_Settings' ) ) {
		return FEI_Settings::get( $key, $default );
	}
	return $default;
}

/** Structured log → error_log + optional transient ring buffer for the admin panel. */
function fei_log( string $channel, string $message, array $context = array() ): void {
	$line = sprintf( '[fei-core][%s] %s %s', $channel, $message, $context ? wp_json_encode_safe( $context ) : '' );
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
	if ( function_exists( 'get_transient' ) ) {
		$buf   = (array) get_transient( 'fei_log_ring' );
		$buf[] = array( 't' => time(), 'c' => $channel, 'm' => $message, 'x' => $context );
		set_transient( 'fei_log_ring', array_slice( $buf, -200 ), DAY_IN_SECONDS );
	}
}

function wp_json_encode_safe( $data ): string {
	if ( function_exists( 'wp_json_encode' ) ) {
		return (string) wp_json_encode( $data );
	}
	return (string) json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
}

/** UUID v4 helper (preRegId fallback). */
function fei_uuid4(): string {
	if ( function_exists( 'wp_generate_uuid4' ) ) {
		return wp_generate_uuid4();
	}
	$d = random_bytes( 16 );
	$d[6] = chr( ( ord( $d[6] ) & 0x0f ) | 0x40 );
	$d[8] = chr( ( ord( $d[8] ) & 0x3f ) | 0x80 );
	return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $d ), 4 ) );
}
