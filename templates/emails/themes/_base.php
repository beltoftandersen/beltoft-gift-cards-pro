<?php
/**
 * Gift Card Delivery Email — Themed Base Template (HTML).
 *
 * Each theme file sets these variables before including this file:
 *   $bgcw_theme_color      - accent color (e.g. '#6B4C9A')
 *   $bgcw_theme_color_light - lighter variant for gradient end (e.g. '#8B6CB3')
 *   $bgcw_theme_bg         - light background for code box (e.g. '#F3EEFC')
 *   $bgcw_theme_heading    - heading text
 *
 * @package BgcwPro
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

<p style="margin: 0 0 4px; font-size: 14px; color: #555;">
	<?php
	printf(
		/* translators: %s: sender name */
		esc_html__( 'From: %s', 'beltoft-gift-cards-for-woocommerce-pro' ),
		'<strong>' . esc_html( $gift_card->sender_name ) . '</strong>'
	);
	?>
</p>

<?php if ( ! empty( $gift_card->message ) ) : ?>
	<p style="font-style: italic; color: #555; padding: 10px 16px; border-left: 3px solid <?php echo esc_attr( $bgcw_theme_color ); ?>; margin: 10px 0 20px; background: #fafafa; border-radius: 0 4px 4px 0; font-size: 14px;">
		&ldquo;<?php echo esc_html( $gift_card->message ); ?>&rdquo;
	</p>
<?php endif; ?>

<!-- Gift Card -->
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 20px 0; border-collapse: collapse;">
	<tr>
		<td align="center">
			<table cellpadding="0" cellspacing="0" border="0" width="420" style="max-width: 100%; border-radius: 12px; overflow: hidden; border: 1px solid #e0e0e0; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
				<!-- Themed Header -->
				<tr>
					<td style="background-color: <?php echo esc_attr( $bgcw_theme_color ); ?>; padding: 28px 20px; text-align: center;">
						<p style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 700; line-height: 1.3;">
							<?php echo esc_html( $bgcw_theme_heading ); ?>
						</p>
					</td>
				</tr>
				<!-- Card Body -->
				<tr>
					<td style="background: #ffffff; padding: 28px 24px; text-align: center;">
						<!-- Amount -->
						<p style="font-size: 34px; font-weight: bold; margin: 0 0 18px; color: <?php echo esc_attr( $bgcw_theme_color ); ?>; line-height: 1;">
							<?php echo wp_kses_post( wc_price( $gift_card->initial_amount, array( 'currency' => $gift_card->currency ) ) ); ?>
						</p>
						<!-- Code Box -->
						<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 0 0 20px;">
							<tr>
								<td align="center">
									<div style="background: <?php echo esc_attr( $bgcw_theme_bg ); ?>; padding: 12px 24px; display: inline-block; border-radius: 6px; border: 1px solid <?php echo esc_attr( $bgcw_theme_color ); ?>33;">
										<span style="font-family: 'Courier New', Courier, monospace; font-size: 18px; letter-spacing: 2px; font-weight: bold; color: #333;">
											<?php echo esc_html( $gift_card->code ); ?>
										</span>
									</div>
								</td>
							</tr>
						</table>
						<!-- Shop Now Button -->
						<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 0 0 14px;">
							<tr>
								<td align="center">
									<a href="<?php echo esc_url( add_query_arg( 'bgcw_apply', rawurlencode( $gift_card->code ), wc_get_page_permalink( 'shop' ) ) ); ?>"
									   style="display: inline-block; background: <?php echo esc_attr( $bgcw_theme_color ); ?>; color: #ffffff; padding: 12px 32px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px;">
										<?php esc_html_e( 'Shop Now', 'beltoft-gift-cards-for-woocommerce-pro' ); ?>
									</a>
								</td>
							</tr>
						</table>
						<?php if ( ! empty( $gift_card->expires_at ) ) : ?>
							<!-- Expiry -->
							<p style="font-size: 12px; color: #999; margin: 0;">
								<?php
								printf(
									/* translators: %s: expiry date */
									esc_html__( 'Expires: %s', 'beltoft-gift-cards-for-woocommerce-pro' ),
									esc_html( date_i18n( get_option( 'date_format' ), strtotime( $gift_card->expires_at ) ) )
								);
								?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<p style="font-size: 12px; color: #999; text-align: center; margin: 16px 0 0;">
	<?php esc_html_e( 'Click "Shop Now" to apply your gift card automatically, or enter the code at checkout in the coupon/gift card field.', 'beltoft-gift-cards-for-woocommerce-pro' ); ?>
</p>

<?php
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard hook.
do_action( 'woocommerce_email_footer', $email );
