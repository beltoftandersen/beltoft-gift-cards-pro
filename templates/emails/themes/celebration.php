<?php
/**
 * Gift Card Delivery Email — Celebration Theme (HTML).
 *
 * @package BgcwPro
 */

defined( 'ABSPATH' ) || exit;

$bgcw_theme_color       = BgcwPro\Support\Options::get( 'theme_color_celebration' ) ?: '#E88700';
$bgcw_theme_color_light = '#F5A623';
$bgcw_theme_bg          = '#FFF3E0';
// translators: Congratulations greeting shown in email header.
$bgcw_theme_heading = BgcwPro\Support\Options::get( 'theme_heading_celebration' )
	?: __( 'Congratulations!', 'beltoft-gift-cards-for-woocommerce-pro' );

require __DIR__ . '/_base.php';
