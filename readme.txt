=== VPN leaks test ===
Contributors: macvk
Tags: security,admin
Requires at least: 4.7
Requires PHP: 5.4
Tested up to: 7.1
Stable tag: 1.0.0.22
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

The plugin contains the leaks test package: 
- DNS leak test
- WEBrtc IP leak test
- Email IP leak test
- Torrent IP leak test
- Open port test
- IP blacklist test
- VPN leak test

The plugin integrates with https://bash.ws, a 3rd party service, to have it do the VPN testing.
The service is free to use and does not require an API key and/or registration, see terms of use https://bash.ws/terms.
The visitor's browser connects to the service to create a test and detect its public IPv4 and IPv6 addresses. The test steps and results use the same test ID.
Depending on the test the following additional data are received by the service:
- DNS leak test: the plugin does several DNS-lookup requests to the service;
- WEBrtc IP leak test: the plugin receives additional IPs from the Internet browser;
- Email IP leak test: the plugin asks user to send an empty email to the service;
- Torrent IP leak test: the plugin asks user to download a torrent file from the service.
- Open port and IP blacklist tests: the visitor's browser requests checks from the service using the test ID.
- VPN leak test: the browser runs DNS, WebRTC, IP, port and blacklist checks and sends browser fingerprint details to bash.ws.

== Installation ==

1. Install and activate the plugin through the 'Plugins' menu in WordPress.
2. Go to Settings > VPN leaks test.
3. Perform the necessary settings and press the Save button.
4. Use shortcode [vltp id=TEST_ID] to integrate the test into frontend of the website.

== Changelog ==

= 1.0.0.22 =
* Render the stored My IP rows from VPN JSON2 results.

= 1.0.0.21 =
* Use JSON2 for VPN results and refresh all browser assets after an update.

= 1.0.0.20 =
* Accept platform test IDs without plugin-side pattern restrictions.
* Safely decode saved test options.

= 1.0.0.19 =
* Render country codes in uppercase in all result templates.

= 1.0.0.18 =
* Preserve client IP location and ASN fields for open-port and blacklist results.
* Show each client IP once.

= 1.0.0.17 =
* Preserve configured client IP row templates.

= 1.0.0.16 =
* Use the dedicated IP row template for existing settings that contain result-only placeholders.

= 1.0.0.15 =
* Highlight the full applicable test-result pattern row.

= 1.0.0.14 =
* Restore selected-test highlighting for test-result patterns.

= 1.0.0.13 =
* Keep separate pattern references for client IP rows and test results.

= 1.0.0.12 =
* Simplify the shared IP pattern reference.

= 1.0.0.11 =
* Split the client-IP heading and row formatting into separate HTML fields.

= 1.0.0.10 =
* Convert the former stock client-IP template to the new one-row format.

= 1.0.0.9 =
* Fix the %client_ip placeholder.

= 1.0.0.8 =
* Format the Your IP HTML once for each detected address using one set of client placeholders.

= 1.0.0.7 =
* Use JSON2 results for every test type.

= 1.0.0.6 =
* Request the result format supported by each leak test.

= 1.0.0.5 =
* Remove the per-IP test registration limit.

= 1.0.0.4 =
* Apply the public request limit when registering a test, not while saving its one-use result.

= 1.0.0.3 =
* Protect administrator actions and media previews with capabilities and nonces.
* Bind public test-result uploads to a short-lived test token and limit requests.

= 1.0.0.2 =
Added open port, IP blacklist and VPN leak tests.
Updated test requests to use a shared test ID and detect the visitor's IPv4 and IPv6 addresses.
Added an optional client IP display template and a WordPress language setting.
Improved test result formatting and error handling.

= 1.0.0.1 =
Torrent leak test added.

= 1.0.0.0 =
Initial version

