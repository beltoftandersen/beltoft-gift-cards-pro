<?php
/**
 * Gift Card Delivery Email — Classic Theme (HTML).
 *
 * @package GiftCardsPro
 */

defined( 'ABSPATH' ) || exit;

$wcgc_theme_image   = 'classic.png';
$wcgc_theme_color   = GiftCardsPro\Support\Options::get( 'theme_color_classic' ) ?: '#7f54b3';
$wcgc_theme_bg      = '#f5f5f5';
$wcgc_theme_heading = GiftCardsPro\Support\Options::get( 'theme_heading_classic' )
	?: __( "You've received a gift card!", 'smart-gift-cards-for-woocommerce-pro' );

require __DIR__ . '/_base.php';
