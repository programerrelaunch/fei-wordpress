<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/_helpers.php';
$name = esc_html( $lead['firstName'] ?: 'there' );
$prog = esc_html( $lead['program'] ?: 'a program at FEI' );
$b    = fei_email_p( "Hi $name," );
$b   .= fei_email_p( "Yesterday you asked about <strong>$prog</strong>. If we have not connected yet, that is on us — and we would still love to talk." );
$b   .= fei_email_p( 'Most people who hesitate are wondering about one of these three things:' );
$b   .= '<ul style="margin:0 0 14px 20px;padding:0;line-height:1.7;">'
	. '<li><strong>“Can I afford it?”</strong> Financial aid is available to those who qualify, and our Financial Aid Office builds the plan with you before you commit to anything.</li>'
	. '<li><strong>“Can I fit it around work and family?”</strong> Several programs are online or hybrid, and classes start every 3–4 weeks, so you choose when to begin.</li>'
	. '<li><strong>“Will I actually get a job?”</strong> Career Services supports you with résumé help, interview prep and employer connections in Miami.</li>'
	. '</ul>';
$b .= fei_email_button( 'tel:' . $org['phone_e164'], 'Talk to an advisor: ' . $org['phone'] );
$b .= fei_email_p( 'Or simply reply to this email with the best time to call you.' );
$b .= fei_email_p( '<strong>FEI Admissions</strong>' );
fei_email_wrap( 'Quick question', $b, 'en', get_defined_vars() );
