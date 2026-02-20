<?php

namespace GiftCardsPro\Support;

defined( 'ABSPATH' ) || exit;

class Installer {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		self::create_options();
		self::create_tables();
		self::schedule_crons();
		self::create_files();
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'wcgc_pro_license_check' );
		wp_clear_scheduled_hook( 'wcgc_pro_process_scheduled_deliveries' );
		wp_clear_scheduled_hook( 'wcgc_pro_send_report' );
		wp_clear_scheduled_hook( 'wcgc_pro_cleanup_old_reports' );
	}

	/**
	 * Check and run migrations if needed.
	 */
	public static function maybe_upgrade() {
		$installed = get_option( 'wcgc_pro_version', '0' );
		if ( version_compare( $installed, WCGC_PRO_VER, '<' ) ) {
			self::create_tables();
			self::schedule_crons();
			update_option( 'wcgc_pro_version', WCGC_PRO_VER );
		}
	}

	/**
	 * Create or update custom database tables.
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sqls = [];

		// Scheduled deliveries table.
		$sqls[] = "CREATE TABLE {$wpdb->prefix}wcgc_scheduled_deliveries (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			gift_card_id bigint(20) unsigned NOT NULL,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			scheduled_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY gift_card_id (gift_card_id),
			KEY status_date (status,scheduled_date)
		) {$charset_collate};";

		// Store credits table.
		$sqls[] = "CREATE TABLE {$wpdb->prefix}wcgc_store_credits (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			gift_card_id bigint(20) unsigned NOT NULL,
			original_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			amount decimal(10,2) NOT NULL DEFAULT 0.00,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY gift_card_id (gift_card_id)
		) {$charset_collate};";

		// BOGO rules table.
		$sqls[] = "CREATE TABLE {$wpdb->prefix}wcgc_bogo_rules (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL DEFAULT '',
			buy_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			get_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			min_quantity int(11) NOT NULL DEFAULT 1,
			max_uses int(11) NOT NULL DEFAULT 0,
			uses_count int(11) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			starts_at datetime DEFAULT NULL,
			ends_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sqls as $sql ) {
			dbDelta( $sql );
		}

		update_option( 'wcgc_pro_db_version', '1.0.0' );
	}

	/**
	 * Create default options if not present.
	 */
	private static function create_options() {
		if ( false === get_option( Options::OPTION ) ) {
			add_option( Options::OPTION, Options::defaults(), '', 'yes' );
		}
	}

	/**
	 * Schedule cron events.
	 */
	private static function schedule_crons() {
		if ( ! wp_next_scheduled( 'wcgc_pro_license_check' ) ) {
			wp_schedule_event( time(), 'daily', 'wcgc_pro_license_check' );
		}
		if ( ! wp_next_scheduled( 'wcgc_pro_process_scheduled_deliveries' ) ) {
			wp_schedule_event( time(), 'hourly', 'wcgc_pro_process_scheduled_deliveries' );
		}
		if ( ! wp_next_scheduled( 'wcgc_pro_send_report' ) ) {
			wp_schedule_event( time(), 'hourly', 'wcgc_pro_send_report' );
		}
		if ( ! wp_next_scheduled( 'wcgc_pro_cleanup_old_reports' ) ) {
			wp_schedule_event( time(), 'daily', 'wcgc_pro_cleanup_old_reports' );
		}
	}

	/**
	 * Create protection files for the report upload directory.
	 */
	public static function create_files() {
		$upload_dir = wp_get_upload_dir();

		$files = [
			[
				'base'    => $upload_dir['basedir'] . '/wcgc-pro-reports',
				'file'    => '.htaccess',
				'content' => 'deny from all',
			],
			[
				'base'    => $upload_dir['basedir'] . '/wcgc-pro-reports',
				'file'    => 'index.html',
				'content' => '',
			],
		];

		foreach ( $files as $file ) {
			if ( wp_mkdir_p( $file['base'] ) && ! file_exists( trailingslashit( $file['base'] ) . $file['file'] ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Following WooCommerce core pattern.
				$file_handle = @fopen( trailingslashit( $file['base'] ) . $file['file'], 'w' );
				if ( $file_handle ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
					fwrite( $file_handle, $file['content'] );
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
					fclose( $file_handle );
				}
			}
		}
	}
}
