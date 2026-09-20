<?php
require_once __DIR__ . '/bootstrap.php';

use Bgcw\GiftCard\ProductLock;
use Bgcw\GiftCard\Repository;
use BgcwPro\Gifting\AddToCartHandler;
use BgcwPro\Gifting\Carrier;
use BgcwPro\Gifting\CartDisplay;
use BgcwPro\Gifting\Giftable;
use BgcwPro\Pdf\PdfGenerator;
use BgcwPro\Support\Options;

$captured = [];
add_filter( 'pre_wp_mail', function ( $null, $atts ) use ( &$captured ) { $captured[] = $atts; return true; }, 10, 2 );

$opts = get_option( 'bgcw_pro_options', [] );
$orig = $opts;
$opts['pdf_enabled'] = '1';
update_option( 'bgcw_pro_options', $opts );
Options::invalidate_cache();
bgcwp_test_register_cleanup( function () use ( $orig ) { $o = get_option( 'bgcw_pro_options', [] ); $orig['gift_carrier_product_id'] = $o['gift_carrier_product_id'] ?? ''; update_option( 'bgcw_pro_options', $orig ); Options::invalidate_cache(); } );

// Products: giftable workshop (meta), category-giftable mug, and a plain product.
$workshop = new WC_Product_Simple(); $workshop->set_name( 'TMP Workshop' ); $workshop->set_regular_price( 80 ); $workshop->set_status( 'publish' ); $workshop_id = $workshop->save();
update_post_meta( $workshop_id, Giftable::META, 'yes' );
$plain = new WC_Product_Simple(); $plain->set_name( 'TMP Plain' ); $plain->set_regular_price( 20 ); $plain->set_status( 'publish' ); $plain_id = $plain->save();
$term = wp_insert_term( 'TMP Giftable Cat ' . wp_rand(), 'product_cat' );
$cat_id = is_wp_error( $term ) ? 0 : (int) $term['term_id'];
$mug = new WC_Product_Simple(); $mug->set_name( 'TMP Mug' ); $mug->set_regular_price( 15 ); $mug->set_status( 'publish' ); $mug->set_category_ids( [ $cat_id ] ); $mug_id = $mug->save();
bgcwp_test_register_cleanup( function () use ( $workshop_id, $plain_id, $mug_id, $cat_id ) { foreach ( [ $workshop_id, $plain_id, $mug_id ] as $p ) { wp_delete_post( $p, true ); } if ( $cat_id ) { wp_delete_term( $cat_id, 'product_cat' ); } } );

bgcwp_assert_eq( true, Giftable::is_giftable( $workshop_id ), 'meta-giftable product is giftable' );
bgcwp_assert_eq( false, Giftable::is_giftable( $plain_id ), 'plain product not giftable' );
bgcwp_assert_eq( false, Giftable::is_giftable( $mug_id ), 'mug not giftable before category setting' );
$opts['giftable_category_ids'] = [ $cat_id ];
update_option( 'bgcw_pro_options', $opts ); Options::invalidate_cache();
bgcwp_assert_eq( true, Giftable::is_giftable( $mug_id ), 'category setting makes mug giftable' );
$expected_amount = (float) wc_get_product( $workshop_id )->get_price();
bgcwp_assert( $expected_amount > 0 && abs( Giftable::gift_amount( wc_get_product( $workshop_id ) ) - $expected_amount ) < 0.001, 'gift amount is the displayed product price (' . $expected_amount . ')' );

// Carrier.
$carrier_id = Carrier::id();
bgcwp_assert( $carrier_id > 0, 'carrier created' );
$carrier = wc_get_product( $carrier_id );
bgcwp_assert( $carrier && 'gift-card' === $carrier->get_type() && 'hidden' === $carrier->get_catalog_visibility() && $carrier->is_purchasable() && 'none' === $carrier->get_tax_status(), 'carrier is a hidden, purchasable, non-taxable gift-card product' );
bgcwp_assert_eq( $carrier_id, Carrier::id(), 'carrier reused' );
bgcwp_assert_eq( true, Carrier::is_carrier( $carrier ), 'carrier detected' );
bgcwp_assert_eq( false, Giftable::is_giftable( $carrier ), 'carrier itself is not giftable' );

// Handler routing.
$_REQUEST['bgcw_gift'] = '1';
bgcwp_assert_eq( 'bgcw_gift', AddToCartHandler::pick_handler( 'simple', wc_get_product( $workshop_id ) ), 'gift flag routes giftable product to our handler' );
bgcwp_assert_eq( 'simple', AddToCartHandler::pick_handler( 'simple', wc_get_product( $plain_id ) ), 'non-giftable keeps default handler' );
unset( $_REQUEST['bgcw_gift'] );
bgcwp_assert_eq( 'simple', AddToCartHandler::pick_handler( 'simple', wc_get_product( $workshop_id ) ), 'no flag keeps default handler' );

// Add gift to cart.
wc_load_cart();
WC()->cart->empty_cart();
WC()->mailer();
$_POST = [ 'bgcw_gift' => '1', 'bgcw_recipient_name' => 'Ana', 'bgcw_recipient_email' => 'gift-recipient@example.test', 'bgcw_message' => 'Enjoy the workshop!' ];
$key = AddToCartHandler::add_gift_to_cart( $workshop_id, 1 );
wc_clear_notices();
bgcwp_assert( is_string( $key ), 'gift added to cart' );
$item = is_string( $key ) ? WC()->cart->get_cart_item( $key ) : null;
bgcwp_assert( $item && (int) $item['product_id'] === $carrier_id, 'cart line is the carrier product' );
bgcwp_assert( abs( (float) ( $item['bgcw_amount'] ?? 0 ) - $expected_amount ) < 0.001, 'cart line amount is the product price' );
bgcwp_assert_eq( $workshop_id, (int) ( $item['bgcw_product_id'] ?? 0 ), 'cart line locked to the workshop' );
bgcwp_assert_eq( 'gift-recipient@example.test', $item['bgcw_recipient_email'] ?? null, 'recipient carried into cart' );
bgcwp_assert( false !== strpos( CartDisplay::item_name( 'x', $item ), 'TMP Workshop' ), 'cart shows Gift: product name' );
WC()->cart->calculate_totals();
$gift_cart_total = (float) WC()->cart->get_total( 'edit' );
bgcwp_assert( $gift_cart_total > 0, 'cart charges for the gift (' . $gift_cart_total . ')' );
$_POST = [];

bgcwp_assert_eq( false, AddToCartHandler::add_gift_to_cart( $plain_id, 1 ), 'non-giftable product rejected' );
wc_clear_notices();

// Checkout -> order -> paid -> locked card with PDF.
$order_id = WC()->checkout()->create_order( [ 'billing_first_name' => 'Rui', 'billing_last_name' => 'Flow', 'billing_email' => 'rui-gift@example.test', 'billing_country' => 'PT', 'payment_method' => 'cod' ] );
bgcwp_assert( is_int( $order_id ) && $order_id > 0, 'order created' );
WC()->cart->empty_cart();
$order = wc_get_order( $order_id );
bgcwp_test_register_cleanup( function () use ( $order_id ) { foreach ( Repository::get_by_order( $order_id ) as $gc ) { PdfGenerator::delete_for_card( $gc->id ); Repository::delete( $gc->id ); } $o = wc_get_order( $order_id ); if ( $o ) { $o->delete( true ); } } );
$items = array_values( $order->get_items() );
bgcwp_assert_eq( 1, count( $items ), 'one order line' );
bgcwp_assert( false !== strpos( $items[0]->get_name(), 'TMP Workshop' ), 'order line named after the gifted product (' . $items[0]->get_name() . ')' );
bgcwp_assert_eq( $workshop_id, (int) $items[0]->get_meta( '_bgcw_product_id' ), 'order line meta has product id' );

$order->update_status( 'processing' );
$cards = Repository::get_by_order( $order_id );
bgcwp_assert_eq( 1, count( $cards ), 'one gift card created' );
$gc = $cards[0];
bgcwp_assert_eq( $workshop_id, (int) $gc->product_id, 'card locked to workshop' );
bgcwp_assert( abs( (float) $gc->initial_amount - $expected_amount ) < 0.001, 'card worth the product price' );
$row = PdfGenerator::record( $gc->id );
bgcwp_assert( $row && file_exists( PdfGenerator::get_or_generate( $gc ) ), 'PDF generated for the locked card' );
$html = \BgcwPro\Pdf\CardRenderer::render( 'classic', PdfGenerator::data_for_card( $gc ) );
bgcwp_assert( false !== strpos( $html, 'bgcw-card--product' ) && false !== strpos( $html, 'TMP Workshop' ), 'card renders the product variant' );
$mails = array_values( array_filter( $captured, function ( $m ) { $to = is_array( $m['to'] ) ? $m['to'][0] : $m['to']; return 'gift-recipient@example.test' === $to; } ) );
bgcwp_assert( 1 === count( $mails ) && ! empty( $mails[0]['attachments'] ) && false !== strpos( $mails[0]['message'], 'TMP Workshop' ), 'recipient email names the product and has the PDF' );

// Redemption: code works only on the workshop, covers it fully.
WC()->cart->empty_cart();
WC()->cart->add_to_cart( $plain_id, 1 );
WC()->cart->calculate_totals();
$plain_only_total = (float) WC()->cart->get_total( 'edit' );
$ok = WC()->cart->apply_coupon( $gc->code ); wc_clear_notices();
bgcwp_assert_eq( false, $ok, 'code rejected without the workshop in cart' );
WC()->cart->add_to_cart( $workshop_id, 1 );
WC()->cart->calculate_totals();
$before = (float) WC()->cart->get_total( 'edit' );
$ok = WC()->cart->apply_coupon( $gc->code ); WC()->cart->calculate_totals(); wc_clear_notices();
$after = (float) WC()->cart->get_total( 'edit' );
WC()->cart->empty_cart();
bgcwp_assert( $ok && $before > $plain_only_total && abs( $after - $plain_only_total ) < 0.05, sprintf( 'code covers only the workshop (plain=%.2f before=%.2f after=%.2f)', $plain_only_total, $before, $after ) );
bgcwp_assert_eq( wc_get_cart_url(), ProductLock::redeem_url( $gc ), 'redeem link goes to the cart' );
