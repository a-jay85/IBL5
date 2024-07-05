<?php
/**
 * Basketball Club functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package basketball-club
 * @since basketball-club 1.0
 */

if ( ! function_exists( 'basketball_club_support' ) ) :

	/**
	 * Sets up theme defaults and registers support for various WordPress features.
	 *
	 * @since basketball-club 1.0
	 *
	 * @return void
	 */
	function basketball_club_support() {
		// Add default posts and comments RSS feed links to head.
		add_theme_support( 'automatic-feed-links' );

		// Add support for block styles.
		add_theme_support( 'wp-block-styles' );

		add_theme_support( 'align-wide' );

		// Enqueue editor styles.
		add_editor_style( 'style.css' );

		add_theme_support( 'responsive-embeds' );

   		add_theme_support( 'woocommerce' );
		
		// Add support for experimental link color control.
		add_theme_support( 'experimental-link-color' );
	}

endif;

add_action( 'after_setup_theme', 'basketball_club_support' );

if ( ! function_exists( 'basketball_club_styles' ) ) :

	/**
	 * Enqueue styles.
	 *
	 * @since basketball-club 1.0
	 *
	 * @return void
	 */
	function basketball_club_styles() {

		// Register theme stylesheet.
		wp_register_style(
			'basketball-club-style',
			get_template_directory_uri() . '/style.css',
			array(),
			wp_get_theme()->get( 'Version' )
		);

		wp_enqueue_style( 
			'basketball-club-animate-css', 
			esc_url(get_template_directory_uri()).'/assets/css/animate.css' 
		);

		// Enqueue theme stylesheet.
		wp_enqueue_style( 'basketball-club-style' );

	}

endif;

add_action( 'wp_enqueue_scripts', 'basketball_club_styles' );

/* Enqueue Wow Js */
function basketball_club_scripts() {
	wp_enqueue_script( 
		'basketball-club-wow', esc_url(get_template_directory_uri()) . '/assets/js/wow.js', 
		array('jquery') 
	);
}
add_action( 'wp_enqueue_scripts', 'basketball_club_scripts' );

// Add block patterns
require get_template_directory() . '/inc/block-pattern.php';

// Add block Style
require get_template_directory() . '/inc/block-style.php';

// Get Started
require get_template_directory() . '/get-started/getstart.php';

// Get Notice
require get_template_directory() . '/get-started/notice.php';

// Add Customizer
require get_template_directory() . '/inc/customizer.php';
