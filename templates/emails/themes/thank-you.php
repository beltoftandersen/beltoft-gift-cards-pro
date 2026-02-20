<?php
/**
 * Gift Card Delivery Email — Thank You Theme (HTML).
 *
 * @package GiftCardsPro
 */

defined( 'ABSPATH' ) || exit;

$wcgc_theme_image   = 'thank-you.png';
$wcgc_theme_color   = GiftCardsPro\Support\Options::get( 'theme_color_thank-you' ) ?: '#4caf50';
$wcgc_theme_bg      = '#e8f5e9';
/* translators: Thank you greeting with green heart emoji */
$wcgc_theme_heading = GiftCardsPro\Support\Options::get( 'theme_heading_thank-you' )
	?: __( "Thank You! \xF0\x9F\x92\x9A", 'smart-gift-cards-for-woocommerce-pro' );

require __DIR__ . '/_base.php';
