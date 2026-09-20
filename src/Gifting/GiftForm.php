<?php

namespace BgcwPro\Gifting;

use BgcwPro\Pdf\EmailAttachment;

defined( 'ABSPATH' ) || exit;

/**
 * "Give as a gift" toggle and recipient fields on giftable product pages.
 */
class GiftForm {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_after_add_to_cart_button', [ __CLASS__, 'render' ] );
	}

	/**
	 * Output the toggle and fields for the current product when giftable.
	 */
	public static function render() {
		global $product;
		if ( ! $product instanceof \WC_Product || ! Giftable::is_giftable( $product ) ) {
			return;
		}

		// The free plugin validates the add-to-cart against the carrier product, so evaluate the
		// field visibility filters against the same product to keep form and validation in step.
		$carrier_id   = Carrier::id();
		$carrier      = $carrier_id ? wc_get_product( $carrier_id ) : null;
		$filter_for   = $carrier ? $carrier : $product;
		$show_name    = apply_filters( 'bgcw_show_recipient_name_field', true, $filter_for );
		$show_email   = apply_filters( 'bgcw_show_recipient_email_field', true, $filter_for );
		$show_message = apply_filters( 'bgcw_show_personal_message_field', true, $filter_for );
		$amount       = Giftable::gift_amount( $product );
		?>
		<div class="bgcw-pro-gift" data-bgcw-gift>
			<label class="bgcw-pro-gift__toggle">
				<input type="checkbox" name="<?php echo esc_attr( AddToCartHandler::FLAG ); ?>" value="1" id="bgcw_gift" />
				<span><?php esc_html_e( 'Give as a gift', 'beltoft-gift-cards-pro' ); ?></span>
			</label>
			<div class="bgcw-pro-gift__fields" hidden>
				<p class="bgcw-pro-gift__note">
					<?php
					printf(
						/* translators: %s: formatted price */
						esc_html__( 'You pay %s now. The recipient gets a gift card for this product by email and chooses when to use it.', 'beltoft-gift-cards-pro' ),
						wp_kses_post( wc_price( $amount ) )
					);
					if ( $product->is_type( 'variable' ) ) {
						echo ' ' . esc_html__( 'The card is worth the lowest option price; the recipient pays any difference when choosing a more expensive option.', 'beltoft-gift-cards-pro' );
					}
					?>
				</p>
				<div class="bgcw-recipient-fields">
					<?php if ( $show_name ) : ?>
						<p class="form-row form-row-wide">
							<label for="bgcw_recipient_name"><?php esc_html_e( 'Recipient Name', 'beltoft-gift-cards-pro' ); ?></label>
							<input type="text" name="bgcw_recipient_name" id="bgcw_recipient_name" class="input-text" />
						</p>
					<?php endif; ?>
					<?php if ( $show_email ) : ?>
						<p class="form-row form-row-wide">
							<label for="bgcw_recipient_email"><?php esc_html_e( 'Recipient Email', 'beltoft-gift-cards-pro' ); ?> <abbr class="required" title="<?php esc_attr_e( 'required', 'beltoft-gift-cards-pro' ); ?>">*</abbr></label>
							<input type="email" name="bgcw_recipient_email" id="bgcw_recipient_email" class="input-text" data-bgcw-required />
						</p>
					<?php endif; ?>
					<?php if ( $show_message ) : ?>
						<p class="form-row form-row-wide">
							<label for="bgcw_message"><?php esc_html_e( 'Personal Message (optional)', 'beltoft-gift-cards-pro' ); ?></label>
							<textarea name="bgcw_message" id="bgcw_message" rows="3" class="input-text"></textarea>
						</p>
					<?php endif; ?>
				</div>
				<?php
				/** This action is documented in the free plugin's ProductPage. Adds scheduled delivery and the card preview. */
				do_action( 'bgcw_product_form_after_recipient_fields', $product );
				?>
			</div>
		</div>
		<?php
	}
}
