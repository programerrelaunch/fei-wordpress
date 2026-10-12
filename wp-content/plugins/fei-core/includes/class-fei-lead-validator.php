<?php
/**
 * Pure validation/normalisation of an incoming lead payload.
 * Mirrors the browser contract of the Astro site.js so n8n keeps receiving the exact
 * same JSON shape. No WordPress dependencies (unit-tested via CLI).
 */

defined( 'ABSPATH' ) || defined( 'FEI_TESTING' ) || exit;

final class FEI_Lead_Validator {

	const STAGES = array( 'pre', 'full' );
	const TYPES  = array( 'Diploma', 'Associate Degree', '' );

	/**
	 * @param array    $in          Raw decoded JSON body.
	 * @param string[] $valid_programs Accepted program keys (English titles).
	 * @param string   $lang        'en'|'es' for error messages.
	 * @return array{ok:bool, errors:array<string,string>, lead:array}
	 */
	public static function validate( array $in, array $valid_programs, string $lang = 'en' ): array {
		$errors = array();
		$get    = static fn( string $k ): string => isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? trim( (string) $in[ $k ] ) : '';
		$bool   = static fn( string $k ): bool => isset( $in[ $k ] ) && filter_var( $in[ $k ], FILTER_VALIDATE_BOOLEAN );

		$stage = $get( 'stage' ) ?: 'full';
		if ( ! in_array( $stage, self::STAGES, true ) ) {
			$stage = 'full';
		}
		$is_pre = 'pre' === $stage;

		$first = self::clean_name( $get( 'firstName' ) );
		$last  = self::clean_name( $get( 'lastName' ) );
		$email = strtolower( $get( 'email' ) );
		$phone = fei_normalize_us_phone( $get( 'phone' ) );
		$zip   = $get( 'zip' );
		$prog  = $get( 'program' );
		$ptype = $get( 'programType' );

		if ( '' === $first ) {
			$errors['firstName'] = self::msg( 'required', $lang );
		}
		if ( '' === $last ) {
			$errors['lastName'] = self::msg( 'required', $lang );
		}
		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) || ! preg_match( '/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/', $email ) ) {
			$errors['email'] = self::msg( 'invalid_email', $lang );
		}
		if ( '' === $phone ) {
			$errors['phone'] = self::msg( 'invalid_phone', $lang );
		}
		if ( ! $is_pre ) {
			if ( ! fei_is_valid_zip( $zip ) ) {
				$errors['zip'] = self::msg( 'invalid_zip', $lang );
			}
			if ( '' === $prog || ! in_array( $prog, $valid_programs, true ) ) {
				$errors['program'] = self::msg( 'select_program', $lang );
			}
		} elseif ( '' !== $prog && ! in_array( $prog, $valid_programs, true ) ) {
			$prog = '';
		}
		if ( ! in_array( $ptype, self::TYPES, true ) ) {
			$ptype = '';
		}

		$pre_reg_id = $get( 'preRegId' );
		if ( '' !== $pre_reg_id && ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $pre_reg_id ) ) {
			$pre_reg_id = '';
		}

		$lead = array(
			'stage'                   => $stage,
			'preRegId'                => $pre_reg_id,
			'firstName'               => $first,
			'lastName'                => $last,
			'email'                   => $email,
			'phone'                   => $phone,
			'zip'                     => $zip,
			'program'                 => $prog,
			'programType'             => $ptype,
			'smsMarketingConsent'     => $bool( 'smsMarketingConsent' ),
			'smsTransactionalConsent' => $bool( 'smsTransactionalConsent' ),
			'referrer'                => self::clean_url( $get( 'referrer' ) ),
			'documentReferrer'        => self::clean_url( $get( 'documentReferrer' ) ),
			'ip'                      => '',
			'fbc'                     => self::clean_token( $get( 'fbc' ), 200 ),
			'fbp'                     => self::clean_token( $get( 'fbp' ), 200 ),
			'lang'                    => in_array( $get( 'lang' ), array( 'en', 'es' ), true ) ? $get( 'lang' ) : $lang,
			'formId'                  => self::clean_token( $get( 'formId' ), 60 ),
			'pageUrl'                 => self::clean_url( $get( 'pageUrl' ) ),
		);
		foreach ( fei_touch_keys() as $k ) {
			$lead[ $k ] = self::clean_token( $get( $k ), 255 );
		}

		return array(
			'ok'     => empty( $errors ),
			'errors' => $errors,
			'lead'   => $lead,
		);
	}

	/** Spam heuristics; returns reason string or '' when clean. */
	public static function spam_reason( array $in, array $lead ): string {
		if ( ! empty( $in['website'] ) || ! empty( $in['company_url'] ) ) { // honeypots
			return 'honeypot';
		}
		if ( isset( $in['_t0'] ) && is_numeric( $in['_t0'] ) && ( time() * 1000 - (int) $in['_t0'] ) < 1500 ) {
			return 'too_fast';
		}
		$name = $lead['firstName'] . ' ' . $lead['lastName'];
		if ( preg_match( '#https?://|www\.#i', $name ) ) {
			return 'url_in_name';
		}
		if ( preg_match( '/[а-яё]/iu', $name ) && 'en' === ( $lead['lang'] ?? 'en' ) ) {
			return 'cyrillic_name';
		}
		return '';
	}

	private static function clean_name( string $v ): string {
		$v = preg_replace( '/[\x00-\x1F\x7F<>]/u', '', $v );
		$v = preg_replace( '/\s+/u', ' ', (string) $v );
		return mb_substr( trim( (string) $v ), 0, 80 );
	}

	private static function clean_token( string $v, int $max ): string {
		$v = preg_replace( '/[\x00-\x1F\x7F<>"\']/u', '', $v );
		return mb_substr( trim( (string) $v ), 0, $max );
	}

	private static function clean_url( string $v ): string {
		$v = self::clean_token( $v, 2000 );
		if ( '' === $v ) {
			return '';
		}
		return preg_match( '#^https?://#i', $v ) ? $v : '';
	}

	private static function msg( string $key, string $lang ): string {
		$m = array(
			'en' => array(
				'required'       => 'This field is required.',
				'invalid_email'  => 'Enter a valid email address.',
				'invalid_phone'  => 'Enter a valid phone number.',
				'invalid_zip'    => 'Enter a valid zip code.',
				'select_program' => 'Select a program.',
			),
			'es' => array(
				'required'       => 'Este campo es obligatorio.',
				'invalid_email'  => 'Ingresa un correo electrónico válido.',
				'invalid_phone'  => 'Ingresa un número de teléfono válido.',
				'invalid_zip'    => 'Ingresa un código postal válido.',
				'select_program' => 'Selecciona un programa.',
			),
		);
		return $m[ $lang ][ $key ] ?? $m['en'][ $key ];
	}
}
