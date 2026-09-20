<?php
/**
 * Gift card markup shared by the live preview and the PDF.
 *
 * Two variants: a value card (amount + code) and a product card (the card is locked to a product).
 *
 * @package BgcwPro
 * @var string $design         Design slug.
 * @var array  $theme          Design definition (name, heading, color, accent, bg).
 * @var string $mode           'preview' or 'pdf'.
 * @var array  $store          Store branding (see CardRenderer::store()).
 * @var string $logo_src       Logo path (pdf) or URL (preview); empty for none.
 * @var string $amount_text    Formatted amount (plain text).
 * @var array  $amount         number, symbol, symbol_first, space, size (see CardRenderer::amount_parts()).
 * @var string $code           Gift card code.
 * @var string $recipient_name Recipient name.
 * @var string $sender_name    Sender name.
 * @var string $message        Personal message (may be empty).
 * @var string $expiry_text    Formatted expiry line.
 * @var string $heading        Headline for this variant.
 * @var string $intro          Store paragraph for this variant (placeholders already replaced).
 * @var bool   $is_product     Whether the card is locked to a product.
 * @var string $product_name   Locked product name (product variant).
 */

defined( 'ABSPATH' ) || exit;

$bgcw_amount_class = 'bgcw-card__amount bgcw-card__amount--' . $amount['size'];
$bgcw_symbol       = '<span class="bgcw-card__currency" data-bgcw-field="currency">' . esc_html( $amount['symbol'] ) . '</span>';
$bgcw_number       = '<span class="bgcw-card__number" data-bgcw-field="number">' . esc_html( $amount['number'] ) . '</span>';
$bgcw_space        = $amount['space'] ? ' ' : '';
$bgcw_message      = mb_strlen( $message ) > 160 ? mb_substr( $message, 0, 157 ) . '…' : $message;
$bgcw_has_people   = '' !== trim( $sender_name ) || '' !== trim( $recipient_name );
$bgcw_product_len  = mb_strlen( $product_name );
$bgcw_product_cls  = 'bgcw-card__product bgcw-card__product--' . ( $bgcw_product_len <= 24 ? 'lg' : ( $bgcw_product_len <= 44 ? 'md' : 'sm' ) );
?>
<div class="bgcw-card bgcw-card--<?php echo esc_attr( $design ); ?><?php echo $is_product ? ' bgcw-card--product' : ''; ?>" data-design="<?php echo esc_attr( $design ); ?>">
	<div class="bgcw-card__brand">
		<?php if ( $logo_src ) : ?>
			<img src="<?php echo esc_attr( $logo_src ); ?>" alt="<?php echo esc_attr( $store['store_name'] ); ?>" />
		<?php else : ?>
			<div class="bgcw-card__brand-name"><?php echo esc_html( $store['store_name'] ); ?></div>
		<?php endif; ?>
	</div>

	<div class="bgcw-card__heading" data-bgcw-field="heading"><?php echo esc_html( $heading ); ?></div>

	<div class="bgcw-card__people" data-bgcw-field="people"<?php echo $bgcw_has_people ? '' : ' style="display:none"'; ?>>
		<?php esc_html_e( 'From', 'beltoft-gift-cards-pro' ); ?> <strong data-bgcw-field="sender_name"><?php echo esc_html( $sender_name ); ?></strong><span class="bgcw-card__gap"></span><?php esc_html_e( 'To', 'beltoft-gift-cards-pro' ); ?> <strong data-bgcw-field="recipient_name"><?php echo esc_html( $recipient_name ); ?></strong>
	</div>

	<div class="bgcw-card__intro">
		<div class="bgcw-card__intro-text" data-bgcw-field="intro"><?php echo esc_html( $intro ); ?></div>
		<div class="bgcw-card__message" data-bgcw-field="message"<?php echo '' === $bgcw_message ? ' style="display:none"' : ''; ?>>&ldquo;<?php echo esc_html( $bgcw_message ); ?>&rdquo;</div>
	</div>

	<div class="bgcw-card__box">
		<table>
			<tr>
				<td class="bgcw-card__cell-left">
					<?php if ( $is_product ) : ?>
						<div class="bgcw-card__gift-word"><?php esc_html_e( 'Gift', 'beltoft-gift-cards-pro' ); ?></div>
					<?php else : ?>
						<div class="<?php echo esc_attr( $bgcw_amount_class ); ?>" data-bgcw-field="amount" title="<?php echo esc_attr( $amount_text ); ?>"><?php
							// Spans are escaped above; order follows the store's currency position setting.
							echo $amount['symbol_first'] ? $bgcw_symbol . $bgcw_space . $bgcw_number : $bgcw_number . $bgcw_space . $bgcw_symbol; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?></div>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $is_product ) : ?>
						<div class="<?php echo esc_attr( $bgcw_product_cls ); ?>" data-bgcw-field="product_name"><?php echo esc_html( $product_name ); ?></div>
						<div class="bgcw-card__code-value bgcw-card__code-value--small" data-bgcw-field="code"><?php echo esc_html( $code ); ?></div>
					<?php else : ?>
						<div class="bgcw-card__code-value" data-bgcw-field="code"><?php echo esc_html( $code ); ?></div>
					<?php endif; ?>
					<div class="bgcw-card__expiry" data-bgcw-field="expiry"><?php echo esc_html( $expiry_text ); ?></div>
				</td>
			</tr>
		</table>
	</div>

	<div class="bgcw-card__footer"><?php
		/* translators: %s: shop domain */
		printf( esc_html__( 'Visit us at %s', 'beltoft-gift-cards-pro' ), esc_html( $store['shop_host'] ) );
	?></div>
</div>
