<?php

namespace BgcwPro;

use BgcwPro\Licensing\License;
use BgcwPro\Licensing\Updater;
use BgcwPro\Admin\SettingsPage;
use BgcwPro\ScheduledDelivery\Scheduler;
use BgcwPro\ScheduledDelivery\ProductFields as ScheduledProductFields;
use BgcwPro\Pdf\CardRenderer;
use BgcwPro\Pdf\PdfGenerator;
use BgcwPro\Pdf\EmailAttachment;
use BgcwPro\Pdf\Download;
use BgcwPro\Pdf\ProductPreview;
use BgcwPro\Gifting\Giftable;
use BgcwPro\Gifting\GiftForm;
use BgcwPro\Gifting\AddToCartHandler;
use BgcwPro\Gifting\CartDisplay;
use BgcwPro\StoreCredit\OrderHandler;
use BgcwPro\BulkGeneration\Generator;
use BgcwPro\BulkGeneration\CsvHandler;
use BgcwPro\Bogo\BogoManager;
use BgcwPro\Bogo\CartHandler as BogoCartHandler;
use BgcwPro\Analytics\Dashboard;
use BgcwPro\Analytics\ReportGenerator;

defined( 'ABSPATH' ) || exit;

class Plugin {

	/**
	 * Initialize the Pro plugin.
	 */
	public static function init() {
		// Always load licensing + updater + admin settings (even without active license).
		License::init();
		Updater::init();

		if ( is_admin() ) {
			SettingsPage::init();
		}

		// Gate Pro features behind an active license.
		// Deliveries already paid for must still go out if the license lapses.
		Scheduler::register_delivery_handler();

		if ( ! License::is_active() ) {
			return;
		}

		// --- Pro features (with valid license) ---

		// Scheduled delivery.
		Scheduler::init();
		ScheduledProductFields::init();

		// PDF gift cards.
		PdfGenerator::init();
		EmailAttachment::init();
		Download::init();
		ProductPreview::init();

		// Give as a gift (product-locked cards).
		Giftable::init();
		GiftForm::init();
		AddToCartHandler::init();
		CartDisplay::init();

		// Store credit on refund.
		OrderHandler::init();

		// Bulk generation + CSV.
		Generator::init();
		CsvHandler::init();

		// BOGO promotions.
		BogoManager::init();
		BogoCartHandler::init();

		// Analytics & reports.
		Dashboard::init();
		ReportGenerator::init();

		// Admin assets.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );

		// Frontend assets.
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_assets' ] );
	}

	/**
	 * Admin CSS/JS on our pages.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue_admin_assets( $hook ) {
		if ( 'woocommerce_page_bgcw-gift-cards' !== $hook ) {
			return;
		}

		// NOTE: Admin CSS is enqueued by SettingsPage::enqueue_assets() (always loaded).
		// This method only loads JS for Pro features (bulk gen, CSV, BOGO) which require
		// an active license. The license tab script is enqueued by SettingsPage::enqueue_assets().

		wp_enqueue_script(
			'bgcw-pro-admin',
			BGCW_PRO_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			self::asset_version( 'assets/js/admin.js' ),
			true
		);

		wp_localize_script( 'bgcw-pro-admin', 'bgcw_pro_params', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'bgcw_pro_admin' ),
			'i18n'     => [
				'generation_failed'  => __( 'Generation failed.', 'beltoft-gift-cards-pro' ),
				'request_failed'     => __( 'Request failed.', 'beltoft-gift-cards-pro' ),
				'select_csv'         => __( 'Please select a CSV file.', 'beltoft-gift-cards-pro' ),
				'importing'          => __( 'Importing...', 'beltoft-gift-cards-pro' ),
				'import_failed'      => __( 'Import failed.', 'beltoft-gift-cards-pro' ),
				'save_failed'        => __( 'Save failed.', 'beltoft-gift-cards-pro' ),
				'confirm_delete_rule' => __( 'Delete this rule?', 'beltoft-gift-cards-pro' ),
			],
		] );
	}

	/**
	 * Frontend assets for Pro features (date picker, live card preview).
	 */
	public static function enqueue_frontend_assets() {
		if ( ! is_product() ) {
			return;
		}

		// Only load on gift-card product pages and on products that can be given as a gift.
		$product = wc_get_product();
		if ( ! $product || ( 'gift-card' !== $product->get_type() && ! Giftable::is_giftable( $product ) ) ) {
			return;
		}

		wp_enqueue_style(
			'bgcw-pro-frontend',
			BGCW_PRO_URL . 'assets/css/frontend.css',
			[],
			self::asset_version( 'assets/css/frontend.css' )
		);

		wp_enqueue_script(
			'bgcw-pro-frontend',
			BGCW_PRO_URL . 'assets/js/frontend.js',
			[],
			self::asset_version( 'assets/js/frontend.js' ),
			true
		);

		wp_localize_script( 'bgcw-pro-frontend', 'bgcw_pro_gift', [
			'gift_button_text' => __( 'Add gift to cart', 'beltoft-gift-cards-pro' ),
		] );

		if ( EmailAttachment::enabled() ) {
			wp_add_inline_style( 'bgcw-pro-frontend', CardRenderer::css( CardRenderer::MODE_PREVIEW ) );
			wp_localize_script( 'bgcw-pro-frontend', 'bgcw_pro_pdf', ProductPreview::script_params() );
		}
	}

	/**
	 * Build a cache-busting asset version from file modification time.
	 *
	 * @param string $relative_path Relative path from plugin root.
	 * @return string
	 */
	private static function asset_version( $relative_path ) {
		$file = BGCW_PRO_PATH . ltrim( $relative_path, '/' );
		if ( file_exists( $file ) ) {
			return (string) filemtime( $file );
		}

		return BGCW_PRO_VER;
	}
}
