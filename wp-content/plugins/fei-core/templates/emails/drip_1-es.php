<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/_helpers.php';
$name = esc_html( $lead['firstName'] ?: '' );
$prog = esc_html( $lead['program'] ?: 'un programa de FEI' );
$b    = fei_email_p( "Hola $name," );
$b   .= fei_email_p( "Ayer preguntaste por <strong>$prog</strong>. Si aún no hemos hablado, es nuestra responsabilidad, y nos encantaría conversar contigo." );
$b   .= fei_email_p( 'La mayoría de las personas que dudan se preguntan una de estas tres cosas:' );
$b   .= '<ul style="margin:0 0 14px 20px;padding:0;line-height:1.7;">'
	. '<li><strong>«¿Puedo pagarlo?»</strong> Hay ayuda financiera disponible para quienes califiquen, y nuestra Oficina de Ayuda Financiera arma el plan contigo antes de que te comprometas a nada.</li>'
	. '<li><strong>«¿Puedo combinarlo con el trabajo y la familia?»</strong> Varios programas son en línea o híbridos, y las clases empiezan cada 3–4 semanas, así que tú eliges cuándo comenzar.</li>'
	. '<li><strong>«¿De verdad conseguiré trabajo?»</strong> Servicios de Carrera te apoya con tu currículum, preparación para entrevistas y conexiones con empleadores en Miami.</li>'
	. '</ul>';
$b .= fei_email_button( 'tel:' . $org['phone_e164'], 'Habla con un asesor: ' . $org['phone'] );
$b .= fei_email_p( 'O simplemente responde a este correo con la mejor hora para llamarte.' );
$b .= fei_email_p( '<strong>Admisiones FEI</strong>' );
fei_email_wrap( 'Una pregunta rápida', $b, 'es', get_defined_vars() );
