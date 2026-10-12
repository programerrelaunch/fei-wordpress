<?php
/**
 * Accordion FAQ + optional FAQPage JSON-LD.
 * @var array $items [['q'=>..,'a'=>..],..] @var bool $schema
 */
defined( 'ABSPATH' ) || exit;
if ( empty( $items ) ) {
	return;
}
$uid = 'faq' . wp_unique_id();
?>
<div class="accordion" id="<?php echo esc_attr( $uid ); ?>">
	<?php foreach ( $items as $i => $it ) : ?>
		<div class="acc-item<?php echo 0 === $i ? ' is-open' : ''; ?>">
			<button class="acc-trigger" type="button" aria-expanded="<?php echo 0 === $i ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $uid . '-' . $i ); ?>">
				<span><?php echo esc_html( $it['q'] ?? '' ); ?></span>
				<span class="acc-ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg></span>
			</button>
			<div class="acc-panel" id="<?php echo esc_attr( $uid . '-' . $i ); ?>"><p><?php echo wp_kses_post( $it['a'] ?? '' ); ?></p></div>
		</div>
	<?php endforeach; ?>
</div>
<?php if ( ! empty( $schema ) ) : ?>
<script type="application/ld+json"><?php
echo wp_json_encode(
	array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => array_map(
			static fn( $it ) => array(
				'@type'          => 'Question',
				'name'           => wp_strip_all_tags( $it['q'] ?? '' ),
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( $it['a'] ?? '' ) ),
			),
			$items
		),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
?></script>
<?php endif; ?>
