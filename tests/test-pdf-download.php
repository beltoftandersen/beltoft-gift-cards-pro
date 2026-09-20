<?php
require_once __DIR__ . '/bootstrap.php';

use Bgcw\GiftCard\GiftCardCreator;
use Bgcw\GiftCard\Repository;
use BgcwPro\Pdf\Download;
use BgcwPro\Pdf\PdfGenerator;

$admin_id = bgcwp_test_admin_id();

// The button depends on pdf_enabled; pin it for this test and restore afterwards.
$bgcwp_orig_opts = get_option( 'bgcw_pro_options', [] );
$bgcwp_opts      = $bgcwp_orig_opts;
$bgcwp_opts['pdf_enabled'] = '1';
update_option( 'bgcw_pro_options', $bgcwp_opts );
BgcwPro\Support\Options::invalidate_cache();
bgcwp_test_register_cleanup( function () use ( $bgcwp_orig_opts ) { update_option( 'bgcw_pro_options', $bgcwp_orig_opts ); BgcwPro\Support\Options::invalidate_cache(); } );
$owner_id = wp_insert_user( [ 'user_login' => 'bgcwp_owner_' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'bgcwp_owner_' . wp_rand() . '@example.test' ] );
$recip_id = wp_insert_user( [ 'user_login' => 'bgcwp_recip_' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'bgcwp_recip_' . wp_rand() . '@example.test' ] );
$other_id = wp_insert_user( [ 'user_login' => 'bgcwp_other_' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'bgcwp_other_' . wp_rand() . '@example.test' ] );
bgcwp_test_register_cleanup( function () use ( $owner_id, $recip_id, $other_id ) { require_once ABSPATH . 'wp-admin/includes/user.php'; foreach ( [ $owner_id, $recip_id, $other_id ] as $u ) { wp_delete_user( $u ); } } );

$recip_email = get_userdata( $recip_id )->user_email;
$id = GiftCardCreator::create_manual( [ 'amount' => 10, 'source' => 'compensation', 'recipient_email' => strtoupper( $recip_email ), 'send_email' => false ] );
bgcwp_test_register_cleanup( function () use ( $id ) { PdfGenerator::delete_for_card( $id ); Repository::delete( $id ); } );
global $wpdb;
$wpdb->update( Repository::table(), [ 'customer_id' => $owner_id ], [ 'id' => $id ] );
Repository::invalidate_code_cache();
$gc = Repository::find( $id );

bgcwp_assert_eq( true, Download::can_access( $gc, $owner_id ), 'buyer can access' );
bgcwp_assert_eq( true, Download::can_access( $gc, $recip_id ), 'recipient can access (case-insensitive email)' );
bgcwp_assert_eq( false, Download::can_access( $gc, $other_id ), 'stranger denied' );
bgcwp_assert_eq( false, Download::can_access( $gc, 0 ), 'guest denied' );
bgcwp_assert_eq( false, Download::can_access( null, $owner_id ), 'missing card denied' );

$url = Download::url_for_card( $gc );
bgcwp_assert( false !== strpos( $url, 'action=bgcw_pro_pdf_download' ) && false !== strpos( $url, 'card=' . $id ) && false !== strpos( $url, '_wpnonce=' ), 'download url has action, card, nonce' );
bgcwp_assert( false !== strpos( Download::sample_url( 'holiday' ), 'design=classic' ), 'sample url normalizes design' );

// Button renders only for logged-in users when enabled.
wp_set_current_user( $owner_id );
ob_start(); Download::render_button( $gc ); $btn = ob_get_clean();
bgcwp_assert( false !== strpos( $btn, 'bgcw-pro-pdf-download' ) && false !== strpos( $btn, 'action=bgcw_pro_pdf_download' ), 'button rendered for logged-in user' );
wp_set_current_user( 0 );
ob_start(); Download::render_button( $gc ); $btn = ob_get_clean();
bgcwp_assert_eq( '', $btn, 'no button for guests' );
