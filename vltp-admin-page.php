<?php

/**
 * Show the VPN leaks settings page
 *
 * @return null
 */
function vltp_admin_page() {
	if ( !current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage VPN leak tests.', 'vpn-leaks-test' ) );
	}

	$add_url = add_query_arg( array( 'page' => 'vltp-admin-page', 'action'=>'add'), admin_url( 'admin.php' ) );
	$edit_url = add_query_arg( array( 'page' => 'vltp-admin-page', 'action'=>'edit', 'id'=>'1'), admin_url( 'admin.php' ) );
	
	
?>
<div class="wrap">
	<div class="wrap">
<?php
	$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
	$test_id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
	$is_save = $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST[ VLTP_NONCE ] );
	
	if ( ( $action != 'edit' && $action != 'add' ) || $is_save ) {
?>
		<h1 class="wp-heading-inline"><?php echo __( 'VPN leaks test configuration', 'vpn-leaks-test' ) ?></h1>
		<a href="<?php echo $add_url; ?>" class="page-title-action">Add New VPN Test</a>

		<hr class="wp-header-end">
<?php
	}
?>
		<div class="vltp-admin">
<?php

	// Save the settings to the database 
	if ( $is_save ) {
		check_admin_referer( 'vltp_save_test', VLTP_NONCE );
	
		$language = isset( $_POST['vltp_language'] ) && wp_unslash( $_POST['vltp_language'] ) == 'wordpress' ? 'wordpress' : 'english';
		$type = isset( $_POST['vltp_type'] ) ? sanitize_text_field( wp_unslash( $_POST['vltp_type'] ) ) : '';
		$image = isset( $_POST['vltp_progress_image'] ) ? absint( $_POST['vltp_progress_image'] ) : 0;
		$start = isset( $_POST['vltp_start'] ) ? wp_kses_post( wp_unslash( $_POST['vltp_start'] ) ) : '';
		$result = isset( $_POST['vltp_result'] ) ? wp_kses_post( wp_unslash( $_POST['vltp_result'] ) ) : '';
		$client_ip = isset( $_POST['vltp_client_ip'] ) ? wp_kses_post( wp_unslash( $_POST['vltp_client_ip'] ) ) : '';
		$client_ip_row = isset( $_POST['vltp_client_ip_row'] ) ? wp_kses_post( wp_unslash( $_POST['vltp_client_ip_row'] ) ) : '';
		$conclusion = isset( $_POST['vltp_conclusion'] ) ? wp_kses_post( wp_unslash( $_POST['vltp_conclusion'] ) ) : '';
		
		$has_errors = false;

		if ( empty( $type ) ) {
			vltp_admin_page_error( __( 'Please choose the Test Type', 'vpn-leaks-test') );
			$has_errors = true;
		}
		
		if ( empty( vltp_test_type_name( $type ) ) ) {
			vltp_admin_page_error( __( 'Wrong Test Type', 'vpn-leaks-test') );
			$has_errors = true;
		}
		
		if ( empty( $result ) ) {
			vltp_admin_page_error( __( 'Please enter the Test Result HTML', 'vpn-leaks-test') );
			$has_errors = true;
		}

		if ( empty( $conclusion ) ) {
			vltp_admin_page_error( __( 'Please enter the Conclusion Result HTML', 'vpn-leaks-test') );
			$has_errors = true;
		}

		if ( empty( $start ) ) {
			vltp_admin_page_error( __( 'Please enter caption for the Start Button', 'vpn-leaks-test') );
			$has_errors = true;
		}

		$row = array();
		
		$row['vltp_type'] = $type;
		
		$options = array();
		// The test frontend design customization:
		// vltp_progress_image - progress image (gif), by default vpn-leaks-test/include/ajax-loader.gif
		$options['vltp_progress_image'] = $image;
		// vltp_start - the label of "start test" button, by default "Start test"
		$options['vltp_start'] = $start;
		$options['vltp_language'] = $language;
		/* vltp_result - line by line HTML used for formatting the test results. The following format characters are available: 
			%ip - IP address found by the test
			%country_code - Country code of the IP
			%country_name - Country name of the IP
			%city_name - City name of the IP (DNS test)
			%region_name - Region name of the IP (DNS test)
			%asn - Asn name to which the IP belongs
			%flag - The full URL to the country flag of the IP (all the flags are located here: vpn-leaks-test/include/flags)
		*/
		$options['vltp_result'] = $result;
		$options['vltp_client_ip'] = $client_ip;
		$options['vltp_client_ip_row'] = $client_ip_row;
		/* vltp_conclusion - line by line HTML for the conclusion of the test results. The following format characters are available: 
			%text - the conclusion text
		*/
		$options['vltp_conclusion'] = $conclusion;
		
		$row['vltp_options'] = serialize( $options );
		
		
		if ( $has_errors ) {
			if ($action == 'add') {
				vltp_admin_page_edit( 0, $row );
			}
			else  {
				vltp_admin_page_edit( $test_id, $row );
			}
		}
		else {
		
			global $wpdb;

			if ( $test_id ) {
				$wpdb->update( $wpdb->prefix.'vltp', $row, array( 'vltp_id' => $test_id ) );
				vltp_test_updated( $test_id );
			}
			else {
				$wpdb->insert( $wpdb->prefix.'vltp', $row );
        
				vltp_test_inserted();
			}
			
			vltp_admin_page_list();
		}
	}
	else if ($action == 'add') {
		vltp_admin_page_edit( 0, $row );
	}
	else if ($action == 'edit') {
		vltp_admin_page_edit( $test_id, $row );
	}
	else if ( $action == 'delete' && $test_id ) {
		check_admin_referer( 'vltp_delete_test_'.$test_id, VLTP_NONCE );
		vltp_admin_page_delete( $test_id );
		vltp_admin_page_list();
	}
	else {
		vltp_admin_page_list();
	}
?>
		</div>
	</div>
</div>
<?php	
}

/**
 * Show the the validation error
 *
 * @param string      $message     Error message
 *
 * @return null
 */
function vltp_admin_page_error( $message ) {
	echo '<div class="notice notice-error is-dismissible"><p>';
	echo esc_attr( $message );
	echo '</p></div>';
}

/**
 * Delete the VPN test
 *
 * @param int      $id     Test ID
 *
 * @return null
 */
function vltp_admin_page_delete( $id ) {
	global $wpdb;
	$id = intval( $id );
	$wpdb->delete( $wpdb->prefix.'vltp',array( 'vltp_id'=>$id ) );
	echo '<div class="notice notice-success is-dismissible"><p>';
	echo sprintf( __( 'The VPN test %d deleted successfully', 'vpn-leaks-test' ), $id );
	echo '</p></div>';
}

/**
 * Shows the notice about updating the VPN test
 *
 * @return null
 */
function vltp_test_updated( $id ) {
	$id = intval( $id );
	echo '<div class="notice notice-success is-dismissible"><p>';
	echo sprintf( __( 'The VPN test %d updated successfully', 'vpn-leaks-test'), $id );
	echo '</p></div>';
}

/**
 * Shows the notice about inserting the VPN test
 *
 * @return null
 */
function vltp_test_inserted() {
	echo '<div class="notice notice-success is-dismissible"><p>';
	echo __('The VPN test inserted successfully', 'vpn-leaks-test');
	echo '</p></div>';
}

/**
 * Shows the VPN tests table 
 *
 * @return null
 */
function vltp_admin_page_list() {
	global $wpdb;

	$total = $wpdb->get_var( 'SELECT count(*) FROM '.$wpdb->prefix.'vltp' );

	if (!$total) {
		echo '<br>'.__( 'No tests was added. Please use "<b>Add New VPN test</b>" button to add new test.', 'vpn-leaks-test' );
		return;
	}

	$results = $wpdb->get_results('SELECT * FROM '.$wpdb->prefix.'vltp',ARRAY_A);
	
	echo '<br>';
	echo '<table class="wp-list-table widefat fixed striped">';
	echo '<tr>';
	echo '<th><b>ID</b></th><th><b>Type</b></th><th><b>Shortcode</b></th>';
	echo '</tr>';
	foreach ($results as $row) {
		echo '<tr>';
		echo '<td class="title column-title has-row-actions column-primary page-title">';
		echo $row['vltp_id'];
		echo '<div class="row-actions">';
		echo '<span class="edit"><a href="'.add_query_arg( array( 'page' => 'vltp-admin-page', 'action'=>'edit', 'id'=> esc_attr( $row['vltp_id'] ) ), admin_url( 'admin.php' ) ).'">Edit</a> | </span>';
		$delete_url = wp_nonce_url( add_query_arg( array( 'page' => 'vltp-admin-page', 'action'=>'delete', 'id'=> absint( $row['vltp_id'] ) ), admin_url( 'admin.php' ) ), 'vltp_delete_test_'.absint( $row['vltp_id'] ), VLTP_NONCE );
		echo '<span class="trash"><a href="'.esc_url( $delete_url ).'" class="submitdelete">Trash</a></span>';
		echo '</div>';
		echo '</td>';
		echo '<td>'.vltp_test_type_name( $row['vltp_type'] ).'</td><td><span style="background:#b6f7b6;padding:0 10px 2px;color:#000">[vltp id='.esc_attr( $row['vltp_id'] ).']<span></td>';
		echo '</tr>';
	}
	echo '</table>';
	
}

/**
 * Determines the name of the VPN test type
 *
 * @param string $type ID of VPN test type
 *
 * @return string      name of the VPN test type 
 */
function vltp_test_type_name( $type ) {
	$names = array();
	$names['dns'] = 'DNS Leak Test';
	$names['email'] = 'Email IP Leak Test';
	$names['webrtc'] = 'WebRTC Leak Test';
	$names['torrent'] = 'Torrent Leak Test';
	$names['openport'] = 'Open Port Test';
	$names['blacklist'] = 'IP Blacklist Test';
	$names['vpn'] = 'VPN Leak Test';
	
	return isset( $names[ $type ] ) ? $names[ $type ] : '';
}

/**
 * Shows the edit form 
 *
 * @param int $id ID of VPN test
 *
 * @return null
 */
function vltp_admin_page_edit( $id, $row ) {

	global $wpdb;
	
	$id = intval($id);
	
	if ( !$row ) {
		if ( $id ) {
			$row = $wpdb->get_row( 'SELECT * FROM '.$wpdb->prefix.'vltp WHERE vltp_id='.$id, ARRAY_A );
		}
		else {
			$row = array();	
		}
	}
	
	if ($id) {
		echo '<h2>'.__( 'VPN Test', 'vpn-leaks-test' ).' #'.$id.'</h2>';
	}
	else {
		echo '<h2>'.__( 'New VPN Test', 'vpn-leaks-test' ).'</h2>';
	}
	
	if ($row) {
		$options = unserialize( stripslashes( $row[ 'vltp_options' ] ), array( 'allowed_classes' => false ) );
		if ( !is_array( $options ) ) {
			$options = array();
		}
		
		$row['vltp_progress_image'] = stripslashes( $options[ 'vltp_progress_image' ] );
		$row['vltp_start'] = stripslashes( $options[ 'vltp_start' ] );
		$row['vltp_language'] = isset( $options['vltp_language'] ) ? $options['vltp_language'] : 'english';
		$row['vltp_result'] = stripslashes( $options[ 'vltp_result' ] );
		$client_ip_row_default = '<div>%ip <img src="%flag" /> %city_name %region_name %country_name %asn</div>';
		$row['vltp_client_ip'] = isset( $options['vltp_client_ip'] ) ? stripslashes( $options['vltp_client_ip'] ) : '<div>Your IP:</div>';
		$row['vltp_client_ip_row'] = isset( $options['vltp_client_ip_row'] ) ? stripslashes( $options['vltp_client_ip_row'] ) : $client_ip_row_default;
		if ( $row['vltp_client_ip'] == '<div>You use the following IPs:</div><div>%client_ipv4</div><div>%client_ipv6</div>' || $row['vltp_client_ip'] == '<div>Your IP:</div><div>%client_ip</div>' ) {
			$row['vltp_client_ip'] = '<div>Your IP:</div>';
		}
		$row['vltp_conclusion'] = stripslashes( $options[ 'vltp_conclusion' ] );
	}
	else {
		$row['vltp_start'] = '<b>'.__( 'Start test', 'vpn-leaks-test' ).'</b>';
		$row['vltp_progress_image'] = 0;
		$row['vltp_type'] = 'dns';
		$row['vltp_language'] = 'english';
		$row['vltp_result'] = '<div>%ip <img src="%flag" /> %city_name %region_name %country_name %asn</div>';
		$row['vltp_client_ip'] = '<div>Your IP:</div>';
		$row['vltp_client_ip_row'] = $row['vltp_result'];
		$row['vltp_conclusion'] = '<div>%text</div>';
	}
	
	$type_select = '<select id="vltp_type" name="vltp_type">';
	
	$types = array( 'dns'=>'DNS leak test', 'webrtc'=>'WebRTC leak test', 'email'=>'Email IP leak test', 'torrent'=>'Torrent IP leak test', 'openport'=>'Open port test', 'blacklist'=>'IP blacklist test', 'vpn'=>'VPN leak test' );
	
	foreach ( $types as $k=>$v ) {
	
		if ( $k == $row['vltp_type'] ) {
			$type_select .= '<option selected="selected" value="'.esc_attr( $k ).'">'.esc_attr__( $v, 'vpn-leaks-test' ).'</option>';
		}
		else {
			$type_select .= '<option value="'.esc_attr( $k ).'">'.esc_attr__( $v, 'vpn-leaks-test' ).'</option>';
		}
	
	}
	
	$type_select.= '</select>';

	$language_select = '<select id="vltp_language" name="vltp_language">';
	$language_select .= '<option value="english"'.selected( $row['vltp_language'], 'english', false ).'>'.esc_html__( 'English', 'vpn-leaks-test' ).'</option>';
	$language_select .= '<option value="wordpress"'.selected( $row['vltp_language'], 'wordpress', false ).'>'.esc_html__( 'Follow WordPress language', 'vpn-leaks-test' ).'</option>';
	$language_select .= '</select>';
	
	$image_id = intval( $row['vltp_progress_image'] );
	
	$progress_image_input = '';
	
	if ( $image_id > 0 ) {
		$progress_image_input .= wp_get_attachment_image( $image_id, 'medium', false, array( 'id' => 'vltp_progress_image_preview' ) );
	} 
	else {
		$progress_image_input .= vltp_default_progress_image();
	}
	
	$progress_image_input.= '<input type="hidden" name="vltp_progress_image" id="vltp_progress_image" value="'.esc_attr( $image_id ).'" class="regular-text" />';
	$progress_image_input.= '<input type="button" class="button-primary" value="'.__( 'Select image', 'vpn-leaks-test' ).'" id="vltp_progress_image_manager"/>';
	
	$start_input = '<input id="vltp_start" name="vltp_start" type="text" value="'.esc_html( $row['vltp_start'] ).'" />';
	$result_input = '<textarea id="vltp_result" name="vltp_result" spellcheck="false">'.esc_html( $row['vltp_result'] ).'</textarea>';
	$client_ip_input = '<textarea id="vltp_client_ip" name="vltp_client_ip" spellcheck="false">'.esc_html( $row['vltp_client_ip'] ).'</textarea>';
	$client_ip_row_input = '<textarea id="vltp_client_ip_row" name="vltp_client_ip_row" spellcheck="false">'.esc_html( $row['vltp_client_ip_row'] ).'</textarea>';
	$client_ip_help = '<p>This HTML is displayed before the detected IP rows. Leave it empty to hide the client IP addresses.</p>';
	$result_help = 'Format characters available for "<strong>Test Results</strong>":';
	$result_help .= '<pre id="vltp_patterns">';
	$result_help .= '<span style="display:inline-block;width:16ch">Pattern</span><span style="display:inline-block;width:40ch">Description</span>Tests'."\n";
	$result_help .= '<span data-tests="dns webrtc email torrent"><span style="display:inline-block;width:16ch">%ip</span><span style="display:inline-block;width:40ch">IP address</span>DNS, WebRTC, email, torrent</span>'."\n";
	$result_help .= '<span data-tests="dns webrtc email torrent"><span style="display:inline-block;width:16ch">%country_code</span><span style="display:inline-block;width:40ch">Country code of the IP address</span>DNS, WebRTC, email, torrent</span>'."\n";
	$result_help .= '<span data-tests="dns webrtc email torrent"><span style="display:inline-block;width:16ch">%country_name</span><span style="display:inline-block;width:40ch">Country name of the IP address</span>DNS, WebRTC, email, torrent</span>'."\n";
	$result_help .= '<span data-tests="dns"><span style="display:inline-block;width:16ch">%city_name</span><span style="display:inline-block;width:40ch">City name of the IP address</span>DNS</span>'."\n";
	$result_help .= '<span data-tests="dns"><span style="display:inline-block;width:16ch">%region_name</span><span style="display:inline-block;width:40ch">Region name of the IP address</span>DNS</span>'."\n";
	$result_help .= '<span data-tests="openport"><span style="display:inline-block;width:16ch">%port</span><span style="display:inline-block;width:40ch">Port number</span>Open port</span>'."\n";
	$result_help .= '<span data-tests="blacklist"><span style="display:inline-block;width:16ch">%blacklist</span><span style="display:inline-block;width:40ch">Blacklist provider</span>IP blacklist</span>'."\n";
	$result_help .= '<span data-tests="openport blacklist"><span style="display:inline-block;width:16ch">%status</span><span style="display:inline-block;width:40ch">Port or blacklist status</span>Open port, IP blacklist</span>'."\n";
	$result_help .= '<span data-tests="dns webrtc email torrent"><span style="display:inline-block;width:16ch">%asn</span><span style="display:inline-block;width:40ch">ASN of the IP address</span>DNS, WebRTC, email, torrent</span>'."\n";
	$result_help .= '<span data-tests="dns webrtc email torrent"><span style="display:inline-block;width:16ch">%flag</span><span style="display:inline-block;width:40ch">URL of the IP country flag</span>DNS, WebRTC, email, torrent</span>'."\n";
	$result_help .= '<span data-tests="vpn"><span style="display:inline-block;width:16ch">%label</span><span style="display:inline-block;width:40ch">Result label</span>VPN</span>'."\n";
	$result_help .= '<span data-tests="vpn"><span style="display:inline-block;width:16ch">%text</span><span style="display:inline-block;width:40ch">Result text</span>VPN</span>'."\n";
	$result_help .= '</pre>';
	$result_help .= '<p>Patterns not supported by the selected test are replaced with an empty value.</p>';
	$client_ip_row_help = 'This HTML formats one client IP row:';
	$client_ip_row_help .= '<pre>';
	$client_ip_row_help .= '<span style="display:inline-block;width:16ch">Pattern</span>Description'."\n";
	$client_ip_row_help .= '<span style="display:inline-block;width:16ch">%ip</span>IP address'."\n";
	$client_ip_row_help .= '<span style="display:inline-block;width:16ch">%country_code</span>Country code of the IP address'."\n";
	$client_ip_row_help .= '<span style="display:inline-block;width:16ch">%country_name</span>Country name of the IP address'."\n";
	$client_ip_row_help .= '<span style="display:inline-block;width:16ch">%asn</span>ASN of the IP address'."\n";
	$client_ip_row_help .= '<span style="display:inline-block;width:16ch">%flag</span>URL of the IP country flag';
	$client_ip_row_help .= '</pre>';
	$result_help = __( $result_help, 'vpn-leaks-test' );

	$conclusion_input = '<textarea id="vltp_conclusion" name="vltp_conclusion" spellcheck="false">'.esc_html( $row['vltp_conclusion'] ).'</textarea>';
	
	$conclusion_help = '';
	$conclusion_help .= 'The following format characters for "<strong>Test conclusion</strong>" are available:<br>';
	$conclusion_help .= '<br><b>%text</b> the conclusion text';
	
	$conclusion_help = __( $conclusion_help, 'vpn-leaks-test' );
	
	echo '<form id="addtest" method="post" action="" class="validate">';
	echo '<input id="vltp_id" name="vltp_id" type="hidden" value="'.esc_attr( $id ).'" />';
	echo wp_nonce_field( 'vltp_save_test', VLTP_NONCE );
	echo '<table class="form-table">';
	echo vltp_admin_page_edit_input( 'Leak Test Type', $type_select, __( 'Please choose the test type from the list', 'vpn-leaks-test' ), 'vltp_type' );
	echo vltp_admin_page_edit_input( 'Result language', $language_select, __( 'Unsupported languages use English.', 'vpn-leaks-test' ), 'vltp_language' );
	echo vltp_admin_page_edit_input( 'Progress image', $progress_image_input, __( 'Select an image for progress bar', 'vpn-leaks-test' ), 'vltp_progress_image_manager' );
	echo vltp_admin_page_edit_input( 'Start button caption', $start_input, __( 'Enter an HTML for <b>Start test</b> button', 'vpn-leaks-test' ), 'vltp_start' );
	echo vltp_admin_page_edit_input_ex( 'Your IP HTML', $client_ip_input, $client_ip_help, 'vltp_client_ip' );
	echo vltp_admin_page_edit_input_ex( 'Your IP row HTML', $client_ip_row_input, __( $client_ip_row_help, 'vpn-leaks-test' ), 'vltp_client_ip_row' );
	echo vltp_admin_page_edit_input_ex( 'Test Results line by line HTML', $result_input, __( 'This HTML is used for formatting the test results line by line. '.$result_help, 'vpn-leaks-test' ), 'vltp_result' );
	echo vltp_admin_page_edit_input_ex( 'Test Conclusion HTML', $conclusion_input, __( 'This HTML is used for formatting the tests conclusion line by line. '.$conclusion_help, 'vpn-leaks-test' ), 'vltp_conclusion' );
	echo '</table>';
	
	if ($id) {
		echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="'.__( 'Update Test', 'vpn-leaks-test' ).'">';
		echo '<a href="'.add_query_arg( array( 'page' => 'vltp-admin-page'), admin_url( 'admin.php' ) ).'">Cancel</a></p>';
	}
	else {
		echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="'.__( 'Add New Test', 'vpn-leaks-test' ).'">';
		echo '<a href="'.add_query_arg( array( 'page' => 'vltp-admin-page'), admin_url( 'admin.php' ) ).'">Cancel</a></p>';
	}
	echo '</form>';

}


function vltp_admin_page_edit_input( $label, $input, $note, $id ) {
	return '<tr class="form-field"><th><label for="'.esc_attr( $id ).'">'.esc_attr( $label ).'</label></th><td>'.$input.'<div class="description">'. $note .'</div></td></tr>';
}

function vltp_admin_page_edit_input_ex( $label, $input, $note, $id ) {
	$content= '<tr class="form-field form-field-help"><th><label for="'.esc_attr( $id ).'">'.esc_attr( $label ).'</label></th><td>'.$input.'</td></tr>';
	$content.='<tr class="help"><th></th><td>'. $note .'</td></tr>';
	return $content;
}


