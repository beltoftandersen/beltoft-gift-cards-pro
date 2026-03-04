<?php
/**
 * Gift Card Delivery Email — Holiday Theme (HTML).
 *
 * @package BgcwPro
 */

defined( 'ABSPATH' ) || exit;

$bgcw_theme_color       = BgcwPro\Support\Options::get( 'theme_color_holiday' ) ?: '#B22222';
$bgcw_theme_color_light = '#D94444';
$bgcw_theme_bg          = '#FDEAEA';
// translators: Holiday greeting shown in email header.
$bgcw_theme_heading = BgcwPro\Support\Options::get( 'theme_heading_holiday' )
	?: __( 'Happy Holidays!', 'beltoft-gift-cards-for-woocommerce-pro' );

require __DIR__ . '/_base.php';
