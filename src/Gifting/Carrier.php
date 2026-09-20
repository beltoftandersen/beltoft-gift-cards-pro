<?php

namespace BgcwPro\Gifting;

use BgcwPro\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * The hidden gift-card product that carries "give as a gift" purchases through the cart.
 */
class Carrier {

	const OPTION = 'gift_carrier_product_id';
	const META   = '_bgcw_pro_carrier';

	/**
	 * Carrier product ID, creating the product when missing.
	 *
	 * @return int 0 on failure.
	 */
	public static function id(): int {
		$id      = (int) Options::get( self::OPTION );
		$product = $id ? wc_get_product( $id ) : null;

		if ( $product && 'gift-card' === $product->get_type() && 'trash' !== $product->get_status() ) {
			// An admin may have set the hidden product to draft/private; it must stay purchasable.
			if ( 'publish' !== $product->get_status() ) {
				$product->set_status( 'publish' );
				$product->save();
			}
			if ( 'none' !== $product->get_tax_status() ) {
				$product->set_tax_status( 'none' );
				$product->save();
			}
			return $id;
		}

		return self::create();
	}

	/**
	 * Whether a product is the carrier.
	 *
	 * @param \WC_Product|int|null $product Product or ID.
	 */
	public static function is_carrier( $product ): bool {
		$id = $product instanceof \WC_Product ? $product->get_id() : (int) $product;

		return $id > 0 && 'yes' === get_post_meta( $id, self::META, true );
	}

	/**
	 * Create the hidden carrier product.
	 */
	private static function create(): int {
		if ( ! class_exists( '\\Bgcw\\Product\\WC_Product_Gift_Card' ) ) {
			return 0;
		}

		$product = new \Bgcw\Product\WC_Product_Gift_Card();
		$product->set_name( __( 'Gift card', 'beltoft-gift-cards-pro' ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_regular_price( '0' );
		$product->set_virtual( true );
		$product->set_tax_status( 'none' );
		$product->set_sold_individually( false );
		$product->update_meta_data( self::META, 'yes' );
		$product->update_meta_data( '_bgcw_amounts', '' );
		$id = $product->save();

		if ( ! $id ) {
			return 0;
		}

		wp_set_object_terms( $id, 'gift-card', 'product_type' );
		Options::set( self::OPTION, (string) $id );

		return (int) $id;
	}
}
