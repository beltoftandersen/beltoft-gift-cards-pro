<?php
/**
 * Gift Card Delivery Email — Classic Theme (HTML).
 *
 * @package BgcwPro
 */

defined( 'ABSPATH' ) || exit;

$bgcw_theme_color       = BgcwPro\Support\Options::get( 'theme_color_classic' ) ?: '#6B4C9A';
$bgcw_theme_color_light = '#8B6CB3';
$bgcw_theme_bg          = '#F3EEFC';
$bgcw_theme_heading     = BgcwPro\Support\Options::get( 'theme_heading_classic' )
	?: __( "You've received a gift card!", 'beltoft-gift-cards-for-woocommerce-pro' );

require __DIR__ . '/_base.php';
