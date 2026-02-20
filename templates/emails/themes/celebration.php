<?php
/**
 * Gift Card Delivery Email — Celebration Theme (HTML).
 *
 * @package GiftCardsPro
 */

defined( 'ABSPATH' ) || exit;

$wcgc_theme_color       = GiftCardsPro\Support\Options::get( 'theme_color_celebration' ) ?: '#E88700';
$wcgc_theme_color_light = '#F5A623';
$wcgc_theme_bg          = '#FFF3E0';
// translators: Congratulations greeting shown in email header.
$wcgc_theme_heading = GiftCardsPro\Support\Options::get( 'theme_heading_celebration' )
	?: __( 'Congratulations!', 'smart-gift-cards-for-woocommerce-pro' );

require __DIR__ . '/_base.php';
