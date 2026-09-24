<?php
require_once __DIR__ . '/bootstrap.php';

use Bgcw\GiftCard\GiftCardCreator;
use Bgcw\GiftCard\Repository;
use BgcwPro\ScheduledDelivery\Scheduler;

global $wpdb;
$table = $wpdb->prefix . 'bgcw_scheduled_deliveries';

// A due row whose order still says "tomorrow 10:00" must stay pending with the re-synced time.
$order = wc_create_order();
$gcp   = wc_get_products( [ 'type' => 'gift-card', 'limit' => 1, 'status' => 'publish' ] );
$item  = new WC_Order_Item_Product();
$item->set_product( $gcp[0] ); $item->set_quantity( 1 );
$tomorrow = current_datetime()->modify( '+1 day' )->format( 'Y-m-d' );
$item->add_meta_data( '_bgcw_amount', 10 );
$item->add_meta_data( '_bgcw_recipient_email', 'resync@example.test' );
$item->add_meta_data( '_bgcw_delivery_date', $tomorrow );
$item->add_meta_data( '_bgcw_delivery_hour', 10 );
$order->add_item( $item ); $order->save();
$id = GiftCardCreator::create_manual( [ 'amount' => 10, 'source' => 'compensation', 'recipient_email' => 'resync@example.test', 'send_email' => false ] );
$wpdb->update( Repository::table(), [ 'order_id' => $order->get_id() ], [ 'id' => $id ] );
$wpdb->insert( $table, [ 'gift_card_id' => $id, 'order_id' => $order->get_id(), 'scheduled_date' => gmdate( 'Y-m-d H:i:s', time() - 600 ), 'status' => 'pending', 'created_at' => current_time( 'mysql', true ) ] );
$row_id = $wpdb->insert_id;
bgcwp_test_register_cleanup( function () use ( $id, $order, $table, $row_id ) { global $wpdb; $wpdb->delete( $table, [ 'gift_card_id' => $id ] ); Repository::delete( $id ); $order->delete( true ); } );

$fired = 0;
add_action( 'bgcw_gift_card_created', function () use ( &$fired ) { $fired++; }, 1 );
Scheduler::process_scheduled();

$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $row_id ) );
bgcwp_assert_eq( 'pending', $row->status, 'row stays pending when the slot is still in the future' );
bgcwp_assert( strtotime( $row->scheduled_date . ' UTC' ) > time(), 'scheduled_date re-synced to the future slot (' . $row->scheduled_date . ')' );
bgcwp_assert_eq( 0, $fired, 'delivery event not fired early' );
bgcwp_assert_eq( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE gift_card_id = %d", $id ) ), 'no duplicate row created' );

// Move the slot into the past: the row is processed and marked sent.
foreach ( $order->get_items() as $it ) { $it->update_meta_data( '_bgcw_delivery_date', current_datetime()->modify( '-1 day' )->format( 'Y-m-d' ) ); $it->save(); }
$wpdb->update( $table, [ 'scheduled_date' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ], [ 'id' => $row_id ] );
Scheduler::process_scheduled();
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $row_id ) );
bgcwp_assert_eq( 'sent', $row->status, 'row marked sent once the slot has passed' );
bgcwp_assert_eq( 1, $fired, 'delivery event fired exactly once' );
