<?php
require_once __DIR__ . '/bootstrap.php';

use Bgcw\GiftCard\GiftCardCreator;
use Bgcw\GiftCard\Repository;
use BgcwPro\Pdf\PdfGenerator;
use BgcwPro\Support\Options;

// End-to-end: real WC mailer + delivery email class, with wp_mail short-circuited.
$captured = null;
add_filter( 'pre_wp_mail', function ( $null, $atts ) use ( &$captured ) { $captured = $atts; return true; }, 10, 2 );

$opts = get_option( 'bgcw_pro_options', [] );
$orig = $opts;
$opts['pdf_enabled'] = '1';
update_option( 'bgcw_pro_options', $opts );
Options::invalidate_cache();
bgcwp_test_register_cleanup( function () use ( $orig ) { update_option( 'bgcw_pro_options', $orig ); Options::invalidate_cache(); } );

WC()->mailer(); // instantiate email classes so the delivery email listens to bgcw_gift_card_created

$id = GiftCardCreator::create_manual( [ 'amount' => 30, 'source' => 'compensation', 'recipient_email' => 'flow@example.test', 'recipient_name' => 'Flow', 'sender_name' => 'Tester', 'message' => 'Hi', 'send_email' => false ] );
bgcwp_test_register_cleanup( function () use ( $id ) { PdfGenerator::delete_for_card( $id ); Repository::delete( $id ); } );

do_action( 'bgcw_gift_card_created', $id, null );

bgcwp_assert( is_array( $captured ), 'delivery email was sent through wp_mail' );
if ( is_array( $captured ) ) {
	$att = $captured['attachments'] ?? [];
	bgcwp_assert( 1 === count( $att ) && file_exists( $att[0] ) && '.pdf' === substr( $att[0], -4 ), 'exactly one PDF attachment' );
	bgcwp_assert( false !== strpos( $captured['message'], __( 'Your gift card is attached to this email as a PDF. Print it or show the code at checkout.', 'beltoft-gift-cards-pro' ) ), 'neutral pdf notice body used' );
	bgcwp_assert( false !== strpos( $captured['message'], Repository::find( $id )->code ), 'code still present in email body' );
	bgcwp_assert_eq( 'flow@example.test', is_array( $captured['to'] ) ? $captured['to'][0] : $captured['to'], 'sent to recipient' );
}

// Disabled: themed swap off, no attachment.
$captured = null;
$opts['pdf_enabled'] = '0';
update_option( 'bgcw_pro_options', $opts );
Options::invalidate_cache();
do_action( 'bgcw_gift_card_created', $id, null );
bgcwp_assert( is_array( $captured ) && empty( $captured['attachments'] ), 'disabled: no attachment' );
bgcwp_assert( is_array( $captured ) && false === strpos( $captured['message'], __( 'Your gift card is attached to this email as a PDF. Print it or show the code at checkout.', 'beltoft-gift-cards-pro' ) ), 'disabled: original email body' );
