<?php
/**
 * About Us Section
 * 
 * slug: basketball-club/about-section
 * title: About Section
 * categories: basketball-club
 */

return array(
    'title'      =>__( 'About Section', 'basketball-club' ),
    'categories' => array( 'basketball-club' ),
    'content'    => '<!-- wp:spacer {"height":"50px"} -->
<div style="height:50px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:group {"className":"product-section","layout":{"type":"constrained","contentSize":"80%"}} -->
<div class="wp-block-group product-section"><!-- wp:columns {"verticalAlignment":"center","className":"wow fadeInUp"} -->
<div class="wp-block-columns are-vertically-aligned-center wow fadeInUp"><!-- wp:column {"verticalAlignment":"center","width":"30%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:30%"><!-- wp:cover {"url":"'.esc_url(get_template_directory_uri()) .'/assets/images/aboutus.png","id":13,"dimRatio":0,"contentPosition":"top center","layout":{"type":"constrained"}} -->
<div class="wp-block-cover has-custom-content-position is-position-top-center"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><img class="wp-block-cover__image-background wp-image-13" alt="" src="'.esc_url(get_template_directory_uri()) .'/assets/images/aboutus.png" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"center","level":3,"style":{"typography":{"fontStyle":"normal","fontWeight":"600"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"fontSize":"large","fontFamily":"teko"} -->
<h3 class="wp-block-heading has-text-align-center has-teko-font-family has-large-font-size" style="margin-top:0;margin-bottom:0;font-style:normal;font-weight:600">'. esc_html('Upcoming Matches','basketball-club') .'</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"typography":{"lineHeight":"1.6"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"fontSize":"medium","fontFamily":"abel"} -->
<p class="has-text-align-center has-abel-font-family has-medium-font-size" style="margin-top:0;margin-bottom:0;line-height:1.6">'. esc_html('There are many Online educational platform for digital skills where students  variation.','basketball-club') .' </p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:cover --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}}},"className":"match-box"} -->
<div class="wp-block-columns are-vertically-aligned-center match-box" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60)"><!-- wp:column {"verticalAlignment":"center","width":"40%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:40%"><!-- wp:columns {"verticalAlignment":"center"} -->
<div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"40%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:40%"><!-- wp:group {"className":"team-logo","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"center"}} -->
<div class="wp-block-group team-logo"><!-- wp:image {"align":"center","id":23,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image aligncenter size-full"><img src="'.esc_url(get_template_directory_uri()) .'/assets/images/teamlogo1.png" alt="" class="wp-image-23"/></figure>
<!-- /wp:image -->

<!-- wp:paragraph {"style":{"color":{"text":"#959fa8"}},"fontSize":"medium","fontFamily":"abel"} -->
<p class="has-text-color has-abel-font-family has-medium-font-size" style="color:#959fa8"><strong>'. esc_html(''. esc_html('NY Yorks','basketball-club') .'','basketball-club') .'</strong></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"20%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:20%"><!-- wp:paragraph {"align":"center","style":{"color":{"text":"#959fa8"}},"fontSize":"upper-heading","fontFamily":"abel"} -->
<p class="has-text-align-center has-text-color has-abel-font-family has-upper-heading-font-size" style="color:#959fa8"><strong>'. esc_html('VS','basketball-club') .'</strong></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"40%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:40%"><!-- wp:group {"className":"team-logo","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"center"}} -->
<div class="wp-block-group team-logo"><!-- wp:image {"align":"center","id":24,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image aligncenter size-full"><img src="'.esc_url(get_template_directory_uri()) .'/assets/images/teamlogo2.png" alt="" class="wp-image-24"/></figure>
<!-- /wp:image -->

<!-- wp:paragraph {"style":{"color":{"text":"#959fa8"}},"fontSize":"medium","fontFamily":"abel"} -->
<p class="has-text-color has-abel-font-family has-medium-font-size" style="color:#959fa8"><strong>'. esc_html('NY Yorks','basketball-club') .'</strong></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"20%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:20%"><!-- wp:paragraph {"align":"center","style":{"color":{"text":"#959fa8"}},"fontSize":"upper-heading","fontFamily":"abel"} -->
<p class="has-text-align-center has-text-color has-abel-font-family has-upper-heading-font-size" style="color:#959fa8"><span class="dashicons dashicons-clock"></span>  <strong>5:00 PM</strong></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"20%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:20%"><!-- wp:paragraph {"align":"center","style":{"color":{"text":"#959fa8"}},"fontSize":"upper-heading","fontFamily":"abel"} -->
<p class="has-text-align-center has-text-color has-abel-font-family has-upper-heading-font-size" style="color:#959fa8"><span class="dashicons dashicons-location"></span>  <strong>'. esc_html('London','basketball-club') .'</strong></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"20%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:20%"><!-- wp:paragraph {"align":"center","style":{"color":{"text":"#959fa8"}},"fontSize":"upper-heading","fontFamily":"abel"} -->
<p class="has-text-align-center has-text-color has-abel-font-family has-upper-heading-font-size" style="color:#959fa8"><span class="dashicons dashicons-heart"></span>  <strong>'. esc_html('View Details','basketball-club') .'</strong></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}}},"className":"match-box"} -->
<div class="wp-block-columns are-vertically-aligned-center match-box" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60)"><!-- wp:column {"verticalAlignment":"center","width":"40%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:40%"><!-- wp:columns {"verticalAlignment":"center"} -->
<div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"40%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:40%"><!-- wp:group {"className":"team-logo","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"center"}} -->
<div class="wp-block-group team-logo"><!-- wp:image {"align":"center","id":23,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image aligncenter size-full"><img src="'.esc_url(get_template_directory_uri()) .'/assets/images/teamlogo1.png" alt="" class="wp-image-23"/></figure>
<!-- /wp:image -->

<!-- wp:paragraph {"style":{"color":{"text":"#959fa8"}},"fontSize":"medium","fontFamily":"abel"} -->
<p class="has-text-color has-abel-font-family has-medium-font-size" style="color:#959fa8"><strong>'. esc_html('NY Yorks','basketball-club') .'</strong></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"20%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:20%"><!-- wp:paragraph {"align":"center","style":{"color":{"text":"#959fa8"}},"fontSize":"upper-heading","fontFamily":"abel"} -->
<p class="has-text-align-center has-text-color has-abel-font-family has-upper-heading-font-size" style="color:#959fa8"><strong>'. esc_html('VS','basketball-club') .'</strong></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"40%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:40%"><!-- wp:group {"className":"team-logo","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"center"}} -->
<div class="wp-block-group team-logo"><!-- wp:image {"align":"center","id":24,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image aligncenter size-full"><img src="'.esc_url(get_template_directory_uri()) .'/assets/images/teamlogo2.png" alt="" class="wp-image-24"/></figure>
<!-- /wp:image -->

<!-- wp:paragraph {"style":{"color":{"text":"#959fa8"}},"fontSize":"medium","fontFamily":"abel"} -->
<p class="has-text-color has-abel-font-family has-medium-font-size" style="color:#959fa8"><strong>'. esc_html('NY Yorks','basketball-club') .'</strong></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"20%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:20%"><!-- wp:paragraph {"align":"center","style":{"color":{"text":"#959fa8"}},"fontSize":"upper-heading","fontFamily":"abel"} -->
<p class="has-text-align-center has-text-color has-abel-font-family has-upper-heading-font-size" style="color:#959fa8"><span class="dashicons dashicons-clock"></span>  <strong>5:00 PM</strong></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"20%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:20%"><!-- wp:paragraph {"align":"center","style":{"color":{"text":"#959fa8"}},"fontSize":"upper-heading","fontFamily":"abel"} -->
<p class="has-text-align-center has-text-color has-abel-font-family has-upper-heading-font-size" style="color:#959fa8"><span class="dashicons dashicons-location"></span>  <strong>'. esc_html('London','basketball-club') .'</strong></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"20%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:20%"><!-- wp:paragraph {"align":"center","style":{"color":{"text":"#959fa8"}},"fontSize":"upper-heading","fontFamily":"abel"} -->
<p class="has-text-align-center has-text-color has-abel-font-family has-upper-heading-font-size" style="color:#959fa8"><span class="dashicons dashicons-heart"></span>  <strong>'. esc_html('View Details','basketball-club') .'</strong></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}}},"className":"match-box"} -->
<div class="wp-block-columns are-vertically-aligned-center match-box" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60)"><!-- wp:column {"verticalAlignment":"center","width":"40%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:40%"><!-- wp:columns {"verticalAlignment":"center"} -->
<div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"40%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:40%"><!-- wp:group {"className":"team-logo","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"center"}} -->
<div class="wp-block-group team-logo"><!-- wp:image {"align":"center","id":23,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image aligncenter size-full"><img src="'.esc_url(get_template_directory_uri()) .'/assets/images/teamlogo1.png" alt="" class="wp-image-23"/></figure>
<!-- /wp:image -->

<!-- wp:paragraph {"style":{"color":{"text":"#959fa8"}},"fontSize":"medium","fontFamily":"abel"} -->
<p class="has-text-color has-abel-font-family has-medium-font-size" style="color:#959fa8"><strong>'. esc_html('NY Yorks','basketball-club') .'</strong></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"20%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:20%"><!-- wp:paragraph {"align":"center","style":{"color":{"text":"#959fa8"}},"fontSize":"upper-heading","fontFamily":"abel"} -->
<p class="has-text-align-center has-text-color has-abel-font-family has-upper-heading-font-size" style="color:#959fa8"><strong>'. esc_html('VS','basketball-club') .'</strong></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"40%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:40%"><!-- wp:group {"className":"team-logo","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"center"}} -->
<div class="wp-block-group team-logo"><!-- wp:image {"align":"center","id":24,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image aligncenter size-full"><img src="'.esc_url(get_template_directory_uri()) .'/assets/images/teamlogo2.png" alt="" class="wp-image-24"/></figure>
<!-- /wp:image -->

<!-- wp:paragraph {"style":{"color":{"text":"#959fa8"}},"fontSize":"medium","fontFamily":"abel"} -->
<p class="has-text-color has-abel-font-family has-medium-font-size" style="color:#959fa8"><strong>'. esc_html('NY Yorks','basketball-club') .'</strong></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"20%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:20%"><!-- wp:paragraph {"align":"center","style":{"color":{"text":"#959fa8"}},"fontSize":"upper-heading","fontFamily":"abel"} -->
<p class="has-text-align-center has-text-color has-abel-font-family has-upper-heading-font-size" style="color:#959fa8"><span class="dashicons dashicons-clock"></span>  <strong>5:00 PM</strong></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"20%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:20%"><!-- wp:paragraph {"align":"center","style":{"color":{"text":"#959fa8"}},"fontSize":"upper-heading","fontFamily":"abel"} -->
<p class="has-text-align-center has-text-color has-abel-font-family has-upper-heading-font-size" style="color:#959fa8"><span class="dashicons dashicons-location"></span>  <strong>'. esc_html('London','basketball-club') .'</strong></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"20%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:20%"><!-- wp:paragraph {"align":"center","style":{"color":{"text":"#959fa8"}},"fontSize":"upper-heading","fontFamily":"abel"} -->
<p class="has-text-align-center has-text-color has-abel-font-family has-upper-heading-font-size" style="color:#959fa8"><span class="dashicons dashicons-heart"></span>  <strong>'. esc_html('View Details','basketball-club') .'</strong></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->

<!-- wp:spacer {"height":"50px"} -->
<div style="height:50px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->',
);