<?php
/**
 * Small helpers shared by email templates.
 */
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'fei_email_button' ) ) {
	function fei_email_button( string $url, string $label ): string {
		return sprintf(
			'<table role="presentation" cellspacing="0" cellpadding="0" style="margin:20px 0;"><tr><td style="background:#ffb81d;"><a href="%s" style="display:inline-block;padding:14px 26px;font-weight:700;color:#1a202c;text-decoration:none;font-size:15px;">%s</a></td></tr></table>',
			esc_url( $url ),
			esc_html( $label )
		);
	}
}

if ( ! function_exists( 'fei_email_wrap' ) ) {
	/** Render body inside the layout. */
	function fei_email_wrap( string $title, string $body_html, string $lang, array $vars ): void {
		$unsubscribe = $vars['unsubscribe'] ?? '';
		$org         = $vars['org'] ?? fei_org();
		include __DIR__ . '/_layout.php';
	}
}

if ( ! function_exists( 'fei_email_p' ) ) {
	function fei_email_p( string $text ): string {
		return '<p style="margin:0 0 14px;">' . wp_kses( $text, array( 'strong' => array(), 'em' => array(), 'a' => array( 'href' => array(), 'style' => array() ), 'br' => array() ) ) . '</p>';
	}
}
