<?php
/**
 * Gift Card Delivery Email — Birthday Theme (HTML).
 *
 * @package BgcwPro
 */

defined( 'ABSPATH' ) || exit;

$bgcw_theme_color       = BgcwPro\Support\Options::get( 'theme_color_birthday' ) ?: '#E91E8C';
$bgcw_theme_color_light = '#F06AB5';
$bgcw_theme_bg          = '#FDE7F3';
// translators: Birthday greeting shown in email header.
$bgcw_theme_heading = BgcwPro\Support\Options::get( 'theme_heading_birthday' )
	?: __( 'Happy Birthday!', 'beltoft-gift-cards-for-woocommerce-pro' );

require __DIR__ . '/_base.php';
