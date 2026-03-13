<?php
/**
 * Gift Card Delivery Email — Thank You Theme (HTML).
 *
 * @package BgcwPro
 */

defined( 'ABSPATH' ) || exit;

$bgcw_theme_color       = BgcwPro\Support\Options::get( 'theme_color_thank-you' ) ?: '#1A9E8F';
$bgcw_theme_color_light = '#3BBFB0';
$bgcw_theme_bg          = '#E6F7F5';
// translators: Thank you greeting shown in email header.
$bgcw_theme_heading = BgcwPro\Support\Options::get( 'theme_heading_thank-you' )
	?: __( 'Thank You!', 'beltoft-gift-cards-pro' );

require __DIR__ . '/_base.php';
