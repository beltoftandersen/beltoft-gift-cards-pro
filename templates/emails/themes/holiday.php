<?php
/**
 * Gift Card Delivery Email — Holiday Theme (HTML).
 *
 * @package GiftCardsPro
 */

defined( 'ABSPATH' ) || exit;

$wcgc_theme_color       = GiftCardsPro\Support\Options::get( 'theme_color_holiday' ) ?: '#B22222';
$wcgc_theme_color_light = '#D94444';
$wcgc_theme_bg          = '#FDEAEA';
// translators: Holiday greeting shown in email header.
$wcgc_theme_heading = GiftCardsPro\Support\Options::get( 'theme_heading_holiday' )
	?: __( 'Happy Holidays!', 'smart-gift-cards-for-woocommerce-pro' );

require __DIR__ . '/_base.php';
