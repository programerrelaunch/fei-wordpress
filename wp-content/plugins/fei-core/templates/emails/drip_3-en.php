<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/_helpers.php';
$name = esc_html( $lead['firstName'] ?: 'there' );
$prog = esc_html( $lead['program'] ?: 'your program' );
$b    = fei_email_p( "Hi $name," );
$b   .= fei_email_p( 'This is the last follow-up from us unless you tell us otherwise — no pressure either way.' );
$b   .= fei_email_p( "The best way to know if FEI is right for you is to visit. Tour the classrooms and labs, meet the instructors who would teach <strong>$prog</strong>, and get a feel for the family-like environment before you commit to anything." );
$b   .= fei_email_p( '<strong>Good to know:</strong> there is no application fee and no deadline. New classes start every 3–4 weeks, so if you decide this is for you, you will not wait long.' );
$b   .= fei_email_button( 'tel:' . $org['phone_e164'], 'Schedule a campus tour: ' . $org['phone'] );
$b   .= fei_email_p( 'We are at ' . esc_html( $org['street'] . ', ' . $org['city'] . ', ' . $org['region'] . ' ' . $org['zip'] ) . '. Reply with a day that works and we will take care of the rest.' );
$b   .= fei_email_p( 'Whatever you decide, we wish you the best — and the door stays open.<br><strong>FEI Admissions</strong>' );
fei_email_wrap( 'Want a campus tour?', $b, 'en', get_defined_vars() );
