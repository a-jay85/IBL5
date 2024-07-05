<?php
/**
 * Block Styles
 *
 * @link https://developer.wordpress.org/reference/functions/register_block_style/
 *
 * @package WordPress
 * @subpackage basketball-club
 * @since basketball-club 1.0
 */

if ( function_exists( 'register_block_style' ) ) {
	/**
	 * Register block styles.
	 *
	 * @since basketball-club 1.0
	 *
	 * @return void
	 */
	function basketball_club_register_block_styles() {
		
		// Image: Borders.
		register_block_style(
			'core/image',
			array(
				'name'  => 'basketball-club-border',
				'label' => esc_html__( 'Borders', 'basketball-club' ),
			)
		);

		
	}
	add_action( 'init', 'basketball_club_register_block_styles' );
}