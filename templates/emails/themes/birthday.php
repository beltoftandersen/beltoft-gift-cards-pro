<?php
/**
 * Gift Card Delivery Email — Birthday Theme (HTML).
 *
 * @package GiftCardsPro
 */

defined( 'ABSPATH' ) || exit;

$wcgc_theme_color       = GiftCardsPro\Support\Options::get( 'theme_color_birthday' ) ?: '#E91E8C';
$wcgc_theme_color_light = '#F06AB5';
$wcgc_theme_bg          = '#FDE7F3';
// translators: Birthday greeting shown in email header.
$wcgc_theme_heading = GiftCardsPro\Support\Options::get( 'theme_heading_birthday' )
	?: __( 'Happy Birthday!', 'smart-gift-cards-for-woocommerce-pro' );

require __DIR__ . '/_base.php';
