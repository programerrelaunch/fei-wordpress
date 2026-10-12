<?php
/**
 * Program tiles (home) / cards (archive). @var array $programs catalog rows @var array $atts
 */
defined( 'ABSPATH' ) || exit;
$style = $atts['style'] ?? 'tiles';
$icons = array(
	'health'   => '<path d="M12 21s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.6-7 10-7 10z"/>',
	'culinary' => '<path d="M6 3v8a3 3 0 0 0 6 0V3M9 3v18M17 3c-2 2-2 6 0 8v10"/>',
	'trades'   => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.1-2.1z"/>',
	'business' => '<path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6"/>',
);
?>
<ul class="prog-list prog-list--<?php echo esc_attr( $style ); ?>">
	<?php foreach ( $programs as $p ) :
		$full = $p['id'] ? FEI_Programs::get_program( (int) $p['id'] ) : array();
		$area = $full['area_slug'] ?? '';
		$key  = str_replace( '-es', '', $area );
		?>
		<li>
			<a href="<?php echo esc_url( $p['url'] ); ?>" class="prog-tile reveal" data-cat="<?php echo esc_attr( $key ); ?>">
				<?php if ( 'cards' === $style && ! empty( $full['image_url'] ) ) : ?>
					<span class="prog-tile__media"><img src="<?php echo esc_url( $full['image_url'] ); ?>" alt="<?php echo esc_attr( $p['label'] ); ?>" loading="lazy" width="640" height="400"></span>
				<?php else : ?>
					<span class="prog-tile__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?php echo $icons[ $key ] ?? $icons['business']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></svg></span>
				<?php endif; ?>
				<span class="prog-tile__body">
					<span class="prog-tile__tag"><?php echo esc_html( 'Associate Degree' === $p['type'] ? 'A.A.S.' : 'Diploma' ); ?><?php echo ! empty( $full['area'] ) ? ' · ' . esc_html( $full['area'] ) : ''; ?></span>
					<span class="prog-tile__name"><?php echo esc_html( str_replace( ' (A.A.S.)', '', $p['label'] ) ); ?></span>
					<?php if ( 'cards' === $style && ! empty( $full['short'] ) ) : ?>
						<span class="prog-tile__desc"><?php echo esc_html( $full['short'] ); ?></span>
					<?php endif; ?>
				</span>
				<svg class="prog-tile__arrow" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
