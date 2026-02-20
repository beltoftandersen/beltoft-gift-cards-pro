<?php
/**
 * Gift Card Delivery Email — Themed Base Template (HTML).
 *
 * This file is included by each theme file after setting:
 *   $wcgc_theme_image  - theme image filename (e.g. 'classic.png')
 *   $wcgc_theme_color  - accent color (e.g. '#7f54b3')
 *   $wcgc_theme_bg     - code box background (e.g. '#f5f5f5')
 *   $wcgc_theme_heading - heading text
 *
 * @package GiftCardsPro
 * @var object   $gift_card     Gift card data.
 * @var WC_Order $order         Order object (may be null).
 * @var string   $email_heading Email heading.
 * @var bool     $sent_to_admin Whether sent to admin.
 * @var bool     $plain_text    Whether plain text.
 * @var WC_Email $email         Email object.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard hook.
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<div style="text-align: center; margin: 20px 0 10px;">
	<img
		src="<?php echo esc_url( WCGC_PRO_URL . 'assets/images/themes/' . $wcgc_theme_image ); ?>"
		alt="<?php echo esc_attr( $wcgc_theme_heading ); ?>"
		style="max-width: 100%; height: auto; display: inline-block;"
	/>
	<h2 style="color: <?php echo esc_attr( $wcgc_theme_color ); ?>; font-size: 24px; margin: 15px 0 5px;">
		<?php echo esc_html( $wcgc_theme_heading ); ?>
	</h2>
</div>

<p>
	<?php
	printf(
		/* translators: %s: sender name */
		esc_html__( 'From: %s', 'smart-gift-cards-for-woocommerce-pro' ),
		esc_html( $gift_card->sender_name )
	);
	?>
</p>

<?php if ( ! empty( $gift_card->message ) ) : ?>
	<p style="font-style: italic; color: #555; padding: 10px 20px; border-left: 3px solid <?php echo esc_attr( $wcgc_theme_color ); ?>; margin: 15px 0;">
		&ldquo;<?php echo esc_html( $gift_card->message ); ?>&rdquo;
	</p>
<?php endif; ?>

<div style="text-align: center; margin: 30px 0;">
	<p style="font-size: 32px; font-weight: bold; margin: 0 0 10px;">
		<?php echo wp_kses_post( wc_price( $gift_card->initial_amount, array( 'currency' => $gift_card->currency ) ) ); ?>
	</p>
	<div style="background: <?php echo esc_attr( $wcgc_theme_bg ); ?>; padding: 15px 25px; display: inline-block; border-radius: 6px; margin: 10px 0; border: 2px dashed <?php echo esc_attr( $wcgc_theme_color ); ?>;">
		<span style="font-family: monospace; font-size: 20px; letter-spacing: 3px; font-weight: bold; color: <?php echo esc_attr( $wcgc_theme_color ); ?>;">
			<?php echo esc_html( $gift_card->code ); ?>
		</span>
	</div>
	<?php if ( ! empty( $gift_card->expires_at ) ) : ?>
		<p style="font-size: 13px; color: #888; margin-top: 10px;">
			<?php
			printf(
				/* translators: %s: expiry date */
				esc_html__( 'Expires: %s', 'smart-gift-cards-for-woocommerce-pro' ),
				esc_html( date_i18n( get_option( 'date_format' ), strtotime( $gift_card->expires_at ) ) )
			);
			?>
		</p>
	<?php endif; ?>
</div>

<p style="text-align: center; margin: 25px 0;">
	<a href="<?php echo esc_url( add_query_arg( 'wcgc_apply', rawurlencode( $gift_card->code ), wc_get_page_permalink( 'shop' ) ) ); ?>"
	   style="display: inline-block; background: <?php echo esc_attr( $wcgc_theme_color ); ?>; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-weight: bold;">
		<?php esc_html_e( 'Shop Now', 'smart-gift-cards-for-woocommerce-pro' ); ?>
	</a>
</p>

<p style="font-size: 13px; color: #888; text-align: center;">
	<?php esc_html_e( 'Click "Shop Now" to apply your gift card automatically, or enter the code at checkout in the coupon/gift card field.', 'smart-gift-cards-for-woocommerce-pro' ); ?>
</p>

<?php
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard hook.
do_action( 'woocommerce_email_footer', $email );
