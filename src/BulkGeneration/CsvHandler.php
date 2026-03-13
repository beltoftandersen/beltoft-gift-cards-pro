<?php

namespace BgcwPro\BulkGeneration;

use BgcwPro\Support\CsvUtil;
use Bgcw\GiftCard\CodeGenerator;
use Bgcw\GiftCard\Repository;
use Bgcw\GiftCard\TransactionRepository;

defined( 'ABSPATH' ) || exit;

class CsvHandler {

	/**
	 * Maximum upload size in bytes (10 MB).
	 */
	const MAX_UPLOAD_SIZE = 10 * 1024 * 1024;

	/**
	 * Maximum number of errors to return in the response.
	 */
	const MAX_ERROR_REPORT = 10;

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_bgcw_pro_export_csv', [ __CLASS__, 'handle_export' ] );
		add_action( 'wp_ajax_bgcw_pro_import_csv', [ __CLASS__, 'handle_import' ] );
	}

	/**
	 * AJAX handler: export gift cards as CSV.
	 */
	public static function handle_export() {
		check_ajax_referer( 'bgcw_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'beltoft-gift-cards-pro' ), 403 );
		}

		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

		$args = [
			'per_page' => 10000,
			'offset'   => 0,
			'orderby'  => 'created_at',
			'order'    => 'DESC',
		];

		if ( ! empty( $status ) && in_array( $status, Repository::VALID_STATUSES, true ) ) {
			$args['status'] = $status;
		}

		$cards = Repository::get_all_paginated( $args );

		$filename = 'gift-cards-' . gmdate( 'Y-m-d-His' ) . '.csv';

		// Send download headers.
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming CSV to php://output.
		$output = fopen( 'php://output', 'w' );

		if ( ! $output ) {
			wp_die( esc_html__( 'Unable to open output stream.', 'beltoft-gift-cards-pro' ) );
		}

		// CSV header row — use English tokens so exported files can be re-imported
		// regardless of the site language at export or import time.
		fputcsv( $output, [
			'Code',
			'Initial Amount',
			'Balance',
			'Status',
			'Recipient Name',
			'Recipient Email',
			'Currency',
			'Created',
			'Expires',
		] );

		foreach ( $cards as $card ) {
			fputcsv( $output, [
				CsvUtil::escape_cell( $card->code ),
				$card->initial_amount,
				$card->balance,
				CsvUtil::escape_cell( $card->status ),
				CsvUtil::escape_cell( $card->recipient_name ),
				CsvUtil::escape_cell( $card->recipient_email ),
				CsvUtil::escape_cell( $card->currency ),
				CsvUtil::escape_cell( $card->created_at ),
				CsvUtil::escape_cell( $card->expires_at ?? '' ),
			] );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://output stream.
		fclose( $output );
		exit;
	}

	/**
	 * AJAX handler: import gift cards from CSV.
	 */
	public static function handle_import() {
		check_ajax_referer( 'bgcw_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				[ 'message' => __( 'You do not have permission to perform this action.', 'beltoft-gift-cards-pro' ) ],
				403
			);
		}

		// Validate uploaded file.
		if ( empty( $_FILES['csv_file'] ) || empty( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_send_json_error(
				[ 'message' => __( 'No file was uploaded.', 'beltoft-gift-cards-pro' ) ]
			);
		}

		$file = $_FILES['csv_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File array handled below.

		// Check for upload errors.
		if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			wp_send_json_error(
				[ 'message' => __( 'File upload error. Please try again.', 'beltoft-gift-cards-pro' ) ]
			);
		}

		// Validate file size.
		if ( $file['size'] > self::MAX_UPLOAD_SIZE ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %d: maximum file size in megabytes */
						__( 'File too large. Maximum size is %d MB.', 'beltoft-gift-cards-pro' ),
						self::MAX_UPLOAD_SIZE / ( 1024 * 1024 )
					),
				]
			);
		}

		// Validate file extension.
		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, [ 'csv', 'txt' ], true ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Invalid file type. Please upload a CSV or TXT file.', 'beltoft-gift-cards-pro' ) ]
			);
		}

		// Verify the temp file is a real upload.
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Invalid upload. Please try again.', 'beltoft-gift-cards-pro' ) ]
			);
		}

		// Open and parse CSV.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reading uploaded temp file.
		$handle = fopen( $file['tmp_name'], 'r' );

		if ( ! $handle ) {
			wp_send_json_error(
				[ 'message' => __( 'Unable to read the uploaded file.', 'beltoft-gift-cards-pro' ) ]
			);
		}

		// Read header row.
		$header_row = fgetcsv( $handle );
		if ( ! $header_row ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing temp file.
			fclose( $handle );
			wp_send_json_error(
				[ 'message' => __( 'CSV file is empty or unreadable.', 'beltoft-gift-cards-pro' ) ]
			);
		}

		// Normalize header names to lowercase, trimmed.
		$headers = array_map( function ( $h ) {
			return strtolower( trim( $h ) );
		}, $header_row );

		// Determine column indexes.
		$col_map = self::build_column_map( $headers );

		// 'amount' or 'initial_amount' is required.
		if ( false === $col_map['amount'] ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing temp file.
			fclose( $handle );
			wp_send_json_error(
				[ 'message' => __( 'CSV must contain an "amount" or "initial_amount" column.', 'beltoft-gift-cards-pro' ) ]
			);
		}

		$imported = 0;
		$skipped  = 0;
		$errors   = [];
		$row_num  = 1; // 1 = first data row after header.

		while ( false !== ( $row = fgetcsv( $handle ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Standard CSV iteration.
			++$row_num;

			// Skip completely empty rows.
			if ( ! $row || ( 1 === count( $row ) && empty( $row[0] ) ) ) {
				continue;
			}

			$amount = self::get_col_value( $row, $col_map['amount'] );
			$amount = (float) $amount;

			if ( $amount <= 0 ) {
				++$skipped;
				if ( count( $errors ) < self::MAX_ERROR_REPORT ) {
					$errors[] = sprintf(
						/* translators: %d: CSV row number */
						__( 'Row %d: Invalid or missing amount.', 'beltoft-gift-cards-pro' ),
						$row_num
					);
				}
				continue;
			}

			$recipient_name  = sanitize_text_field( self::get_col_value( $row, $col_map['recipient_name'] ) );
			$recipient_email = sanitize_email( self::get_col_value( $row, $col_map['recipient_email'] ) );
			$message         = sanitize_textarea_field( self::get_col_value( $row, $col_map['message'] ) );

			// Resolve expiry: absolute date ("Expires" column) takes priority over
			// relative days ("Expiry Days" column).
			$expires_at    = null;
			$raw_expires   = sanitize_text_field( self::get_col_value( $row, $col_map['expires_at'] ) );
			$raw_exp_days  = self::get_col_value( $row, $col_map['expiry_days'] );

			if ( ! empty( $raw_expires ) ) {
				// Absolute date from export, e.g. "2026-12-31 00:00:00" or "2026-12-31".
				$ts = strtotime( $raw_expires );
				if ( false !== $ts && $ts > 0 ) {
					$expires_at = gmdate( 'Y-m-d H:i:s', $ts );
				}
			} elseif ( ! empty( $raw_exp_days ) && ctype_digit( $raw_exp_days ) ) {
				// Relative days from now.
				$expiry_days = absint( $raw_exp_days );
				if ( $expiry_days > 0 ) {
					$expires_at = gmdate( 'Y-m-d H:i:s', time() + ( $expiry_days * DAY_IN_SECONDS ) );
				}
			}

			$code = CodeGenerator::generate();

			$gc_id = Repository::insert( [
				'code'            => $code,
				'initial_amount'  => $amount,
				'balance'         => $amount,
				'currency'        => get_woocommerce_currency(),
				'sender_name'     => '',
				'sender_email'    => '',
				'recipient_name'  => $recipient_name,
				'recipient_email' => $recipient_email,
				'message'         => $message,
				'order_id'        => null,
				'customer_id'     => null,
				'status'          => 'active',
				'expires_at'      => $expires_at,
			] );

			if ( ! $gc_id ) {
				++$skipped;
				if ( count( $errors ) < self::MAX_ERROR_REPORT ) {
					$errors[] = sprintf(
						/* translators: %d: CSV row number */
						__( 'Row %d: Failed to create gift card.', 'beltoft-gift-cards-pro' ),
						$row_num
					);
				}
				continue;
			}

			TransactionRepository::insert( [
				'gift_card_id'  => $gc_id,
				'type'          => 'credit',
				'amount'        => $amount,
				'balance_after' => $amount,
				'note'          => __( 'Imported from CSV', 'beltoft-gift-cards-pro' ),
			] );

			if ( ! empty( $recipient_email ) ) {
				/**
				 * Fire the gift card created action to trigger email delivery.
				 *
				 * @param int  $gc_id Gift card ID.
				 * @param null $order No order for imported cards.
				 */
				do_action( 'bgcw_gift_card_created', $gc_id, null );
			}

			++$imported;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing temp file.
		fclose( $handle );

		wp_send_json_success( [
			'imported' => $imported,
			'skipped'  => $skipped,
			'errors'   => $errors,
			'message'  => sprintf(
				/* translators: 1: imported count, 2: skipped count */
				__( '%1$d gift card(s) imported, %2$d skipped.', 'beltoft-gift-cards-pro' ),
				$imported,
				$skipped
			),
		] );
	}

	/**
	 * Build a column index map from normalized CSV headers.
	 *
	 * @param array $headers Lowercased, trimmed header names.
	 * @return array Associative map of field => column index (or false if missing).
	 */
	private static function build_column_map( $headers ) {
		$map = [
			'amount'          => false,
			'recipient_name'  => false,
			'recipient_email' => false,
			'message'         => false,
			'expiry_days'     => false,
			'expires_at'      => false,
		];

		foreach ( $headers as $index => $header ) {
			// Strip BOM and any surrounding whitespace.
			$header = ltrim( $header, "\xEF\xBB\xBF" );
			$header = trim( $header );

			switch ( $header ) {
				case 'amount':
				case 'initial_amount':
				case 'initial amount':
					if ( false === $map['amount'] ) {
						$map['amount'] = $index;
					}
					break;

				case 'recipient_name':
				case 'recipient name':
					$map['recipient_name'] = $index;
					break;

				case 'recipient_email':
				case 'recipient email':
					$map['recipient_email'] = $index;
					break;

				case 'message':
					$map['message'] = $index;
					break;

				case 'expiry_days':
				case 'expiry days':
					$map['expiry_days'] = $index;
					break;

				case 'expires':
				case 'expires_at':
				case 'expires at':
					$map['expires_at'] = $index;
					break;
			}
		}

		return $map;
	}

	/**
	 * Safely retrieve a column value from a CSV row.
	 *
	 * @param array    $row   CSV row data.
	 * @param int|bool $index Column index, or false if column is absent.
	 * @return string Column value or empty string.
	 */
	private static function get_col_value( $row, $index ) {
		if ( false === $index || ! isset( $row[ $index ] ) ) {
			return '';
		}
		return trim( $row[ $index ] );
	}

}
