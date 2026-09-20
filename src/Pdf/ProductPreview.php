<?php

namespace BgcwPro\Pdf;

defined( 'ABSPATH' ) || exit;

/**
 * Live card preview and design picker on the gift card product page.
 *
 * The chosen design travels as `bgcw_design_theme` (cart item data) and
 * `_bgcw_design_theme` (order item meta), unchanged from the previous email themes.
 */
class ProductPreview {

	const FIELD = 'bgcw_design_theme';
	const META  = '_bgcw_design_theme';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'bgcw_product_form_after_recipient_fields', [ __CLASS__, 'render' ] );
		add_filter( 'bgcw_add_to_cart_data', [ __CLASS__, 'add_cart_data' ], 10, 2 );
		add_filter( 'woocommerce_get_item_data', [ __CLASS__, 'display_cart_data' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'save_order_item_meta' ], 10, 4 );
	}

	/**
	 * Script parameters for the live preview.
	 *
	 * @return array
	 */
	public static function script_params(): array {
		$headings = [];
		foreach ( Designs::get() as $slug => $design ) {
			$headings[ $slug ] = $design['heading'];
		}
		$placeholders = CardRenderer::placeholders();
		$parts        = CardRenderer::amount_parts( 0, get_woocommerce_currency() );

		return [
			'currency'     => [
				'symbol'       => $parts['symbol'],
				'symbol_first' => $parts['symbol_first'],
				'space'        => $parts['space'],
				'decimals'     => wc_get_price_decimals(),
				'decimal_sep'  => wc_get_price_decimal_separator(),
				'thousand_sep' => wc_get_price_thousand_separator(),
			],
			'headings'     => $headings,
			'placeholders' => [
				'recipient_name' => $placeholders['recipient_name'],
				'sender_name'    => $placeholders['sender_name'],
				'message'        => $placeholders['message'],
			],
			'card_width'   => CardRenderer::WIDTH,
			'card_height'  => CardRenderer::HEIGHT,
		];
	}

	/**
	 * Output the preview card and the design swatches.
	 *
	 * @param \WC_Product $product Current product.
	 */
	public static function render( $product ) {
		if ( ! EmailAttachment::enabled() ) {
			return;
		}

		$designs = Designs::get();
		$default = Designs::DEFAULT_SLUG;
		?>
		<div class="bgcw-pro-preview" data-bgcw-preview>
			<h4 class="bgcw-pro-preview__title"><?php esc_html_e( 'Card design', 'beltoft-gift-cards-pro' ); ?></h4>
			<div class="bgcw-pro-designs" role="radiogroup" aria-label="<?php esc_attr_e( 'Card design', 'beltoft-gift-cards-pro' ); ?>">
				<?php foreach ( $designs as $slug => $design ) : ?>
					<label class="bgcw-pro-design<?php echo $slug === $default ? ' is-selected' : ''; ?>">
						<input type="radio" name="<?php echo esc_attr( self::FIELD ); ?>" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $slug, $default ); ?> />
						<span class="bgcw-pro-design__swatch" style="background-color:<?php echo esc_attr( $design['color'] ); ?>;"><span style="background-color:<?php echo esc_attr( $design['accent'] ); ?>;"></span></span>
						<span class="bgcw-pro-design__name"><?php echo esc_html( $design['name'] ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
			<p class="bgcw-pro-preview__actions">
				<button type="button" class="bgcw-pro-preview__open" aria-haspopup="dialog" aria-controls="bgcw-pro-lightbox"><?php esc_html_e( 'Preview your card', 'beltoft-gift-cards-pro' ); ?></button>
				<span class="bgcw-pro-preview__note"><?php esc_html_e( 'The recipient gets it as a PDF by email once the order is paid.', 'beltoft-gift-cards-pro' ); ?></span>
			</p>
			<div class="bgcw-pro-lightbox" id="bgcw-pro-lightbox" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Gift card preview', 'beltoft-gift-cards-pro' ); ?>" hidden>
				<div class="bgcw-pro-lightbox__backdrop" data-bgcw-close></div>
				<div class="bgcw-pro-lightbox__panel">
					<button type="button" class="bgcw-pro-lightbox__close" data-bgcw-close aria-label="<?php esc_attr_e( 'Close preview', 'beltoft-gift-cards-pro' ); ?>">&times;</button>
					<div class="bgcw-pro-preview__stage">
						<div class="bgcw-pro-preview__scale">
							<?php
							// CardRenderer escapes all dynamic values.
							echo CardRenderer::render( $default, CardRenderer::placeholders(), CardRenderer::MODE_PREVIEW ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</div>
					</div>
					<p class="bgcw-pro-lightbox__note"><?php esc_html_e( 'This is how the PDF will look. The code is added when the order is paid.', 'beltoft-gift-cards-pro' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Store the selected design in cart item data.
	 *
	 * @param array $cart_data  Cart item data.
	 * @param int   $product_id Product ID.
	 * @return array
	 */
	public static function add_cart_data( $cart_data, $product_id ) {
		if ( ! EmailAttachment::enabled() ) {
			return $cart_data;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce handled by WooCommerce add-to-cart form.
		if ( isset( $_POST[ self::FIELD ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$cart_data[ self::FIELD ] = Designs::normalize( sanitize_key( wp_unslash( $_POST[ self::FIELD ] ) ) );
		}

		return $cart_data;
	}

	/**
	 * Show the design name in the cart.
	 *
	 * @param array $item_data Cart item display data.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public static function display_cart_data( $item_data, $cart_item ) {
		if ( empty( $cart_item[ self::FIELD ] ) ) {
			return $item_data;
		}

		$designs = Designs::get();
		$slug    = Designs::normalize( $cart_item[ self::FIELD ] );

		$item_data[] = [
			'key'   => __( 'Design', 'beltoft-gift-cards-pro' ),
			'value' => esc_html( $designs[ $slug ]['name'] ),
		];

		return $item_data;
	}

	/**
	 * Save the design as order item meta at checkout.
	 *
	 * @param \WC_Order_Item_Product $item          Order item.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array                  $values        Cart item data.
	 * @param \WC_Order              $order         Order object.
	 */
	public static function save_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values[ self::FIELD ] ) ) {
			$item->add_meta_data( self::META, Designs::normalize( $values[ self::FIELD ] ) );
		}
	}
}
