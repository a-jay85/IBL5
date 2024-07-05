<?php
/**
 * 404 Section
 * 
 * slug: basketball-club/404-page
 * title: 404 Page
 * categories: basketball-club
 */

return array(
    'title'      =>__( '404 Page', 'basketball-club' ),
    'categories' => array( 'basketball-club' ),
    'content'    => '<!-- wp:group {"style":{"spacing":{"padding":{"top":"60px","bottom":"60px"}}},"backgroundColor":"background","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background-background-color has-background" style="padding-top:60px;padding-bottom:60px"><!-- wp:heading {"textAlign":"center","style":{"typography":{"fontSize":"200px"},"elements":{"link":{"color":{"text":"var:preset|color|accent"}}}},"textColor":"accent"} -->
<h2 class="wp-block-heading has-text-align-center has-accent-color has-text-color has-link-color" style="font-size:200px">404</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","fontSize":"content-heading"} -->
<p class="has-text-align-center has-content-heading-font-size"><strong>'. esc_html('Unfortunately we can’t find that page.','basketball-club') .'</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">'. esc_html('The page you are looking for doesnt exist or has been moved. Try another url or go to the site homepage.','basketball-club') .'</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->',
);