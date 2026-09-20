<?php

namespace BgcwPro\Gifting;

defined( 'ABSPATH' ) || exit;

/**
 * Show gifted products as "Gift: Product" in cart, checkout and orders.
 */
class CartDisplay {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_cart_item_name', [ __CLASS__, 'item_name' ], 10, 2 );
		add_filter( 'woocommerce_cart_item_thumbnail', [ __CLASS__, 'item_thumbnail' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'order_item_name' ], 20, 3 );
	}

	/**
	 * Gifted product for a cart item, if any.
	 *
	 * @return \WC_Product|null
	 */
	private static function gifted( $cart_item ) {
		if ( empty( $cart_item['bgcw_product_id'] ) ) {
			return null;
		}
		$product = wc_get_product( (int) $cart_item['bgcw_product_id'] );

		return $product ? $product : null;
	}

	/**
	 * Gift label for a product name.
	 */
	public static function label( string $name ): string {
		/* translators: %s: product name */
		return sprintf( __( 'Gift: %s', 'beltoft-gift-cards-pro' ), $name );
	}

	/**
	 * @param string $name      Item name HTML.
	 * @param array  $cart_item Cart item.
	 * @return string
	 */
	public static function item_name( $name, $cart_item ) {
		$gifted = self::gifted( $cart_item );
		if ( ! $gifted ) {
			return $name;
		}
		$label = esc_html( self::label( $gifted->get_name() ) );

		return is_cart() ? '<a href="' . esc_url( $gifted->get_permalink() ) . '">' . $label . '</a>' : $label;
	}

	/**
	 * @param string $thumb     Thumbnail HTML.
	 * @param array  $cart_item Cart item.
	 * @return string
	 */
	public static function item_thumbnail( $thumb, $cart_item ) {
		$gifted = self::gifted( $cart_item );

		return $gifted ? $gifted->get_image() : $thumb;
	}

	/**
	 * Name the order line after the gifted product.
	 *
	 * @param \WC_Order_Item_Product $item          Order item.
	 * @param string                 $cart_item_key Cart key.
	 * @param array                  $values        Cart item data.
	 */
	public static function order_item_name( $item, $cart_item_key, $values ) {
		$gifted = self::gifted( $values );
		if ( $gifted ) {
			$item->set_name( self::label( $gifted->get_name() ) );
		}
	}
}
