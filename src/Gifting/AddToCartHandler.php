<?php

namespace BgcwPro\Gifting;

defined( 'ABSPATH' ) || exit;

/**
 * Turns "Give as a gift" submissions into a carrier gift-card line locked to the product.
 */
class AddToCartHandler {

	const FLAG = 'bgcw_gift';
	const TYPE = 'bgcw_gift';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_add_to_cart_handler', [ __CLASS__, 'pick_handler' ], 10, 2 );
		add_action( 'woocommerce_add_to_cart_handler_' . self::TYPE, [ __CLASS__, 'handle' ] );
	}

	/**
	 * Route giftable products to our handler when the gift flag is posted.
	 *
	 * @param string      $type    Handler type (product type by default).
	 * @param \WC_Product $product Product being added.
	 * @return string
	 */
	public static function pick_handler( $type, $product ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- WooCommerce add-to-cart form.
		if ( empty( $_REQUEST[ self::FLAG ] ) || ! Giftable::is_giftable( $product ) ) {
			return $type;
		}

		return self::TYPE;
	}

	/**
	 * WooCommerce add-to-cart handler for gifts.
	 *
	 * @param string|false $url Redirect URL WooCommerce would use.
	 */
	public static function handle( $url = false ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- WooCommerce add-to-cart form.
		$product_id = isset( $_REQUEST['add-to-cart'] ) ? absint( wp_unslash( $_REQUEST['add-to-cart'] ) ) : 0;
		$quantity   = isset( $_REQUEST['quantity'] ) ? wc_stock_amount( absint( wp_unslash( $_REQUEST['quantity'] ) ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended

		$key = self::add_gift_to_cart( $product_id, max( 1, $quantity ) );
		if ( ! $key ) {
			return;
		}

		wc_add_to_cart_message( [ Carrier::id() => $quantity ], true );

		if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}
		if ( $url ) {
			wp_safe_redirect( $url );
			exit;
		}
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : get_permalink( $product_id ) );
		exit;
	}

	/**
	 * Add a gift of $product_id to the cart. Recipient fields are read from $_POST by the free plugin.
	 *
	 * @return string|false Cart item key.
	 */
	public static function add_gift_to_cart( int $product_id, int $quantity = 1 ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! Giftable::is_giftable( $product ) ) {
			wc_add_notice( __( 'This product cannot be given as a gift.', 'beltoft-gift-cards-pro' ), 'error' );
			return false;
		}

		$amount = Giftable::gift_amount( $product );
		if ( $amount <= 0 ) {
			wc_add_notice( __( 'This product has no price, so it cannot be given as a gift.', 'beltoft-gift-cards-pro' ), 'error' );
			return false;
		}

		$carrier = Carrier::id();
		if ( ! $carrier || ! WC()->cart ) {
			wc_add_notice( __( 'Gifting is not available right now. Please try again later.', 'beltoft-gift-cards-pro' ), 'error' );
			return false;
		}

		// The free plugin reads the amount from POST and enforces min/max on custom amounts; a gift is priced by its product.
		$_POST['bgcw_amount'] = (string) $amount;
		unset( $_POST['bgcw_custom_amount'] );
		add_filter( 'bgcw_validate_amount_limits', '__return_false' );

		$key = WC()->cart->add_to_cart( $carrier, $quantity, 0, [], [ 'bgcw_product_id' => $product->get_id() ] );

		remove_filter( 'bgcw_validate_amount_limits', '__return_false' );

		return $key ? $key : false;
	}
}
