<?php

namespace GiftCardsPro\EmailThemes;

use GiftCardsPro\Support\Options;

defined( 'ABSPATH' ) || exit;

class ThemeManager {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_filter( 'wcgc_email_template_html', [ __CLASS__, 'swap_template' ], 10, 2 );
		add_action( 'wcgc_email_before_card_design', [ __CLASS__, 'inject_theme_header' ], 10, 2 );
		add_filter( 'wcgc_gift_card_creation_args', [ __CLASS__, 'store_theme_in_meta' ], 10, 3 );
	}

	/**
	 * Get available email themes.
	 *
	 * @return array<string, array{name: string, image: string}>
	 */
	public static function get_available_themes() {
		return [
			'classic'     => [
				'name'  => __( 'Classic', 'smart-gift-cards-for-woocommerce-pro' ),
				'image' => WCGC_PRO_URL . 'assets/images/themes/classic.png',
			],
			'birthday'    => [
				'name'  => __( 'Birthday', 'smart-gift-cards-for-woocommerce-pro' ),
				'image' => WCGC_PRO_URL . 'assets/images/themes/birthday.png',
			],
			'celebration' => [
				'name'  => __( 'Celebration', 'smart-gift-cards-for-woocommerce-pro' ),
				'image' => WCGC_PRO_URL . 'assets/images/themes/celebration.png',
			],
			'thank-you'   => [
				'name'  => __( 'Thank You', 'smart-gift-cards-for-woocommerce-pro' ),
				'image' => WCGC_PRO_URL . 'assets/images/themes/thank-you.png',
			],
			'holiday'     => [
				'name'  => __( 'Holiday', 'smart-gift-cards-for-woocommerce-pro' ),
				'image' => WCGC_PRO_URL . 'assets/images/themes/holiday.png',
			],
		];
	}

	/**
	 * Swap the email template path when a theme is selected.
	 *
	 * Hooks into `wcgc_email_template_html` to redirect to a themed template
	 * in the Pro plugin. Uses a one-time `woocommerce_locate_template` filter
	 * to ensure WooCommerce resolves the template from the Pro templates directory.
	 *
	 * @param string      $template  Template relative path.
	 * @param object|null $gift_card Gift card data object.
	 * @return string
	 */
	public static function swap_template( $template, $gift_card ) {
		if ( Options::get( 'email_themes' ) !== '1' ) {
			return $template;
		}

		if ( ! $gift_card || empty( $gift_card->order_id ) ) {
			return $template;
		}

		$theme_slug = self::get_theme_for_gift_card( $gift_card );

		if ( empty( $theme_slug ) ) {
			return $template;
		}

		$themes = self::get_available_themes();
		if ( ! isset( $themes[ $theme_slug ] ) ) {
			return $template;
		}

		$themed_template = 'emails/themes/' . $theme_slug . '.php';
		$full_path       = WCGC_PRO_PATH . 'templates/' . $themed_template;

		if ( ! file_exists( $full_path ) ) {
			return $template;
		}

		// Add a one-time filter so wc_get_template_html resolves our Pro template path.
		add_filter(
			'woocommerce_locate_template',
			function ( $located, $template_name ) use ( $themed_template, $full_path ) {
				if ( $template_name === $themed_template ) {
					return $full_path;
				}
				return $located;
			},
			10,
			2
		);

		return $themed_template;
	}

	/**
	 * Inject the theme header image before the card design section in the email.
	 *
	 * @param object         $gift_card Gift card data object.
	 * @param \WC_Order|null $order     Order object.
	 */
	public static function inject_theme_header( $gift_card, $order ) {
		if ( Options::get( 'email_themes' ) !== '1' ) {
			return;
		}

		if ( ! $gift_card || empty( $gift_card->order_id ) ) {
			return;
		}

		$theme_slug = self::get_theme_for_gift_card( $gift_card );

		if ( empty( $theme_slug ) ) {
			return;
		}

		$themes = self::get_available_themes();
		if ( ! isset( $themes[ $theme_slug ] ) ) {
			return;
		}

		$theme     = $themes[ $theme_slug ];
		$image_url = $theme['image'];
		$alt_text  = $theme['name'];
		?>
		<div style="text-align: center; margin: 20px 0 10px;">
			<img
				src="<?php echo esc_url( $image_url ); ?>"
				alt="<?php echo esc_attr( $alt_text ); ?>"
				style="max-width: 100%; height: auto; display: inline-block;"
			/>
		</div>
		<?php
	}

	/**
	 * Hook for gift card creation args filter.
	 *
	 * The theme is already stored in order item meta by ProductFields
	 * at checkout time. This hook is available for future extensions
	 * but does not modify the gift card data itself.
	 *
	 * @param array          $gc_data Gift card data array.
	 * @param \WC_Order      $order   Order object.
	 * @param \WC_Order_Item $item    Line item.
	 * @return array
	 */
	public static function store_theme_in_meta( $gc_data, $order, $item ) {
		return $gc_data;
	}

	/**
	 * Look up the design theme for a gift card by finding the originating order item.
	 *
	 * Retrieves the order, iterates its line items, and checks for the
	 * `_wcgc_design_theme` meta on items that match the gift card's recipient email.
	 *
	 * @param object $gift_card Gift card data object (must have order_id).
	 * @return string Theme slug, or empty string if not found.
	 */
	private static function get_theme_for_gift_card( $gift_card ) {
		$order = wc_get_order( $gift_card->order_id );
		if ( ! $order ) {
			return '';
		}

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product || $product->get_type() !== 'gift-card' ) {
				continue;
			}

			// Match by recipient email if available, otherwise take the first gift-card item.
			$item_recipient = $item->get_meta( '_wcgc_recipient_email' );
			if ( ! empty( $gift_card->recipient_email ) && ! empty( $item_recipient ) ) {
				if ( strtolower( $item_recipient ) !== strtolower( $gift_card->recipient_email ) ) {
					continue;
				}
			}

			$theme = $item->get_meta( '_wcgc_design_theme' );
			if ( ! empty( $theme ) ) {
				return sanitize_key( $theme );
			}
		}

		return '';
	}
}
