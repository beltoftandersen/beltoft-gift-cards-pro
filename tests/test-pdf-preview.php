<?php
require_once __DIR__ . '/bootstrap.php';

use BgcwPro\Pdf\ProductPreview;

$_POST['bgcw_design_theme'] = 'holiday';
$data = ProductPreview::add_cart_data( [], 1 );
bgcwp_assert_eq( 'classic', $data['bgcw_design_theme'] ?? null, 'removed theme slug stored as classic' );
$_POST['bgcw_design_theme'] = 'birthday';
$data = ProductPreview::add_cart_data( [], 1 );
bgcwp_assert_eq( 'birthday', $data['bgcw_design_theme'] ?? null, 'valid slug kept' );
unset( $_POST['bgcw_design_theme'] );
bgcwp_assert_eq( [], ProductPreview::add_cart_data( [], 1 ), 'no field, no data' );

$display = ProductPreview::display_cart_data( [], [ 'bgcw_design_theme' => 'celebration' ] );
bgcwp_assert_eq( \BgcwPro\Pdf\Designs::get()['celebration']['name'], $display[0]['value'] ?? null, 'design name shown in cart' );

$item = new WC_Order_Item_Product();
ProductPreview::save_order_item_meta( $item, 'k', [ 'bgcw_design_theme' => 'thank-you' ], null );
bgcwp_assert_eq( 'classic', $item->get_meta( '_bgcw_design_theme' ), 'order item meta normalized' );

ob_start();
ProductPreview::render( null );
$html = ob_get_clean();
bgcwp_assert_eq( 3, substr_count( $html, 'name="bgcw_design_theme"' ), 'three design radios' );
bgcwp_assert( false !== strpos( $html, 'class="bgcw-card bgcw-card--classic"' ), 'preview card rendered with classic' );
bgcwp_assert( false !== strpos( $html, 'GIFT-XXXX-XXXX' ), 'placeholder code shown' );

$params = ProductPreview::script_params();
bgcwp_assert( isset( $params['currency']['symbol'], $params['headings']['birthday'], $params['placeholders']['message'] ), 'script params complete' );
bgcwp_assert_eq( 559, $params['card_width'], 'card width param' );
