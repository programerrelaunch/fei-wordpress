<?php
/**
 * Email layout. Expects: $title, $body_html, $lang, $unsubscribe (optional), $org.
 * Bulletproof table layout, FEI tokens (navy #1a202c, gold #ffb81d, blue #0072ce), square corners.
 */
defined( 'ABSPATH' ) || exit;
$org  = $org ?? fei_org();
$lang = $lang ?? 'en';
$home = home_url( 'es' === $lang ? '/es/' : '/' );
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( $lang ); ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html( $title ?? $org['name'] ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f7fafc;font-family:Montserrat,Helvetica,Arial,sans-serif;color:#1a202c;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f7fafc;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border-top:6px solid #ffb81d;">
	<tr><td style="padding:28px 32px 8px;">
		<a href="<?php echo esc_url( $home ); ?>" style="text-decoration:none;color:#1a202c;font-weight:800;font-size:22px;letter-spacing:.02em;">FEI</a>
		<span style="display:block;font-size:12px;color:#4a5568;"><?php echo esc_html( $org['name'] ); ?></span>
	</td></tr>
	<tr><td style="padding:8px 32px 28px;font-size:16px;line-height:1.6;">
		<?php echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- composed from escaped fragments. ?>
	</td></tr>
	<tr><td style="padding:20px 32px;background:#1a202c;color:#edf2f7;font-size:13px;line-height:1.6;">
		<strong style="color:#fff;"><?php echo esc_html( $org['name'] ); ?></strong><br>
		<?php echo esc_html( $org['street'] . ', ' . $org['city'] . ', ' . $org['region'] . ' ' . $org['zip'] ); ?><br>
		<a href="tel:<?php echo esc_attr( $org['phone_e164'] ); ?>" style="color:#ffb81d;text-decoration:none;"><?php echo esc_html( $org['phone'] ); ?></a> ·
		<a href="mailto:<?php echo esc_attr( $org['email'] ); ?>" style="color:#ffb81d;text-decoration:none;"><?php echo esc_html( $org['email'] ); ?></a><br>
		<span style="color:#a0aec0;"><?php echo esc_html( 'es' === $lang ? 'Acreditado por el Council on Occupational Education (COE). Ayuda financiera disponible para quienes califiquen.' : 'Accredited by the Council on Occupational Education (COE). Financial aid available to those who qualify.' ); ?></span>
		<?php if ( ! empty( $unsubscribe ) ) : ?>
			<br><a href="<?php echo esc_url( $unsubscribe ); ?>" style="color:#a0aec0;"><?php echo esc_html( 'es' === $lang ? 'Dejar de recibir correos de seguimiento' : 'Stop follow-up emails' ); ?></a>
		<?php endif; ?>
	</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
