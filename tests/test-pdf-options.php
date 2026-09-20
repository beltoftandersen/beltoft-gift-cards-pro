<?php
require_once __DIR__ . '/bootstrap.php';

use BgcwPro\Support\Options;

$orig = get_option( 'bgcw_pro_options', [] );
bgcwp_test_register_cleanup( function () use ( $orig ) { update_option( 'bgcw_pro_options', $orig ); Options::invalidate_cache(); } );
Options::invalidate_cache();
$version = (int) Options::get( 'pdf_design_version' );

// Browser posts the built-in default color (lowercased) for untouched pickers: not a change.
$clean = Options::sanitize( [ 'pdf_enabled' => '1', 'pdf_color_classic' => '#16213e', 'pdf_heading_classic' => '' ] );
bgcwp_assert_eq( '', $clean['pdf_color_classic'], 'default color is not stored as override' );
bgcwp_assert_eq( $version, (int) $clean['pdf_design_version'], 'no version bump on untouched save' );

// A real color change bumps the version once.
$clean = Options::sanitize( [ 'pdf_enabled' => '1', 'pdf_color_classic' => '#123456' ] );
bgcwp_assert_eq( '#123456', $clean['pdf_color_classic'], 'custom color stored' );
bgcwp_assert_eq( $version + 1, (int) $clean['pdf_design_version'], 'version bumped on color change' );

// Legacy theme keys are dropped.
$clean = Options::sanitize( [ 'pdf_enabled' => '1' ] );
bgcwp_assert( ! array_key_exists( 'email_themes', $clean ) && ! array_key_exists( 'theme_color_holiday', $clean ), 'legacy theme options removed' );
bgcwp_assert( ! array_key_exists( 'pdf_color_birthday', $clean ), 'no per-design keys for removed designs' );

// Invalid logo id -> empty.
$clean = Options::sanitize( [ 'pdf_enabled' => '1', 'pdf_logo_id' => 'abc' ] );
bgcwp_assert_eq( '', $clean['pdf_logo_id'], 'invalid logo id cleared' );
