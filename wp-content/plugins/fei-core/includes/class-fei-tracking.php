<?php
/**
 * GTM container (head + noscript), page-type dataLayer context, and server-side
 * dedupe keys for GA4/Meta CAPI (lead id travels with the form_conversion event).
 */

defined( 'ABSPATH' ) || exit;

final class FEI_Tracking {

	private static ?FEI_Tracking $instance = null;

	public static function instance(): FEI_Tracking {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'wp_head', array( $this, 'head' ), 1 );
		add_action( 'wp_body_open', array( $this, 'noscript' ), 1 );
	}

	private function gtm_id(): string {
		$id = (string) fei_opt( 'gtm_id', '' );
		return preg_match( '/^GTM-[A-Z0-9]{4,10}$/', $id ) ? $id : '';
	}

	public function head(): void {
		if ( is_admin() || is_user_logged_in() && current_user_can( 'edit_posts' ) && ! apply_filters( 'fei_track_editors', false ) ) {
			return; // don't pollute analytics with staff sessions.
		}
		$ctx = array(
			'page_type' => $this->page_type(),
			'lang'      => fei_lang(),
			'program'   => is_singular( FEI_Programs::CPT ) ? FEI_Programs::get_field( get_queried_object_id(), 'key' ) : '',
		);
		echo '<script>window.dataLayer=window.dataLayer||[];window.dataLayer.push(' . wp_json_encode( $ctx ) . ');</script>' . "\n";
		$gtm = $this->gtm_id();
		if ( ! $gtm ) {
			return;
		}
		// Standard GTM snippet (same container as the Astro site).
		echo "<!-- Google Tag Manager --><script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','" . esc_js( $gtm ) . "');</script><!-- End Google Tag Manager -->\n";
	}

	public function noscript(): void {
		$gtm = $this->gtm_id();
		if ( ! $gtm || is_admin() ) {
			return;
		}
		echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr( $gtm ) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
	}

	private function page_type(): string {
		if ( is_front_page() ) {
			return 'home';
		}
		if ( is_singular( FEI_Programs::CPT ) ) {
			return 'program';
		}
		if ( is_post_type_archive( FEI_Programs::CPT ) ) {
			return 'programs_index';
		}
		if ( is_singular( FEI_Landing::CPT ) ) {
			return 'landing';
		}
		if ( is_singular( 'post' ) ) {
			return 'blog_post';
		}
		if ( is_home() ) {
			return 'blog_index';
		}
		if ( is_page() ) {
			$slug = get_post_field( 'post_name', get_queried_object_id() );
			return in_array( $slug, array( 'apply', 'inscripcion' ), true ) ? 'apply' : 'page_' . $slug;
		}
		return 'other';
	}
}
