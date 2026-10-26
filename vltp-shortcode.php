<?php

function vltp_request_test_id() {
	return isset( $_REQUEST['vltp_test_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['vltp_test_id'] ) ) : '';
}

function vltp_language_prefix( $language ) {
	$lang = strtolower( explode( '_', str_replace( '-', '_', $language ) )[0] );
	return in_array( $lang, array( 'pl', 'de', 'fr', 'zh', 'ru', 'pt', 'es' ), true ) ? '/'.$lang : '';
}

add_action( 'wp_ajax_vltp_leak_results', 'vltp_leak_results' );
add_action( 'wp_ajax_nopriv_vltp_leak_results', 'vltp_leak_results' );
add_action( 'wp_ajax_vltp_network_test', 'vltp_network_test' );
add_action( 'wp_ajax_nopriv_vltp_network_test', 'vltp_network_test' );
add_action( 'wp_ajax_vltp_vpn_results', 'vltp_vpn_results' );
add_action( 'wp_ajax_nopriv_vltp_vpn_results', 'vltp_vpn_results' );
add_action( 'wp_ajax_vltp_register_test', 'vltp_register_test' );
add_action( 'wp_ajax_nopriv_vltp_register_test', 'vltp_register_test' );

function vltp_result_token_key( $test_id ) {
	return 'vltp_result_token_'.$test_id;
}

function vltp_validate_result_token( $test_id ) {
	$token = isset( $_POST['vltp_result_token'] ) ? sanitize_text_field( wp_unslash( $_POST['vltp_result_token'] ) ) : '';
	$data = get_transient( vltp_result_token_key( $test_id ) );
	if ( !is_array( $data ) || !isset( $data['token'] ) || !is_string( $data['token'] ) || !hash_equals( $data['token'], $token ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid test request.', 'vpn-leaks-test' ) ), 403 );
	}
}

function vltp_complete_result_token( $test_id ) {
	delete_transient( vltp_result_token_key( $test_id ) );
}

function vltp_register_test() {
	$test_id = vltp_request_test_id();
	$vltp_id = isset( $_POST['vltp_id'] ) ? absint( $_POST['vltp_id'] ) : 0;
	if ( !$test_id || !$vltp_id || !check_ajax_referer( 'vltp_register_test_'.$vltp_id, 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid test request.', 'vpn-leaks-test' ) ), 403 );
	}

	global $wpdb;
	$table = $wpdb->prefix.'vltp';
	$exists = $wpdb->get_var( $wpdb->prepare( "SELECT vltp_id FROM $table WHERE vltp_id = %d", $vltp_id ) );
	if ( !$exists ) {
		wp_send_json_error( array( 'message' => __( 'Invalid test request.', 'vpn-leaks-test' ) ), 403 );
	}

	$token = wp_generate_password( 32, false, false );
	set_transient( vltp_result_token_key( $test_id ), array( 'token' => $token, 'vltp_id' => $vltp_id ), 15 * MINUTE_IN_SECONDS );
	wp_send_json_success( array( 'token' => $token ) );
}

function vltp_leak_results() {
	$test_id = vltp_request_test_id();
	$type = isset( $_POST['test_type'] ) ? sanitize_text_field( wp_unslash( $_POST['test_type'] ) ) : '';
	$input = isset( $_POST['records'] ) && is_string( $_POST['records'] ) && strlen( $_POST['records'] ) <= 131072 ? json_decode( wp_unslash( $_POST['records'] ), true ) : null;
	if ( !$test_id || !in_array( $type, array( 'dns', 'webrtc', 'email', 'torrent' ), true ) || !is_array( $input ) || !$input || count( $input ) > 500 ) {
		wp_send_json_error();
	}
	vltp_validate_result_token( $test_id );

	$records = array();
	foreach ( $input as $record ) {
		if ( !is_array( $record ) || !isset( $record['type'] ) || !is_string( $record['type'] ) || $record['type'] == 'error' ) {
			wp_send_json_error();
		}
		$clean = array();
		foreach ( array( 'type', 'ip', 'country', 'country_name', 'city_name', 'region_name', 'asn' ) as $field ) {
			if ( isset( $record[$field] ) && is_scalar( $record[$field] ) ) {
				$clean[$field] = sanitize_text_field( (string) $record[$field] );
			}
		}
		$records[] = $clean;
	}
	set_transient( 'vltp_'.$type.'_'.$test_id, $records, DAY_IN_SECONDS );
	vltp_complete_result_token( $test_id );
	wp_send_json_success();
}

function vltp_vpn_results() {
	$test_id = vltp_request_test_id();
	$input = isset( $_POST['records'] ) && is_string( $_POST['records'] ) && strlen( $_POST['records'] ) <= 200000 ? json_decode( wp_unslash( $_POST['records'] ), true ) : null;
	if ( !$test_id || !is_array( $input ) || !$input || count( $input ) > 100 ) {
		wp_send_json_error();
	}
	vltp_validate_result_token( $test_id );

	$records = array();
	$conclusion = false;
	foreach ( $input as $record ) {
		if ( !is_array( $record ) || !isset( $record['type'] ) || !is_string( $record['type'] ) || $record['type'] == 'error' ) {
			wp_send_json_error();
		}

		if ( $record['type'] == 'ip' ) {
			if ( !isset( $record['ip'] ) || !is_string( $record['ip'] ) || !filter_var( $record['ip'], FILTER_VALIDATE_IP ) ) {
				wp_send_json_error();
			}

			$records[] = array(
				'type' => 'ip',
				'ip' => sanitize_text_field( $record['ip'] ),
				'country' => isset( $record['country'] ) && is_scalar( $record['country'] ) ? sanitize_text_field( (string) $record['country'] ) : '',
				'country_name' => isset( $record['country_name'] ) && is_scalar( $record['country_name'] ) ? sanitize_text_field( (string) $record['country_name'] ) : '',
				'city_name' => isset( $record['city_name'] ) && is_scalar( $record['city_name'] ) ? sanitize_text_field( (string) $record['city_name'] ) : '',
				'region_name' => isset( $record['region_name'] ) && is_scalar( $record['region_name'] ) ? sanitize_text_field( (string) $record['region_name'] ) : '',
				'asn' => isset( $record['asn'] ) && is_scalar( $record['asn'] ) ? sanitize_text_field( (string) $record['asn'] ) : ''
			);
			continue;
		}

		if ( !isset( $record['text'] ) || !is_string( $record['text'] ) ) {
			wp_send_json_error();
		}

		if ( $record['type'] == 'conclusion' ) {
			$conclusion = true;
		}
		$records[] = array(
			'type' => sanitize_text_field( substr( $record['type'], 0, 64 ) ),
			'label' => isset( $record['label'] ) && is_string( $record['label'] ) ? sanitize_text_field( substr( $record['label'], 0, 256 ) ) : '',
			'text' => sanitize_text_field( substr( $record['text'], 0, 4096 ) )
		);
	}
	if ( !$conclusion ) {
		wp_send_json_error();
	}
	set_transient( 'vltp_vpn_'.$test_id, $records, DAY_IN_SECONDS );
	vltp_complete_result_token( $test_id );
	wp_send_json_success();
}

function vltp_network_test() {
	$test_id = vltp_request_test_id();
	$type = isset( $_POST['test_type'] ) ? sanitize_text_field( wp_unslash( $_POST['test_type'] ) ) : '';
	$input = isset( $_POST['records'] ) && is_string( $_POST['records'] ) && strlen( $_POST['records'] ) <= 65536 ? json_decode( wp_unslash( $_POST['records'] ), true ) : null;

	if ( !$test_id || !in_array( $type, array( 'openport', 'blacklist' ), true ) || !is_array( $input ) || !$input || count( $input ) > 256 ) {
		wp_send_json_error();
	}
	vltp_validate_result_token( $test_id );
	$records = array();
	foreach ( $input as $record ) {
		if ( !is_array( $record ) || !isset( $record['type'] ) || !is_string( $record['type'] ) || !in_array( $record['type'], array( 'ip', $type, 'conclusion' ), true ) ) {
			wp_send_json_error();
		}
		$records[] = array(
			'type' => $record['type'],
			'ip' => isset( $record['ip'] ) && is_string( $record['ip'] ) ? sanitize_text_field( $record['ip'] ) : '',
			'country' => isset( $record['country'] ) && is_scalar( $record['country'] ) ? sanitize_text_field( (string) $record['country'] ) : '',
			'country_name' => isset( $record['country_name'] ) && is_scalar( $record['country_name'] ) ? sanitize_text_field( (string) $record['country_name'] ) : '',
			'city_name' => isset( $record['city_name'] ) && is_scalar( $record['city_name'] ) ? sanitize_text_field( (string) $record['city_name'] ) : '',
			'region_name' => isset( $record['region_name'] ) && is_scalar( $record['region_name'] ) ? sanitize_text_field( (string) $record['region_name'] ) : '',
			'asn' => isset( $record['asn'] ) && is_scalar( $record['asn'] ) ? sanitize_text_field( (string) $record['asn'] ) : '',
			'port' => isset( $record['port'] ) ? intval( $record['port'] ) : 0,
			'opened' => !empty( $record['opened'] ) ? 1 : 0,
			'blacklist' => isset( $record['blacklist'] ) && is_string( $record['blacklist'] ) ? sanitize_text_field( $record['blacklist'] ) : '',
			'listed' => !empty( $record['listed'] ) ? 1 : 0
		);
	}

	set_transient( 'vltp_'.$type.'_'.$test_id, $records, DAY_IN_SECONDS );
	vltp_complete_result_token( $test_id );
	wp_send_json_success();
}

/**
 * Process the [vltp] shortcode.
 *
 * @param array $attrs {
 *     Shortcode attributes. 
 *
 *     @type int $id  The VPN test ID
 * }
 *
 * @return string Content with shortcode parsed
*/
function vltp_shortcode($attrs) {

	global $wpdb;

	extract( shortcode_atts( array(
		'id' => 1,
	), $attrs ) );

	if ( !$id ) {
		return '';
	}	

	$id = intval( $id );
	
	// Retrives the Test configuration
	$row = $wpdb->get_row( 'SELECT * FROM '.$wpdb->prefix.'vltp WHERE vltp_id='.$id, ARRAY_A );
	
	if ( !$row ) {
		return '';
	}
	
	if ( !in_array( $row['vltp_type'], array( 'dns', 'email', 'webrtc', 'torrent', 'openport', 'blacklist', 'vpn' ) ) ) {
		return '';
	}
	
	$content = '';	
	
	// 
	$options = unserialize( stripslashes( $row['vltp_options'] ), array( 'allowed_classes' => false ) );
	if ( !is_array( $options ) ) {
		$options = array();
	}
	
	// Extract the Test configuration from the database table `vltp`
	// For fields description see file vltp_admin_page.php, function vltp_admin_page() 
	$row['vltp_progress_image'] = isset( $options['vltp_progress_image'] ) ? stripslashes( $options['vltp_progress_image'] ) : '';
	$row['vltp_start'] = isset( $options['vltp_start'] ) ? stripslashes( $options['vltp_start'] ) : '';
	$row['vltp_result'] = isset( $options['vltp_result'] ) ? stripslashes( $options['vltp_result'] ) : '';
	$client_ip_row_default = '<div>%ip <img src="%flag" /> %city_name %region_name %country_name %asn</div>';
	$row['vltp_client_ip'] = isset( $options['vltp_client_ip'] ) ? stripslashes( $options['vltp_client_ip'] ) : '<div>Your IP:</div>';
	$row['vltp_client_ip_row'] = isset( $options['vltp_client_ip_row'] ) ? stripslashes( $options['vltp_client_ip_row'] ) : $client_ip_row_default;
	if ( $row['vltp_client_ip'] == '<div>You use the following IPs:</div><div>%client_ipv4</div><div>%client_ipv6</div>' || $row['vltp_client_ip'] == '<div>Your IP:</div><div>%client_ip</div>' ) {
		$row['vltp_client_ip'] = '<div>Your IP:</div>';
	}
	$row['vltp_conclusion'] = isset( $options['vltp_conclusion'] ) ? stripslashes( $options['vltp_conclusion'] ) : '';
	$row['vltp_language'] = isset( $options['vltp_language'] ) && $options['vltp_language'] == 'wordpress' ? vltp_language_prefix( get_locale() ) : '';
	
	wp_enqueue_style( 'vltp-css' );
	wp_enqueue_script( 'vltp-js' );
	if ( $row['vltp_type'] == 'vpn' ) {
		wp_enqueue_script( 'vltp-vpn-js' );
	}
	
	$image = '';
	if ( $row['vltp_progress_image'] ) {
		$arr = wp_get_attachment_image_src( intval( $row['vltp_progress_image'] ), 'medium', false );
		if ( isset( $arr[0] ) ) {
			$image = $arr[0];
		}
	}
	
	if ( !$image ) {
		$image = plugin_dir_url( __FILE__ ).'include/ajax-loader.gif';
	}
	
	$script_array = array( );
	$script_array['vltp_progress_image'] = $image;
	$script_array['vltp_language'] = $row['vltp_language'];

	$script_array['vltp_test_id'] = 'VLTPTESTID';
	$script_array['vltp_id'] = $id;
	$script_array['vltp_register_nonce'] = wp_create_nonce( 'vltp_register_test_'.$id );
	$script_array['vltp_url'] = add_query_arg( array( 'vltp_test_id'=>$script_array['vltp_test_id'], 'vltp_id'=>$id ), get_permalink() );
	$script_array['vltp_ajax_url'] = admin_url( 'admin-ajax.php' );
	$script_array['vltp_id_error'] = __( 'Unable to start the test. Please try again.', 'vpn-leaks-test' );
	$script_array['vltp_network_error'] = __( 'Unable to complete the test. Please try again.', 'vpn-leaks-test' );
	
	if ( $row['vltp_type'] == 'email' ) {
		$m = $script_array['vltp_test_id'].'@bash.ws';
		$script_array['vltp_email_message'] = sprintf( __('Please send an email to <a href="mailto:%s">%s</a>. The email subject and body doesn\'t matter. Do not refresh the page.', 'vpn-leaks-test') ,$m, $m );
	}

	if ( $row['vltp_type'] == 'torrent' ) {
		$m = 'https://bash.ws/torrent/'.$script_array['vltp_test_id'];
		$script_array['vltp_torrent_message'] = sprintf( __('Please download the torrent <a href="%s">%s.torrent</a>. Do not refresh the page.', 'vpn-leaks-test') ,$m, $script_array['vltp_test_id'] );
	}
	
	wp_localize_script( 'vltp-js', 'vltp_settings_'.$id, $script_array );
	
	$content = '<div class="vltp-test">';
	$content.= '<div class="vltp-start" data-type="'.esc_attr( $row['vltp_type'] ).'" data-id="'.esc_attr( $id ).'">'.$row['vltp_start'].'</div>';

	$test_id = vltp_request_test_id();
	$vltp_id = isset( $_REQUEST['vltp_id'] ) ? intval( $_REQUEST['vltp_id'] ) : 0;
	
	if ( $test_id && $vltp_id == $id ) {
		$content .= vltp_test_result( $row );
	}

	$content .= '</div>';
	
	return $content;

}

function vltp_client_ip_result( $heading, $template, $data ) {
	if ( $heading === '' || $template === '' || !is_array( $data ) ) {
		return '';
	}

	$content = '';
	$shown_ips = array();
	foreach ( $data as $record ) {
		if ( !is_array( $record ) || !isset( $record['type'], $record['ip'] ) || $record['type'] != 'ip' || !is_string( $record['ip'] ) || !filter_var( $record['ip'], FILTER_VALIDATE_IP ) ) {
			continue;
		}

		if ( isset( $shown_ips[$record['ip']] ) ) {
			continue;
		}

		$shown_ips[$record['ip']] = true;
		$country = isset( $record['country'] ) && is_string( $record['country'] ) ? strtolower( $record['country'] ) : '';
		$values = array(
			'%ip' => esc_html( $record['ip'] ),
			'%country_code' => isset( $record['country'] ) && is_scalar( $record['country'] ) ? esc_html( strtoupper( $record['country'] ) ) : '',
			'%country_name' => isset( $record['country_name'] ) && is_scalar( $record['country_name'] ) ? esc_html( $record['country_name'] ) : '',
			'%city_name' => isset( $record['city_name'] ) && is_scalar( $record['city_name'] ) ? esc_html( $record['city_name'] ) : '',
			'%region_name' => isset( $record['region_name'] ) && is_scalar( $record['region_name'] ) ? esc_html( $record['region_name'] ) : '',
			'%asn' => isset( $record['asn'] ) && is_scalar( $record['asn'] ) ? esc_html( $record['asn'] ) : '',
			'%flag' => strlen( $country ) === 2 && ctype_alpha( $country ) ? esc_url( plugin_dir_url( __FILE__ ).'include/flags/'.$country.'.png' ) : ''
		);
		$content .= vltp_result_patterns( $template, $values );
	}

	return $content === '' ? '' : $heading.$content;
}

/**
 * Receives and displays the results of testing from 3rd party service bash.ws
 * 
 * @param array $row {
 *     The VPN test configuration
 *
 *     @type string $vltp_type        VPN test type
 *     @type string $vltp_result      Line by line HTML to format the VPN test result
 *     @type string $vltp_conclusion  Line by line HTML to format the VPN test conclusion
 * }
 *
 * @return string The result of testing
*/
function vltp_result_patterns( $template, $values ) {
	$patterns = array_fill_keys( array( '%ip', '%country_code', '%country_name', '%city_name', '%region_name', '%port', '%blacklist', '%status', '%asn', '%flag', '%label', '%text' ), '' );
	return strtr( $template, array_merge( $patterns, $values ) );
}

function vltp_network_result( $row, $test_id ) {
	$records = get_transient( 'vltp_'.$row['vltp_type'].'_'.$test_id );
	if ( !is_array( $records ) ) {
		return '<div class="vltp-results">'.__( 'The Test failed, please try again...', 'vpn-leaks-test' ).'</div>';
	}

	$content = vltp_client_ip_result( $row['vltp_client_ip'], $row['vltp_client_ip_row'], $records );
	$content .= '<div class="vltp-results">';
	$conclusion = '';
	foreach ( $records as $record ) {
		if ( $record['type'] == 'conclusion' ) {
			$conclusion = isset( $record['ip'] ) ? $record['ip'] : '';
			continue;
		}
		if ( $record['type'] != $row['vltp_type'] ) {
			continue;
		}

		$port = isset( $record['port'] ) ? intval( $record['port'] ) : '';
		$blacklist = isset( $record['blacklist'] ) ? $record['blacklist'] : '';
		if ( $row['vltp_type'] == 'openport' ) {
			$status = !empty( $record['opened'] ) ? __( 'Open', 'vpn-leaks-test' ) : __( 'Closed', 'vpn-leaks-test' );
		}
		else {
			$status = !empty( $record['listed'] ) ? __( 'Listed', 'vpn-leaks-test' ) : __( 'Not listed', 'vpn-leaks-test' );
		}
		$replace = array( '%port'=>esc_html( $port ), '%blacklist'=>esc_html( $blacklist ), '%status'=>esc_html( $status ) );
		$content .= vltp_result_patterns( $row['vltp_result'], $replace );
	}

	$content .= '</div><div class="vltp-conclusion">';
	$content .= str_replace( '%text', esc_html( $conclusion ), $row['vltp_conclusion'] );
	return $content.'</div>';
}

function vltp_vpn_result( $row, $test_id ) {
	$records = get_transient( 'vltp_vpn_'.$test_id );
	if ( !is_array( $records ) ) {
		return '<div class="vltp-results">'.__( 'The Test failed, please try again...', 'vpn-leaks-test' ).'</div>';
	}

	$content = vltp_client_ip_result( $row['vltp_client_ip'], $row['vltp_client_ip_row'], $records );
	$content .= '<div class="vltp-results">';
	$conclusion = '';
	foreach ( $records as $record ) {
		if ( $record['type'] == 'ip' ) {
			continue;
		}
		if ( $record['type'] == 'conclusion' || $record['type'] == 'info' ) {
			$conclusion .= str_replace( '%text', esc_html( $record['text'] ), $row['vltp_conclusion'] );
		}
		else if ( $record['type'] == 'group' ) {
			$content .= '<div class="vltp-title">'.esc_html( $record['text'] ).'</div>';
		}
		else {
			$replace = array( '%label'=>esc_html( $record['label'] ), '%text'=>esc_html( $record['text'] ) );
			$content .= vltp_result_patterns( $row['vltp_result'], $replace );
		}
	}
	return $content.'</div><div class="vltp-conclusion">'.$conclusion.'</div>';
}

function vltp_test_result( $row ) {
	$content = '';
	
	$test_id = vltp_request_test_id();

	if ( !$test_id ) {
		return '';
	}

	if ( $row['vltp_type'] == 'openport' || $row['vltp_type'] == 'blacklist' ) {
		return vltp_network_result( $row, $test_id );
	}
	if ( $row['vltp_type'] == 'vpn' ) {
		return vltp_vpn_result( $row, $test_id );
	}
	if ( !in_array( $row['vltp_type'], array( 'dns', 'email', 'webrtc', 'torrent' ), true ) ) {
		return '';
	}
	$results = get_transient( 'vltp_'.$row['vltp_type'].'_'.$test_id );

	if ( $results ) {
		$content .= vltp_client_ip_result( $row['vltp_client_ip'], $row['vltp_client_ip_row'], $results );

		$total  = 0;
		foreach ($results as $k=>$v) {
		
			if ( !isset( $v['type'] ) ) {
				continue;
			}
			
			if ( $row['vltp_type'] == 'dns' ) {
				if ( $v['type'] != 'dns' ) {
					continue;
				}
			}
			else if ($row['vltp_type'] == 'email') {
				if ($v['type'] != 'mail') {
					continue;
				}
			}
			else if ($row['vltp_type'] == 'webrtc') {
				if ($v['type'] != 'webrtc') {
					continue;
				}
			}
			else if ($row['vltp_type'] == 'torrent') {
				if ($v['type'] != 'torrent') {
					continue;
				}
			}
			$total ++;
		}

		$content .= '<div class="vltp-title">';

		if ( $row['vltp_type'] == 'dns' ) {
			$content .= sprintf( _n('You use %d DNS server', 'You use %d DNS servers', $total, 'vpn-leaks-test'), $total);
		}
		else if ( $row['vltp_type'] == 'email' ) {
			$content .= sprintf( _n('Your email contains %d IP', 'Your email contains %d IPs', $total, 'vpn-leaks-test'), $total );
		}
		else if ( $row['vltp_type'] == 'webrtc' ) {
			$content .= sprintf( _n( 'WebRTC is able to see %d IP', 'WebRTC is able to see %d IPs', $total, 'vpn-leaks-test' ), $total );
		}
		else if ( $row['vltp_type'] == 'torrent' ) {
			$content .= sprintf( _n('Your torrent application shares %d IP', 'Your torrent application shares %d IPs', $total, 'vpn-leaks-test'), $total );
		}

		$content .= '</div>';
		$content .= '<div class="vltp-results">';



		foreach ( $results as $k=>$v ) {
		
			if ( !isset( $v['type'], $v['ip'] ) ) {
				continue;
			}

			if ( $row['vltp_type'] == 'dns' ) {
				if ( $v['type'] != 'dns' ) {
					continue;
				}
			}
			else if ( $row['vltp_type'] == 'email' ) {
				if ( $v['type'] != 'mail' ) {
					continue;
				}
			}
			else if ( $row['vltp_type'] == 'webrtc' ) {
				if ( $v['type'] != 'webrtc' ) {
					continue;
				}
			}
			else if ( $row['vltp_type'] == 'torrent' ) {
				if ( $v['type'] != 'torrent' ) {
					continue;
				}
			}
			
			$r = array();
			$country_code = isset( $v['country'] ) ? strtolower( $v['country'] ) : '';

			$r['%country_code'] = esc_attr( strtoupper( $country_code ) );
			$r['%country_name'] = esc_attr( isset( $v['country_name'] ) ? $v['country_name'] : '' );
			$r['%city_name'] = esc_attr( isset( $v['city_name'] ) ? $v['city_name'] : '' );
			$r['%region_name'] = esc_attr( isset( $v['region_name'] ) ? $v['region_name'] : '' );
			$r['%asn'] = esc_attr( isset( $v['asn'] ) ? $v['asn'] : '' );
			$r['%ip'] = esc_attr( $v['ip'] );
			$r['%flag'] = $country_code ? plugin_dir_url( __FILE__ ).'include/flags/'.$country_code.'.png' : '';
			
			$content .=  vltp_result_patterns( $row['vltp_result'], $r );


		}
		$content .= '</div>';
		$content .= '<div class="vltp-conclusion">';



		foreach ( $results as $k=>$v ) {
		
			if ( !isset( $v['type'] ) ) {
				continue;
			}
			
			if ( $v['type'] != 'conclusion' ) {
				continue;
			}
			
			$r = array();
			$r['%text'] = esc_attr( $v['ip'] );
			$content .= str_replace( array_keys($r), array_values($r),  $row['vltp_conclusion'] );
		}
		$content .= '</div>';
	}
	else {
		$content.= '<div class="vltp-results">'.__( 'The Test failed, please try again...', 'vpn-leaks-test' ).'</div>';
	}

	return $content;	
}
