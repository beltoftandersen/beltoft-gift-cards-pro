<?php

namespace BgcwPro\Gifting;

use BgcwPro\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Which products can be given as a gift (per-product checkbox or by category).
 */
class Giftable {

	const META = '_bgcw_pro_giftable';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_product_options_general_product_data', [ __CLASS__, 'render_checkbox' ] );
		add_action( 'woocommerce_process_product_meta', [ __CLASS__, 'save_checkbox' ] );
	}

	/**
	 * Whether a product may be gifted.
	 *
	 * @param \WC_Product|int|null $product Product or ID.
	 */
	public static function is_giftable( $product ): bool {
		$product = is_numeric( $product ) ? wc_get_product( (int) $product ) : $product;
		if ( ! $product instanceof \WC_Product ) {
			return false;
		}
		if ( 'gift-card' === $product->get_type() || Carrier::is_carrier( $product ) ) {
			return false;
		}
		if ( ! $product->is_purchasable() ) {
			return false;
		}

		$parent_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();

		if ( 'yes' === get_post_meta( $parent_id, self::META, true ) ) {
			return (bool) apply_filters( 'bgcw_pro_product_is_giftable', true, $product );
		}

		$category_ids = array_map( 'intval', (array) Options::get( 'giftable_category_ids' ) );
		$giftable     = ! empty( $category_ids ) && ! empty( array_intersect( $category_ids, wc_get_product_term_ids( $parent_id, 'product_cat' ) ) );

		/**
		 * Filter whether a product can be given as a gift.
		 *
		 * @param bool        $giftable Result.
		 * @param \WC_Product $product  Product.
		 */
		return (bool) apply_filters( 'bgcw_pro_product_is_giftable', $giftable, $product );
	}

	/**
	 * Amount the gift card is issued for: the product's displayed price (min price for variable products).
	 */
	public static function gift_amount( \WC_Product $product ): float {
		if ( $product->is_type( 'variable' ) ) {
			$prices = $product->get_variation_prices( true );
			$min    = ! empty( $prices['price'] ) ? min( $prices['price'] ) : 0;

			return (float) $min;
		}

		return (float) wc_get_price_to_display( $product );
	}

	/**
	 * Product edit checkbox.
	 */
	public static function render_checkbox() {
		woocommerce_wp_checkbox( [
			'id'          => self::META,
			'label'       => __( 'Can be given as a gift', 'beltoft-gift-cards-pro' ),
			'description' => __( 'Shows a "Give as a gift" option on the product page. The buyer pays now and the recipient gets a gift card locked to this product.', 'beltoft-gift-cards-pro' ),
		] );
	}

	/**
	 * Save the checkbox (WooCommerce verifies the product nonce).
	 *
	 * @param int $product_id Product ID.
	 */
	public static function save_checkbox( $product_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Handled by WooCommerce.
		$value = isset( $_POST[ self::META ] ) ? 'yes' : 'no';
		update_post_meta( $product_id, self::META, $value );
	}
}
