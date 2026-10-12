<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/_helpers.php';
/** @var array $lead @var array $program @var string $apply_url @var string $aid_url @var array $org */
$name = esc_html( $lead['firstName'] ?: 'there' );
$prog = esc_html( $lead['program'] ?: '' );
$b    = '';
$b   .= fei_email_p( "Hi $name," );
$b   .= fei_email_p( $prog ? "Thanks for asking about the <strong>$prog</strong> program at Florida Education Institute. You will never be a number here, so here is exactly what happens next:" : 'Thanks for reaching out to Florida Education Institute. You will never be a number here, so here is exactly what happens next:' );
$b   .= '<ol style="margin:0 0 14px 20px;padding:0;line-height:1.7;">'
	. '<li><strong>An Admissions Advisor calls you</strong> — usually within one business day, from ' . esc_html( $org['phone'] ) . '. Save the number so you recognise us.</li>'
	. '<li><strong>We answer your questions</strong> about schedules, start dates (classes begin every 3–4 weeks) and whether the program fits your goals.</li>'
	. '<li><strong>Financial aid, explained one-on-one</strong> — Pell Grant, loans, scholarships and payment plans, for those who qualify.</li>'
	. '</ol>';
if ( ! empty( $program['length'] ) ) {
	$b .= fei_email_p( '<strong>' . $prog . ' at a glance:</strong> ' . esc_html( $program['length'] ) . ' · ' . esc_html( $program['credential'] ?? '' ) . ' · ' . esc_html( $program['format'] ?? '' ) . ( ! empty( $program['campus'] ) ? ' · ' . esc_html( $program['campus'] ) : '' ) );
}
$b .= fei_email_button( 'tel:' . $org['phone_e164'], 'Call us now: ' . $org['phone'] );
$b .= fei_email_p( 'Prefer to see the campus first? Reply to this email with a day and time and we will set up a tour at ' . esc_html( $org['street'] . ', ' . $org['city'] ) . '.' );
$b .= fei_email_p( 'Talk soon,<br><strong>FEI Admissions</strong>' );
fei_email_wrap( 'We got your request', $b, 'en', get_defined_vars() );
