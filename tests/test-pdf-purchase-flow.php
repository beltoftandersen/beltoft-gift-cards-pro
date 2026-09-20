<?php
require_once __DIR__ . '/bootstrap.php';

use Bgcw\GiftCard\Repository;
use BgcwPro\Pdf\PdfGenerator;
use BgcwPro\Support\Options;

// Simulated purchase: product page -> cart -> checkout order -> paid -> cards + PDFs.
$captured = [];
add_filter( 'pre_wp_mail', function ( $null, $atts ) use ( &$captured ) { $captured[] = $atts; return true; }, 10, 2 );

$opts = get_option( 'bgcw_pro_options', [] );
$orig = $opts;
$opts['pdf_enabled'] = '1';
update_option( 'bgcw_pro_options', $opts );
Options::invalidate_cache();
bgcwp_test_register_cleanup( function () use ( $orig ) { update_option( 'bgcw_pro_options', $orig ); Options::invalidate_cache(); } );

$ids = wc_get_products( [ 'type' => 'gift-card', 'limit' => 1, 'status' => 'publish', 'return' => 'ids' ] );
bgcwp_assert( ! empty( $ids ), 'a published gift card product exists' );
$product_id = (int) $ids[0];

wc_load_cart();
WC()->cart->empty_cart();
WC()->mailer();
$recipient = 'buyer-flow-' . wp_rand() . '@example.test';

// Two items to the same recipient; posted design slugs are ignored (single design).
foreach ( [ [ 50, 'birthday' ], [ 100, 'classic' ] ] as $line ) {
	$_POST = [
		'bgcw_amount'          => (string) $line[0],
		'bgcw_recipient_name'  => 'Ana Flow',
		'bgcw_recipient_email' => $recipient,
		'bgcw_message'         => 'Design ' . $line[1],
		'bgcw_design_theme'    => $line[1],
	];
	$key = WC()->cart->add_to_cart( $product_id, 1 );
	bgcwp_assert( is_string( $key ) && '' !== $key, 'added ' . $line[1] . ' card to cart' );
}
$_POST = [];
bgcwp_assert_eq( 2, count( WC()->cart->get_cart() ), 'two cart lines' );

$order_id = WC()->checkout()->create_order( [
	'billing_first_name' => 'Rui',
	'billing_last_name'  => 'Flow',
	'billing_email'      => 'rui-flow@example.test',
	'billing_country'    => 'PT',
	'payment_method'     => 'cod',
] );
bgcwp_assert( is_int( $order_id ) && $order_id > 0, 'order created from cart' . ( is_wp_error( $order_id ) ? ': ' . $order_id->get_error_message() : '' ) );
WC()->cart->empty_cart();
$order = wc_get_order( $order_id );
bgcwp_test_register_cleanup( function () use ( $order_id ) {
	foreach ( Repository::get_by_order( $order_id ) as $gc ) { PdfGenerator::delete_for_card( $gc->id ); Repository::delete( $gc->id ); }
	$o = wc_get_order( $order_id ); if ( $o ) { $o->delete( true ); }
} );

$designs_by_amount = [];
foreach ( $order->get_items() as $item ) {
	$designs_by_amount[ (string) (float) $item->get_meta( '_bgcw_amount' ) ] = $item->get_meta( '_bgcw_design_theme' );
}
bgcwp_assert_eq( 'classic', $designs_by_amount['50'] ?? null, 'order item 50 stores classic design (posted slug normalized)' );
bgcwp_assert_eq( 'classic', $designs_by_amount['100'] ?? null, 'order item 100 stores classic design' );

// Pay -> cards created -> emails with PDFs.
$order->update_status( 'processing' );
$cards = Repository::get_by_order( $order_id );
bgcwp_assert_eq( 2, count( $cards ), 'two gift cards created on processing' );

foreach ( $cards as $gc ) {
	$expected = 'classic';
	bgcwp_assert_eq( $expected, PdfGenerator::design_for_card( $gc ), 'card ' . $gc->initial_amount . ' resolves ' . $expected . ' design' );
	$row = PdfGenerator::record( $gc->id );
	bgcwp_assert( $row && $expected === $row->design, 'stored PDF for ' . $gc->initial_amount . ' uses ' . $expected );
	bgcwp_assert_eq( $recipient, $gc->recipient_email, 'recipient stored on card ' . $gc->initial_amount );
	bgcwp_assert_eq( 'Rui', $gc->sender_name, 'sender from billing name' );
}

$delivery = array_values( array_filter( $captured, function ( $m ) use ( $recipient ) { $to = is_array( $m['to'] ) ? $m['to'][0] : $m['to']; return $to === $recipient; } ) );
bgcwp_assert_eq( 2, count( $delivery ), 'two delivery emails sent to recipient' );
foreach ( $delivery as $m ) {
	bgcwp_assert( 1 === count( $m['attachments'] ?? [] ) && file_exists( $m['attachments'][0] ), 'delivery email has one PDF attached' );
	bgcwp_assert( false !== strpos( $m['message'], 'attached to this email as a PDF' ), 'delivery email uses the pdf notice' );
}
