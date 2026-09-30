<?php
require_once __DIR__ . '/bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/template.php';

use BgcwPro\Pdf\CardRenderer;
use BgcwPro\Pdf\Logo;
use BgcwPro\Pdf\PdfGenerator;
use BgcwPro\Support\Options;

$orig = get_option( 'bgcw_pro_options', [] );
$orig_logo = get_option( Logo::OPTION, null );
$attachments = [];
bgcwp_test_register_cleanup( function () use ( $orig, $orig_logo, &$attachments ) {
	Logo::delete();
	foreach ( $attachments as $att_id ) {
		wp_delete_attachment( $att_id, true );
	}
	update_option( 'bgcw_pro_options', $orig );
	null === $orig_logo ? delete_option( Logo::OPTION ) : update_option( Logo::OPTION, $orig_logo );
	Options::invalidate_cache();
} );

/**
 * Create an image attachment whose file lives in uploads.
 */
function bgcwp_logo_attachment( array &$attachments, string $color = 'red', bool $real_image = true ): int {
	$upload = wp_upload_dir();
	$path   = trailingslashit( $upload['path'] ) . 'bgcw-test-logo-' . wp_generate_password( 8, false, false ) . '.png';
	if ( $real_image ) {
		$im = imagecreatetruecolor( 40, 20 );
		imagefill( $im, 0, 0, 'red' === $color ? imagecolorallocate( $im, 255, 0, 0 ) : imagecolorallocate( $im, 0, 0, 255 ) );
		imagepng( $im, $path );
		imagedestroy( $im );
	} else {
		file_put_contents( $path, "not an image\n" );
	}
	$id = wp_insert_attachment( [ 'post_mime_type' => 'image/png', 'post_title' => 'bgcw test logo', 'post_status' => 'inherit' ], $path );
	$attachments[] = $id;
	return (int) $id;
}

Logo::delete();
Options::set( 'pdf_logo_id', '' );
$dir     = PdfGenerator::dir();
$version = (int) Options::get( 'pdf_design_version' );

// 1. Choosing a logo copies it into the Pro PDF folder.
$a     = bgcwp_logo_attachment( $attachments );
$clean = Options::sanitize( [ 'pdf_enabled' => '1', 'pdf_logo_id' => (string) $a ] );
bgcwp_assert_eq( (string) $a, $clean['pdf_logo_id'], 'logo id saved' );
bgcwp_assert_eq( $version + 1, (int) $clean['pdf_design_version'], 'logo change bumps design version' );
update_option( 'bgcw_pro_options', $clean );
Options::invalidate_cache();
$copy = Logo::path();
bgcwp_assert( '' !== $copy && 0 === strpos( $copy, $dir . '/' ) && file_exists( $copy ), 'copy stored in the Pro PDF folder' );
bgcwp_assert_eq( md5_file( get_attached_file( $a ) ), md5_file( $copy ), 'copy matches the original' );

// 2. Offload tool deletes the local original: the copy is still used.
$src_a = get_attached_file( $a );
wp_delete_file( $src_a );
bgcwp_assert_eq( $copy, Logo::path(), 'copy still served after the original is deleted' );
bgcwp_assert_eq( $copy, CardRenderer::store()['logo_path'], 'renderer uses the copy' );
$html = CardRenderer::render( 'classic', CardRenderer::placeholders(), CardRenderer::MODE_PDF );
bgcwp_assert( false !== strpos( $html, esc_attr( $copy ) ), 'PDF html points at the copy' );
$sample = PdfGenerator::sample( 'classic' );
bgcwp_assert( is_string( $sample ) && false !== strpos( (string) file_get_contents( $sample ), '/Subtype /Image' ), 'real PDF contains the logo image' );
if ( is_string( $sample ) ) {
	wp_delete_file( $sample );
}

// 3. Choosing a logo whose file is not on this server: error, previous logo kept.
$b = bgcwp_logo_attachment( $attachments );
wp_delete_file( get_attached_file( $b ) );
$GLOBALS['wp_settings_errors'] = [];
$version = (int) Options::get( 'pdf_design_version' );
$clean   = Options::sanitize( [ 'pdf_enabled' => '1', 'pdf_logo_id' => (string) $b ] );
bgcwp_assert_eq( (string) $a, $clean['pdf_logo_id'], 'missing file: previous logo kept' );
bgcwp_assert_eq( $version, (int) $clean['pdf_design_version'], 'missing file: no design version bump' );
$errors = wp_list_pluck( get_settings_errors( Options::OPTION ), 'code' );
bgcwp_assert( in_array( 'bgcw_pro_logo_missing', $errors, true ), 'missing file: settings error shown' );
bgcwp_assert_eq( $copy, Logo::path(), 'missing file: previous copy untouched' );

// 4. Not an image: rejected.
$c = bgcwp_logo_attachment( $attachments, 'red', false );
$GLOBALS['wp_settings_errors'] = [];
$clean  = Options::sanitize( [ 'pdf_enabled' => '1', 'pdf_logo_id' => (string) $c ] );
$errors = wp_list_pluck( get_settings_errors( Options::OPTION ), 'code' );
bgcwp_assert( (string) $a === $clean['pdf_logo_id'] && in_array( 'bgcw_pro_logo_invalid', $errors, true ), 'non-image rejected with an error' );

// 5. Existing sites (logo set before this version): the copy is made on first use.
Logo::delete();
$d = bgcwp_logo_attachment( $attachments );
Options::set( 'pdf_logo_id', (string) $d );
$lazy = Logo::path();
bgcwp_assert( '' !== $lazy && file_exists( $lazy ) && md5_file( get_attached_file( $d ) ) === md5_file( $lazy ), 'copy created on first use' );

// 6. Image edited in the Media Library (new file): the copy is refreshed.
$upload = wp_upload_dir();
$edited = trailingslashit( $upload['path'] ) . 'bgcw-test-logo-edited-' . wp_generate_password( 8, false, false ) . '.png';
$im     = imagecreatetruecolor( 40, 20 );
imagefill( $im, 0, 0, imagecolorallocate( $im, 0, 0, 255 ) );
imagepng( $im, $edited );
imagedestroy( $im );
$old_src = get_attached_file( $d );
update_attached_file( $d, $edited );
wp_delete_file( $old_src );
$refreshed = Logo::path();
bgcwp_assert( '' !== $refreshed && md5_file( $edited ) === md5_file( $refreshed ), 'copy refreshed after the image is edited' );

// 7. Unchanged save with the original gone does not error or drop the logo.
wp_delete_file( $edited );
$GLOBALS['wp_settings_errors'] = [];
$clean = Options::sanitize( [ 'pdf_enabled' => '1', 'pdf_logo_id' => (string) $d ] );
bgcwp_assert( (string) $d === $clean['pdf_logo_id'] && [] === get_settings_errors( Options::OPTION ), 'resave without the original keeps the logo silently' );
bgcwp_assert_eq( $refreshed, Logo::path(), 'resave without the original keeps the copy' );

// 8. Removing the logo deletes the copy.
$clean = Options::sanitize( [ 'pdf_enabled' => '1', 'pdf_logo_id' => '' ] );
bgcwp_assert( '' === $clean['pdf_logo_id'] && ! file_exists( $refreshed ), 'removing the logo deletes the copy' );
update_option( 'bgcw_pro_options', $clean );
Options::invalidate_cache();
bgcwp_assert_eq( '', Logo::path(), 'no logo: empty path' );
