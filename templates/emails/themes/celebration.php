<?php
/**
 * Gift Card Delivery Email — Celebration Theme (HTML).
 *
 * @package GiftCardsPro
 */

defined( 'ABSPATH' ) || exit;

$wcgc_theme_image   = 'celebration.png';
$wcgc_theme_color   = GiftCardsPro\Support\Options::get( 'theme_color_celebration' ) ?: '#ff9800';
$wcgc_theme_bg      = '#fff3e0';
/* translators: Congratulations greeting with party emoji */
$wcgc_theme_heading = GiftCardsPro\Support\Options::get( 'theme_heading_celebration' )
	?: __( "Congratulations! \xF0\x9F\x8E\x89", 'smart-gift-cards-for-woocommerce-pro' );

require __DIR__ . '/_base.php';
