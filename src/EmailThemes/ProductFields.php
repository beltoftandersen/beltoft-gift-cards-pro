<?php

namespace BgcwPro\EmailThemes;

use BgcwPro\Support\Options;

defined( 'ABSPATH' ) || exit;

class ProductFields {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'bgcw_product_form_after_recipient_fields', [ __CLASS__, 'render_theme_picker' ] );
		add_filter( 'bgcw_add_to_cart_data', [ __CLASS__, 'add_cart_data' ], 10, 2 );
		add_filter( 'woocommerce_get_item_data', [ __CLASS__, 'display_cart_data' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'save_order_item_meta' ], 10, 4 );
	}

	/**
	 * Render mini email preview cards as the theme picker on the product page.
	 *
	 * Each card is a tiny visual mockup of the email showing the themed header,
	 * amount placeholder, code placeholder, and button — all in pure HTML/CSS.
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
		<div class="bgcw-theme-picker">
			<h4>
				<?php esc_html_e( 'Choose a Design', 'beltoft-gift-cards-for-woocommerce-pro' ); ?>
			</h4>
			<div class="bgcw-theme-options">
				<?php foreach ( $themes as $slug => $theme ) : ?>
					<label class="bgcw-theme-option<?php echo $slug === $default_theme ? ' selected' : ''; ?>">
						<input
							type="radio"
							name="bgcw_design_theme"
							value="<?php echo esc_attr( $slug ); ?>"
							<?php checked( $slug, $default_theme ); ?>
						/>
						<span class="bgcw-theme-preview">
							<span class="bgcw-theme-preview-header" style="background: linear-gradient(135deg, <?php echo esc_attr( $theme['color'] ); ?>, <?php echo esc_attr( $theme['color_light'] ); ?>);">
								<span class="bgcw-theme-preview-heading"><?php echo esc_html( $theme['heading'] ); ?></span>
							</span>
							<span class="bgcw-theme-preview-body">
								<span class="bgcw-theme-preview-amount" style="color: <?php echo esc_attr( $theme['color'] ); ?>;">$&mdash;</span>
								<span class="bgcw-theme-preview-code" style="border-color: <?php echo esc_attr( $theme['color'] ); ?>33; background: <?php echo esc_attr( $theme['bg'] ); ?>;">XXXX</span>
								<span class="bgcw-theme-preview-btn" style="background: <?php echo esc_attr( $theme['color'] ); ?>;"></span>
							</span>
						</span>
						<span class="bgcw-theme-label"><?php echo esc_html( $theme['name'] ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
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
		if ( isset( $_POST['bgcw_design_theme'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$cart_data['bgcw_design_theme'] = sanitize_key( wp_unslash( $_POST['bgcw_design_theme'] ) );
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
		if ( empty( $cart_item['bgcw_design_theme'] ) ) {
			return $item_data;
		}

		$themes = ThemeManager::get_available_themes();
		$slug   = $cart_item['bgcw_design_theme'];

		if ( ! isset( $themes[ $slug ] ) ) {
			return $item_data;
		}

		$item_data[] = [
			'key'   => __( 'Design', 'beltoft-gift-cards-for-woocommerce-pro' ),
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
		if ( ! empty( $values['bgcw_design_theme'] ) ) {
			$item->add_meta_data( '_bgcw_design_theme', sanitize_key( $values['bgcw_design_theme'] ) );
		}
	}
}
