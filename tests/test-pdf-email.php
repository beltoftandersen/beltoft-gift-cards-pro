<?php
require_once __DIR__ . '/bootstrap.php';

use Bgcw\GiftCard\GiftCardCreator;
use Bgcw\GiftCard\Repository;
use BgcwPro\Pdf\EmailAttachment;
use BgcwPro\Pdf\PdfGenerator;
use BgcwPro\Support\Options;

EmailAttachment::init();

$id = GiftCardCreator::create_manual( [ 'amount' => 20, 'source' => 'compensation', 'recipient_name' => 'Ana', 'sender_name' => 'Rui', 'send_email' => false ] );
bgcwp_test_register_cleanup( function () use ( $id ) { PdfGenerator::delete_for_card( $id ); Repository::delete( $id ); } );
$gc    = Repository::find( $id );
$email = (object) [ 'gift_card' => $gc ];

$opts = get_option( 'bgcw_pro_options', [] );
$orig = $opts;
bgcwp_test_register_cleanup( function () use ( $orig ) { update_option( 'bgcw_pro_options', $orig ); Options::invalidate_cache(); } );
$opts['pdf_enabled'] = '1';
update_option( 'bgcw_pro_options', $opts );
Options::invalidate_cache();

$att = apply_filters( 'woocommerce_email_attachments', [], 'bgcw_gift_card_delivery', $gc, $email );
bgcwp_assert( 1 === count( $att ) && '.pdf' === substr( $att[0], -4 ) && file_exists( $att[0] ), 'PDF attached to delivery email' );
bgcwp_assert_eq( [], apply_filters( 'woocommerce_email_attachments', [], 'customer_completed_order', null, null ), 'other emails untouched' );
bgcwp_assert_eq( [ 'x.txt' ], apply_filters( 'woocommerce_email_attachments', [ 'x.txt' ], 'customer_completed_order', null, null ), 'existing attachments preserved' );

$tpl = apply_filters( 'bgcw_email_template_html', 'emails/gift-card-delivery.php', $gc );
bgcwp_assert_eq( 'emails/pdf-notice.php', $tpl, 'html template swapped to pdf notice' );
$located = apply_filters( 'woocommerce_locate_template', 'orig', 'emails/pdf-notice.php', '' );
bgcwp_assert_eq( BGCW_PRO_PATH . 'templates/emails/pdf-notice.php', $located, 'pdf notice resolves to Pro template' );

$html = wc_get_template_html( 'emails/pdf-notice.php', [ 'gift_card' => $gc, 'order' => null, 'email_heading' => 'H', 'sent_to_admin' => false, 'plain_text' => false, 'email' => new WC_Email() ], '', BGCW_PRO_PATH . 'templates/' );
bgcwp_assert( false !== strpos( $html, $gc->code ) && false !== strpos( $html, 'attached to this email as a PDF' ), 'notice email renders code and attachment sentence' );

// Disabled -> nothing.
$opts['pdf_enabled'] = '0';
update_option( 'bgcw_pro_options', $opts );
Options::invalidate_cache();
bgcwp_assert_eq( [], apply_filters( 'woocommerce_email_attachments', [], 'bgcw_gift_card_delivery', $gc, $email ), 'disabled: no attachment' );
bgcwp_assert_eq( 'emails/gift-card-delivery.php', apply_filters( 'bgcw_email_template_html', 'emails/gift-card-delivery.php', $gc ), 'disabled: template unchanged' );
