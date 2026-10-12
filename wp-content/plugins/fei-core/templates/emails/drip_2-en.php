<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/_helpers.php';
$name = esc_html( $lead['firstName'] ?: 'there' );
$b    = fei_email_p( "Hi $name," );
$b   .= fei_email_p( '“Can I afford it?” is the question we hear most, so here is the straight answer: <strong>do not let cost decide for you before you have seen the numbers.</strong>' );
$b   .= fei_email_p( 'Ways FEI students fund their program (for those who qualify):' );
$b   .= '<ul style="margin:0 0 14px 20px;padding:0;line-height:1.7;">'
	. '<li><strong>Federal Pell Grant</strong> and <strong>FSEOG</strong> — grants you do not repay.</li>'
	. '<li><strong>Federal Direct Loans</strong> (subsidized &amp; unsubsidized) and <strong>Parent PLUS</strong> loans.</li>'
	. '<li><strong>Veterans benefits</strong> — Post-9/11 GI Bill® and Vocational Rehabilitation.</li>'
	. '<li><strong>FEI scholarships</strong> — including the $2,500 High School Student Scholarship — and the <strong>FEI Institutional Loan</strong>.</li>'
	. '</ul>';
$b .= fei_email_p( 'Three steps: complete the FAFSA at studentaid.gov, send us your documents, and sit down one-on-one with our Financial Aid Office. You are never filling out forms alone.' );
$b .= fei_email_button( $aid_url, 'See your financial aid options' );
$b .= fei_email_p( 'Want an estimate first? Our Net Price Calculator is on that page too. Questions? Call ' . esc_html( $org['phone'] ) . '.' );
$b .= fei_email_p( '<strong>FEI Financial Aid &amp; Admissions</strong>' );
fei_email_wrap( 'Paying for school at FEI', $b, 'en', get_defined_vars() );
