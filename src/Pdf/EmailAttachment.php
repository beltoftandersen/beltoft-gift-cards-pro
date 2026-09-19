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
		$path = PdfGenerator::get_or_generate( $gift_card );
		if ( is_wp_error( $path ) ) {
			return $template;
		}

		$full_path = BGCW_PRO_PATH . 'templates/' . self::TEMPLATE;
		if ( ! file_exists( $full_path ) ) {
			return $template;
		}

		add_filter(
			'woocommerce_locate_template',
			function ( $located, $template_name ) use ( $full_path ) {
				return self::TEMPLATE === $template_name ? $full_path : $located;
			},
			10,
			2
		);

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
