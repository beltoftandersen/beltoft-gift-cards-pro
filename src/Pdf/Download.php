<?php

namespace BgcwPro\Pdf;

use Bgcw\GiftCard\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Nonce-protected PDF downloads for customers (My Account) and admins (design samples).
 */
class Download {

	const ACTION_DOWNLOAD = 'bgcw_pro_pdf_download';
	const ACTION_SAMPLE   = 'bgcw_pro_pdf_sample';

	/**
	 * Register hooks.
	 */
	public static function init() {
		// Customer download runs on the front end (wc-ajax): many sites protect /wp-admin/ with a proxy or SSO.
		add_action( 'wc_ajax_' . self::ACTION_DOWNLOAD, [ __CLASS__, 'handle_download' ] );
		// Older links from 1.5.x still work.
		add_action( 'admin_post_' . self::ACTION_DOWNLOAD, [ __CLASS__, 'handle_download' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION_DOWNLOAD, [ __CLASS__, 'handle_download' ] );
		add_action( 'admin_post_' . self::ACTION_SAMPLE, [ __CLASS__, 'handle_sample' ] );
		add_action( 'bgcw_my_account_card_actions', [ __CLASS__, 'render_button' ] );
	}

	/**
	 * Download URL for a card (for the logged-in user).
	 */
	public static function url_for_card( $gc ): string {
		return wp_nonce_url(
			add_query_arg( [ 'card' => (int) $gc->id ], \WC_AJAX::get_endpoint( self::ACTION_DOWNLOAD ) ),
			self::ACTION_DOWNLOAD . '_' . (int) $gc->id
		);
	}

	/**
	 * Sample URL for a design (admins).
	 */
	public static function sample_url( string $design ): string {
		return wp_nonce_url(
			add_query_arg( [ 'action' => self::ACTION_SAMPLE, 'design' => Designs::normalize( $design ) ], admin_url( 'admin-post.php' ) ),
			self::ACTION_SAMPLE
		);
	}

	/**
	 * Whether a user may download a card: the buyer (customer_id) or the recipient (email match).
	 */
	public static function can_access( $gc, int $user_id ): bool {
		if ( $user_id <= 0 || ! $gc ) {
			return false;
		}

		if ( (int) $gc->customer_id === $user_id ) {
			return true;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || empty( $gc->recipient_email ) ) {
			return false;
		}

		return strtolower( (string) $user->user_email ) === strtolower( (string) $gc->recipient_email );
	}

	/**
	 * "Download PDF" button inside each My Account card row.
	 */
	public static function render_button( $gc ) {
		if ( ! EmailAttachment::enabled() || ! is_user_logged_in() ) {
			return;
		}

		echo '<a class="button bgcw-pro-pdf-download" href="' . esc_url( self::url_for_card( $gc ) ) . '">'
			. esc_html__( 'Download PDF', 'beltoft-gift-cards-pro' ) . '</a>';
	}

	/**
	 * Stream a card PDF to its owner.
	 */
	public static function handle_download() {
		// Nonces are per session: a logged-out visitor is sent to log in and back to My Account,
		// where a fresh download link is rendered.
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( wc_get_account_endpoint_url( 'gift-cards' ) ) );
			exit;
		}

		$card_id = isset( $_GET['card'] ) ? absint( wp_unslash( $_GET['card'] ) ) : 0;

		if ( ! $card_id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::ACTION_DOWNLOAD . '_' . $card_id ) ) {
			wp_die( esc_html__( 'This download link is not valid.', 'beltoft-gift-cards-pro' ), '', [ 'response' => 403 ] );
		}

		$gc = Repository::find( $card_id );
		if ( ! $gc || ! self::can_access( $gc, get_current_user_id() ) ) {
			wp_die( esc_html__( 'You do not have access to this gift card.', 'beltoft-gift-cards-pro' ), '', [ 'response' => 403 ] );
		}

		$path = PdfGenerator::get_or_generate( $gc );
		if ( is_wp_error( $path ) ) {
			wp_die( esc_html__( 'The gift card PDF could not be created. Please try again later.', 'beltoft-gift-cards-pro' ), '', [ 'response' => 500 ] );
		}

		self::stream( $path, 'gift-card-' . sanitize_file_name( $gc->code ) . '.pdf' );
	}

	/**
	 * Stream a sample PDF for a design (admin).
	 */
	public static function handle_sample() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::ACTION_SAMPLE ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'beltoft-gift-cards-pro' ), '', [ 'response' => 403 ] );
		}

		$design = isset( $_GET['design'] ) ? Designs::normalize( sanitize_key( wp_unslash( $_GET['design'] ) ) ) : Designs::DEFAULT_SLUG;
		$path   = PdfGenerator::sample( $design );
		if ( is_wp_error( $path ) ) {
			wp_die( esc_html( $path->get_error_message() ), '', [ 'response' => 500 ] );
		}

		self::stream( $path, 'gift-card-sample-' . $design . '.pdf', true );
	}

	/**
	 * Send a PDF file to the browser.
	 */
	private static function stream( string $path, string $filename, bool $delete_after = false ) {
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming a generated PDF.
		readfile( $path );

		if ( $delete_after ) {
			wp_delete_file( $path );
		}
		exit;
	}
}
