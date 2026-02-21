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
	}

	/**
	 * Get available email themes with color definitions.
	 *
	 * @return array<string, array{name: string, color: string, color_light: string, bg: string, heading: string}>
	 */
	public static function get_available_themes() {
		$themes = [
			'classic'     => [
				'name'        => __( 'Classic', 'smart-gift-cards-for-woocommerce-pro' ),
				'color'       => '#6B4C9A',
				'color_light' => '#8B6CB3',
				'bg'          => '#F3EEFC',
				'heading'     => __( "You've received a gift card!", 'smart-gift-cards-for-woocommerce-pro' ),
			],
			'birthday'    => [
				'name'        => __( 'Birthday', 'smart-gift-cards-for-woocommerce-pro' ),
				'color'       => '#E91E8C',
				'color_light' => '#F06AB5',
				'bg'          => '#FDE7F3',
				'heading'     => __( 'Happy Birthday!', 'smart-gift-cards-for-woocommerce-pro' ),
			],
			'celebration' => [
				'name'        => __( 'Celebration', 'smart-gift-cards-for-woocommerce-pro' ),
				'color'       => '#E88700',
				'color_light' => '#F5A623',
				'bg'          => '#FFF3E0',
				'heading'     => __( 'Congratulations!', 'smart-gift-cards-for-woocommerce-pro' ),
			],
			'thank-you'   => [
				'name'        => __( 'Thank You', 'smart-gift-cards-for-woocommerce-pro' ),
				'color'       => '#1A9E8F',
				'color_light' => '#3BBFB0',
				'bg'          => '#E6F7F5',
				'heading'     => __( 'Thank You!', 'smart-gift-cards-for-woocommerce-pro' ),
			],
			'holiday'     => [
				'name'        => __( 'Holiday', 'smart-gift-cards-for-woocommerce-pro' ),
				'color'       => '#B22222',
				'color_light' => '#D94444',
				'bg'          => '#FDEAEA',
				'heading'     => __( 'Happy Holidays!', 'smart-gift-cards-for-woocommerce-pro' ),
			],
		];

		// Apply admin overrides from Pro Settings.
		foreach ( $themes as $slug => &$theme ) {
			$custom_heading = Options::get( 'theme_heading_' . $slug );
			if ( ! empty( $custom_heading ) ) {
				$theme['heading'] = $custom_heading;
			}
			$custom_color = Options::get( 'theme_color_' . $slug );
			if ( ! empty( $custom_color ) ) {
				$theme['color'] = $custom_color;
			}
		}
		unset( $theme );

		return $themes;
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
