<?php
/**
 * Gift Card Delivery Email — short notice used when the card is attached as a PDF.
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

<p style="margin: 0 0 16px; font-size: 15px; color: #333;">
	<?php
	printf(
		/* translators: %s: sender name */
		esc_html__( '%s sent you a gift card.', 'beltoft-gift-cards-pro' ),
		'<strong>' . esc_html( $gift_card->sender_name ) . '</strong>'
	);
	?>
</p>

<?php if ( ! empty( $gift_card->message ) ) : ?>
	<p style="margin: 0 0 20px; padding: 12px 16px; border-left: 3px solid #cccccc; color: #555; font-size: 14px; background: #fafafa;">
		&ldquo;<?php echo esc_html( $gift_card->message ); ?>&rdquo;
	</p>
<?php endif; ?>

<p style="margin: 0 0 16px; font-size: 15px; color: #333;">
	<?php esc_html_e( 'Your gift card is attached to this email as a PDF. Print it or show the code at checkout.', 'beltoft-gift-cards-pro' ); ?>
</p>

<p style="margin: 0 0 6px; font-size: 13px; color: #666;"><?php esc_html_e( 'Gift card code', 'beltoft-gift-cards-pro' ); ?></p>
<p style="margin: 0 0 20px; font-family: 'Courier New', Courier, monospace; font-size: 18px; letter-spacing: 2px; font-weight: bold; color: #222;">
	<?php echo esc_html( $gift_card->code ); ?>
</p>

<p style="margin: 0 0 24px;">
	<a href="<?php echo esc_url( add_query_arg( 'bgcw_apply', rawurlencode( $gift_card->code ), wc_get_page_permalink( 'shop' ) ) ); ?>"
	   style="display: inline-block; background: #222222; color: #ffffff; padding: 12px 28px; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 15px;">
		<?php esc_html_e( 'Shop now', 'beltoft-gift-cards-pro' ); ?>
	</a>
</p>

<?php if ( ! empty( $gift_card->expires_at ) ) : ?>
	<p style="margin: 0; font-size: 12px; color: #888;">
		<?php
		printf(
			/* translators: %s: expiry date */
			esc_html__( 'Valid until %s.', 'beltoft-gift-cards-pro' ),
			esc_html( wp_date( get_option( 'date_format' ), strtotime( $gift_card->expires_at ) ) )
		);
		?>
	</p>
<?php endif; ?>

<?php
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard hook.
do_action( 'woocommerce_email_footer', $email );
