<?php
/**
 * Gift Card Delivery Email — Thank You Theme (HTML).
 *
 * @package GiftCardsPro
 */

defined( 'ABSPATH' ) || exit;

$wcgc_theme_color       = GiftCardsPro\Support\Options::get( 'theme_color_thank-you' ) ?: '#1A9E8F';
$wcgc_theme_color_light = '#3BBFB0';
$wcgc_theme_bg          = '#E6F7F5';
// translators: Thank you greeting shown in email header.
$wcgc_theme_heading = GiftCardsPro\Support\Options::get( 'theme_heading_thank-you' )
	?: __( 'Thank You!', 'smart-gift-cards-for-woocommerce-pro' );

require __DIR__ . '/_base.php';
