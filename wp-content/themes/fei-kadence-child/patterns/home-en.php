<?php
/**
 * Pattern: FEI Home (English) — full page, copy verbatim from fei.edu/ (2026-10-12).
 * Insert on the "Home" page (Template: Full width / no title). Hero image: upload
 * Bundled campus banner is used until official Astro photography is uploaded.
 */
defined( 'ABSPATH' ) || exit;

$hero = esc_url( function_exists( 'fei_banner_url' ) ? fei_banner_url( 'campus' ) : '' );

return array(
	'title'       => 'FEI Home — English',
	'description' => 'Hero, program tiles, FEI Advantage, admissions steps, financial aid, support quote, FAQ, final CTA.',
	'keywords'    => array( 'fei', 'home' ),
	'blockTypes'  => array( 'core/post-content' ),
	'content'     => <<<HTML
<!-- wp:cover {"url":"{$hero}","dimRatio":70,"isUserOverlayColor":true,"minHeight":88,"minHeightUnit":"vh","contentPosition":"center left","align":"full","className":"fei-hero"} -->
<div class="wp-block-cover alignfull has-custom-content-position is-position-center-left fei-hero" style="min-height:88vh"><img class="wp-block-cover__image-background" alt="" src="{$hero}" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-background-dim-70 has-background-dim" style="background-color:#112550"></span><div class="wp-block-cover__inner-container">
<!-- wp:group {"layout":{"type":"constrained","contentSize":"1200px"}} --><div class="wp-block-group">
<!-- wp:paragraph {"className":"fei-kicker"} --><p class="fei-kicker" style="color:#ffb81d">Miami, Florida · Career Education</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">You will never be a <em>number.</em></h1><!-- /wp:heading -->
<!-- wp:paragraph {"fontSize":"large"} --><p class="has-large-font-size">At Florida Education Institute we know our students by name. Real instructors, hands-on training, and the personal attention that gets you across the finish line, and into a career.</p><!-- /wp:paragraph -->
<!-- wp:buttons --><div class="wp-block-buttons">
<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/apply/" data-apply>Start today</a></div><!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/programs/" style="color:#fff;border-color:#fff">Explore programs</a></div><!-- /wp:button -->
</div><!-- /wp:buttons -->
<!-- wp:paragraph --><p><strong>11</strong> career programs &nbsp;·&nbsp; <strong>1:1</strong> student attention &nbsp;·&nbsp; <strong>Aid</strong> for those who qualify</p><!-- /wp:paragraph -->
<!-- wp:paragraph {"fontSize":"small"} --><p class="has-small-font-size"><span style="color:#ffb81d">★★★★★</span> <em>"They actually knew me."</em></p><!-- /wp:paragraph -->
</div><!-- /wp:group --></div></div>
<!-- /wp:cover -->

<!-- wp:html --><div class="fei-marquee" aria-hidden="true"><div class="fei-marquee__track"><span>Medical Assistant</span><span class="sep">✦</span><span>Pharmacy Technician</span><span class="sep">✦</span><span>Culinary Arts</span><span class="sep">✦</span><span>HVAC / R</span><span class="sep">✦</span><span>Medical Billing &amp; Coding</span><span class="sep">✦</span><span>Pastry &amp; Baking Arts</span><span class="sep">✦</span><span>Business Management</span><span class="sep">✦</span><span>Medical Office Administrator</span><span class="sep">✦</span><span>Medical Assistant</span><span class="sep">✦</span><span>Pharmacy Technician</span><span class="sep">✦</span><span>Culinary Arts</span><span class="sep">✦</span><span>HVAC / R</span><span class="sep">✦</span><span>Medical Billing &amp; Coding</span><span class="sep">✦</span><span>Pastry &amp; Baking Arts</span><span class="sep">✦</span><span>Business Management</span><span class="sep">✦</span><span>Medical Office Administrator</span><span class="sep">✦</span></div></div><!-- /wp:html -->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"5rem"}}},"layout":{"type":"constrained","contentSize":"1200px"}} --><div class="wp-block-group alignfull" style="padding-top:5rem;padding-bottom:5rem" id="programs">
<!-- wp:paragraph {"className":"fei-kicker"} --><p class="fei-kicker">Programs</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2 class="wp-block-heading">Pick the career.<br>We will teach you the skills.</h2><!-- /wp:heading -->
<!-- wp:shortcode -->[fei_programs_grid style="cards"]<!-- /wp:shortcode -->
<!-- wp:paragraph --><p><a href="/programs/">View all programs →</a></p><!-- /wp:paragraph -->
</div><!-- /wp:group -->

<!-- wp:group {"align":"full","className":"fei-dark","style":{"spacing":{"padding":{"top":"5rem","bottom":"5rem"}}},"layout":{"type":"constrained","contentSize":"1200px"}} --><div class="wp-block-group alignfull fei-dark" style="padding-top:5rem;padding-bottom:5rem" id="advantage">
<!-- wp:columns --><div class="wp-block-columns">
<!-- wp:column {"width":"40%"} --><div class="wp-block-column" style="flex-basis:40%">
<!-- wp:paragraph {"className":"fei-kicker"} --><p class="fei-kicker">The FEI Advantage</p><!-- /wp:paragraph -->
<!-- wp:heading {"style":{"color":{"text":"#ffffff"}}} --><h2 class="wp-block-heading has-text-color" style="color:#ffffff">More attention.<br>Better outcomes.</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>The FEI difference is not a slogan, it is a class size. Our instructors and staff know your name, your goals, and are ready to help you along the way.</p><!-- /wp:paragraph -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/apply/" data-apply>Meet with an advisor</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</div><!-- /wp:column -->
<!-- wp:column {"width":"60%"} --><div class="wp-block-column" style="flex-basis:60%">
<!-- wp:columns --><div class="wp-block-columns">
<!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Personal attention</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Small classes and real relationships. You will never get lost in a lecture hall.</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">10 Success Habits</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Technical training paired with the professional habits.</p><!-- /wp:paragraph --></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Career education</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Every program is built around the skills employers in South Florida favor.</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Employer connections</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Career services, an employer job board, and connections that open doors.</p><!-- /wp:paragraph --></div><!-- /wp:column -->
</div><!-- /wp:columns -->
</div><!-- /wp:column -->
</div><!-- /wp:columns -->
</div><!-- /wp:group -->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"5rem"}}},"layout":{"type":"constrained","contentSize":"1200px"}} --><div class="wp-block-group alignfull" style="padding-top:5rem;padding-bottom:5rem" id="admissions">
<!-- wp:paragraph {"align":"center","className":"fei-kicker"} --><p class="has-text-align-center fei-kicker">Admissions</p><!-- /wp:paragraph -->
<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">Getting started is simple.</h2><!-- /wp:heading -->
<!-- wp:columns --><div class="wp-block-columns">
<!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">1. Reach out</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Call, apply online, or visit the campus. We will answer your questions.</p><!-- /wp:paragraph --></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">2. Meet &amp; tour</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Sit down with an advisor, see the classrooms and labs, and pick your program.</p><!-- /wp:paragraph --></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">3. Plan your aid</h3><!-- /wp:heading --><!-- wp:paragraph --><p>We will help you understand financial aid, scholarships, and what you qualify for.</p><!-- /wp:paragraph --></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">4. Enroll</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Make the choice to change your life with education. Enroll that same day!</p><!-- /wp:paragraph --></div><!-- /wp:column -->
</div><!-- /wp:columns -->
</div><!-- /wp:group -->

<!-- wp:group {"align":"full","style":{"color":{"background":"#0072ce","text":"#ffffff"},"spacing":{"padding":{"top":"5rem","bottom":"5rem"}}},"layout":{"type":"constrained","contentSize":"1200px"}} --><div class="wp-block-group alignfull has-text-color has-background" style="color:#ffffff;background-color:#0072ce;padding-top:5rem;padding-bottom:5rem" id="aid">
<!-- wp:paragraph {"className":"fei-kicker","style":{"color":{"text":"#ffb81d"}}} --><p class="fei-kicker has-text-color" style="color:#ffb81d">Financial Aid</p><!-- /wp:paragraph -->
<!-- wp:heading {"style":{"color":{"text":"#ffffff"}}} --><h2 class="wp-block-heading has-text-color" style="color:#ffffff">A new career<br>is possible!</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Financial aid is available to those who qualify. From federal aid to scholarships, our team helps you build a plan that makes attending school a reality.</p><!-- /wp:paragraph -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/financial-aid/">Find out how</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</div><!-- /wp:group -->

<!-- wp:group {"align":"full","className":"fei-tint","style":{"spacing":{"padding":{"top":"5rem","bottom":"5rem"}}},"layout":{"type":"constrained","contentSize":"1200px"}} --><div class="wp-block-group alignfull fei-tint" style="padding-top:5rem;padding-bottom:5rem" id="support">
<!-- wp:columns --><div class="wp-block-columns">
<!-- wp:column --><div class="wp-block-column"><!-- wp:quote --><blockquote class="wp-block-quote"><p>“At FEI, I was not a seat in a room. My instructors knew my name, pushed me when I needed it, and helped me land the job I have today.”</p><cite>A recent FEI graduate — Healthcare program</cite></blockquote><!-- /wp:quote --></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Perpetual Support</h3><!-- /wp:heading --><!-- wp:list --><ul class="wp-block-list"><li>Student Service department &amp; Student Portal</li><li>Career services &amp; job placement support</li><li>Employer connections &amp; job board</li><li>Expansive Alumni network</li></ul><!-- /wp:list --></div><!-- /wp:column -->
</div><!-- /wp:columns -->
</div><!-- /wp:group -->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"5rem"}}},"layout":{"type":"constrained","contentSize":"1200px"}} --><div class="wp-block-group alignfull" style="padding-top:5rem;padding-bottom:5rem" id="faq">
<!-- wp:paragraph {"className":"fei-kicker"} --><p class="fei-kicker">FAQ</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2 class="wp-block-heading">Questions before<br>you call?</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Here are the answers we get asked most. Anything else, just call us.</p><!-- /wp:paragraph -->
<!-- wp:shortcode -->[fei_faq]
Is FEI accredited? || Yes. FEI is accredited by the Council on Occupational Education (COE). Some programs, like Pharmacy Technician and HVAC/R, also prepare you for industry-specific certifications or registrations.
Is it easy to apply for financial aid? || Yes it is easy to apply for Financial aid! We have a team dedicated to guiding you step by step through the process. Financial aid is available to those who qualify, including scholarships and payment plans. Our Financial Aid department can walk you through a personalized plan before you commit to anything.
How long does it take to complete a program? || Between 10 to 18 months depending on the program you choose.
Do you help with job placement after graduation? || Yes, of course! Our Career Services team supports graduates with resume help, interview preparation, and connections to employers hiring in Miami.
Can I complete my program online? || Depending on the program selected online may be an option. We recommend meeting with an admissions advisor so they can assist you in picking a program that you enjoy and has a learning format that works for you.
What is it like to be a student at FEI? || Amazing! Your professors and FEI's student service department will be there along the way to help get you to graduation and into your new career. You will build camaraderie with fellow classmates as you all strive to achieve your personal goals in the same career field.
[/fei_faq]<!-- /wp:shortcode -->
</div><!-- /wp:group -->

<!-- wp:group {"align":"full","style":{"color":{"background":"#1a202c","text":"#ffffff"},"spacing":{"padding":{"top":"6rem","bottom":"6rem"}}},"layout":{"type":"constrained","contentSize":"800px"}} --><div class="wp-block-group alignfull has-text-color has-background" style="color:#ffffff;background-color:#1a202c;padding-top:6rem;padding-bottom:6rem" id="apply">
<!-- wp:paragraph {"align":"center","className":"fei-kicker","style":{"color":{"text":"#ffb81d"}}} --><p class="has-text-align-center fei-kicker has-text-color" style="color:#ffb81d">Chase your dreams!</p><!-- /wp:paragraph -->
<!-- wp:heading {"textAlign":"center","style":{"color":{"text":"#ffffff"}}} --><h2 class="wp-block-heading has-text-align-center has-text-color" style="color:#ffffff">Your new<br>career awaits!</h2><!-- /wp:heading -->
<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Contact us today to discuss programs, start dates, and financial aid.</p><!-- /wp:paragraph -->
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons">
<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="tel:+17866658310">Call 786-665-8310</a></div><!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/apply/" style="color:#fff;border-color:#fff" data-apply>Schedule a call</a></div><!-- /wp:button -->
</div><!-- /wp:buttons -->
</div><!-- /wp:group -->
HTML,
);
