<?php

namespace BgcwPro\Pdf;

use BgcwPro\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Pro's own copy of the card logo, kept in the PDF storage folder.
 *
 * Offload tools (Amazon S3 and similar) can remove the Media Library original
 * from this server. The copy lives outside the Media Library, so it stays, and
 * dompdf keeps reading a local file with remote loading switched off.
 */
class Logo {

	/**
	 * Option holding the copy record: attachment_id, file, source, source_mtime.
	 */
	const OPTION = 'bgcw_pro_pdf_logo';

	/**
	 * Image types dompdf renders, mapped to the copy's extension.
	 */
	const TYPES = [
		'image/png'  => 'png',
		'image/jpeg' => 'jpg',
		'image/gif'  => 'gif',
		'image/webp' => 'webp',
	];

	/**
	 * Copy an attachment's local file into the PDF folder.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|\WP_Error Absolute path of the copy.
	 */
	public static function store( int $attachment_id ) {
		$source = self::local_source( $attachment_id );
		if ( '' === $source ) {
			return new \WP_Error(
				'bgcw_pro_logo_missing',
				__( 'The logo was not saved because its file is not on this server. It may have been moved to external storage such as Amazon S3. Keep a copy on the server or pause offloading, then choose the logo again.', 'beltoft-gift-cards-pro' )
			);
		}

		$mime = wp_get_image_mime( $source );
		if ( ! $mime || ! isset( self::TYPES[ $mime ] ) ) {
			return new \WP_Error(
				'bgcw_pro_logo_invalid',
				__( 'The logo must be a PNG, JPG, GIF or WebP image.', 'beltoft-gift-cards-pro' )
			);
		}

		$dir = PdfGenerator::dir();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$file = 'logo-' . $attachment_id . '.' . self::TYPES[ $mime ];
		$path = $dir . '/' . $file;
		// Write to a temp name and rename, so a PDF rendering at the same time never reads a half-written file.
		$tmp = $path . '.' . wp_generate_password( 8, false, false ) . '.tmp';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic replace of a file in the Pro uploads folder.
		if ( ! copy( $source, $tmp ) || ! rename( $tmp, $path ) ) {
			wp_delete_file( $tmp );
			return new \WP_Error( 'bgcw_pro_logo_write', __( 'Could not save a copy of the logo.', 'beltoft-gift-cards-pro' ) );
		}

		$old = self::record();
		if ( $old && $old['file'] !== $file ) {
			wp_delete_file( $dir . '/' . $old['file'] );
		}

		update_option(
			self::OPTION,
			[
				'attachment_id' => $attachment_id,
				'file'          => $file,
				'source'        => $source,
				'source_mtime'  => (int) filemtime( $source ),
			],
			false
		);

		return $path;
	}

	/**
	 * Path of the logo copy for the configured logo, or '' when there is none.
	 *
	 * Creates the copy on first use (logos chosen before copies existed) and
	 * refreshes it when the original changed, as long as the original is local.
	 *
	 * @return string
	 */
	public static function path(): string {
		$attachment_id = (int) Options::get( 'pdf_logo_id' );
		if ( $attachment_id <= 0 ) {
			return '';
		}

		$dir = PdfGenerator::dir();
		if ( is_wp_error( $dir ) ) {
			return '';
		}

		$record = self::record();
		$copy   = ( $record && $attachment_id === $record['attachment_id'] ) ? $dir . '/' . $record['file'] : '';
		$copy   = ( '' !== $copy && file_exists( $copy ) ) ? $copy : '';

		$source = self::local_source( $attachment_id );
		if ( '' !== $source && ( '' === $copy || $record['source'] !== $source || $record['source_mtime'] !== (int) filemtime( $source ) ) ) {
			$stored = self::store( $attachment_id );
			if ( ! is_wp_error( $stored ) ) {
				return $stored;
			}
		}

		return $copy;
	}

	/**
	 * Delete the copy and its record.
	 */
	public static function delete() {
		$record = self::record();
		$dir    = PdfGenerator::dir();
		if ( $record && ! is_wp_error( $dir ) ) {
			wp_delete_file( $dir . '/' . $record['file'] );
		}
		delete_option( self::OPTION );
	}

	/**
	 * The attachment's file when it is on this server (not a stream such as s3://).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private static function local_source( int $attachment_id ): string {
		$source = $attachment_id > 0 ? get_attached_file( $attachment_id ) : false;
		if ( ! $source || wp_is_stream( $source ) || ! file_exists( $source ) ) {
			return '';
		}
		return $source;
	}

	/**
	 * Stored copy record, normalized.
	 *
	 * @return array{attachment_id:int,file:string,source:string,source_mtime:int}|null
	 */
	private static function record() {
		$record = get_option( self::OPTION );
		if ( ! is_array( $record ) || empty( $record['file'] ) ) {
			return null;
		}
		return [
			'attachment_id' => (int) ( $record['attachment_id'] ?? 0 ),
			'file'          => sanitize_file_name( basename( (string) $record['file'] ) ),
			'source'        => (string) ( $record['source'] ?? '' ),
			'source_mtime'  => (int) ( $record['source_mtime'] ?? 0 ),
		];
	}
}
