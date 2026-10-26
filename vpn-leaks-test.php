<?php

/*
Plugin Name: VPN Leaks Test Package
Plugin URI: https://bash.ws/
Description: Add VPN, DNS, WebRTC, email and torrent leak tests, an open port test and an IP blacklist test to your website.
Version: 1.0.0.22
Author: macvk
Author URI: https://github.com/macvk
License: GPLv2 or later
*/

if ( !defined( 'ABSPATH' ) ) {
	die( 'An attempt to call the vltp plugin directly...' );
}

define('VLTP_NONCE','vpn-leaks-nonce');
define('VLTP_FILE',__FILE__);

require_once dirname( __FILE__ ) . '/vltp-init.php';

if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	require_once dirname( __FILE__ ) . '/vltp-init-admin.php';
}

