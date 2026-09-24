<?php

namespace BgcwPro\Licensing;

use BgcwPro\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress auto-update mechanism for the Pro plugin.
 *
 * Checks for new versions via the cached license_remote_version option
 * (populated by License::cron_check). Downloads use a fresh signed URL
 * fetched at download time via upgrader_pre_download to avoid stale tokens.
 */
class Updater {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'site_transient_update_plugins', [ __CLASS__, 'check_update' ] );
		add_filter( 'plugins_api', [ __CLASS__, 'plugin_info' ], 10, 3 );
		add_filter( 'upgrader_pre_download', [ __CLASS__, 'get_download_package' ], 10, 3 );
		add_action( 'upgrader_process_complete', [ __CLASS__, 'after_update' ], 10, 2 );
	}

	/**
	 * Inject update information into the WordPress update transient.
	 *
	 * @param object $transient The update_plugins transient data.
	 * @return object
	 */
	public static function check_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		// Only offer updates when the license is actively valid.
		if ( ! License::is_valid_for_updates() ) {
			return $transient;
		}

		$remote_version = Options::get( 'license_remote_version' );
		if ( empty( $remote_version ) ) {
			return $transient;
		}

		// No update if local version is current or newer.
		if ( ! version_compare( BGCW_PRO_VER, $remote_version, '<' ) ) {
			return $transient;
		}

		$plugin_basename = self::get_plugin_basename();

		$update              = new \stdClass();
		$update->slug        = self::get_plugin_slug();
		$update->plugin      = $plugin_basename;
		$update->new_version = $remote_version;
		$update->url         = 'https://beltoft.net';
		$update->package     = 'bgcw-pro-deferred-download'; // Sentinel - real URL fetched in get_download_package().
		$update->requires_php = '7.4';
		$update->tested      = get_bloginfo( 'version' );

		$transient->response[ $plugin_basename ] = $update;

		return $transient;
	}

	/**
	 * Provide plugin information for the "View details" modal.
	 *
	 * @param false|object|array $result Default value.
	 * @param string             $action API action ('plugin_information').
	 * @param object             $args   Query arguments.
	 * @return false|object
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || self::get_plugin_slug() !== $args->slug ) {
			return $result;
		}

		$remote_version = Options::get( 'license_remote_version' );

		$info = [
			'name'         => 'Beltoft Gift Cards for WooCommerce - Pro',
			'slug'         => self::get_plugin_slug(),
			'version'      => $remote_version ? $remote_version : BGCW_PRO_VER,
			'author'       => '<a href="https://beltoft.net">beltoft.net</a>',
			'homepage'     => 'https://beltoft.net',
			'requires'     => '5.8',
			'tested'       => get_bloginfo( 'version' ),
			'requires_php' => '7.4',
			'sections'     => [
				'description' => 'Premium add-on for Beltoft Gift Cards for WooCommerce &mdash; scheduled delivery, PDF gift cards, store credit, bulk generation, BOGO promotions, and analytics.',
			],
		];

		return (object) $info;
	}

	/**
	 * Fetch a fresh signed download URL at the moment WordPress actually downloads.
	 *
	 * The update transient stores a sentinel value ('bgcw-pro-deferred-download')
	 * instead of a real URL because signed URLs have a short TTL (5 minutes).
	 * This filter intercepts the download and provides a fresh URL.
	 *
	 * @param bool|string|\WP_Error $reply   Whether to short-circuit the download. Default false.
	 * @param string                $package The package URL.
	 * @param \WP_Upgrader          $upgrader The upgrader instance.
	 * @return bool|string|\WP_Error
	 */
	public static function get_download_package( $reply, $package, $upgrader ) {
		if ( 'bgcw-pro-deferred-download' !== $package ) {
			return $reply;
		}

		$license_key = Options::get( 'license_key' );
		if ( empty( $license_key ) ) {
			return new \WP_Error(
				'bgcw_no_license',
				__( 'A valid license key is required to download updates.', 'beltoft-gift-cards-pro' )
			);
		}

		// Try up to 2 times: the signed URL may have expired (401) between
		// the initial request and the actual download.
		for ( $attempt = 1; $attempt <= 2; $attempt++ ) {
			$result = self::fetch_and_download( $license_key );

			// Success - return temp file path.
			if ( ! is_wp_error( $result ) ) {
				return $result;
			}

			// Only retry once on download failure (stale token).
			if ( 2 === $attempt ) {
				return $result;
			}
		}

		// @codeCoverageIgnoreStart - unreachable, loop always returns.
		return new \WP_Error( 'bgcw_download_error', __( 'Download failed.', 'beltoft-gift-cards-pro' ) );
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Request a signed download URL and download the package.
	 *
	 * @param string $license_key The license key.
	 * @return string|\WP_Error Temp file path on success, WP_Error on failure.
	 */
	private static function fetch_and_download( string $license_key ) {
		$response = License::api_request(
			'/license/download',
			[
				'license_key' => $license_key,
				'domain'      => untrailingslashit( home_url() ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'bgcw_download_error',
				__( 'Could not connect to license server to download the update.', 'beltoft-gift-cards-pro' )
			);
		}

		if ( empty( $response['download_url'] ) ) {
			$message = ! empty( $response['message'] )
				? sanitize_text_field( $response['message'] )
				: __( 'Could not retrieve download URL from license server.', 'beltoft-gift-cards-pro' );

			return new \WP_Error( 'bgcw_no_download_url', $message );
		}

		return download_url( esc_url_raw( $response['download_url'] ) );
	}

	/**
	 * Clear cached remote version after a successful update.
	 *
	 * Forces the next cron check to re-fetch the latest version.
	 *
	 * @param \WP_Upgrader $upgrader Upgrader instance.
	 * @param array        $options  Update details.
	 */
	public static function after_update( $upgrader, $options ) {
		if ( 'update' !== ( $options['action'] ?? '' ) || 'plugin' !== ( $options['type'] ?? '' ) ) {
			return;
		}

		$plugins = $options['plugins'] ?? [];
		if ( ! in_array( self::get_plugin_basename(), $plugins, true ) ) {
			return;
		}

		Options::set( 'license_remote_version', '' );
	}

	/**
	 * Get the plugin basename (e.g., "beltoft-gift-cards-pro/beltoft-gift-cards-pro.php").
	 *
	 * @return string
	 */
	private static function get_plugin_basename(): string {
		return plugin_basename( BGCW_PRO_FILE );
	}

	/**
	 * Get the plugin directory slug (e.g., "beltoft-gift-cards-pro").
	 *
	 * @return string
	 */
	private static function get_plugin_slug(): string {
		return dirname( self::get_plugin_basename() );
	}
}
