<?php
/**
 * Apply form — single source for the modal (prefix am), /apply (af) and LP step-2 (lp).
 * Field names/ids and hidden attribution inputs match the Astro ApplyForm so fei-site.js
 * (port of site.js) works unchanged.
 *
 * @var string $prefix @var string $form_id @var string $stage @var string $title @var string $success_id
 * @var array  $programs @var bool $standalone
 */
defined( 'ABSPATH' ) || exit;
$p     = $prefix;
$lang  = fei_lang();
$org   = fei_org();
$terms = home_url( 'es' === $lang ? '/es/legal/terminos/' : '/legal/terms/' );
$priv  = home_url( 'es' === $lang ? '/es/legal/privacidad/' : '/legal/privacy/' );
$field = static function ( string $id, string $label, string $type, string $name, string $autocomplete, bool $required = true, array $extra = array() ) use ( $p ): string {
	$attrs = '';
	foreach ( $extra as $k => $v ) {
		$attrs .= ' ' . $k . '="' . esc_attr( $v ) . '"';
	}
	return sprintf(
		'<div class="field%7$s"><label for="%1$s-%2$s">%3$s</label><input type="%4$s" id="%1$s-%2$s" name="%5$s" autocomplete="%6$s" %8$s aria-describedby="%1$s-%2$s-error"%9$s><p class="field-error" id="%1$s-%2$s-error" hidden></p></div>',
		esc_attr( $p ),
		esc_attr( $id ),
		esc_html( $label ),
		esc_attr( $type ),
		esc_attr( $name ),
		esc_attr( $autocomplete ),
		'zip' === $id ? ' field--full' : '',
		$required ? 'required' : '',
		$attrs
	);
};
?>
<?php if ( ! empty( $standalone ) ) : ?>
<div class="apply-standalone" id="<?php echo esc_attr( $form_id ); ?>Wrap">
<?php endif; ?>
<?php if ( $title ) : ?>
	<h2 class="modal__title" id="<?php echo esc_attr( $form_id ); ?>Title" data-apply-to="<?php echo esc_attr( fei_t( 'apply_to' ) ); ?>"><?php echo esc_html( $title ); ?></h2>
<?php endif; ?>
<form class="form fei-form" id="<?php echo esc_attr( $form_id ); ?>" novalidate
	<?php echo $stage ? 'data-stage="' . esc_attr( $stage ) . '"' : ''; ?>
	data-msg-required="<?php echo esc_attr( fei_t( 'msg_required' ) ); ?>"
	data-msg-invalid-email="<?php echo esc_attr( fei_t( 'msg_invalid_email' ) ); ?>"
	data-msg-invalid-phone="<?php echo esc_attr( fei_t( 'msg_invalid_phone' ) ); ?>"
	data-msg-invalid-zip="<?php echo esc_attr( fei_t( 'msg_invalid_zip' ) ); ?>"
	data-msg-select-program="<?php echo esc_attr( fei_t( 'msg_select_program' ) ); ?>"
	data-msg-error-summary="<?php echo esc_attr( fei_t( 'msg_error_summary' ) ); ?>">
	<div class="form-summary" id="<?php echo esc_attr( $p ); ?>-summary" role="alert" tabindex="-1" hidden></div>
	<?php
	echo $field( 'first', fei_t( 'first_name' ), 'text', 'firstName', 'given-name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $field( 'last', fei_t( 'last_name' ), 'text', 'lastName', 'family-name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $field( 'email', fei_t( 'email' ), 'email', 'email', 'email', true, array( 'inputmode' => 'email' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $field( 'phone', fei_t( 'phone' ), 'tel', 'phone', 'tel', false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $field( 'zip', fei_t( 'zip' ), 'text', 'zip', 'postal-code', true, array( 'inputmode' => 'numeric', 'maxlength' => '10' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
	<div class="field field--full" id="<?php echo esc_attr( $p ); ?>-program-field">
		<label for="<?php echo esc_attr( $p ); ?>-program"><?php echo esc_html( fei_t( 'program_of_interest' ) ); ?></label>
		<select id="<?php echo esc_attr( $p ); ?>-program" name="program" required aria-describedby="<?php echo esc_attr( $p ); ?>-program-error">
			<option value="" disabled selected><?php echo esc_html( fei_t( 'select_program' ) ); ?></option>
			<?php foreach ( $programs as $prog ) : ?>
				<option value="<?php echo esc_attr( $prog['key'] ); ?>" data-program-type="<?php echo esc_attr( $prog['type'] ); ?>"><?php echo esc_html( $prog['label'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="field-error" id="<?php echo esc_attr( $p ); ?>-program-error" hidden></p>
	</div>
	<input type="hidden" id="<?php echo esc_attr( $p ); ?>-program-type" name="programType">
	<?php foreach ( fei_touch_keys() as $k ) : ?>
		<input type="hidden" id="<?php echo esc_attr( $p . '-' . str_replace( '_', '-', $k ) ); ?>" name="<?php echo esc_attr( $k ); ?>">
	<?php endforeach; ?>
	<input type="hidden" name="lang" value="<?php echo esc_attr( $lang ); ?>">
	<input type="hidden" name="_t0" class="fei-t0" value="">
	<div class="fei-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
	<label class="consent"><input type="checkbox" id="<?php echo esc_attr( $p ); ?>-sms-marketing" name="smsMarketing"><span><?php echo esc_html( fei_t( 'sms_marketing' ) ); ?></span></label>
	<label class="consent"><input type="checkbox" id="<?php echo esc_attr( $p ); ?>-sms-transactional" name="smsTransactional"><span><?php echo esc_html( fei_t( 'sms_transactional' ) ); ?></span></label>
	<p class="consent-fine"><?php echo wp_kses_post( sprintf( fei_t( 'consent_fine' ), '<a href="' . esc_url( $terms ) . '">' . esc_html( fei_t( 'terms' ) ) . '</a>', '<a href="' . esc_url( $priv ) . '">' . esc_html( fei_t( 'privacy' ) ) . '</a>' ) ); ?></p>
	<?php if ( fei_opt( 'turnstile_site_key' ) ) : ?>
		<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( fei_opt( 'turnstile_site_key' ) ); ?>" data-size="flexible"></div>
	<?php endif; ?>
	<button type="submit" class="btn btn--solid modal__submit"><?php echo esc_html( fei_t( 'send' ) ); ?><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
	<p class="form-error" id="<?php echo esc_attr( $p ); ?>-error" role="alert" hidden><?php echo wp_kses_post( sprintf( fei_t( 'form_error' ), '<a href="tel:' . esc_attr( $org['phone_e164'] ) . '">' . esc_html( $org['phone'] ) . '</a>' ) ); ?></p>
</form>
<div id="<?php echo esc_attr( $success_id ); ?>" class="apply-success" role="status" tabindex="-1" hidden>
	<div class="apply-success__mark" aria-hidden="true"><svg viewBox="0 0 24 24" width="30" height="30"><path d="M5 13l4 4L19 7" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
	<h2 class="modal__title"><?php echo wp_kses_post( sprintf( fei_t( 'thank_you' ), '<span id="' . esc_attr( $success_id ) . 'Name">' . esc_html( fei_t( 'there' ) ) . '</span>' ) ); ?></h2>
	<p class="modal__sub"><?php echo esc_html( fei_t( 'success_sub' ) ); ?></p>
</div>
<?php if ( ! empty( $standalone ) ) : ?>
</div>
<?php endif; ?>
