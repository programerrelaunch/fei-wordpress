<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/_helpers.php';
$name = esc_html( $lead['firstName'] ?: '' );
$prog = esc_html( $lead['program'] ?: 'tu programa' );
$b    = fei_email_p( "Hola $name," );
$b   .= fei_email_p( 'Este es nuestro último seguimiento, a menos que nos digas lo contrario. Sin presión en ningún caso.' );
$b   .= fei_email_p( "La mejor manera de saber si FEI es para ti es visitarnos. Recorre las aulas y los laboratorios, conoce a los instructores que te enseñarían <strong>$prog</strong> y siente el ambiente familiar antes de comprometerte a nada." );
$b   .= fei_email_p( '<strong>Bueno saberlo:</strong> no hay cuota de solicitud ni fecha límite. Las clases nuevas empiezan cada 3–4 semanas, así que si decides que esto es para ti, no esperarás mucho.' );
$b   .= fei_email_button( 'tel:' . $org['phone_e164'], 'Agenda tu visita al campus: ' . $org['phone'] );
$b   .= fei_email_p( 'Estamos en ' . esc_html( $org['street'] . ', ' . $org['city'] . ', ' . $org['region'] . ' ' . $org['zip'] ) . '. Responde con un día que te convenga y nosotros nos encargamos del resto.' );
$b   .= fei_email_p( 'Decidas lo que decidas, te deseamos lo mejor, y la puerta sigue abierta.<br><strong>Admisiones FEI</strong>' );
fei_email_wrap( '¿Quieres visitar el campus?', $b, 'es', get_defined_vars() );
