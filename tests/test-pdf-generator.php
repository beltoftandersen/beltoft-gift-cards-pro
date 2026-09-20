<?php
require_once __DIR__ . '/bootstrap.php';

use Bgcw\GiftCard\GiftCardCreator;
use Bgcw\GiftCard\Repository;
use BgcwPro\Pdf\PdfGenerator;
use BgcwPro\Support\Options;

global $wpdb;
bgcwp_assert_eq( PdfGenerator::table(), $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', PdfGenerator::table() ) ), 'pdf table exists' );

$id = GiftCardCreator::create_manual( [ 'amount' => 25, 'source' => 'compensation', 'recipient_name' => 'Ana', 'sender_name' => 'Rui', 'message' => 'Enjoy', 'send_email' => false ] );
bgcwp_assert( $id > 0, 'card created' );
bgcwp_test_register_cleanup( function () use ( $id ) { PdfGenerator::delete_for_card( $id ); Repository::delete( $id ); } );
$gc = Repository::find( $id );

bgcwp_assert_eq( 'classic', PdfGenerator::design_for_card( $gc ), 'manual card uses classic' );

$path = PdfGenerator::generate( $gc );
bgcwp_assert( is_string( $path ), 'generate returns a path' . ( is_wp_error( $path ) ? ': ' . $path->get_error_message() : '' ) );
bgcwp_assert( is_string( $path ) && file_exists( $path ) && 0 === strpos( file_get_contents( $path, false, null, 0, 4 ), '%PDF' ), 'file is a PDF' );
bgcwp_assert( false !== strpos( $path, '/bgcw-pdf/' ), 'stored in bgcw-pdf dir' );
bgcwp_assert( file_exists( dirname( $path ) . '/.htaccess' ) && file_exists( dirname( $path ) . '/index.php' ), 'guard files present' );

$row = PdfGenerator::record( $id );
bgcwp_assert_eq( basename( $path ), $row->file, 'record stores file name' );
bgcwp_assert_eq( 'classic', $row->design, 'record stores design' );

$again = PdfGenerator::get_or_generate( $gc );
bgcwp_assert_eq( $path, $again, 'get_or_generate returns cached path' );

// Bump design version -> regenerate, old file removed.
$opts = get_option( 'bgcw_pro_options', [] );
$orig = $opts;
$opts['pdf_design_version'] = (string) ( (int) ( $opts['pdf_design_version'] ?? 1 ) + 1 );
update_option( 'bgcw_pro_options', $opts );
Options::invalidate_cache();
bgcwp_test_register_cleanup( function () use ( $orig ) { update_option( 'bgcw_pro_options', $orig ); Options::invalidate_cache(); } );
$new = PdfGenerator::get_or_generate( $gc );
bgcwp_assert( is_string( $new ) && $new !== $path, 'version bump regenerates to a new file' );
bgcwp_assert( ! file_exists( $path ), 'old file removed' );
bgcwp_assert( file_exists( $new ), 'new file exists' );

// Delete removes everything.
PdfGenerator::delete_for_card( $id );
bgcwp_assert( ! file_exists( $new ), 'delete removes file' );
bgcwp_assert_eq( null, PdfGenerator::record( $id ), 'delete removes record' );

// Deleting the card via repository triggers cleanup through the hook.
PdfGenerator::init();
$p2 = PdfGenerator::generate( $gc );
Repository::delete( $id );
bgcwp_assert( is_string( $p2 ) && ! file_exists( $p2 ), 'bgcw_gift_card_deleted hook removes file' );

// Sample.
$sample = PdfGenerator::sample( 'classic' );
bgcwp_assert( is_string( $sample ) && file_exists( $sample ), 'sample pdf written' );
if ( is_string( $sample ) ) { wp_delete_file( $sample ); }
