<?php
/**
 * Gift Card Delivery Email — Birthday Theme (HTML).
 *
 * @package GiftCardsPro
 */

defined( 'ABSPATH' ) || exit;

$wcgc_theme_image   = 'birthday.png';
$wcgc_theme_color   = GiftCardsPro\Support\Options::get( 'theme_color_birthday' ) ?: '#e91e63';
$wcgc_theme_bg      = '#fce4ec';
/* translators: Birthday greeting with cake emoji */
$wcgc_theme_heading = GiftCardsPro\Support\Options::get( 'theme_heading_birthday' )
	?: __( "Happy Birthday! \xF0\x9F\x8E\x82", 'smart-gift-cards-for-woocommerce-pro' );

require __DIR__ . '/_base.php';
