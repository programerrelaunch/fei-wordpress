<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/_helpers.php';
$name = esc_html( $lead['firstName'] ?: '' );
$prog = esc_html( $lead['program'] ?: '' );
$b    = fei_email_p( "Hola $name," );
$b   .= fei_email_p( $prog ? "Gracias por tu interés en el programa de <strong>$prog</strong> en Florida Education Institute. Aquí nunca serás un número, así que esto es exactamente lo que sigue:" : 'Gracias por contactar a Florida Education Institute. Aquí nunca serás un número, así que esto es exactamente lo que sigue:' );
$b   .= '<ol style="margin:0 0 14px 20px;padding:0;line-height:1.7;">'
	. '<li><strong>Un Asesor de Admisiones te llamará</strong>, normalmente dentro de un día hábil, desde el ' . esc_html( $org['phone'] ) . '. Guarda el número para reconocernos.</li>'
	. '<li><strong>Respondemos tus preguntas</strong> sobre horarios, fechas de inicio (las clases empiezan cada 3–4 semanas) y si el programa encaja con tus metas.</li>'
	. '<li><strong>Ayuda financiera explicada de forma personalizada</strong>: Beca Pell, préstamos, becas y planes de pago, para quienes califiquen.</li>'
	. '</ol>';
if ( ! empty( $program['length'] ) ) {
	$b .= fei_email_p( '<strong>' . $prog . ' en resumen:</strong> ' . esc_html( $program['length'] ) . ' · ' . esc_html( $program['credential'] ?? '' ) . ' · ' . esc_html( $program['format'] ?? '' ) . ( ! empty( $program['campus'] ) ? ' · ' . esc_html( $program['campus'] ) : '' ) );
}
$b .= fei_email_button( 'tel:' . $org['phone_e164'], 'Llámanos ahora: ' . $org['phone'] );
$b .= fei_email_p( '¿Prefieres conocer el campus primero? Responde a este correo con un día y una hora y organizamos tu visita en ' . esc_html( $org['street'] . ', ' . $org['city'] ) . '.' );
$b .= fei_email_p( 'Hablamos pronto,<br><strong>Admisiones FEI</strong>' );
fei_email_wrap( 'Recibimos tu solicitud', $b, 'es', get_defined_vars() );
