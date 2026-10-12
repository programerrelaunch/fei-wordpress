<?php
/** Pattern: /apply page (English) — copy verbatim from fei.edu/apply. ES: /es/inscripcion uses the same shortcode. */
defined( 'ABSPATH' ) || exit;

return array(
	'title'      => 'FEI Apply page — English',
	'keywords'   => array( 'fei', 'apply' ),
	'blockTypes' => array( 'core/post-content' ),
	'content'    => <<<'HTML'
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"4rem","bottom":"5rem"}}},"layout":{"type":"constrained","contentSize":"1200px"}} --><div class="wp-block-group alignfull" style="padding-top:4rem;padding-bottom:5rem">
<!-- wp:columns {"verticalAlignment":"top"} --><div class="wp-block-columns are-vertically-aligned-top">
<!-- wp:column {"width":"45%"} --><div class="wp-block-column" style="flex-basis:45%">
<!-- wp:paragraph {"className":"fei-kicker"} --><p class="fei-kicker">Apply Now</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Applying is simple.</h1><!-- /wp:heading -->
<!-- wp:paragraph --><p>Applying for enrollment at Florida Education Institute was designed to be simple. Completing the application form here begins the enrollment process.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>After applying, we will contact you to set your admissions appointment with one of our Career Education Advisors. We want your education to be life-changing and meaningful to your future, so selecting a career that is right for you will be our first priority.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>We will discuss your goals and find out if Florida Education Institute is right for you. We will give you a tour of our facilities and have you speak with our staff and instructors, who are committed to personal attention and student success. We also encourage you to ask questions, so we can provide you with an education and career plan that you believe in.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><strong>Florida Education Institute</strong><br>5818 SW 8th Street, Miami, FL 33144<br><a href="tel:+17866658310">(786) 665-8310</a> · <a href="mailto:admissions@fei.edu">admissions@fei.edu</a></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"fontSize":"small"} --><p class="has-small-font-size">Please note that applying herein does not in any way constitute acceptance to Florida Education Institute.</p><!-- /wp:paragraph -->
</div><!-- /wp:column -->
<!-- wp:column {"width":"55%"} --><div class="wp-block-column" style="flex-basis:55%">
<!-- wp:group {"className":"card","layout":{"type":"constrained"}} --><div class="wp-block-group card">
<!-- wp:shortcode -->[fei_apply_form prefix="af" id="applyForm" title="Request program details"]<!-- /wp:shortcode -->
</div><!-- /wp:group -->
</div><!-- /wp:column -->
</div><!-- /wp:columns -->
</div><!-- /wp:group -->
HTML,
);
