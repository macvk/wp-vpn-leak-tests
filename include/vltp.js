
var vpn_test_started = false;

jQuery(document).ready(function(){
	jQuery('.vltp-test .vltp-start').click(function(){
		var o = jQuery(this);
		var test_type = o.attr('data-type');
		var vltp_id = parseInt(o.attr('data-id'));
		
		if (vpn_test_started) {
			return;
		}
		
		if (typeof test_type == 'undefined' || !test_type) {
			return;
		}
		
		if (['dns', 'email', 'webrtc', 'torrent', 'openport', 'blacklist', 'vpn'].indexOf(test_type) == -1) {
			return;
		}

		if (!vltp_id) {
			return;
		}
		
		vpn_test_started = true;
		
		var vltp_settings = vltp_get_settings(vltp_id);
		var sav_button = o.html();

		if (!vltp_settings) {
			vpn_test_started = false;
			return;
		}

		var request = jQuery.ajax({url:'https://bash.ws/id', dataType:'text', cache:false, timeout:20000});
		request.done(function(response) {
			var test_id = typeof response == 'string' ? jQuery.trim(response) : '';

			if (!test_id) {
				vpn_test_started = false;
				alert(vltp_settings.vltp_id_error);
				return;
			}

			vltp_settings.vltp_test_id = test_id;
			vltp_settings.vltp_url = vltp_settings.vltp_url.replace(/([?&]vltp_test_id=)[^&]*/, function(match, prefix) { return prefix+test_id; });
			if (vltp_settings.vltp_email_message) {
				vltp_settings.vltp_email_message = vltp_settings.vltp_email_message.replace(/VLTPTESTID/g, test_id);
			}
			if (vltp_settings.vltp_torrent_message) {
				vltp_settings.vltp_torrent_message = vltp_settings.vltp_torrent_message.replace(/VLTPTESTID/g, test_id);
			}

			vltp_register_test(vltp_settings, function() {
				var image = new Image();
				image.src = vltp_settings.vltp_progress_image;
				o.html(image);
				vltp_probe_addresses(test_id, function() {
					vltp_test(o,sav_button,test_type,vltp_id);
				});
			}, function() {
				vpn_test_started = false;
				o.html(sav_button);
				alert(vltp_settings.vltp_network_error);
			});
		}).fail(function() {
			vpn_test_started = false;
			alert(vltp_settings.vltp_id_error);
		});

	});
});

function vltp_probe_addresses(test_id, callback) {
	var remaining = 2;
	['ipv4', 'ipv6'].forEach(function(family) {
		jQuery.ajax({url:'https://'+family+'.bash.ws/myip/test/'+test_id, dataType:'text', cache:false, timeout:5000}).always(function() {
			if (!--remaining) {
				callback();
			}
		});
	});
}

function vltp_register_test(settings, success, failure) {
	var data = {
		action: 'vltp_register_test',
		vltp_id: settings.vltp_id,
		vltp_test_id: settings.vltp_test_id,
		nonce: settings.vltp_register_nonce
	};

	jQuery.ajax({url:settings.vltp_ajax_url, type:'POST', data:data, dataType:'json', timeout:20000}).done(function(response) {
		if (!response || !response.success || !response.data || typeof response.data.token != 'string') {
			failure();
			return;
		}

		settings.vltp_result_token = response.data.token;
		success();
	}).fail(failure);
}

function vltp_save_leak_results(o, sav_button, test_type, vltp_id) {
	var settings = vltp_get_settings(vltp_id);
	var routes = {dns:'dnsleak', webrtc:'webrtc-leak-test', email:'email-leak-test', torrent:'torrent-leak-test'};
	var format = '?json=2';
	var url = 'https://bash.ws'+settings.vltp_language+'/'+routes[test_type]+'/test/'+settings.vltp_test_id+format;

	function failed() {
		vpn_test_started = false;
		o.html(sav_button);
		alert(settings.vltp_network_error);
	}

	jQuery.ajax({url:url, dataType:'json', timeout:20000}).done(function(records) {
		if (!Array.isArray(records) || !records.length || records.some(function(record) {
			return record && record.type == 'error';
		})) {
			failed();
			return;
		}

		var data = {action:'vltp_leak_results', vltp_test_id:settings.vltp_test_id, vltp_result_token:settings.vltp_result_token, test_type:test_type, records:JSON.stringify(records)};
		jQuery.ajax({url:settings.vltp_ajax_url, type:'POST', data:data, dataType:'json', timeout:20000}).done(function(response) {
			if (response && response.success) {
				document.location = settings.vltp_url;
			}
			else {
				failed();
			}
		}).fail(failed);
	}).fail(failed);
}

function vltp_get_settings(vltp_id) {
	var settings = window["vltp_settings_"+vltp_id];
	
	if (typeof settings == 'undefined' || !settings) {
		return false;
	}

	return settings;
}

function vltp_test(o,sav_button,test_type,vltp_id) {


	if (test_type.localeCompare('dns') == 0) {
		vltp_test_dns(o,sav_button,test_type,vltp_id);
	}

	if (test_type.localeCompare('email') == 0) {

		var vltp_settings = vltp_get_settings(vltp_id);

		if (!vltp_settings) {
			return;
		}

		o.parent().append('<div class="vltp-info">'+vltp_settings.vltp_email_message+'</div>');
		vltp_test_poll(o,sav_button,test_type,vltp_id);
	}

	if (test_type.localeCompare('torrent') == 0) {

		var vltp_settings = vltp_get_settings(vltp_id);

		if (!vltp_settings) {
			return;
		}

		o.parent().append('<div class="vltp-info">'+vltp_settings.vltp_torrent_message+'</div>');
		vltp_test_poll(o,sav_button,test_type,vltp_id);
	}

	if (test_type.localeCompare('webrtc') == 0) {
		vltp_test_webrtc(o,sav_button,test_type,vltp_id);
	}

	if (test_type == 'openport' || test_type == 'blacklist') {
		vltp_test_network(o,sav_button,test_type,vltp_id);
	}

	if (test_type == 'vpn') {
		vltp_test_vpn(o,sav_button,vltp_id);
	}
}

function vltp_test_network(o,sav_button,test_type,vltp_id) {
	var settings = vltp_get_settings(vltp_id);

	function failed(message) {
		vpn_test_started = false;
		o.html(sav_button);
		alert(message || settings.vltp_network_error);
	}

	var route = test_type == 'openport' ? 'open-port-check' : 'ip-blacklist-check';
	var test_url = 'https://bash.ws'+settings.vltp_language+'/'+route+'/test/'+settings.vltp_test_id;
	var result_url = test_url+'?json=2';

	function save(records) {
		if (!Array.isArray(records) || !records.length || records.some(function(record) {
			return record && record.type == 'error';
		})) {
			failed();
			return;
		}

		var data = {action:'vltp_network_test', vltp_test_id:settings.vltp_test_id, vltp_result_token:settings.vltp_result_token, test_type:test_type, records:JSON.stringify(records)};
		jQuery.ajax({url:settings.vltp_ajax_url, type:'POST', data:data, dataType:'json', timeout:20000}).done(function(response) {
			if (response && response.success) {
				document.location = settings.vltp_url;
			}
			else {
				failed(response && response.data ? response.data.message : '');
			}
		}).fail(function() { failed(); });
	}

	jQuery.ajax({url:test_url, type:'POST', dataType:'json', timeout:120000}).done(function(response) {
		if (!response || !response.done) {
			failed(response && response.error ? response.error : '');
			return;
		}

		jQuery.ajax({url:result_url, dataType:'json', timeout:20000}).done(save).fail(function() { failed(); });
	}).fail(function() { failed(); });
}

function vltp_test_poll(o,sav_button,test_type,vltp_id,failures) {
	var settings = vltp_get_settings(vltp_id);
	if (!settings) {
		return;
	}

	var route = test_type == 'email' ? 'email-leak-test' : 'torrent-leak-test';
	var url = 'https://bash.ws'+settings.vltp_language+'/'+route+'/test/'+settings.vltp_test_id;
	jQuery.ajax({url:url, type:'POST', data:{ajax:1}, timeout:20000, complete:function(xhr) {
		var done = false;
		var valid = false;
		try {
			var response = JSON.parse(xhr.responseText);
			valid = xhr.status >= 200 && xhr.status < 300 && response && typeof response.done != 'undefined';
			done = parseInt(response.done);
		}
		catch (error) {
		}

		failures = valid ? 0 : (failures || 0) + 1;
		if (failures >= 3) {
			vpn_test_started = false;
			o.html(sav_button);
			alert(settings.vltp_network_error);
			return;
		}

		if (done) {
			vltp_save_leak_results(o,sav_button,test_type,vltp_id);
		}
		else {
			setTimeout(function() {
				vltp_test_poll(o,sav_button,test_type,vltp_id,failures);
			}, 1000);
		}
	}});
}

function vltp_test_dns(o,sav_button,test_type,vltp_id) {

	var image_count = 30;
	var images = [];
	var i;
	var remaining = image_count;
	var finished = false;


	var vltp_settings = vltp_get_settings(vltp_id);

	if (!vltp_settings) {
		return;
	}

	var test_id = vltp_settings.vltp_test_id;
	
	for (i=0;i<image_count;i++)
	{
		images.push(new Image());
	}
	
	div = jQuery('<div id="vltp-image-test"></div>');
	jQuery('body').append(div);
	
	function complete() {
		if (!finished) {
			finished = true;
			vltp_save_leak_results(o,sav_button,test_type,vltp_id);
		}
	}

	function probe_done() {
		remaining--;
		if (!remaining) {
			complete();
		}
	}

	for (i=0;i<image_count;i++) {
		div.append(images[i]);
		images[i].onload = probe_done;
		images[i].onerror = probe_done;
		images[i].src = 'https://'+(i+1)+'.' +test_id+'.bash.ws/img.png';
	}

	setTimeout(complete, 8000);
	
}

function vltp_test_webrtc(o,sav_button,test_type,vltp_id) {
	var settings = vltp_get_settings(vltp_id);
	if (!settings) {
		return;
	}

	vltp_collect_webrtc(function(ips) {
		var url = 'https://bash.ws'+settings.vltp_language+'/webrtc-leak-test/test/'+settings.vltp_test_id;
		jQuery.ajax({url:url, type:'POST', data:{ajax:1, ips:ips}, complete:function(xhr) {
			var done = false;
			try {
				var response = JSON.parse(xhr.responseText);
				done = parseInt(response.done);
			}
			catch (error) {
			}

			if (done) {
				vltp_save_leak_results(o,sav_button,test_type,vltp_id);
			}
			else {
				vpn_test_started = false;
				o.html(sav_button);
				alert(settings.vltp_network_error);
			}
		}});
	});
}

function vltp_collect_webrtc(callback) {
	var Peer = window.RTCPeerConnection || window.mozRTCPeerConnection || window.webkitRTCPeerConnection;
	if (!Peer) {
		callback([], false);
		return;
	}

	var ips = [];
	var finished = false;
	var peer;
	var timer;

	function finish(success) {
		if (finished) {
			return;
		}
		finished = true;
		clearTimeout(timer);
		if (peer) {
			peer.close();
		}
		callback(ips, success);
	}

	try {
		peer = new Peer({iceServers:[{urls:'stun:stun.l.google.com:19302'}]});
		peer.onicecandidate = function(event) {
			if (!event.candidate) {
				finish(true);
				return;
			}
			var ip = event.candidate.address || event.candidate.candidate.split(' ')[4];
			if (ip && ips.indexOf(ip) == -1 && /^[0-9a-fA-F:.]+$/.test(ip)) {
				ips.push(ip);
			}
		};
		peer.createDataChannel('rtc');
		peer.createOffer().then(function(offer) {
			return peer.setLocalDescription(offer);
		}).catch(function() {
			finish(false);
		});
		timer = setTimeout(function() {
			finish(true);
		}, 5000);
	}
	catch (error) {
		finish(false);
	}
}
