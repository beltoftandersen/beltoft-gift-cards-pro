<?php
require_once __DIR__ . '/bootstrap.php';

use BgcwPro\Pdf\CardRenderer;
use BgcwPro\Pdf\Designs;

bgcwp_assert_eq( 'classic', Designs::normalize( 'holiday' ), 'removed theme normalizes to classic' );
bgcwp_assert_eq( 'classic', Designs::normalize( '' ), 'empty normalizes to classic' );
bgcwp_assert_eq( 'birthday', Designs::normalize( 'birthday' ), 'valid slug kept' );
bgcwp_assert_eq( [ 'classic', 'birthday', 'celebration' ], array_keys( Designs::get() ), 'three designs' );

$data = [
	'amount'         => 75.5,
	'currency'       => 'EUR',
	'code'           => 'GIFT-ABCD-EFGH-IJKL',
	'recipient_name' => 'Ana Silva',
	'sender_name'    => 'Rui Costa',
	'message'        => 'Parabéns & obrigado <3',
	'expires_at'     => '2030-05-01 00:00:00',
];
foreach ( Designs::get() as $slug => $theme ) {
	$html = CardRenderer::render( $slug, $data );
	bgcwp_assert( false !== strpos( $html, 'bgcw-card--' . $slug ), "$slug: design class present" );
	bgcwp_assert( false !== strpos( $html, esc_html( $theme['heading'] ) ), "$slug: heading present" );
	bgcwp_assert( false !== strpos( $html, esc_html( CardRenderer::format_amount( 75.5, 'EUR' ) ) ), "$slug: amount present" );
	bgcwp_assert( false !== strpos( $html, 'GIFT-ABCD-EFGH-IJKL' ), "$slug: code present" );
	bgcwp_assert( false !== strpos( $html, 'Ana Silva' ) && false !== strpos( $html, 'Rui Costa' ), "$slug: names present" );
	bgcwp_assert( false !== strpos( $html, esc_html( 'Parabéns & obrigado <3' ) ), "$slug: message escaped and present" );
	bgcwp_assert( false !== strpos( $html, '2030' ), "$slug: expiry year present" );
	bgcwp_assert( false === strpos( $html, '<style' ), "$slug: preview fragment has no style block" );
}

$no_msg = CardRenderer::render( 'classic', array_merge( $data, [ 'message' => '' ] ) );
bgcwp_assert( false !== strpos( $no_msg, 'bgcw-card__message" data-bgcw-field="message" style="display:none"' ), 'empty message hides block' );

$pdf_html = CardRenderer::render( 'celebration', $data, CardRenderer::MODE_PDF );
bgcwp_assert( 0 === strpos( $pdf_html, '<!DOCTYPE html>' ), 'pdf mode is a full document' );
bgcwp_assert( false !== strpos( $pdf_html, '@page' ), 'pdf mode has @page' );
bgcwp_assert( false !== strpos( $pdf_html, 'file://' ), 'pdf mode fonts use file paths' );
bgcwp_assert( false !== strpos( CardRenderer::css(), BGCW_PRO_URL . 'assets/fonts/' ), 'preview css fonts use plugin URL' );
bgcwp_assert( false !== strpos( CardRenderer::css(), '.bgcw-card--birthday .bgcw-card__panel{background-color:#E4577B;}' ), 'design rules generated' );

// Admin override changes heading + color.
$opts = get_option( 'bgcw_pro_options', [] );
$orig = $opts;
$opts['pdf_heading_classic'] = 'Custom heading';
$opts['pdf_color_classic']   = '#123456';
update_option( 'bgcw_pro_options', $opts );
BgcwPro\Support\Options::invalidate_cache();
bgcwp_test_register_cleanup( function () use ( $orig ) { update_option( 'bgcw_pro_options', $orig ); BgcwPro\Support\Options::invalidate_cache(); } );
$d = Designs::get();
bgcwp_assert_eq( 'Custom heading', $d['classic']['heading'], 'heading override applied' );
bgcwp_assert_eq( '#123456', $d['classic']['color'], 'color override applied' );
bgcwp_assert_eq( 'No expiry date', CardRenderer::format_expiry( null ), 'null expiry text' );
