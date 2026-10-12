<?php
/**
 * Apply modal overlay — identical ids to the Astro site (applyModal, applyModalForm, am-*).
 * @var array $programs
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="modal-overlay" id="applyModal" aria-hidden="true">
	<div class="modal" role="dialog" aria-modal="true" aria-labelledby="applyModalTitle">
		<button class="modal__close" id="applyModalClose" type="button" aria-label="<?php esc_attr_e( 'Close', 'fei-core' ); ?>"><svg viewBox="0 0 24 24" width="20" height="20"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg></button>
		<div id="modalFormWrap">
			<p class="modal__eyebrow"><?php echo esc_html( fei_t( 'modal_eyebrow' ) ); ?></p>
			<h2 class="modal__title" id="applyModalTitle" data-apply-to="<?php echo esc_attr( fei_t( 'apply_to' ) ); ?>"><?php echo esc_html( fei_t( 'modal_title' ) ); ?></h2>
			<p class="modal__sub"><?php echo esc_html( fei_t( 'modal_sub' ) ); ?></p>
			<?php
			echo FEI_Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'apply-form',
				array(
					'prefix'     => 'am',
					'form_id'    => 'applyModalForm',
					'stage'      => '',
					'title'      => '',
					'success_id' => 'modalSuccessWrap',
					'programs'   => $programs,
					'standalone' => false,
				)
			);
			?>
		</div>
	</div>
</div>
