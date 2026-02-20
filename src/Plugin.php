<?php

namespace GiftCardsPro;

use GiftCardsPro\Licensing\License;
use GiftCardsPro\Admin\SettingsPage;
use GiftCardsPro\ScheduledDelivery\Scheduler;
use GiftCardsPro\ScheduledDelivery\ProductFields as ScheduledProductFields;
use GiftCardsPro\EmailThemes\ThemeManager;
use GiftCardsPro\EmailThemes\ProductFields as ThemeProductFields;
use GiftCardsPro\StoreCredit\OrderHandler;
use GiftCardsPro\BulkGeneration\Generator;
use GiftCardsPro\BulkGeneration\CsvHandler;
use GiftCardsPro\Bogo\BogoManager;
use GiftCardsPro\Bogo\CartHandler as BogoCartHandler;
use GiftCardsPro\Analytics\Dashboard;
use GiftCardsPro\Analytics\ReportGenerator;

defined( 'ABSPATH' ) || exit;

class Plugin {

	/**
	 * Initialize the Pro plugin.
	 */
	public static function init() {
		// Always load licensing + admin settings (even without active license).
		License::init();

		if ( is_admin() ) {
			SettingsPage::init();
		}

		// Gate Pro features behind an active license.
		if ( ! License::is_active() ) {
			return;
		}

		// --- Pro features (with valid license) ---

		// Scheduled delivery.
		Scheduler::init();
		ScheduledProductFields::init();

		// Email themes.
		ThemeManager::init();
		ThemeProductFields::init();

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
		if ( 'woocommerce_page_wcgc-gift-cards' !== $hook ) {
			return;
		}

		// NOTE: Admin CSS is enqueued by SettingsPage::enqueue_assets() (always loaded).
		// This method only loads JS for Pro features (bulk gen, CSV, BOGO) which require
		// an active license. License tab JS is inline in render_license_tab().

		wp_enqueue_script(
			'wcgc-pro-admin',
			WCGC_PRO_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			self::asset_version( 'assets/js/admin.js' ),
			true
		);

		wp_localize_script( 'wcgc-pro-admin', 'wcgc_pro_params', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wcgc_pro_admin' ),
			'i18n'     => [
				'generation_failed'  => __( 'Generation failed.', 'smart-gift-cards-for-woocommerce-pro' ),
				'request_failed'     => __( 'Request failed.', 'smart-gift-cards-for-woocommerce-pro' ),
				'select_csv'         => __( 'Please select a CSV file.', 'smart-gift-cards-for-woocommerce-pro' ),
				'importing'          => __( 'Importing...', 'smart-gift-cards-for-woocommerce-pro' ),
				'import_failed'      => __( 'Import failed.', 'smart-gift-cards-for-woocommerce-pro' ),
				'save_failed'        => __( 'Save failed.', 'smart-gift-cards-for-woocommerce-pro' ),
				'confirm_delete_rule' => __( 'Delete this rule?', 'smart-gift-cards-for-woocommerce-pro' ),
			],
		] );
	}

	/**
	 * Frontend assets for Pro features (date picker, theme picker).
	 */
	public static function enqueue_frontend_assets() {
		if ( ! is_product() ) {
			return;
		}

		// Only load on gift-card product pages.
		$product = wc_get_product();
		if ( ! $product || 'gift-card' !== $product->get_type() ) {
			return;
		}

		wp_enqueue_style(
			'wcgc-pro-frontend',
			WCGC_PRO_URL . 'assets/css/frontend.css',
			[],
			self::asset_version( 'assets/css/frontend.css' )
		);

		wp_enqueue_script(
			'wcgc-pro-frontend',
			WCGC_PRO_URL . 'assets/js/frontend.js',
			[],
			self::asset_version( 'assets/js/frontend.js' ),
			true
		);
	}

	/**
	 * Build a cache-busting asset version from file modification time.
	 *
	 * @param string $relative_path Relative path from plugin root.
	 * @return string
	 */
	private static function asset_version( $relative_path ) {
		$file = WCGC_PRO_PATH . ltrim( $relative_path, '/' );
		if ( file_exists( $file ) ) {
			return (string) filemtime( $file );
		}

		return WCGC_PRO_VER;
	}
}
