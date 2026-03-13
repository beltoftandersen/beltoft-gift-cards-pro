<?php

namespace BgcwPro\ScheduledDelivery;

use BgcwPro\Support\Options;

defined( 'ABSPATH' ) || exit;

class ProductFields {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'bgcw_product_form_after_recipient_fields', [ __CLASS__, 'render_date_picker' ] );
		add_filter( 'bgcw_add_to_cart_data', [ __CLASS__, 'add_cart_data' ], 10, 2 );
		add_filter( 'bgcw_add_to_cart_validation', [ __CLASS__, 'validate' ], 10, 3 );
		add_filter( 'woocommerce_get_item_data', [ __CLASS__, 'display_cart_data' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'save_order_item_meta' ], 10, 4 );
	}

	/**
	 * Render a date and time picker for scheduled delivery on the product page.
	 *
	 * @param \WC_Product $product Current product.
	 */
	public static function render_date_picker( $product ) {
		if ( Options::get( 'scheduled_delivery' ) !== '1' ) {
			return;
		}

		$today = wp_date( 'Y-m-d' );
		?>
		<div class="bgcw-scheduled-delivery">
			<p class="form-row form-row-wide">
				<label for="bgcw_delivery_date">
					<?php esc_html_e( 'Delivery Date & Time (optional)', 'beltoft-gift-cards-pro' ); ?>
				</label>
				<span class="bgcw-delivery-datetime-row">
					<input
						type="date"
						name="bgcw_delivery_date"
						id="bgcw_delivery_date"
						class="input-text bgcw-delivery-date"
						min="<?php echo esc_attr( $today ); ?>"
					/>
					<select name="bgcw_delivery_hour" id="bgcw_delivery_hour" class="input-text bgcw-delivery-hour" disabled>
						<option value=""><?php esc_html_e( 'Hour', 'beltoft-gift-cards-pro' ); ?></option>
						<?php for ( $h = 0; $h < 24; $h++ ) : ?>
							<option value="<?php echo esc_attr( $h ); ?>"<?php selected( $h, 9 ); ?>>
								<?php echo esc_html( sprintf( '%02d:00', $h ) ); ?>
							</option>
						<?php endfor; ?>
					</select>
				</span>
				<span class="bgcw-delivery-date-note">
					<?php esc_html_e( 'Leave empty to send the gift card immediately.', 'beltoft-gift-cards-pro' ); ?>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Store the delivery date and hour in cart item data.
	 *
	 * @param array $cart_data  Cart item data.
	 * @param int   $product_id Product ID.
	 * @return array
	 */
	public static function add_cart_data( $cart_data, $product_id ) {
		if ( Options::get( 'scheduled_delivery' ) !== '1' ) {
			return $cart_data;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce handled by WooCommerce add-to-cart form.
		$date     = isset( $_POST['bgcw_delivery_date'] ) ? sanitize_text_field( wp_unslash( $_POST['bgcw_delivery_date'] ) ) : '';
		$raw_hour = isset( $_POST['bgcw_delivery_hour'] ) ? wp_unslash( $_POST['bgcw_delivery_hour'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via absint below.
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! empty( $date ) ) {
			$cart_data['bgcw_delivery_date'] = $date;
			// Default to 9 AM when the hour select is left on the empty placeholder.
			$hour = ( '' !== $raw_hour && is_numeric( $raw_hour ) ) ? absint( $raw_hour ) : 9;
			$cart_data['bgcw_delivery_hour'] = min( $hour, 23 );
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

		$date = isset( $post_data['bgcw_delivery_date'] ) ? sanitize_text_field( wp_unslash( $post_data['bgcw_delivery_date'] ) ) : '';

		if ( empty( $date ) ) {
			return $valid;
		}

		// Validate date format (YYYY-MM-DD).
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
			wc_add_notice( __( 'Please enter a valid delivery date.', 'beltoft-gift-cards-pro' ), 'error' );
			return false;
		}

		// Reject impossible calendar dates like 2026-02-31.
		if ( ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			wc_add_notice( __( 'Please enter a valid delivery date.', 'beltoft-gift-cards-pro' ), 'error' );
			return false;
		}

		$today = wp_date( 'Y-m-d' );

		if ( $date < $today ) {
			wc_add_notice( __( 'The delivery date cannot be in the past.', 'beltoft-gift-cards-pro' ), 'error' );
			return false;
		}

		return $valid;
	}

	/**
	 * Display the delivery date and time in the cart.
	 *
	 * @param array $item_data Cart item display data.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public static function display_cart_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['bgcw_delivery_date'] ) ) {
			return $item_data;
		}

		$hour      = isset( $cart_item['bgcw_delivery_hour'] ) ? absint( $cart_item['bgcw_delivery_hour'] ) : 9;
		$timestamp = strtotime( $cart_item['bgcw_delivery_date'] );
		$formatted = $timestamp ? wp_date( get_option( 'date_format' ), $timestamp ) : $cart_item['bgcw_delivery_date'];
		$formatted .= ' ' . sprintf( '%02d:00', $hour );

		$item_data[] = [
			'key'   => __( 'Delivery Date', 'beltoft-gift-cards-pro' ),
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
		if ( ! empty( $values['bgcw_delivery_date'] ) ) {
			$item->add_meta_data( '_bgcw_delivery_date', sanitize_text_field( $values['bgcw_delivery_date'] ) );
			$hour = isset( $values['bgcw_delivery_hour'] ) ? absint( $values['bgcw_delivery_hour'] ) : 9;
			$item->add_meta_data( '_bgcw_delivery_hour', min( 23, $hour ) );
		}
	}
}
