<?php
/**
 * Uninstall handler for Beltoft Gift Cards for WooCommerce - Pro.
 *
 * @package BgcwPro
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- uninstall.php runs in isolation.
$bgcw_pro_options = get_option( 'bgcw_pro_options', [] );

// Only clean up if the user opted in.
if ( empty( $bgcw_pro_options['cleanup_on_uninstall'] ) || '1' !== $bgcw_pro_options['cleanup_on_uninstall'] ) {
	return;
}

// Free activation slot on remote license server.
$bgcw_pro_license_key = $bgcw_pro_options['license_key'] ?? '';
if ( ! empty( $bgcw_pro_license_key ) ) {
	wp_remote_post(
		'https://beltoft.net/license/deactivate',
		[
			'body'    => wp_json_encode(
				[
					'license_key' => $bgcw_pro_license_key,
					'domain'      => untrailingslashit( home_url() ),
				]
			),
			'headers'  => [ 'Content-Type' => 'application/json' ],
			'timeout'  => 5,
			'blocking' => false,
		]
	);
}

// Delete options.
delete_option( 'bgcw_pro_options' );
delete_option( 'bgcw_pro_version' );
delete_option( 'bgcw_pro_db_version' );

// Drop custom tables.
global $wpdb;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bgcw_scheduled_deliveries" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bgcw_store_credits" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bgcw_bogo_rules" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bgcw_pro_pdf" );
// phpcs:enable

// Remove generated PDF files.
$bgcw_pro_upload = wp_upload_dir();
$bgcw_pro_pdf_dir = trailingslashit( $bgcw_pro_upload['basedir'] ) . 'bgcw-pdf';
if ( is_dir( $bgcw_pro_pdf_dir ) ) {
	global $wp_filesystem;
	if ( ! $wp_filesystem ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
	}
	if ( $wp_filesystem ) {
		$wp_filesystem->delete( $bgcw_pro_pdf_dir, true );
	}
}

// Clear scheduled crons.
wp_clear_scheduled_hook( 'bgcw_pro_license_check' );
wp_clear_scheduled_hook( 'bgcw_pro_process_scheduled_deliveries' );
wp_clear_scheduled_hook( 'bgcw_pro_send_report' );
wp_clear_scheduled_hook( 'bgcw_pro_cleanup_old_reports' );
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'bgcw_pro_deliver_gift_card', [], 'bgcw-pro' );
}

// Remove report files directory.
$bgcw_pro_upload_dir = wp_get_upload_dir();
$bgcw_pro_report_dir = $bgcw_pro_upload_dir['basedir'] . '/bgcw-pro-reports';

if ( is_dir( $bgcw_pro_report_dir ) ) {
	$bgcw_pro_files = glob( trailingslashit( $bgcw_pro_report_dir ) . '*' );
	if ( is_array( $bgcw_pro_files ) ) {
		array_map( 'unlink', $bgcw_pro_files ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}
	rmdir( $bgcw_pro_report_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
}
// phpcs:enable
