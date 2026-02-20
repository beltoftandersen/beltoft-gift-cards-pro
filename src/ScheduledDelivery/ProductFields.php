<?php

namespace GiftCardsPro\ScheduledDelivery;

use GiftCardsPro\Support\Options;

defined( 'ABSPATH' ) || exit;

class ProductFields {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'wcgc_product_form_after_recipient_fields', [ __CLASS__, 'render_date_picker' ] );
		add_filter( 'wcgc_add_to_cart_data', [ __CLASS__, 'add_cart_data' ], 10, 2 );
		add_filter( 'wcgc_add_to_cart_validation', [ __CLASS__, 'validate' ], 10, 3 );
		add_filter( 'woocommerce_get_item_data', [ __CLASS__, 'display_cart_data' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'save_order_item_meta' ], 10, 4 );
	}

	/**
	 * Render a date picker for scheduled delivery on the product page.
	 *
	 * @param \WC_Product $product Current product.
	 */
	public static function render_date_picker( $product ) {
		if ( Options::get( 'scheduled_delivery' ) !== '1' ) {
			return;
		}

		$today = wp_date( 'Y-m-d' );
		?>
		<div class="wcgc-scheduled-delivery">
			<p class="form-row form-row-wide">
				<label for="wcgc_delivery_date">
					<?php esc_html_e( 'Delivery Date (optional)', 'smart-gift-cards-for-woocommerce-pro' ); ?>
				</label>
				<input
					type="date"
					name="wcgc_delivery_date"
					id="wcgc_delivery_date"
					class="input-text"
					min="<?php echo esc_attr( $today ); ?>"
				/>
				<span class="wcgc-delivery-date-note">
					<?php esc_html_e( 'Leave empty to send the gift card immediately.', 'smart-gift-cards-for-woocommerce-pro' ); ?>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Store the delivery date in cart item data.
	 *
	 * @param array $cart_data  Cart item data.
	 * @param int   $product_id Product ID.
	 * @return array
	 */
	public static function add_cart_data( $cart_data, $product_id ) {
		if ( Options::get( 'scheduled_delivery' ) !== '1' ) {
			return $cart_data;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce handled by WooCommerce add-to-cart form.
		$date = isset( $_POST['wcgc_delivery_date'] ) ? sanitize_text_field( wp_unslash( $_POST['wcgc_delivery_date'] ) ) : '';

		if ( ! empty( $date ) ) {
			$cart_data['wcgc_delivery_date'] = $date;
		}

		return $cart_data;
	}

	/**
	 * Validate that the delivery date is not in the past.
	 *
	 * @param bool  $valid       Current validation result.
	 * @param int   $product_id  Product ID.
	 * @param array $post_data   Raw POST data (unsanitized).
	 * @return bool
	 */
	public static function validate( $valid, $product_id, $post_data ) {
		if ( Options::get( 'scheduled_delivery' ) !== '1' ) {
			return $valid;
		}

		$date = isset( $post_data['wcgc_delivery_date'] ) ? sanitize_text_field( wp_unslash( $post_data['wcgc_delivery_date'] ) ) : '';

		if ( empty( $date ) ) {
			return $valid;
		}

		// Validate date format (YYYY-MM-DD).
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			wc_add_notice( __( 'Please enter a valid delivery date.', 'smart-gift-cards-for-woocommerce-pro' ), 'error' );
			return false;
		}

		$today = wp_date( 'Y-m-d' );

		if ( $date < $today ) {
			wc_add_notice( __( 'The delivery date cannot be in the past.', 'smart-gift-cards-for-woocommerce-pro' ), 'error' );
			return false;
		}

		return $valid;
	}

	/**
	 * Display the delivery date in the cart.
	 *
	 * @param array $item_data Cart item display data.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public static function display_cart_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['wcgc_delivery_date'] ) ) {
			return $item_data;
		}

		$timestamp = strtotime( $cart_item['wcgc_delivery_date'] );
		$formatted = $timestamp ? wp_date( get_option( 'date_format' ), $timestamp ) : $cart_item['wcgc_delivery_date'];

		$item_data[] = [
			'key'   => __( 'Delivery Date', 'smart-gift-cards-for-woocommerce-pro' ),
			'value' => esc_html( $formatted ),
		];

		return $item_data;
	}

	/**
	 * Save the delivery date to order item meta.
	 *
	 * @param \WC_Order_Item_Product $item          Order item.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array                  $values        Cart item data.
	 * @param \WC_Order              $order         Order object.
	 */
	public static function save_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values['wcgc_delivery_date'] ) ) {
			$item->add_meta_data( '_wcgc_delivery_date', sanitize_text_field( $values['wcgc_delivery_date'] ) );
		}
	}
}
