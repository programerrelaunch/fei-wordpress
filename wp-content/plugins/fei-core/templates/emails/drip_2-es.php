<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/_helpers.php';
$name = esc_html( $lead['firstName'] ?: '' );
$b    = fei_email_p( "Hola $name," );
$b   .= fei_email_p( '«¿Puedo pagarlo?» es la pregunta que más escuchamos, así que aquí va la respuesta directa: <strong>no dejes que el costo decida por ti antes de ver los números.</strong>' );
$b   .= fei_email_p( 'Formas en que los estudiantes de FEI financian su programa (para quienes califiquen):' );
$b   .= '<ul style="margin:0 0 14px 20px;padding:0;line-height:1.7;">'
	. '<li><strong>Beca Federal Pell</strong> y <strong>FSEOG</strong>: ayudas que no se devuelven.</li>'
	. '<li><strong>Préstamos Federales Directos</strong> (subsidiados y no subsidiados) y préstamos <strong>PLUS para padres</strong>.</li>'
	. '<li><strong>Beneficios para veteranos</strong>: GI Bill® Post-9/11 y Rehabilitación Vocacional.</li>'
	. '<li><strong>Becas de FEI</strong>, incluida la Beca para Estudiantes de Secundaria de $2,500, y el <strong>Préstamo Institucional de FEI</strong>.</li>'
	. '</ul>';
$b .= fei_email_p( 'Tres pasos: completa la FAFSA en studentaid.gov, envíanos tus documentos y reúnete de forma personalizada con nuestra Oficina de Ayuda Financiera. Nunca llenarás formularios solo.' );
$b .= fei_email_button( $aid_url, 'Ver tus opciones de ayuda financiera' );
$b .= fei_email_p( '¿Quieres un estimado primero? La Calculadora de Precio Neto está en esa misma página. ¿Preguntas? Llama al ' . esc_html( $org['phone'] ) . '.' );
$b .= fei_email_p( '<strong>Ayuda Financiera y Admisiones FEI</strong>' );
fei_email_wrap( 'Cómo pagar tus estudios en FEI', $b, 'es', get_defined_vars() );
