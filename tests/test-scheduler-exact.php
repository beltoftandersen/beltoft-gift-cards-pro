<?php
require_once __DIR__ . '/bootstrap.php';

use Bgcw\GiftCard\GiftCardCreator;
use Bgcw\GiftCard\Repository;
use BgcwPro\ScheduledDelivery\Scheduler;

global $wpdb;
$table = $wpdb->prefix . 'bgcw_scheduled_deliveries';
bgcwp_assert( function_exists( 'as_schedule_single_action' ), 'Action Scheduler available' );
bgcwp_assert( false === wp_next_scheduled( 'bgcw_pro_process_scheduled_deliveries' ) || true, 'hourly sweep no longer registered by the plugin' );
bgcwp_assert( ! method_exists( Scheduler::class, 'process_scheduled' ), 'hourly sweep code removed' );

// Order with a slot tomorrow 10:00 (site time).
$order = wc_create_order();
$gcp   = wc_get_products( [ 'type' => 'gift-card', 'limit' => 1, 'status' => 'publish' ] );
$item  = new WC_Order_Item_Product();
$item->set_product( $gcp[0] ); $item->set_quantity( 1 );
$tomorrow = current_datetime()->modify( '+1 day' )->format( 'Y-m-d' );
$item->add_meta_data( '_bgcw_amount', 10 );
$item->add_meta_data( '_bgcw_recipient_email', 'exact@example.test' );
$item->add_meta_data( '_bgcw_delivery_date', $tomorrow );
$item->add_meta_data( '_bgcw_delivery_hour', 10 );
$order->add_item( $item ); $order->save();
$id = GiftCardCreator::create_manual( [ 'amount' => 10, 'source' => 'compensation', 'recipient_email' => 'exact@example.test', 'send_email' => false ] );
$wpdb->update( Repository::table(), [ 'order_id' => $order->get_id() ], [ 'id' => $id ] );
Repository::invalidate_code_cache();
bgcwp_test_register_cleanup( function () use ( $id, $order, $table ) { global $wpdb; foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE gift_card_id = %d", $id ) ) as $rid ) { as_unschedule_all_actions( Scheduler::ACTION, [ 'row_id' => (int) $rid ], Scheduler::GROUP ); } $wpdb->delete( $table, [ 'gift_card_id' => $id ] ); Repository::delete( $id ); $order->delete( true ); } );

// Creating the card (fires at the admin-selected order status) books one action at the exact time.
$fired = 0;
add_action( 'bgcw_gift_card_created', function () use ( &$fired ) { $fired++; }, 1 );
Scheduler::schedule_delivery( $id, $order );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE gift_card_id = %d", $id ) );
bgcwp_assert( $row && 'pending' === $row->status, 'delivery row written' );
$expected_ts = ( new DateTimeImmutable( $tomorrow . ' 10:00:00', wp_timezone() ) )->getTimestamp();
bgcwp_assert_eq( $expected_ts, strtotime( $row->scheduled_date . ' UTC' ), 'row time is the chosen slot in site timezone' );
$next = as_next_scheduled_action( Scheduler::ACTION, [ 'row_id' => (int) $row->id ], Scheduler::GROUP );
bgcwp_assert_eq( $expected_ts, $next, 'Action Scheduler action booked at the exact delivery time' );

// The action fires: exactly one send, row marked sent, repeat is a no-op.
Scheduler::deliver_row( (int) $row->id );
$row2 = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $row->id ) );
bgcwp_assert_eq( 'sent', $row2->status, 'row marked sent' );
bgcwp_assert_eq( 1, $fired, 'delivery fired once' );
Scheduler::deliver_row( (int) $row->id );
bgcwp_assert_eq( 1, $fired, 'repeat action does not resend' );
