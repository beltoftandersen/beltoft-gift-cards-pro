<?php
/**
 * Gift Card Delivery Email — Holiday Theme (HTML).
 *
 * @package GiftCardsPro
 */

defined( 'ABSPATH' ) || exit;

$wcgc_theme_image   = 'holiday.png';
$wcgc_theme_color   = GiftCardsPro\Support\Options::get( 'theme_color_holiday' ) ?: '#c62828';
$wcgc_theme_bg      = '#fbe9e7';
/* translators: Holiday greeting with star emoji */
$wcgc_theme_heading = GiftCardsPro\Support\Options::get( 'theme_heading_holiday' )
	?: __( "Happy Holidays! \xE2\xAD\x90", 'smart-gift-cards-for-woocommerce-pro' );

require __DIR__ . '/_base.php';
