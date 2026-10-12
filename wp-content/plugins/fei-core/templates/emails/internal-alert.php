<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/_helpers.php';
/** @var array $lead @var int $lead_id @var string $edit_url */
$rows = array(
	'Name'       => trim( $lead['firstName'] . ' ' . $lead['lastName'] ),
	'Phone'      => $lead['phone'],
	'Email'      => $lead['email'],
	'Zip'        => $lead['zip'] ?? '',
	'Program'    => trim( ( $lead['program'] ?? '' ) . ' ' . ( $lead['programType'] ? '(' . $lead['programType'] . ')' : '' ) ),
	'Language'   => strtoupper( $lead['lang'] ?? 'en' ),
	'Stage'      => $lead['stage'] ?? 'full',
	'SMS consent' => ( ! empty( $lead['smsTransactionalConsent'] ) ? 'transactional ' : '' ) . ( ! empty( $lead['smsMarketingConsent'] ) ? 'marketing' : '' ),
	'Source'     => FEI_Zoho_Mapper::lead_source( $lead ),
	'Landing'    => $lead['referrer'] ?? '',
	'Referrer'   => $lead['documentReferrer'] ?? '',
	'Web lead ID' => $lead['preRegId'] ?? '',
);
foreach ( fei_touch_keys() as $k ) {
	if ( ! empty( $lead[ $k ] ) ) {
		$rows[ $k ] = $lead[ $k ];
	}
}
$b = fei_email_p( '<strong>New web lead — call within 5 minutes for best contact rate.</strong>' );
$b .= '<table role="presentation" cellspacing="0" cellpadding="6" style="border-collapse:collapse;font-size:14px;width:100%;">';
foreach ( $rows as $k => $v ) {
	if ( '' === trim( (string) $v ) ) {
		continue;
	}
	$val = 'Phone' === $k ? '<a href="tel:' . esc_attr( $v ) . '">' . esc_html( $v ) . '</a>' : ( 'Email' === $k ? '<a href="mailto:' . esc_attr( $v ) . '">' . esc_html( $v ) . '</a>' : esc_html( (string) $v ) );
	$b  .= '<tr><th align="left" style="border-bottom:1px solid #edf2f7;white-space:nowrap;vertical-align:top;">' . esc_html( $k ) . '</th><td style="border-bottom:1px solid #edf2f7;word-break:break-all;">' . $val . '</td></tr>';
}
$b .= '</table>';
$b .= fei_email_button( $edit_url, 'Open lead in WordPress' );
fei_email_wrap( 'New lead', $b, 'en', array( 'org' => fei_org() ) );
