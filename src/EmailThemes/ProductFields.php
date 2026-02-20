<?php

namespace GiftCardsPro\EmailThemes;

use GiftCardsPro\Support\Options;
use GiftCardsPro\EmailThemes\ThemeManager;

defined( 'ABSPATH' ) || exit;

class ProductFields {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'wcgc_product_form_after_recipient_fields', [ __CLASS__, 'render_theme_picker' ] );
		add_filter( 'wcgc_add_to_cart_data', [ __CLASS__, 'add_cart_data' ], 10, 2 );
		add_filter( 'woocommerce_get_item_data', [ __CLASS__, 'display_cart_data' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'save_order_item_meta' ], 10, 4 );
	}

	/**
	 * Render visual theme picker with radio buttons on the product page.
	 *
	 * @param \WC_Product $product Current product.
	 */
	public static function render_theme_picker( $product ) {
		if ( Options::get( 'email_themes' ) !== '1' ) {
			return;
		}

		$themes        = ThemeManager::get_available_themes();
		$default_theme = Options::get( 'default_theme' );

		if ( empty( $default_theme ) || ! isset( $themes[ $default_theme ] ) ) {
			$default_theme = 'classic';
		}
		?>
		<div class="wcgc-theme-picker" style="margin-top: 15px;">
			<h4 style="margin-bottom: 8px;">
				<?php esc_html_e( 'Choose a Design', 'smart-gift-cards-for-woocommerce-pro' ); ?>
			</h4>
			<div class="wcgc-theme-options" style="display: flex; flex-wrap: wrap; gap: 12px;">
				<?php foreach ( $themes as $slug => $theme ) : ?>
					<label
						class="wcgc-theme-option"
						style="display: inline-block; text-align: center; cursor: pointer; padding: 6px; border: 2px solid <?php echo $slug === $default_theme ? '#7f54b3' : '#ddd'; ?>; border-radius: 6px; transition: border-color 0.2s;"
					>
						<input
							type="radio"
							name="wcgc_design_theme"
							value="<?php echo esc_attr( $slug ); ?>"
							<?php checked( $slug, $default_theme ); ?>
							style="display: none;"
						/>
						<img
							src="<?php echo esc_url( $theme['image'] ); ?>"
							alt="<?php echo esc_attr( $theme['name'] ); ?>"
							style="width: 60px; height: 60px; object-fit: cover; display: block; border-radius: 4px; margin: 0 auto 4px;"
						/>
						<span style="font-size: 12px; display: block;">
							<?php echo esc_html( $theme['name'] ); ?>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<script>
			(function() {
				var options = document.querySelectorAll('.wcgc-theme-option');
				if ( ! options.length ) {
					return;
				}
				options.forEach(function(label) {
					var radio = label.querySelector('input[type="radio"]');
					if ( ! radio ) {
						return;
					}
					radio.addEventListener('change', function() {
						options.forEach(function(l) {
							l.style.borderColor = '#ddd';
						});
						if ( radio.checked ) {
							label.style.borderColor = '#7f54b3';
						}
					});
				});
			})();
		</script>
		<?php
	}

	/**
	 * Store the selected theme in cart item data.
	 *
	 * @param array $cart_data  Cart item data.
	 * @param int   $product_id Product ID.
	 * @return array
	 */
	public static function add_cart_data( $cart_data, $product_id ) {
		if ( Options::get( 'email_themes' ) !== '1' ) {
			return $cart_data;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce handled by WooCommerce add-to-cart form.
		if ( isset( $_POST['wcgc_design_theme'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$cart_data['wcgc_design_theme'] = sanitize_key( wp_unslash( $_POST['wcgc_design_theme'] ) );
		}

		return $cart_data;
	}

	/**
	 * Display the selected theme name in the cart.
	 *
	 * @param array $item_data Cart item display data.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public static function display_cart_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['wcgc_design_theme'] ) ) {
			return $item_data;
		}

		$themes = ThemeManager::get_available_themes();
		$slug   = $cart_item['wcgc_design_theme'];

		if ( ! isset( $themes[ $slug ] ) ) {
			return $item_data;
		}

		$item_data[] = [
			'key'   => __( 'Design', 'smart-gift-cards-for-woocommerce-pro' ),
			'value' => esc_html( $themes[ $slug ]['name'] ),
		];

		return $item_data;
	}

	/**
	 * Save the selected theme as order item meta at checkout.
	 *
	 * @param \WC_Order_Item_Product $item          Order item.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array                  $values        Cart item data.
	 * @param \WC_Order              $order         Order object.
	 */
	public static function save_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values['wcgc_design_theme'] ) ) {
			$item->add_meta_data( '_wcgc_design_theme', sanitize_key( $values['wcgc_design_theme'] ) );
		}
	}
}
