function vpnFingerprintHash(value) {
	var hash = 2166136261;

	for (var index = 0; index < value.length; index++) {
		var number = typeof value === 'string' ? value.charCodeAt(index) : Math.round(value[index] * 1000000);
		hash ^= number;
		hash = Math.imul(hash, 16777619);
	}

	return ('00000000' + (hash >>> 0).toString(16)).slice(-8);
}

function vpnFingerprint(callback) {
	var result = {};
	result.screen = window.screen.width + ' x ' + window.screen.height;
	result.language = (navigator.languages || [navigator.language]).join(', ');
	try {
		result.timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
	} catch (error) {
	}

	try {
		var context = document.createElement('canvas').getContext('2d');
		var sample = 'mmmmmmmmmmlliWW0123456789';
		var bases = ['monospace', 'serif', 'sans-serif'];
		var widths = bases.map(function (base) {
			context.font = '72px ' + base;
			return context.measureText(sample).width;
		});
		result.fonts = ['Arial', 'Verdana', 'Times New Roman', 'Courier New', 'Georgia', 'Trebuchet MS', 'Helvetica', 'Menlo', 'Consolas', 'Roboto', 'Noto Sans'].filter(function (font) {
			return bases.some(function (base, index) {
				context.font = '72px "' + font + '", ' + base;
				return context.measureText(sample).width !== widths[index];
			});
		}).join(', ');
	} catch (error) {
	}

	try {
		var canvas = document.createElement('canvas');
		canvas.width = 240;
		canvas.height = 60;
		var canvasContext = canvas.getContext('2d');
		canvasContext.fillStyle = '#f60';
		canvasContext.fillRect(10, 10, 100, 30);
		canvasContext.fillStyle = '#069';
		canvasContext.font = '18px Arial';
		canvasContext.fillText('bash.ws fingerprint', 4, 25);
		canvasContext.fillStyle = 'rgba(102, 204, 0, 0.7)';
		canvasContext.fillText('Canvas', 80, 48);
		result.canvas = vpnFingerprintHash(canvas.toDataURL());
	} catch (error) {
	}

	try {
		var webglCanvas = document.createElement('canvas');
		var gl = webglCanvas.getContext('webgl2') || webglCanvas.getContext('webgl') || webglCanvas.getContext('experimental-webgl');
		if (gl) {
			result.graphics = gl.getParameter(gl.VERSION) + ' — ' + gl.getParameter(gl.RENDERER);
			var debug = gl.getExtension('WEBGL_debug_renderer_info');
			if (debug) {
				var renderer = gl.getParameter(debug.UNMASKED_RENDERER_WEBGL);
				if (renderer && renderer !== gl.getParameter(gl.RENDERER)) {
					result.graphics += ' — ' + renderer;
				}
			}
			result.texture = String(gl.getParameter(gl.MAX_TEXTURE_SIZE));
			var lose = gl.getExtension('WEBGL_lose_context');
			if (lose) {
				lose.loseContext();
			}
		}
	} catch (error) {
	}

	try {
		var OfflineContext = window.OfflineAudioContext || window.webkitOfflineAudioContext;

		if (!OfflineContext) {
			callback(result);
			return;
		}

		var audioContext = new OfflineContext(1, 44100, 44100);
		var oscillator = audioContext.createOscillator();
		var compressor = audioContext.createDynamicsCompressor();
		oscillator.type = 'triangle';
		oscillator.frequency.value = 10000;
		compressor.threshold.value = -50;
		compressor.knee.value = 40;
		compressor.ratio.value = 12;
		compressor.attack.value = 0;
		compressor.release.value = 0.25;
		oscillator.connect(compressor);
		compressor.connect(audioContext.destination);
		oscillator.start(0);
		var completed = false;
		function complete(buffer) {
			if (completed) {
				return;
			}
			completed = true;

			try {
				if (buffer) {
					result.audio = vpnFingerprintHash(buffer.getChannelData(0));
				}
			} catch (error) {
			}

			callback(result);
		}
		audioContext.oncomplete = function (event) { complete(event.renderedBuffer); };
		var rendering = audioContext.startRendering();

		if (rendering && typeof rendering.then === 'function') {
			rendering.then(complete, function () { complete(); });
		}

		window.setTimeout(function () { complete(); }, 1500);
	} catch (error) {
		callback(result);
	}
}

function vltp_test_vpn(button, saved_button, vltp_id) {
	var settings = vltp_get_settings(vltp_id);
	var test_id = settings.vltp_test_id;
	var stopped = false;
	var failures = 0;
	var timer = setTimeout(function() { fail(); }, 120000);

	function fail() {
		if (stopped) {
			return;
		}
		stopped = true;
		clearTimeout(timer);
		vpn_test_started = false;
		button.html(saved_button);
		alert(settings.vltp_network_error);
	}

	function request(url, data, timeout) {
		return jQuery.ajax({url:'https://bash.ws/', type:'POST', data:jQuery.extend({ajax:1, url:url}, data), dataType:'json', timeout:timeout || 15000});
	}

	function report(data, callback) {
		if (stopped) {
			return;
		}
		request(settings.vltp_language+'/vpn-leak-test', jQuery.extend({exec:1, run_id:test_id}, data)).done(function(response) {
			if (response.error) {
				fail();
				return;
			}
			if (callback) {
				callback();
			}
		}).fail(fail);
	}

	function child(route, data, callback) {
		request(settings.vltp_language+route, jQuery.extend({start:1, run_id:test_id}, data || {})).done(function(response) {
			if (stopped || response.error || !response.url) {
				return;
			}
			request(response.url, {exec:1}, 90000).done(function(result) {
				if (!stopped && !result.error && callback) {
					callback(response.url);
				}
			});
		});
	}

	function saveResults() {
		jQuery.ajax({url:'https://bash.ws'+settings.vltp_language+'/vpn-leak-test/test/'+test_id+'?json=2', dataType:'json', timeout:20000}).done(function(records) {
			if (stopped || !Array.isArray(records) || !records.length || records[0].type == 'error') {
				fail();
				return;
			}
			jQuery.ajax({url:settings.vltp_ajax_url, type:'POST', dataType:'json', timeout:20000,
				data:{action:'vltp_vpn_results', vltp_test_id:test_id, vltp_result_token:settings.vltp_result_token, records:JSON.stringify(records)}}).done(function(response) {
				if (!response || !response.success) {
					fail();
					return;
				}
				stopped = true;
				clearTimeout(timer);
				document.location = settings.vltp_url;
			}).fail(fail);
		}).fail(fail);
	}

	function poll() {
		if (stopped) {
			return;
		}
		request(settings.vltp_language+'/vpn-leak-test', {checkrun:1, run_id:test_id}).done(function(response) {
			if (response.error) {
				fail();
				return;
			}
			failures = 0;
			if (response.done) {
				saveResults();
			}
			else {
				setTimeout(poll, 1500);
			}
		}).fail(function() {
			if (++failures >= 4) {
				fail();
			}
			else {
				setTimeout(poll, 1500);
			}
		});
	}

	function checkDns() {
		child('/dnsleak', {}, function() {
			var remaining = 30;
			var done = false;
			var images = [];
			function complete() {
				if (done) {
					return;
				}
				done = true;
				report({dns_ready:1});
			}
			for (var i = 0; i < 30; i++) {
				var image = new Image();
				images.push(image);
				image.onload = image.onerror = function() {
					if (!--remaining) {
						setTimeout(complete, 1500);
					}
				};
				image.src = 'https://'+(Math.floor(Math.random()*9000000000)+1000000000)+'.'+test_id+'.bash.ws/css/z.css';
			}
			setTimeout(complete, 6000);
		});
	}

	function checkWebrtc() {
		child('/webrtc-leak-test', {}, function(url) {
			vltp_collect_webrtc(function(ips, success) {
				if (success && !stopped) {
					request(url, {exec:1, save:1, ips:ips});
				}
			});
		});
	}

	vpnFingerprint(function(fingerprint) {
		report({fingerprint:fingerprint}, function() {
			poll();
			child('/ip-blacklist-check');
			child('/open-port-check');
			checkDns();
			checkWebrtc();
		});
	});
}
