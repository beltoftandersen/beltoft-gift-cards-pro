<?php

namespace BgcwPro\Analytics;

use BgcwPro\Support\Options;
use BgcwPro\Support\CsvUtil;

defined( 'ABSPATH' ) || exit;

class ReportGenerator {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'bgcw_pro_send_report', [ __CLASS__, 'maybe_send_report' ] );
		add_action( 'bgcw_pro_cleanup_old_reports', [ __CLASS__, 'cleanup_old_reports' ] );
	}

	/**
	 * Hourly cron handler: check if a report should be sent.
	 */
	public static function maybe_send_report() {
		if ( '1' !== Options::get( 'analytics_enabled' ) ) {
			return;
		}

		$frequency = Options::get( 'report_frequency' );

		// Check if today is the right day for the configured frequency.
		$now = current_datetime();

		if ( 'weekly' === $frequency ) {
			$day_of_week = (int) $now->format( 'N' ); // 1 (Monday) through 7 (Sunday).
			if ( $day_of_week !== (int) Options::get( 'report_day_of_week' ) ) {
				return;
			}
		} elseif ( 'monthly' === $frequency ) {
			$day_of_month = (int) $now->format( 'j' );
			if ( $day_of_month !== (int) Options::get( 'report_day_of_month' ) ) {
				return;
			}
		}
		// 'daily' always proceeds.

		// Check if current hour matches report time.
		$current_hour = (int) $now->format( 'G' );
		if ( $current_hour !== (int) Options::get( 'report_time_of_day' ) ) {
			return;
		}

		// Guard against double-sends: don't send if already sent today.
		$last_sent = Options::get( 'report_last_sent' );
		if ( $last_sent ) {
			$today = $now->format( 'Y-m-d' );
			if ( substr( $last_sent, 0, 10 ) === $today ) {
				return;
			}
		}

		self::send_report();
	}

	/**
	 * Generate and send the report email with CSV attachment.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function send_report() {
		$csv_path = self::generate_csv();
		if ( ! $csv_path ) {
			return false;
		}

		$recipients = Options::get( 'report_recipients' );
		if ( empty( $recipients ) ) {
			$recipients = get_option( 'admin_email' );
		}

		$site_name = get_bloginfo( 'name' );
		$date      = current_datetime()->format( get_option( 'date_format' ) );

		$subject = sprintf(
			/* translators: 1: site name, 2: date */
			__( '[%1$s] Gift Card Report - %2$s', 'beltoft-gift-cards-pro' ),
			$site_name,
			$date
		);

		$body = sprintf(
			/* translators: 1: site name, 2: date */
			__( 'Please find attached the gift card report for %1$s generated on %2$s.', 'beltoft-gift-cards-pro' ),
			$site_name,
			$date
		);

		$headers     = [ 'Content-Type: text/plain; charset=UTF-8' ];
		$attachments = [ $csv_path ];

		try {
			$sent = wp_mail( $recipients, $subject, $body, $headers, $attachments );

			if ( $sent ) {
				Options::set( 'report_last_sent', current_datetime()->format( 'Y-m-d H:i:s' ) );
			}

			return $sent;
		} finally {
			if ( file_exists( $csv_path ) ) {
				wp_delete_file( $csv_path );
			}
		}
	}

	/**
	 * Generate a CSV report file with all gift card data.
	 *
	 * @return string|false File path on success, false on failure.
	 */
	public static function generate_csv() {
		$filepath = wp_tempnam( 'bgcw-pro-report.csv' );
		if ( ! $filepath ) {
			return false;
		}

		global $wpdb;

		$gc_table    = $wpdb->prefix . 'bgcw_gift_cards';
		$tx_table    = $wpdb->prefix . 'bgcw_transactions';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom tables, no user input.
		$gift_cards = $wpdb->get_results(
			"SELECT gc.*, COALESCE(tx.tx_count, 0) AS transactions_count
			FROM {$gc_table} AS gc
			LEFT JOIN (
				SELECT gift_card_id, COUNT(*) AS tx_count
				FROM {$tx_table}
				GROUP BY gift_card_id
			) AS tx ON tx.gift_card_id = gc.id
			ORDER BY gc.created_at DESC"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( ! is_array( $gift_cards ) ) {
			wp_delete_file( $filepath );
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Writing CSV report file.
		$handle = fopen( $filepath, 'w' );
		if ( ! $handle ) {
			wp_delete_file( $filepath );
			return false;
		}

		// CSV headers.
		$headers = [
			__( 'Code', 'beltoft-gift-cards-pro' ),
			__( 'Initial Amount', 'beltoft-gift-cards-pro' ),
			__( 'Balance', 'beltoft-gift-cards-pro' ),
			__( 'Status', 'beltoft-gift-cards-pro' ),
			__( 'Recipient', 'beltoft-gift-cards-pro' ),
			__( 'Created', 'beltoft-gift-cards-pro' ),
			__( 'Expires', 'beltoft-gift-cards-pro' ),
			__( 'Transactions Count', 'beltoft-gift-cards-pro' ),
		];

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CSV writing via fputcsv.
		fputcsv( $handle, $headers );

		foreach ( $gift_cards as $gc ) {
			$row = [
				CsvUtil::escape_cell( $gc->code ),
				number_format( (float) $gc->initial_amount, 2, '.', '' ),
				number_format( (float) $gc->balance, 2, '.', '' ),
				CsvUtil::escape_cell( $gc->status ),
				CsvUtil::escape_cell( $gc->recipient_email ),
				CsvUtil::escape_cell( $gc->created_at ),
				CsvUtil::escape_cell( $gc->expires_at ? $gc->expires_at : '' ),
				(int) $gc->transactions_count,
			];

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CSV writing via fputcsv.
			fputcsv( $handle, $row );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing CSV file handle.
		fclose( $handle );

		return $filepath;
	}

	/**
	 * Delete CSV report files older than 1 day.
	 *
	 * Hooked to `bgcw_pro_cleanup_old_reports` daily cron.
	 */
	public static function cleanup_old_reports() {
		$upload_dir = wp_get_upload_dir();
		$report_dir = trailingslashit( $upload_dir['basedir'] ) . 'bgcw-pro-reports';

		if ( ! is_dir( $report_dir ) ) {
			return;
		}

		$one_day_ago = time() - DAY_IN_SECONDS;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_glob -- Scanning report directory for cleanup.
		$files = glob( trailingslashit( $report_dir ) . '*.csv' );
		if ( ! is_array( $files ) ) {
			return;
		}

		foreach ( $files as $file ) {
			if ( is_file( $file ) && filemtime( $file ) < $one_day_ago ) {
				wp_delete_file( $file );
			}
		}
	}

}
