<?php
/**
 * Gift Card Delivery Email — Classic Theme (HTML).
 *
 * @package GiftCardsPro
 */

defined( 'ABSPATH' ) || exit;

$wcgc_theme_color       = GiftCardsPro\Support\Options::get( 'theme_color_classic' ) ?: '#6B4C9A';
$wcgc_theme_color_light = '#8B6CB3';
$wcgc_theme_bg          = '#F3EEFC';
$wcgc_theme_heading     = GiftCardsPro\Support\Options::get( 'theme_heading_classic' )
	?: __( "You've received a gift card!", 'smart-gift-cards-for-woocommerce-pro' );

require __DIR__ . '/_base.php';
