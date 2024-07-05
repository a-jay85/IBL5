<?php
/**
 * Customizer
 * 
 * @package WordPress
 * @subpackage basketball-club
 * @since basketball-club 1.0
 */

/**
 * Add postMessage support for site title and description for the Theme Customizer.
 *
 * @param WP_Customize_Manager $wp_customize Theme Customizer object.
 */
function basketball_club_customize_register( $wp_customize ) {
	$wp_customize->add_section( new Basketball_Club_Upsell_Section($wp_customize,'upsell_section',array(
		'title'            => __( 'Basketball Club Pro', 'basketball-club' ),
		'button_text'      => __( 'Upgrade Pro', 'basketball-club' ),
		'url'              => 'https://www.wpradiant.net/products/basketball-club-wordpress-theme/',
		'priority'         => 0,
	)));
}
add_action( 'customize_register', 'basketball_club_customize_register' );

/**
 * Enqueue script for custom customize control.
 */
function basketball_club_custom_control_scripts() {
	wp_enqueue_script( 'basketball-club-custom-controls-js', get_template_directory_uri() . '/assets/js/custom-controls.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-sortable' ), '1.0', true );
}
add_action( 'customize_controls_enqueue_scripts', 'basketball_club_custom_control_scripts' );