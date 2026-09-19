<?php
/**
 * Gift card markup shared by the live preview and the PDF.
 *
 * @package BgcwPro
 * @var string $design         Design slug.
 * @var array  $theme          Design definition (name, heading, color, accent, bg, motif).
 * @var string $mode           'preview' or 'pdf'.
 * @var array  $store          Store branding (see CardRenderer::store()).
 * @var string $logo_src       Logo path (pdf) or URL (preview); empty for none.
 * @var string $amount_text    Formatted amount.
 * @var string $code           Gift card code.
 * @var string $recipient_name Recipient name.
 * @var string $sender_name    Sender name.
 * @var string $message        Personal message (may be empty).
 * @var string $expiry_text    Formatted expiry line.
 */

defined( 'ABSPATH' ) || exit;

$bgcw_amount_class = 'bgcw-card__amount';
if ( mb_strlen( $amount_text ) > 9 ) {
	$bgcw_amount_class .= ' bgcw-card__amount--long';
}
$bgcw_message_short = mb_strlen( $message ) > 220 ? mb_substr( $message, 0, 217 ) . '…' : $message;
?>
<div class="bgcw-card bgcw-card--<?php echo esc_attr( $design ); ?>" data-design="<?php echo esc_attr( $design ); ?>">
	<div class="bgcw-card__panel">
		<?php if ( 'frame' === $theme['motif'] ) : ?>
			<div class="bgcw-card__frame"></div>
		<?php elseif ( 'confetti' === $theme['motif'] ) : ?>
			<?php for ( $bgcw_i = 1; $bgcw_i <= 6; $bgcw_i++ ) : ?>
				<div class="bgcw-card__confetti bgcw-card__confetti--<?php echo (int) $bgcw_i; ?>"></div>
			<?php endfor; ?>
		<?php elseif ( 'ribbon' === $theme['motif'] ) : ?>
			<div class="bgcw-card__ribbon bgcw-card__ribbon--1"></div>
			<div class="bgcw-card__ribbon bgcw-card__ribbon--2"></div>
			<div class="bgcw-card__ribbon bgcw-card__ribbon--3"></div>
		<?php endif; ?>
		<div class="bgcw-card__panel-inner">
			<div class="bgcw-card__heading" data-bgcw-field="heading"><?php echo esc_html( $theme['heading'] ); ?></div>
			<div class="<?php echo esc_attr( $bgcw_amount_class ); ?>" data-bgcw-field="amount"><?php echo esc_html( $amount_text ); ?></div>
			<div class="bgcw-card__accent"></div>
		</div>
		<div class="bgcw-card__panel-foot"><?php echo esc_html( $store['store_name'] ); ?></div>
	</div>
	<div class="bgcw-card__body">
		<div class="bgcw-card__brand">
			<?php if ( $logo_src ) : ?>
				<img src="<?php echo esc_attr( $logo_src ); ?>" alt="<?php echo esc_attr( $store['store_name'] ); ?>" />
			<?php else : ?>
				<div class="bgcw-card__brand-name"><?php echo esc_html( $store['store_name'] ); ?></div>
			<?php endif; ?>
		</div>
		<table class="bgcw-card__people">
			<tr>
				<td>
					<span class="bgcw-card__label"><?php esc_html_e( 'To', 'beltoft-gift-cards-pro' ); ?></span>
					<span class="bgcw-card__value" data-bgcw-field="recipient_name"><?php echo esc_html( $recipient_name ); ?></span>
				</td>
				<td>
					<span class="bgcw-card__label"><?php esc_html_e( 'From', 'beltoft-gift-cards-pro' ); ?></span>
					<span class="bgcw-card__value" data-bgcw-field="sender_name"><?php echo esc_html( $sender_name ); ?></span>
				</td>
			</tr>
		</table>
		<div class="bgcw-card__message" data-bgcw-field="message"<?php echo '' === $bgcw_message_short ? ' style="display:none"' : ''; ?>><?php echo esc_html( $bgcw_message_short ); ?></div>
		<div class="bgcw-card__code">
			<span class="bgcw-card__label"><?php esc_html_e( 'Gift card code', 'beltoft-gift-cards-pro' ); ?></span>
			<span class="bgcw-card__code-value" data-bgcw-field="code"><?php echo esc_html( $code ); ?></span>
		</div>
		<table class="bgcw-card__footer">
			<tr>
				<td>
					<span class="bgcw-card__expiry" data-bgcw-field="expiry"><?php echo esc_html( $expiry_text ); ?></span><br />
					<?php echo esc_html( $store['redeem_text'] ); ?>
				</td>
				<td class="bgcw-card__host"><?php echo esc_html( $store['shop_host'] ); ?></td>
			</tr>
		</table>
	</div>
</div>
