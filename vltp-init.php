<?php

if ( !defined( 'ABSPATH' ) ) {
	die( 'An attempt to call the vltp plugin directly...' );
}

include dirname(__FILE__).'/vltp-shortcode.php';

add_action( 'init', 'vltp_init');

/**
 * Initialization hook
 */
function vltp_init() {
	wp_register_style( 'vltp-css', plugins_url( '/include/vltp.css', __FILE__ ), array(), '1.0.0.22', 'all' );
	wp_register_script( 'vltp-js', plugins_url( '/include/vltp.js', __FILE__ ), array('jquery'), '1.0.0.22', 'all' );
	wp_register_script( 'vltp-vpn-js', plugins_url( '/include/vltp-vpn.js', __FILE__ ), array('vltp-js'), '1.0.0.22', 'all' );
	add_shortcode('vltp', 'vltp_shortcode');
}

/**
 * Default progress image 
 */
function vltp_default_progress_image() {
	return '<img id="vltp_progress_image_preview" src="'.plugin_dir_url( __FILE__ ).'include/ajax-loader.gif" />';
}
