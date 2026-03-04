<?php

namespace BgcwPro\Licensing;

use BgcwPro\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Self-hosted license validation.
 *
 * Valid license keys are stored in wp_option `bgcw_pro_licenses` as an array:
 *   [ 'XXXX-XXXX-XXXX-XXXX' => [ 'expires' => '2027-12-31', 'status' => 'active', 'created' => '...' ], ... ]
 *
 * Generate keys via WP-CLI:
 *   wp bgcw-pro license:generate --expires=2027-12-31 --allow-root
 *   wp bgcw-pro license:list --allow-root
 *   wp bgcw-pro license:revoke --key=XXXX-XXXX-XXXX-XXXX --allow-root
 */
class License {

	const LICENSES_OPTION = 'bgcw_pro_licenses';

	/** @var int Grace period in days after license expiry. */
	const GRACE_DAYS = 7;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'bgcw_pro_license_check', [ __CLASS__, 'cron_check' ] );
		add_action( 'admin_notices', [ __CLASS__, 'admin_notices' ] );

		// AJAX handlers.
		add_action( 'wp_ajax_bgcw_pro_activate_license', [ __CLASS__, 'ajax_activate' ] );
		add_action( 'wp_ajax_bgcw_pro_deactivate_license', [ __CLASS__, 'ajax_deactivate' ] );

		// WP-CLI commands.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'bgcw-pro', [ __CLASS__, 'cli_dispatch' ] );
		}
	}

	/**
	 * Check if the license is currently active (including grace period).
	 */
	public static function is_active(): bool {
		$status = Options::get( 'license_status' );

		if ( 'valid' === $status ) {
			return true;
		}

		// Check grace period.
		if ( 'expired' === $status || 'grace' === $status ) {
			$grace_until = Options::get( 'license_grace_until' );
			if ( $grace_until && strtotime( $grace_until ) > time() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Activate a license key by checking it against locally stored valid keys.
	 *
	 * @param string $key License key.
	 * @return array { success: bool, message: string }
	 */
	public static function activate( string $key ): array {
		$key = strtoupper( trim( $key ) );

		if ( empty( $key ) ) {
			return [ 'success' => false, 'message' => __( 'Please enter a license key.', 'beltoft-gift-cards-for-woocommerce-pro' ) ];
		}

		$licenses = get_option( self::LICENSES_OPTION, [] );

		if ( ! isset( $licenses[ $key ] ) ) {
			return [ 'success' => false, 'message' => __( 'This license key is invalid.', 'beltoft-gift-cards-for-woocommerce-pro' ) ];
		}

		$license = $licenses[ $key ];

		if ( ( $license['status'] ?? '' ) === 'revoked' ) {
			return [ 'success' => false, 'message' => __( 'This license key has been revoked.', 'beltoft-gift-cards-for-woocommerce-pro' ) ];
		}

		// Check expiry.
		$expires = $license['expires'] ?? 'lifetime';
		if ( 'lifetime' !== $expires && strtotime( $expires ) < time() ) {
			return [ 'success' => false, 'message' => __( 'This license key has expired.', 'beltoft-gift-cards-for-woocommerce-pro' ) ];
		}

		// Mark as activated.
		$licenses[ $key ]['activated_site'] = home_url();
		$licenses[ $key ]['activated_at']   = current_time( 'mysql' );
		update_option( self::LICENSES_OPTION, $licenses );

		Options::set( [
			'license_key'          => $key,
			'license_status'       => 'valid',
			'license_expires'      => $expires,
			'license_last_checked' => current_time( 'mysql' ),
			'license_grace_until'  => '',
		] );

		return [ 'success' => true, 'message' => __( 'License activated successfully.', 'beltoft-gift-cards-for-woocommerce-pro' ) ];
	}

	/**
	 * Deactivate the license key.
	 *
	 * @return array { success: bool, message: string }
	 */
	public static function deactivate(): array {
		$key = Options::get( 'license_key' );
		if ( empty( $key ) ) {
			return [ 'success' => false, 'message' => __( 'No license key to deactivate.', 'beltoft-gift-cards-for-woocommerce-pro' ) ];
		}

		Options::set( [
			'license_key'          => '',
			'license_status'       => '',
			'license_expires'      => '',
			'license_last_checked' => '',
			'license_grace_until'  => '',
		] );

		return [ 'success' => true, 'message' => __( 'License deactivated successfully.', 'beltoft-gift-cards-for-woocommerce-pro' ) ];
	}

	/**
	 * Check license status (called daily by cron).
	 */
	public static function cron_check() {
		$key = Options::get( 'license_key' );
		if ( empty( $key ) ) {
			return;
		}

		$licenses = get_option( self::LICENSES_OPTION, [] );
		Options::set( 'license_last_checked', current_time( 'mysql' ) );

		// Key no longer exists or was revoked.
		if ( ! isset( $licenses[ $key ] ) || ( $licenses[ $key ]['status'] ?? '' ) === 'revoked' ) {
			self::start_grace_or_expire();
			return;
		}

		$expires = $licenses[ $key ]['expires'] ?? 'lifetime';

		if ( 'lifetime' === $expires || strtotime( $expires ) >= time() ) {
			Options::set( [
				'license_status'      => 'valid',
				'license_expires'     => $expires,
				'license_grace_until' => '',
			] );
			return;
		}

		// License expired.
		self::start_grace_or_expire();
	}

	/**
	 * Start grace period on first expiry detection, or mark fully expired.
	 */
	private static function start_grace_or_expire() {
		$current_status = Options::get( 'license_status' );

		if ( 'valid' === $current_status ) {
			$grace_until = gmdate( 'Y-m-d H:i:s', time() + ( self::GRACE_DAYS * DAY_IN_SECONDS ) );
			Options::set( [
				'license_status'      => 'expired',
				'license_grace_until' => $grace_until,
			] );
		}
	}

	/**
	 * Show admin notices for license issues.
	 */
	public static function admin_notices() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$status = Options::get( 'license_status' );
		$key    = Options::get( 'license_key' );

		if ( empty( $key ) && empty( $status ) ) {
			$url = admin_url( 'admin.php?page=bgcw-gift-cards&tab=license' );
			echo '<div class="notice notice-warning"><p>';
			printf(
				wp_kses(
					/* translators: %s: license settings page URL */
					__( 'Beltoft Gift Cards Pro: Please <a href="%s">enter your license key</a> to enable Pro features.', 'beltoft-gift-cards-for-woocommerce-pro' ),
					[ 'a' => [ 'href' => [] ] ]
				),
				esc_url( $url )
			);
			echo '</p></div>';
			return;
		}

		if ( 'expired' === $status ) {
			$grace = Options::get( 'license_grace_until' );
			if ( $grace && strtotime( $grace ) > time() ) {
				$days_left = max( 1, (int) ceil( ( strtotime( $grace ) - time() ) / DAY_IN_SECONDS ) );
				echo '<div class="notice notice-warning"><p>';
				printf(
					/* translators: %d: number of days remaining in grace period */
					esc_html__( 'Beltoft Gift Cards Pro: Your license has expired. Pro features will be disabled in %d day(s).', 'beltoft-gift-cards-for-woocommerce-pro' ),
					(int) $days_left
				);
				echo '</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Beltoft Gift Cards Pro: Your license has expired. Pro features are disabled.', 'beltoft-gift-cards-for-woocommerce-pro' );
				echo '</p></div>';
			}
		}
	}

	/**
	 * AJAX: Activate license.
	 */
	public static function ajax_activate() {
		check_ajax_referer( 'bgcw_pro_license', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'beltoft-gift-cards-for-woocommerce-pro' ) ] );
		}

		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		if ( empty( $key ) ) {
			wp_send_json_error( [ 'message' => __( 'Please enter a license key.', 'beltoft-gift-cards-for-woocommerce-pro' ) ] );
		}

		$result = self::activate( $key );
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Deactivate license.
	 */
	public static function ajax_deactivate() {
		check_ajax_referer( 'bgcw_pro_license', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'beltoft-gift-cards-for-woocommerce-pro' ) ] );
		}

		$result = self::deactivate();
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	// ──────────────────────────────────────────────
	// WP-CLI Commands
	// ──────────────────────────────────────────────

	/**
	 * Dispatch WP-CLI subcommands.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bgcw-pro license:generate --expires=2027-12-31
	 *     wp bgcw-pro license:list
	 *     wp bgcw-pro license:revoke --key=XXXX-XXXX-XXXX-XXXX
	 *     wp bgcw-pro license:status
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function cli_dispatch( $args, $assoc_args ) {
		$subcommand = $args[0] ?? '';

		switch ( $subcommand ) {
			case 'license:generate':
				self::cli_generate( $assoc_args );
				break;
			case 'license:list':
				self::cli_list();
				break;
			case 'license:revoke':
				self::cli_revoke( $assoc_args );
				break;
			case 'license:status':
				self::cli_status();
				break;
			default:
				\WP_CLI::log( 'Available subcommands: license:generate, license:list, license:revoke, license:status' );
				break;
		}
	}

	/**
	 * Generate a new license key.
	 *
	 * @param array $assoc_args Named arguments.
	 */
	private static function cli_generate( array $assoc_args ) {
		$expires = $assoc_args['expires'] ?? 'lifetime';

		if ( 'lifetime' !== $expires && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expires ) ) {
			\WP_CLI::error( 'Invalid --expires format. Use YYYY-MM-DD or "lifetime".' );
			return;
		}

		$key = self::generate_key();

		$licenses = get_option( self::LICENSES_OPTION, [] );
		$licenses[ $key ] = [
			'expires' => $expires,
			'status'  => 'active',
			'created' => current_time( 'mysql' ),
		];
		update_option( self::LICENSES_OPTION, $licenses );

		\WP_CLI::success( 'License key generated:' );
		\WP_CLI::log( '' );
		\WP_CLI::log( "  Key:     {$key}" );
		\WP_CLI::log( "  Expires: {$expires}" );
		\WP_CLI::log( '' );
		\WP_CLI::log( 'Enter this key at: WooCommerce > Gift Cards > License tab' );
	}

	/**
	 * List all license keys.
	 */
	private static function cli_list() {
		$licenses = get_option( self::LICENSES_OPTION, [] );

		if ( empty( $licenses ) ) {
			\WP_CLI::log( 'No license keys found. Generate one with: wp bgcw-pro license:generate' );
			return;
		}

		$items = [];
		foreach ( $licenses as $key => $data ) {
			$items[] = [
				'Key'       => $key,
				'Status'    => $data['status'] ?? 'active',
				'Expires'   => $data['expires'] ?? 'lifetime',
				'Created'   => $data['created'] ?? '-',
				'Activated' => $data['activated_site'] ?? '-',
			];
		}

		\WP_CLI\Utils\format_items( 'table', $items, [ 'Key', 'Status', 'Expires', 'Created', 'Activated' ] );
	}

	/**
	 * Revoke a license key.
	 *
	 * @param array $assoc_args Named arguments.
	 */
	private static function cli_revoke( array $assoc_args ) {
		$key = strtoupper( trim( $assoc_args['key'] ?? '' ) );
		if ( empty( $key ) ) {
			\WP_CLI::error( 'Please provide --key=XXXX-XXXX-XXXX-XXXX' );
			return;
		}

		$licenses = get_option( self::LICENSES_OPTION, [] );

		if ( ! isset( $licenses[ $key ] ) ) {
			\WP_CLI::error( "Key not found: {$key}" );
			return;
		}

		$licenses[ $key ]['status'] = 'revoked';
		update_option( self::LICENSES_OPTION, $licenses );

		\WP_CLI::success( "License key revoked: {$key}" );
	}

	/**
	 * Show current activation status.
	 */
	private static function cli_status() {
		$opts = Options::get();
		\WP_CLI::log( 'License Status:' );
		\WP_CLI::log( '  Key:          ' . ( $opts['license_key'] ?: '(none)' ) );
		\WP_CLI::log( '  Status:       ' . ( $opts['license_status'] ?: '(inactive)' ) );
		\WP_CLI::log( '  Expires:      ' . ( $opts['license_expires'] ?: '-' ) );
		\WP_CLI::log( '  Last Checked: ' . ( $opts['license_last_checked'] ?: '-' ) );
		\WP_CLI::log( '  Is Active:    ' . ( self::is_active() ? 'Yes' : 'No' ) );
	}

	/**
	 * Generate a formatted license key (XXXX-XXXX-XXXX-XXXX).
	 *
	 * @return string
	 */
	private static function generate_key(): string {
		$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$parts = [];

		for ( $i = 0; $i < 4; $i++ ) {
			$segment = '';
			for ( $j = 0; $j < 4; $j++ ) {
				$segment .= $chars[ wp_rand( 0, strlen( $chars ) - 1 ) ];
			}
			$parts[] = $segment;
		}

		return implode( '-', $parts );
	}
}
