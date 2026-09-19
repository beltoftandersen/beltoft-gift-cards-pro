<?php

namespace BgcwPro\Pdf;

use BgcwPro\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Attaches the PDF to the gift card delivery email and swaps in a short neutral email body.
 */
class EmailAttachment {

	const EMAIL_ID = 'bgcw_gift_card_delivery';
	const TEMPLATE = 'emails/pdf-notice.php';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_email_attachments', [ __CLASS__, 'attach' ], 10, 4 );
		add_filter( 'bgcw_email_template_html', [ __CLASS__, 'swap_template' ], 20, 2 );
		add_filter( 'woocommerce_locate_template', [ __CLASS__, 'locate_template' ], 10, 2 );
	}

	/**
	 * Resolve our notice template to the Pro plugin path (no-op for every other template).
	 *
	 * @param string $located       Located path.
	 * @param string $template_name Template being looked up.
	 * @return string
	 */
	public static function locate_template( $located, $template_name ) {
		if ( self::TEMPLATE !== $template_name ) {
			return $located;
		}

		$full_path = BGCW_PRO_PATH . 'templates/' . self::TEMPLATE;

		return file_exists( $full_path ) ? $full_path : $located;
	}

	/**
	 * Whether PDF delivery is enabled.
	 */
	public static function enabled(): bool {
		return '1' === Options::get( 'pdf_enabled' );
	}

	/**
	 * Add the generated PDF to the delivery email.
	 *
	 * @param array  $attachments Existing attachments.
	 * @param string $email_id    WooCommerce email ID.
	 * @param mixed  $object      Email object payload (unused).
	 * @param mixed  $email       WC_Email instance (has ->gift_card on the delivery email).
	 * @return array
	 */
	public static function attach( $attachments, $email_id, $object = null, $email = null ) {
		$attachments = is_array( $attachments ) ? $attachments : [];

		if ( self::EMAIL_ID !== $email_id || ! self::enabled() ) {
			return $attachments;
		}

		$gift_card = self::gift_card_from( $object, $email );
		if ( ! $gift_card ) {
			return $attachments;
		}

		$path = PdfGenerator::get_or_generate( $gift_card );
		if ( is_wp_error( $path ) ) {
			return $attachments;
		}

		$attachments[] = $path;

		return $attachments;
	}

	/**
	 * Use the short "your gift card is attached" email when the PDF is available.
	 *
	 * @param string      $template  Relative template path.
	 * @param object|null $gift_card Gift card row.
	 * @return string
	 */
	public static function swap_template( $template, $gift_card ) {
		if ( ! self::enabled() || ! $gift_card || empty( $gift_card->id ) ) {
			return $template;
		}

		// Only swap when a PDF actually exists, so a failed render still delivers the code inline.
		// WC_Email builds the content before the attachments, so this is the first (and only) render;
		// attach() then reuses the stored file.
		$path = PdfGenerator::get_or_generate( $gift_card );
		if ( is_wp_error( $path ) ) {
			return $template;
		}

		if ( ! file_exists( BGCW_PRO_PATH . 'templates/' . self::TEMPLATE ) ) {
			return $template;
		}

		return self::TEMPLATE;
	}

	/**
	 * Resolve the gift card row from the filter arguments.
	 *
	 * @return object|null
	 */
	private static function gift_card_from( $object, $email ) {
		if ( is_object( $email ) && ! empty( $email->gift_card ) && is_object( $email->gift_card ) ) {
			return $email->gift_card;
		}
		if ( is_object( $object ) && isset( $object->code, $object->id ) ) {
			return $object;
		}

		return null;
	}
}
