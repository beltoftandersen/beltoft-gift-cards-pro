<?php

namespace BgcwPro\Support;

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
		self::reactivate_license();
	}

	/**
	 * Re-activate the license on the remote server after plugin reactivation.
	 *
	 * When the plugin is deactivated, remote_deactivate() frees the activation
	 * slot. On reactivation we must re-register the domain so downloads and
	 * updates work again.
	 */
	private static function reactivate_license() {
		$key = Options::get( 'license_key' );
		if ( empty( $key ) ) {
			return;
		}

		\BgcwPro\Licensing\License::activate( $key );
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		\BgcwPro\Licensing\License::remote_deactivate();

		wp_clear_scheduled_hook( 'bgcw_pro_license_check' );
		wp_clear_scheduled_hook( 'bgcw_pro_process_scheduled_deliveries' );
		wp_clear_scheduled_hook( 'bgcw_pro_send_report' );
		wp_clear_scheduled_hook( 'bgcw_pro_cleanup_old_reports' );
	}

	/**
	 * 1.3.0: the free plugin's 1.5.0 backfill labels every card with an order ID as
	 * "order" (paid). Store credits issued from refunds carry the refunded order's ID,
	 * so they were mislabelled; they replace money already received and are paid_offline.
	 * The free plugin's version gate guarantees the source column exists before this runs.
	 */
	private static function reclassify_store_credit_source() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-off migration across two custom tables.
		$wpdb->query(
			"UPDATE {$wpdb->prefix}bgcw_gift_cards gc
			 INNER JOIN {$wpdb->prefix}bgcw_store_credits sc ON sc.gift_card_id = gc.id
			 SET gc.source = 'paid_offline'
			 WHERE gc.source <> 'paid_offline'"
		);
	}

	/**
	 * Check and run migrations if needed.
	 */
	public static function maybe_upgrade() {
		$installed = get_option( 'bgcw_pro_version', '0' );
		if ( version_compare( $installed, BGCW_PRO_VER, '<' ) ) {
			self::create_tables();
			self::schedule_crons();
			self::create_files();

			if ( version_compare( $installed, '1.3.0', '<' ) ) {
				self::reclassify_store_credit_source();
			}

			// 1.5.10: deliveries fire via Action Scheduler at their exact time; the hourly sweep is gone.
			wp_clear_scheduled_hook( 'bgcw_pro_process_scheduled_deliveries' );

			// 1.5.8 changed the card layout: stored PDFs regenerate on next send/download.
			if ( version_compare( $installed, '1.5.8', '<' ) && '0' !== $installed ) {
				Options::set( 'pdf_design_version', (string) ( max( 1, (int) Options::get( 'pdf_design_version' ) ) + 1 ) );
			}

			update_option( 'bgcw_pro_version', BGCW_PRO_VER );
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
		$sqls[] = "CREATE TABLE {$wpdb->prefix}bgcw_scheduled_deliveries (
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
		$sqls[] = "CREATE TABLE {$wpdb->prefix}bgcw_store_credits (
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
		$sqls[] = "CREATE TABLE {$wpdb->prefix}bgcw_bogo_rules (
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

		// Generated PDF files per gift card.
		$sqls[] = "CREATE TABLE {$wpdb->prefix}bgcw_pro_pdf (
			gift_card_id bigint(20) unsigned NOT NULL,
			file varchar(255) NOT NULL DEFAULT '',
			design varchar(32) NOT NULL DEFAULT 'classic',
			design_version int(11) unsigned NOT NULL DEFAULT 1,
			generated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (gift_card_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sqls as $sql ) {
			dbDelta( $sql );
		}

		update_option( 'bgcw_pro_db_version', '1.1.0' );
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
		if ( ! wp_next_scheduled( 'bgcw_pro_license_check' ) ) {
			wp_schedule_event( time(), 'daily', 'bgcw_pro_license_check' );
		}
		if ( ! wp_next_scheduled( 'bgcw_pro_send_report' ) ) {
			wp_schedule_event( time(), 'hourly', 'bgcw_pro_send_report' );
		}
		if ( ! wp_next_scheduled( 'bgcw_pro_cleanup_old_reports' ) ) {
			wp_schedule_event( time(), 'daily', 'bgcw_pro_cleanup_old_reports' );
		}
	}

	/**
	 * Create protection files for the report upload directory.
	 */
	public static function create_files() {
		$upload_dir = wp_get_upload_dir();

		$files = [
			[
				'base'    => $upload_dir['basedir'] . '/bgcw-pro-reports',
				'file'    => '.htaccess',
				'content' => "Order deny,allow\nDeny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n",
			],
			[
				'base'    => $upload_dir['basedir'] . '/bgcw-pro-reports',
				'file'    => 'index.html',
				'content' => '',
			],
			[
				'base'    => $upload_dir['basedir'] . '/bgcw-pro-reports',
				'file'    => 'index.php',
				'content' => "<?php\n// Silence is golden.\n",
			],
			[
				'base'    => $upload_dir['basedir'] . '/bgcw-pro-reports',
				'file'    => 'web.config',
				'content' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n\t<system.webServer>\n\t\t<authorization>\n\t\t\t<remove users=\"*\" roles=\"\" verbs=\"\" />\n\t\t\t<add accessType=\"Deny\" users=\"*\" />\n\t\t</authorization>\n\t</system.webServer>\n</configuration>\n",
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
