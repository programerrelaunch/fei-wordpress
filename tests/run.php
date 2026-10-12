<?php
/**
 * FEI Core logic tests — run with:  php tests/run.php
 * No WordPress required: exercises the pure classes (validator, Zoho mapper, URL map,
 * helpers, programs seed integrity) plus a smoke-render of the email templates.
 */

declare(strict_types=1);

define( 'FEI_TESTING', true );
define( 'ABSPATH', __DIR__ . '/' );
define( 'FEI_CORE_DIR', dirname( __DIR__ ) . '/wp-content/plugins/fei-core/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );

// ---- Minimal WP stubs used by the pure code paths ----
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f | JSON_UNESCAPED_UNICODE ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return (string) $s; }
function wp_kses( $s ) { return (string) $s; }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function home_url( $p = '/' ) { return 'https://fei.edu' . $p; }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function wp_date( $f, $t ) { return gmdate( $f, $t ); }

require FEI_CORE_DIR . 'includes/fei-helpers.php';
require FEI_CORE_DIR . 'includes/class-fei-lead-validator.php';
require FEI_CORE_DIR . 'includes/class-fei-zoho-mapper.php';
require FEI_CORE_DIR . 'includes/class-fei-url-map.php';

$pass = 0;
$fail = 0;
function ok( bool $cond, string $name ): void {
	global $pass, $fail;
	if ( $cond ) { ++$pass; echo "  ok   $name\n"; } else { ++$fail; echo "  FAIL $name\n"; }
}
function eq( $a, $b, string $name ): void {
	ok( $a === $b, $name . ( $a === $b ? '' : '  (got ' . var_export( $a, true ) . ', want ' . var_export( $b, true ) . ')' ) );
}

$programs = array( 'Medical Assistant', 'HVAC / R', 'Business Administration' );

echo "helpers\n";
eq( fei_normalize_us_phone( '(786) 665-8310' ), '+17866658310', 'phone: formatted → E.164' );
eq( fei_normalize_us_phone( '+1 786 665 8310' ), '+17866658310', 'phone: +1 prefixed' );
eq( fei_normalize_us_phone( '17866658310' ), '+17866658310', 'phone: 11 digits' );
eq( fei_normalize_us_phone( '086 665 8310' ), '', 'phone: invalid NANP area code' );
eq( fei_normalize_us_phone( '12345' ), '', 'phone: too short' );
ok( fei_is_valid_zip( '33144' ) && fei_is_valid_zip( '33144-1234' ) && ! fei_is_valid_zip( '3314' ) && ! fei_is_valid_zip( 'ABCDE' ), 'zip validation' );
eq( count( fei_touch_keys() ), 18, '18 attribution keys (7 UTM + 11 click ids) — same as site.js' );
ok( preg_match( '/^[0-9a-f-]{36}$/', fei_uuid4() ) === 1, 'uuid4 shape' );

echo "validator — full lead\n";
$full = array(
	'firstName' => '  Maria ', 'lastName' => 'Lopez', 'email' => 'Maria@Example.com', 'phone' => '786-665-8310', 'zip' => '33144',
	'program' => 'Medical Assistant', 'programType' => 'Diploma', 'smsMarketingConsent' => 'true', 'smsTransactionalConsent' => false,
	'utm_source' => 'google', 'utm_medium' => 'cpc', 'gclid' => 'abc123', 'referrer' => 'https://fei.edu/programs/medical-assistant?utm_source=google',
	'documentReferrer' => 'https://www.google.com/', 'fbc' => '', 'fbp' => 'fb.1.123.456', 'stage' => 'full', 'lang' => 'en',
	'preRegId' => '3f2504e0-4f89-41d3-9a0c-0305e82c3301',
);
$r = FEI_Lead_Validator::validate( $full, $programs );
ok( $r['ok'], 'valid full lead passes' );
eq( $r['lead']['firstName'], 'Maria', 'trims names' );
eq( $r['lead']['email'], 'maria@example.com', 'lowercases email' );
eq( $r['lead']['phone'], '+17866658310', 'normalises phone' );
eq( $r['lead']['smsMarketingConsent'], true, 'bool consent from "true"' );
eq( $r['lead']['smsTransactionalConsent'], false, 'bool consent false' );
eq( $r['lead']['gclid'], 'abc123', 'carries click id' );
eq( $r['lead']['preRegId'], '3f2504e0-4f89-41d3-9a0c-0305e82c3301', 'keeps v4 preRegId' );

$bad = FEI_Lead_Validator::validate( array( 'firstName' => '', 'email' => 'nope', 'phone' => '1', 'zip' => '1', 'program' => 'Rocket Science' ), $programs, 'es' );
ok( ! $bad['ok'], 'invalid lead fails' );
eq( array_keys( $bad['errors'] ), array( 'firstName', 'lastName', 'email', 'phone', 'zip', 'program' ), 'all six errors reported' );
eq( $bad['errors']['email'], 'Ingresa un correo electrónico válido.', 'spanish error message' );

echo "validator — pre-registration\n";
$pre = FEI_Lead_Validator::validate( array( 'stage' => 'pre', 'firstName' => 'Ana', 'lastName' => 'Diaz', 'email' => 'ana@x.io', 'phone' => '3055551234', 'program' => 'HVAC / R' ), $programs );
ok( $pre['ok'], 'pre lead needs no zip/program' );
eq( $pre['lead']['stage'], 'pre', 'stage preserved' );
$pre2 = FEI_Lead_Validator::validate( array( 'stage' => 'pre', 'firstName' => 'Ana', 'lastName' => 'Diaz', 'email' => 'ana@x.io', 'phone' => '3055551234', 'program' => 'Unknown' ), $programs );
eq( $pre2['lead']['program'], '', 'unknown program dropped on pre stage' );
$bogus = FEI_Lead_Validator::validate( array_merge( $full, array( 'stage' => 'weird', 'programType' => 'PhD', 'preRegId' => 'pre-123' ) ), $programs );
eq( $bogus['lead']['stage'], 'full', 'unknown stage → full' );
eq( $bogus['lead']['programType'], '', 'unknown programType dropped' );
eq( $bogus['lead']['preRegId'], '', 'non-v4 preRegId dropped' );
$xss = FEI_Lead_Validator::validate( array_merge( $full, array( 'firstName' => "<script>alert(1)</script>Bob", 'utm_campaign' => 'x"onmouseover="y', 'referrer' => 'javascript:alert(1)' ) ), $programs );
ok( ! str_contains( $xss['lead']['firstName'], '<' ) && ! str_contains( $xss['lead']['utm_campaign'], '"' ) && '' === $xss['lead']['referrer'], 'strips angle brackets/quotes, rejects non-http referrer' );

echo "validator — spam\n";
eq( FEI_Lead_Validator::spam_reason( array( 'website' => 'http://spam' ), $r['lead'] ), 'honeypot', 'honeypot' );
eq( FEI_Lead_Validator::spam_reason( array( '_t0' => (string) ( time() * 1000 ) ), $r['lead'] ), 'too_fast', 'too fast' );
eq( FEI_Lead_Validator::spam_reason( array( '_t0' => (string) ( time() * 1000 - 20000 ) ), $r['lead'] ), '', 'normal timing is clean' );
eq( FEI_Lead_Validator::spam_reason( array(), array_merge( $r['lead'], array( 'lastName' => 'www.buy.now' ) ) ), 'url_in_name', 'url in name' );

echo "zoho mapper\n";
$rec = FEI_Zoho_Mapper::to_record( $r['lead'], array(), array( 'Owner' => array( 'id' => '123' ) ) );
eq( $rec['First_Name'], 'Maria', 'maps first name' );
eq( $rec['Mobile'], '+17866658310', 'maps phone → Mobile' );
eq( $rec['Program_of_Interest'], 'Medical Assistant', 'maps program' );
eq( $rec['Lead_Source'], 'Google Ads', 'gclid → Google Ads' );
eq( $rec['Web_Lead_ID'], '3f2504e0-4f89-41d3-9a0c-0305e82c3301', 'maps preRegId' );
eq( $rec['SMS_Marketing_Consent'], true, 'bool passthrough' );
eq( $rec['Owner'], array( 'id' => '123' ), 'extra static values' );
ok( str_contains( $rec['Description'], 'gclid=abc123' ), 'description carries attribution' );
$rec2 = FEI_Zoho_Mapper::to_record( $r['lead'], array( 'zip' => 'Zip_Code_Custom', 'fbp' => '' ) );
ok( isset( $rec2['Zip_Code_Custom'] ) && ! isset( $rec2['Zip_Code'] ) && ! isset( $rec2['FBP'] ), 'override renames and drops fields' );
eq( FEI_Zoho_Mapper::lead_source( array( 'fbclid' => 'x' ) ), 'Meta Ads', 'fbclid → Meta Ads' );
eq( FEI_Zoho_Mapper::lead_source( array( 'utm_source' => 'instagram', 'utm_medium' => 'social' ) ), 'Social', 'organic social' );
eq( FEI_Zoho_Mapper::lead_source( array( 'documentReferrer' => 'https://www.bing.com/search' ) ), 'Organic Search', 'organic search by referrer' );
eq( FEI_Zoho_Mapper::lead_source( array( 'documentReferrer' => 'https://chatgpt.com/' ) ), 'AI Referral', 'AI referral' );
eq( FEI_Zoho_Mapper::lead_source( array() ), 'Website', 'direct → Website' );
eq( FEI_Zoho_Mapper::lead_source( array( 'utm_source' => 'newsletter', 'utm_medium' => 'email' ) ), 'Newsletter / email', 'generic utm' );
$noLast = FEI_Zoho_Mapper::to_record( array_merge( $r['lead'], array( 'lastName' => '' ) ) );
eq( $noLast['Last_Name'], 'Maria', 'Last_Name fallback (Zoho mandatory)' );
eq( FEI_Zoho_Mapper::search_criteria( $r['lead'] )[0], array( 'Web_Lead_ID', '3f2504e0-4f89-41d3-9a0c-0305e82c3301' ), 'dedupe prefers Web_Lead_ID' );

echo "url map\n";
eq( FEI_URL_Map::normalize( '/Programs/HVAC/' ), '/programs/hvac', 'normalise case + trailing slash' );
eq( FEI_URL_Map::normalize( '/apply?x=1' ), '/apply', 'normalise strips query' );
eq( FEI_URL_Map::resolve( '/sitemap-pages.xml' ), '/sitemap_index.xml', 'legacy sitemap → Rank Math index' );
eq( FEI_URL_Map::resolve( '/home' ), '/', 'vendor /home → /' );
eq( FEI_URL_Map::resolve( '/programs/hvac' ), null, 'identity paths do not redirect' );
eq( FEI_URL_Map::resolve( '/legal/privacy/' ), null, 'identity with trailing slash does not loop' );
eq( FEI_URL_Map::counterpart( '/programs/hvac' ), '/es/programas/climatizacion-y-refrigeracion', 'EN → ES counterpart' );
eq( FEI_URL_Map::counterpart( '/es/inscripcion/' ), '/apply', 'ES → EN counterpart' );
ok( FEI_URL_Map::is_spanish( '/es' ) && FEI_URL_Map::is_spanish( '/es/programas' ) && ! FEI_URL_Map::is_spanish( '/escape' ), 'spanish detection' );
eq( count( FEI_URL_Map::pairs() ), 25, '25 EN/ES pairs (14 pages + 11 programs)' );
foreach ( FEI_URL_Map::redirects() as $from => $to ) {
	$resolved = FEI_URL_Map::resolve( $from );
	ok( null === $resolved || null === FEI_URL_Map::resolve( $resolved ), "no redirect chain for $from" );
}

echo "programs seed\n";
$seed = json_decode( (string) file_get_contents( FEI_CORE_DIR . 'data/programs.json' ), true );
eq( count( $seed['programs'] ), 11, '11 programs' );
$keys = array_column( $seed['programs'], 'key' );
eq( count( array_unique( $keys ) ), 11, 'unique CRM keys' );
$live_form_keys = array( 'Medical Assistant', 'Medical Billing & Coding', 'Pharmacy Technician', 'Medical Office Administrator', 'Culinary Arts', 'Culinary & Hospitality Management', 'Pastry & Baking Arts', 'Pastry & Baking Management', 'HVAC / R', 'Business Management', 'Business Administration' );
eq( $keys, $live_form_keys, 'keys match the live form <option value>s exactly (CRM continuity)' );
$pairs = FEI_URL_Map::pairs();
foreach ( $seed['programs'] as $p ) {
	ok( in_array( $p['type'], array( 'Diploma', 'Associate Degree' ), true ), "{$p['key']}: programType vocabulary" );
	ok( isset( $pairs[ '/programs/' . $p['en']['slug'] ] ) && $pairs[ '/programs/' . $p['en']['slug'] ] === '/es/programas/' . $p['es']['slug'], "{$p['key']}: EN/ES slugs agree with URL map" );
	ok( count( $p['en']['skills'] ) >= 5 && count( $p['en']['careers'] ) >= 3 && count( $p['en']['faq'] ) === 3 && count( $p['en']['why'] ) === 5, "{$p['key']}: EN content complete" );
	ok( ( 'Associate Degree' === $p['type'] ) === ( 'A.A.S.' === $p['credential'] ), "{$p['key']}: type/credential consistent" );
}

echo "strings\n";
$strings = include FEI_CORE_DIR . 'data/strings.php';
eq( array_diff_key( $strings['en'], $strings['es'] ), array(), 'every EN string has an ES string' );
eq( array_diff_key( $strings['es'], $strings['en'] ), array(), 'no orphan ES strings' );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
