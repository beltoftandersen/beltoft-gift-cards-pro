<?php

namespace BgcwPro\Pdf;

use BgcwPro\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Generates and stores gift card PDFs with dompdf.
 */
class PdfGenerator {

	const DIR_NAME = 'bgcw-pdf';
	const LOG_SOURCE = 'bgcw-pro-pdf';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'bgcw_gift_card_deleted', [ __CLASS__, 'delete_for_card' ] );
	}

	/**
	 * Table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'bgcw_pro_pdf';
	}

	/**
	 * Storage directory (created on first use, with index.php and .htaccess guards).
	 *
	 * @return string|\WP_Error Absolute path without trailing slash.
	 */
	public static function dir() {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new \WP_Error( 'bgcw_pdf_upload_dir', $upload['error'] );
		}

		$dir = trailingslashit( $upload['basedir'] ) . self::DIR_NAME;

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'bgcw_pdf_mkdir', __( 'Could not create the PDF storage folder.', 'beltoft-gift-cards-pro' ) );
		}

		$guards = [
			'index.php' => "<?php // Silence is golden.\n",
			'.htaccess' => "Order deny,allow\nDeny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n",
		];
		foreach ( $guards as $name => $content ) {
			$path = $dir . '/' . $name;
			if ( ! file_exists( $path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing a guard file in uploads.
				file_put_contents( $path, $content );
			}
		}

		$fonts = $dir . '/fonts';
		if ( ! is_dir( $fonts ) ) {
			wp_mkdir_p( $fonts );
		}

		return $dir;
	}

	/**
	 * Design slug for a gift card, from its order item meta when available.
	 */
	public static function design_for_card( $gc ): string {
		if ( empty( $gc->order_id ) ) {
			return Designs::DEFAULT_SLUG;
		}

		$order = wc_get_order( (int) $gc->order_id );
		if ( ! $order ) {
			return Designs::DEFAULT_SLUG;
		}

		$fallback = '';
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product || 'gift-card' !== $product->get_type() ) {
				continue;
			}
			$design = (string) $item->get_meta( '_bgcw_design_theme' );
			if ( '' === $fallback && '' !== $design ) {
				$fallback = $design;
			}
			// Same multi-signal match (amount, names, emails, message) the scheduler uses.
			if ( '' !== $design && \BgcwPro\ScheduledDelivery\Scheduler::item_matches_gift_card( $item, $gc ) ) {
				return Designs::normalize( $design );
			}
		}

		return Designs::normalize( $fallback );
	}

	/**
	 * Card data array for the renderer.
	 */
	public static function data_for_card( $gc ): array {
		return [
			'amount'         => (float) $gc->initial_amount,
			'currency'       => (string) $gc->currency,
			'code'           => (string) $gc->code,
			'recipient_name' => (string) $gc->recipient_name,
			'sender_name'    => (string) $gc->sender_name,
			'message'        => (string) $gc->message,
			'expires_at'     => $gc->expires_at,
		];
	}

	/**
	 * Stored record for a card.
	 *
	 * @return object|null
	 */
	public static function record( int $gift_card_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bgcw_pro_pdf WHERE gift_card_id = %d", $gift_card_id ) );
	}

	/**
	 * Return the stored PDF path, generating it when missing or outdated.
	 *
	 * @return string|\WP_Error
	 */
	public static function get_or_generate( $gc ) {
		$dir = self::dir();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$row = self::record( (int) $gc->id );
		if ( $row ) {
			$path = self::path_for( $row->file, $dir );
			if ( $path && file_exists( $path ) && (int) $row->design_version === Designs::version() ) {
				return $path;
			}
		}

		return self::generate( $gc, $row, $dir );
	}

	/**
	 * Generate (or regenerate) the PDF for a gift card.
	 *
	 * @param object            $gc  Gift card row.
	 * @param object|null|false $row Existing record when already loaded (false = not loaded).
	 * @param string            $dir Storage dir when already resolved.
	 * @return string|\WP_Error Absolute path.
	 */
	public static function generate( $gc, $row = false, string $dir = '' ) {
		global $wpdb;

		if ( '' === $dir ) {
			$dir = self::dir();
			if ( is_wp_error( $dir ) ) {
				return $dir;
			}
		}

		$design = self::design_for_card( $gc );
		$html   = CardRenderer::render( $design, self::data_for_card( $gc ), CardRenderer::MODE_PDF );
		$bytes  = self::render_pdf( $html, $dir );
		if ( is_wp_error( $bytes ) ) {
			return $bytes;
		}

		$file = (int) $gc->id . '-' . wp_generate_password( 16, false, false ) . '.pdf';
		$path = $dir . '/' . $file;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Binary PDF write in uploads.
		if ( false === file_put_contents( $path, $bytes ) ) {
			return self::fail( 'bgcw_pdf_write', __( 'Could not write the PDF file.', 'beltoft-gift-cards-pro' ) );
		}

		$old = false === $row ? self::record( (int) $gc->id ) : $row;
		if ( $old ) {
			$old_path = self::path_for( $old->file, $dir );
			if ( $old_path && $old_path !== $path && file_exists( $old_path ) ) {
				wp_delete_file( $old_path );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->replace(
			self::table(),
			[
				'gift_card_id'   => (int) $gc->id,
				'file'           => $file,
				'design'         => $design,
				'design_version' => Designs::version(),
				'generated_at'   => current_time( 'mysql', true ),
			],
			[ '%d', '%s', '%s', '%d', '%s' ]
		);

		return $path;
	}

	/**
	 * Write a sample PDF for a design (placeholder data) to a temp file.
	 *
	 * @return string|\WP_Error
	 */
	public static function sample( string $design ) {
		$dir = self::dir();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$html  = CardRenderer::render( $design, CardRenderer::placeholders(), CardRenderer::MODE_PDF );
		$bytes = self::render_pdf( $html, $dir );
		if ( is_wp_error( $bytes ) ) {
			return $bytes;
		}

		$path = wp_tempnam( 'bgcw-sample-' . Designs::normalize( $design ) . '.pdf' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Temp file.
		if ( ! $path || false === file_put_contents( $path, $bytes ) ) {
			return self::fail( 'bgcw_pdf_write', __( 'Could not write the PDF file.', 'beltoft-gift-cards-pro' ) );
		}

		return $path;
	}

	/**
	 * Remove the stored PDF and record for a card.
	 */
	public static function delete_for_card( $gift_card_id ) {
		global $wpdb;

		$row = self::record( (int) $gift_card_id );
		if ( ! $row ) {
			return;
		}

		$path = self::path_for( $row->file );
		if ( $path && file_exists( $path ) ) {
			wp_delete_file( $path );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->delete( self::table(), [ 'gift_card_id' => (int) $gift_card_id ], [ '%d' ] );
	}

	/**
	 * Absolute path for a stored file name (rejects anything outside the storage dir).
	 */
	private static function path_for( string $file, string $dir = '' ) {
		$file = basename( $file );
		if ( '' === $file || ! preg_match( '/^\d+-[A-Za-z0-9]+\.pdf$/', $file ) ) {
			return '';
		}

		if ( '' === $dir ) {
			$dir = self::dir();
			if ( is_wp_error( $dir ) ) {
				return '';
			}
		}

		return $dir . '/' . $file;
	}

	/**
	 * Run dompdf on the card HTML.
	 *
	 * @return string|\WP_Error PDF bytes.
	 */
	private static function render_pdf( string $html, string $dir ) {
		if ( ! Loader::load() ) {
			return self::fail( 'bgcw_pdf_library', __( 'The PDF library is missing.', 'beltoft-gift-cards-pro' ) );
		}

		$upload = wp_upload_dir();

		try {
			$options = new \Dompdf\Options();
			$options->set( 'isRemoteEnabled', false );
			$options->set( 'isPhpEnabled', false );
			$options->set( 'isJavascriptEnabled', false );
			$options->set( 'chroot', [ untrailingslashit( BGCW_PRO_PATH ), untrailingslashit( $upload['basedir'] ) ] );
			$options->set( 'fontDir', $dir . '/fonts' );
			$options->set( 'fontCache', $dir . '/fonts' );
			$options->set( 'isFontSubsettingEnabled', true );
			$options->set( 'dpi', 96 );
			$options->set( 'tempDir', get_temp_dir() );

			$dompdf = new \Dompdf\Dompdf( $options );
			// A5 landscape in points (210 x 148 mm).
			$dompdf->setPaper( [ 0, 0, 595.276, 419.528 ] );
			$dompdf->loadHtml( $html, 'UTF-8' );
			$dompdf->render();

			$bytes = $dompdf->output();
		} catch ( \Throwable $e ) {
			return self::fail( 'bgcw_pdf_failed', $e->getMessage() );
		}

		if ( ! is_string( $bytes ) || 0 !== strpos( $bytes, '%PDF' ) ) {
			return self::fail( 'bgcw_pdf_failed', __( 'The PDF renderer returned no document.', 'beltoft-gift-cards-pro' ) );
		}

		return $bytes;
	}

	/**
	 * Log and return an error.
	 */
	private static function fail( string $code, string $message ): \WP_Error {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $message, [ 'source' => self::LOG_SOURCE ] );
		}

		return new \WP_Error( $code, $message );
	}
}
