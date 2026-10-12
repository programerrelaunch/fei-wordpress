<?php
/**
 * LP step-1 pre-registration (prefix pr) — name, email, phone. Posts stage:"pre" and
 * hands off to step 2 (data-next) carrying the preRegId in sessionStorage.
 * @var string $program @var string $program_type @var string $next @var string $title @var string $cta
 */
defined( 'ABSPATH' ) || exit;
$lang = fei_lang();
$next = $next ?: home_url( 'es' === $lang ? '/es/inscripcion/' : '/apply/' );
?>
<div class="prereg" id="preRegWrap">
	<p class="modal__eyebrow"><?php echo esc_html( fei_t( 'modal_eyebrow' ) ); ?></p>
	<h2 class="prereg__title"><?php echo esc_html( $title ?: fei_t( 'prereg_title' ) ); ?></h2>
	<p class="modal__sub"><?php echo esc_html( fei_t( 'prereg_sub' ) ); ?></p>
	<form class="form fei-form prereg__form" id="preRegForm" novalidate
		data-program="<?php echo esc_attr( $program ); ?>" data-program-type="<?php echo esc_attr( $program_type ); ?>" data-next="<?php echo esc_url( $next ); ?>"
		data-msg-required="<?php echo esc_attr( fei_t( 'msg_required' ) ); ?>"
		data-msg-invalid-email="<?php echo esc_attr( fei_t( 'msg_invalid_email' ) ); ?>"
		data-msg-invalid-phone="<?php echo esc_attr( fei_t( 'msg_invalid_phone' ) ); ?>"
		data-msg-error-summary="<?php echo esc_attr( fei_t( 'msg_error_summary' ) ); ?>">
		<div class="form-summary" id="pr-summary" role="alert" tabindex="-1" hidden></div>
		<div class="field"><label for="pr-first"><?php echo esc_html( fei_t( 'first_name' ) ); ?></label><input type="text" id="pr-first" name="firstName" autocomplete="given-name" required aria-describedby="pr-first-error"><p class="field-error" id="pr-first-error" hidden></p></div>
		<div class="field"><label for="pr-last"><?php echo esc_html( fei_t( 'last_name' ) ); ?></label><input type="text" id="pr-last" name="lastName" autocomplete="family-name" required aria-describedby="pr-last-error"><p class="field-error" id="pr-last-error" hidden></p></div>
		<div class="field field--full"><label for="pr-email"><?php echo esc_html( fei_t( 'email' ) ); ?></label><input type="email" id="pr-email" name="email" autocomplete="email" inputmode="email" required aria-describedby="pr-email-error"><p class="field-error" id="pr-email-error" hidden></p></div>
		<div class="field field--full"><label for="pr-phone"><?php echo esc_html( fei_t( 'phone' ) ); ?></label><input type="tel" id="pr-phone" name="phone" autocomplete="tel" required aria-describedby="pr-phone-error"><p class="field-error" id="pr-phone-error" hidden></p></div>
		<input type="hidden" name="lang" value="<?php echo esc_attr( $lang ); ?>">
		<input type="hidden" name="_t0" class="fei-t0" value="">
		<div class="fei-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
		<button type="submit" class="btn btn--solid btn--lg prereg__submit"><?php echo esc_html( $cta ?: fei_t( 'next_step' ) ); ?><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
		<p class="consent-fine"><?php echo esc_html( 'es' === $lang ? 'Sin compromiso. Nunca compartimos tu información.' : 'No obligation. We never share your information.' ); ?></p>
		<p class="form-error" id="pr-error" role="alert" hidden></p>
	</form>
</div>
