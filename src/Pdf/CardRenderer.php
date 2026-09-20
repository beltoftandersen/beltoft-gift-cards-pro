<?php

namespace BgcwPro\Pdf;

use BgcwPro\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the gift card HTML used by both the live preview and the PDF.
 */
class CardRenderer {

	const MODE_PREVIEW = 'preview';
	const MODE_PDF     = 'pdf';

	/**
	 * Card size in CSS pixels at 96 dpi (A5 landscape, 210 x 148 mm).
	 */
	const WIDTH  = 794;
	const HEIGHT = 559;

	/** Personal message is cut to this many characters on the card (preview mirrors it). */
	const MESSAGE_MAX = 160;

	/**
	 * Dummy data for the product-page preview and admin samples.
	 *
	 * @return array
	 */
	public static function placeholders(): array {
		return [
			'amount'         => 50,
			'currency'       => get_woocommerce_currency(),
			'code'           => 'GIFT-XXXX-XXXX',
			'recipient_name' => __( 'Recipient', 'beltoft-gift-cards-pro' ),
			'sender_name'    => __( 'You', 'beltoft-gift-cards-pro' ),
			'message'        => '',
			'expires_at'     => gmdate( 'Y-m-d H:i:s', time() + YEAR_IN_SECONDS ),
			'product_id'     => 0,
			'product_name'   => '',
		];
	}

	/**
	 * Store branding used on every card.
	 *
	 * @return array{logo_path:string,logo_url:string,store_name:string,shop_url:string,shop_host:string,redeem_text:string}
	 */
	public static function store(): array {
		$logo_id   = (int) Options::get( 'pdf_logo_id' );
		$logo_path = '';
		$logo_url  = '';

		if ( $logo_id > 0 ) {
			$path = get_attached_file( $logo_id );
			if ( $path && file_exists( $path ) ) {
				$logo_path = $path;
				$logo_url  = (string) wp_get_attachment_url( $logo_id );
			}
		}

		$shop_url = wc_get_page_permalink( 'shop' );
		if ( ! $shop_url || is_wp_error( $shop_url ) ) {
			$shop_url = home_url( '/' );
		}
		$shop_host = wp_parse_url( $shop_url, PHP_URL_HOST ) ?: home_url();

		/* translators: %s: shop domain */
		$redeem = sprintf( __( 'Enter the code at checkout on %s, or show this card in store.', 'beltoft-gift-cards-pro' ), $shop_host );

		$intro         = (string) Options::get( 'pdf_intro' );
		$intro_product = (string) Options::get( 'pdf_intro_product' );

		return [
			'intro'         => '' !== trim( $intro ) ? $intro : __( 'Congratulations! You have received a gift card to spend at {store}. We hope you enjoy it!', 'beltoft-gift-cards-pro' ),
			'intro_product' => '' !== trim( $intro_product ) ? $intro_product : __( 'Congratulations! You have received {product} as a gift from {store}. We hope you enjoy it!', 'beltoft-gift-cards-pro' ),
			'heading_product' => '' !== trim( (string) Options::get( 'pdf_heading_product' ) ) ? (string) Options::get( 'pdf_heading_product' ) : __( 'Surprise! A gift just for you!', 'beltoft-gift-cards-pro' ),
			'logo_path'   => $logo_path,
			'logo_url'    => $logo_url,
			'store_name'  => get_bloginfo( 'name' ),
			'shop_url'    => $shop_url,
			'shop_host'   => $shop_host,
			/**
			 * Filter the redemption sentence printed on the card footer.
			 *
			 * @param string $redeem Sentence.
			 */
			'redeem_text' => apply_filters( 'bgcw_pro_pdf_redeem_text', $redeem ),
		];
	}

	/**
	 * Format an amount as plain text (no HTML).
	 */
	public static function format_amount( $amount, string $currency ): string {
		$html = wc_price( (float) $amount, [ 'currency' => $currency ] );

		return html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Split an amount into number and currency symbol for the card.
	 *
	 * Zero decimals are dropped (50,00 -> 50) so the number reads like a denomination.
	 *
	 * @return array{number:string,symbol:string,symbol_first:bool,space:bool,size:string}
	 */
	public static function amount_parts( $amount, string $currency ): array {
		$decimals = wc_get_price_decimals();
		$number   = number_format( (float) $amount, $decimals, wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );
		if ( $decimals > 0 ) {
			$zeros  = wc_get_price_decimal_separator() . str_repeat( '0', $decimals );
			$number = substr( $number, -strlen( $zeros ) ) === $zeros ? substr( $number, 0, -strlen( $zeros ) ) : $number;
		}
		$pos    = (string) get_option( 'woocommerce_currency_pos', 'left' );
		$symbol = html_entity_decode( get_woocommerce_currency_symbol( $currency ), ENT_QUOTES, 'UTF-8' );
		$len    = mb_strlen( $number );
		$size   = $len <= 5 ? 'xl' : ( $len <= 8 ? 'lg' : 'md' );

		return [
			'number'       => $number,
			'symbol'       => $symbol,
			'symbol_first' => in_array( $pos, [ 'left', 'left_space' ], true ),
			'space'        => in_array( $pos, [ 'left_space', 'right_space' ], true ),
			'size'         => $size,
		];
	}

	/**
	 * Format an expiry date as plain text.
	 *
	 * @param string|null $expires_at MySQL datetime or null.
	 */
	public static function format_expiry( $expires_at ): string {
		if ( empty( $expires_at ) ) {
			return __( 'No expiry date', 'beltoft-gift-cards-pro' );
		}

		/* translators: %s: formatted date */
		return sprintf( __( 'Valid until %s', 'beltoft-gift-cards-pro' ), wp_date( get_option( 'date_format' ), strtotime( $expires_at ) ) );
	}

	/**
	 * Render the card.
	 *
	 * @param string $design Design slug.
	 * @param array  $data   amount, currency, code, recipient_name, sender_name, message, expires_at.
	 * @param string $mode   'preview' returns a fragment; 'pdf' returns a full document with embedded CSS.
	 * @return string
	 */
	public static function render( string $design, array $data, string $mode = self::MODE_PREVIEW ): string {
		$design  = Designs::normalize( $design );
		$designs = Designs::get();
		$theme   = $designs[ $design ];
		$store   = self::store();
		$data    = wp_parse_args( $data, self::placeholders() );

		$is_product   = ! empty( $data['product_id'] ) || '' !== trim( (string) $data['product_name'] );
		$product_name = (string) $data['product_name'];
		if ( $is_product && '' === $product_name && function_exists( 'wc_get_product' ) ) {
			$product      = wc_get_product( (int) $data['product_id'] );
			$product_name = $product ? $product->get_name() : '';
		}
		$intro = str_replace(
			[ '{store}', '{product}' ],
			[ $store['store_name'], $product_name ],
			$is_product ? $store['intro_product'] : $store['intro']
		);

		$vars = [
			'design'         => $design,
			'heading'        => $is_product ? $store['heading_product'] : $theme['heading'],
			'intro'          => $intro,
			'is_product'     => $is_product,
			'product_name'   => $product_name,
			'theme'          => $theme,
			'mode'           => $mode,
			'store'          => $store,
			'logo_src'       => self::MODE_PDF === $mode ? $store['logo_path'] : $store['logo_url'],
			'amount_text'    => self::format_amount( $data['amount'], (string) $data['currency'] ),
			'amount'         => self::amount_parts( $data['amount'], (string) $data['currency'] ),
			'code'           => (string) $data['code'],
			'recipient_name' => (string) $data['recipient_name'],
			'sender_name'    => (string) $data['sender_name'],
			'message'        => trim( (string) $data['message'] ),
			'expiry_text'    => self::format_expiry( $data['expires_at'] ),
		];

		ob_start();
		self::include_template( 'templates/pdf/_card.php', $vars );
		$card = (string) ob_get_clean();

		if ( self::MODE_PDF !== $mode ) {
			return $card;
		}

		$html  = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . esc_html( $store['store_name'] ) . '</title>';
		$html .= '<style>' . self::css( self::MODE_PDF ) . '</style></head><body>' . $card . '</body></html>';

		return $html;
	}

	/**
	 * Complete card CSS: fonts, base rules, and one rule set per design.
	 *
	 * @param string $mode 'preview' uses plugin URLs for fonts; 'pdf' uses file paths and adds @page.
	 */
	public static function css( string $mode = self::MODE_PREVIEW ): string {
		$base = (string) file_get_contents( BGCW_PRO_PATH . 'templates/pdf/card.css' );

		$font_base = self::MODE_PDF === $mode
			? 'file://' . BGCW_PRO_PATH . 'assets/fonts/'
			: BGCW_PRO_URL . 'assets/fonts/';

		$fonts = sprintf(
			"@font-face{font-family:'BgcwSans';font-weight:400;src:url('%1\$sOnest-Regular.ttf') format('truetype');}\n" .
			"@font-face{font-family:'BgcwSans';font-weight:700;src:url('%1\$sOnest-Bold.ttf') format('truetype');}\n" .
			"@font-face{font-family:'BgcwSans';font-weight:800;src:url('%1\$sOnest-ExtraBold.ttf') format('truetype');}\n",
			$font_base
		);

		$page = self::MODE_PDF === $mode
			? sprintf( "@page{size:%dpx %dpx;margin:0;}\nhtml,body{margin:0;padding:0;}\n", self::WIDTH, self::HEIGHT )
			: '';

		$designs = '';
		foreach ( Designs::get() as $slug => $theme ) {
			$designs .= sprintf(
				".bgcw-card--%1\$s,.bgcw-card--%1\$s .bgcw-card__heading,.bgcw-card--%1\$s .bgcw-card__brand-name,.bgcw-card--%1\$s .bgcw-card__footer,.bgcw-card--%1\$s .bgcw-card__people,.bgcw-card--%1\$s .bgcw-card__intro,.bgcw-card--%1\$s .bgcw-card__box{color:%2\$s;}\n" .
				".bgcw-card--%1\$s .bgcw-card__box,.bgcw-card--%1\$s .bgcw-card__cell-left{border-color:%2\$s;}\n",
				esc_attr( $slug ),
				esc_attr( $theme['color'] )
			);
		}

		return $page . $fonts . $base . $designs;
	}

	/**
	 * Include a template with scoped variables.
	 */
	private static function include_template( string $relative, array $vars ): void {
		$file = BGCW_PRO_PATH . $relative;
		if ( ! file_exists( $file ) ) {
			return;
		}
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Scoped template variables.
		extract( $vars, EXTR_SKIP );
		include $file;
	}
}
