<?php
require_once __DIR__ . '/bootstrap.php';

use BgcwPro\Licensing\License;
use BgcwPro\Support\Options;

// Activation must persist even when the Settings API sanitizer is registered (as on every admin/AJAX request).
$backup = get_option( 'bgcw_pro_options' );
bgcwp_test_register_cleanup( function () use ( $backup ) {
	// Restore without the sanitizer, which would otherwise keep the test license.
	remove_all_filters( 'sanitize_option_bgcw_pro_options' );
	update_option( 'bgcw_pro_options', $backup );
	Options::invalidate_cache();
} );

\BgcwPro\Admin\SettingsPage::register_settings();
bgcwp_assert( false !== has_filter( 'sanitize_option_bgcw_pro_options' ), 'settings sanitizer registered' );

add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false === strpos( $url, 'beltoft.net' ) ) {
		return $pre;
	}
	return [ 'response' => [ 'code' => 200, 'message' => 'OK' ], 'headers' => [], 'body' => wp_json_encode( [ 'success' => true, 'valid' => true, 'license_active' => true, 'expires_at' => '2027-12-31', 'current_version' => BGCW_PRO_VER, 'max_activations' => 3 ] ), 'cookies' => [] ];
}, 10, 3 );

$result = License::activate( 'TEST-PERSIST-KEY' );
bgcwp_assert_eq( true, $result['success'], 'activation reports success' );
Options::invalidate_cache();
$stored = get_option( 'bgcw_pro_options' );
bgcwp_assert_eq( 'TEST-PERSIST-KEY', $stored['license_key'] ?? null, 'license key persisted' );
bgcwp_assert_eq( 'valid', $stored['license_status'] ?? null, 'license status persisted' );
bgcwp_assert_eq( '2027-12-31', $stored['license_expires'] ?? null, 'expiry persisted' );
bgcwp_assert_eq( true, License::is_active(), 'license active after activation' );

// The sanitizer is still in place afterwards and still ignores request-driven license changes.
bgcwp_assert( false !== has_filter( 'sanitize_option_bgcw_pro_options' ), 'sanitizer re-attached after internal write' );
$clean = Options::sanitize( [ 'pdf_enabled' => '1', 'license_key' => 'HACKED', 'license_status' => 'valid' ] );
bgcwp_assert_eq( 'TEST-PERSIST-KEY', $clean['license_key'], 'settings form cannot change the license key' );

// No admin notice asking for a key once activated.
$admin = bgcwp_test_admin_id(); wp_set_current_user( $admin ); set_current_screen( 'dashboard' );
ob_start(); License::admin_notices(); $notice = wp_strip_all_tags( ob_get_clean() );
bgcwp_assert( false === strpos( $notice, 'enter your license key' ), 'no "enter your license key" notice after activation' );
