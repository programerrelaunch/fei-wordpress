<?php
/**
 * Pure mapping from an FEI lead array to a Zoho CRM Leads record.
 *
 * Standard Zoho Leads API names are used where they exist; everything else goes to
 * custom fields whose API names are the DEFAULT guesses below and can be overridden
 * from Settings → Zoho → "Field map override" once the org's real API names are
 * confirmed (Setup → Developer Space → APIs → API Names).
 */

defined( 'ABSPATH' ) || defined( 'FEI_TESTING' ) || exit;

final class FEI_Zoho_Mapper {

	/** lead field => Zoho API name (defaults). */
	public static function default_map(): array {
		return array(
			'firstName'               => 'First_Name',
			'lastName'                => 'Last_Name',
			'email'                   => 'Email',
			'phone'                   => 'Mobile',
			'zip'                     => 'Zip_Code',
			'program'                 => 'Program_of_Interest',
			'programType'             => 'Program_Type',
			'stage'                   => 'Lead_Stage',
			'preRegId'                => 'Web_Lead_ID',
			'smsMarketingConsent'     => 'SMS_Marketing_Consent',
			'smsTransactionalConsent' => 'SMS_Transactional_Consent',
			'referrer'                => 'Landing_Page',
			'documentReferrer'        => 'Referrer_URL',
			'ip'                      => 'IP_Address',
			'lang'                    => 'Preferred_Language',
			'utm_source'              => 'UTM_Source',
			'utm_medium'              => 'UTM_Medium',
			'utm_campaign'            => 'UTM_Campaign',
			'utm_content'             => 'UTM_Content',
			'utm_term'                => 'UTM_Term',
			'utm_id'                  => 'UTM_ID',
			'utm_platform'            => 'UTM_Platform',
			'gclid'                   => 'GCLID',
			'gbraid'                  => 'GBRAID',
			'wbraid'                  => 'WBRAID',
			'fbclid'                  => 'FBCLID',
			'fbc'                     => 'FBC',
			'fbp'                     => 'FBP',
			'msclkid'                 => 'MSCLKID',
			'ttclid'                  => 'TTCLID',
		);
	}

	/**
	 * @param array $lead     Validated lead.
	 * @param array $override Partial map {leadField: ZohoApiName}; a null/'' value drops the field.
	 * @param array $extra    Extra static values (Owner, Layout, …).
	 */
	public static function to_record( array $lead, array $override = array(), array $extra = array() ): array {
		$map = array_merge( self::default_map(), $override );
		$rec = array();
		foreach ( $map as $src => $api ) {
			if ( ! $api || ! array_key_exists( $src, $lead ) ) {
				continue;
			}
			$v = $lead[ $src ];
			if ( is_bool( $v ) ) {
				$rec[ $api ] = $v;
			} elseif ( '' !== (string) $v ) {
				$rec[ $api ] = (string) $v;
			}
		}
		// Zoho requires Last_Name; pre-registrations may lack it in theory.
		if ( empty( $rec['Last_Name'] ) ) {
			$rec['Last_Name'] = $lead['lastName'] ?: ( $lead['firstName'] ?: 'Unknown' );
		}
		$rec['Lead_Source'] = self::lead_source( $lead );
		$rec['Description'] = self::description( $lead );
		if ( ! empty( $lead['smsMarketingConsent'] ) || ! empty( $lead['smsTransactionalConsent'] ) ) {
			$rec['Email_Opt_Out'] = false;
		}
		foreach ( $extra as $k => $v ) {
			if ( null !== $v && '' !== $v ) {
				$rec[ $k ] = $v;
			}
		}
		return $rec;
	}

	/** Derive a human lead source from attribution (last-touch). */
	public static function lead_source( array $lead ): string {
		$src = strtolower( (string) ( $lead['utm_source'] ?? '' ) );
		$med = strtolower( (string) ( $lead['utm_medium'] ?? '' ) );
		if ( ! empty( $lead['gclid'] ) || ! empty( $lead['gbraid'] ) || ! empty( $lead['wbraid'] ) || 'google' === $src && in_array( $med, array( 'cpc', 'ppc', 'paid' ), true ) ) {
			return 'Google Ads';
		}
		if ( ! empty( $lead['fbclid'] ) || in_array( $src, array( 'facebook', 'fb', 'instagram', 'ig', 'meta' ), true ) ) {
			return in_array( $med, array( 'cpc', 'paid', 'paid_social', 'ppc' ), true ) || ! empty( $lead['fbclid'] ) ? 'Meta Ads' : 'Social';
		}
		if ( ! empty( $lead['msclkid'] ) || 'bing' === $src ) {
			return 'Microsoft Ads';
		}
		if ( ! empty( $lead['ttclid'] ) || 'tiktok' === $src ) {
			return 'TikTok Ads';
		}
		if ( '' !== $src ) {
			return ucfirst( $src ) . ( $med ? ' / ' . $med : '' );
		}
		$ref = strtolower( (string) ( $lead['documentReferrer'] ?? '' ) );
		if ( preg_match( '#(google|bing|yahoo|duckduckgo)\.#', $ref ) ) {
			return 'Organic Search';
		}
		if ( preg_match( '#(chatgpt|openai|perplexity|claude|gemini|copilot)\.#', $ref ) ) {
			return 'AI Referral';
		}
		if ( preg_match( '#(facebook|instagram|tiktok|x\.com|twitter|linkedin|youtube)\.#', $ref ) ) {
			return 'Social';
		}
		if ( '' !== $ref ) {
			return 'Referral';
		}
		return 'Website';
	}

	public static function description( array $lead ): string {
		$lines = array(
			'Web lead (' . ( $lead['stage'] ?? 'full' ) . ') from ' . ( $lead['pageUrl'] ?: $lead['referrer'] ?: 'fei.edu' ),
			'Program: ' . ( $lead['program'] ?: '—' ) . ( $lead['programType'] ? ' (' . $lead['programType'] . ')' : '' ),
			'Language: ' . strtoupper( (string) ( $lead['lang'] ?? 'en' ) ),
		);
		$utms = array();
		foreach ( fei_touch_keys() as $k ) {
			if ( ! empty( $lead[ $k ] ) ) {
				$utms[] = $k . '=' . $lead[ $k ];
			}
		}
		if ( $utms ) {
			$lines[] = 'Attribution: ' . implode( ' ', $utms );
		}
		return implode( "\n", $lines );
	}

	/** Zoho COQL/search criteria to find an existing lead (dedupe). */
	public static function search_criteria( array $lead ): array {
		$c = array();
		if ( ! empty( $lead['preRegId'] ) ) {
			$c[] = array( 'Web_Lead_ID', $lead['preRegId'] );
		}
		if ( ! empty( $lead['email'] ) ) {
			$c[] = array( 'Email', $lead['email'] );
		}
		if ( ! empty( $lead['phone'] ) ) {
			$c[] = array( 'Mobile', $lead['phone'] );
		}
		return $c;
	}
}
