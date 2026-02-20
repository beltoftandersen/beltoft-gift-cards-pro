<?php
/**
 * Uninstall handler for Smart Gift Cards for WooCommerce - Pro.
 *
 * @package GiftCardsPro
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- uninstall.php runs in isolation.
$wcgc_pro_options = get_option( 'wcgc_pro_options', [] );

// Only clean up if the user opted in.
if ( empty( $wcgc_pro_options['cleanup_on_uninstall'] ) || '1' !== $wcgc_pro_options['cleanup_on_uninstall'] ) {
	return;
}

// Delete options.
delete_option( 'wcgc_pro_options' );
delete_option( 'wcgc_pro_licenses' );
delete_option( 'wcgc_pro_version' );
delete_option( 'wcgc_pro_db_version' );

// Drop custom tables.
global $wpdb;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wcgc_scheduled_deliveries" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wcgc_store_credits" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wcgc_bogo_rules" );
// phpcs:enable

// Clear scheduled crons.
wp_clear_scheduled_hook( 'wcgc_pro_license_check' );
wp_clear_scheduled_hook( 'wcgc_pro_process_scheduled_deliveries' );
wp_clear_scheduled_hook( 'wcgc_pro_send_report' );
wp_clear_scheduled_hook( 'wcgc_pro_cleanup_old_reports' );

// Remove report files directory.
$wcgc_pro_upload_dir = wp_get_upload_dir();
$wcgc_pro_report_dir = $wcgc_pro_upload_dir['basedir'] . '/wcgc-pro-reports';

if ( is_dir( $wcgc_pro_report_dir ) ) {
	$wcgc_pro_files = glob( trailingslashit( $wcgc_pro_report_dir ) . '*' );
	if ( is_array( $wcgc_pro_files ) ) {
		array_map( 'unlink', $wcgc_pro_files ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}
	rmdir( $wcgc_pro_report_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
}
// phpcs:enable
