<?php

namespace BgcwPro\Support;

defined( 'ABSPATH' ) || exit;

class Options {

	const OPTION = 'bgcw_pro_options';

	/**
	 * In-memory cache to avoid repeated get_option + wp_parse_args.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default option values.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return [
			// License.
			'license_key'          => '',
			'license_status'       => '',
			'license_expires'      => '',
			'license_last_checked'    => '',
			'license_remote_version'  => '',
			'license_max_activations' => '',

			// Scheduled Delivery.
			'scheduled_delivery' => '1',

			// PDF gift cards.
			'pdf_enabled'        => '1',
			'pdf_logo_id'        => '',
			'pdf_design_version' => '1',

			// Per-design customization (empty = use built-in default).
			'pdf_heading_classic' => '',
			'pdf_color_classic'   => '',
			'pdf_heading_product' => '',
			'pdf_intro'           => '',
			'pdf_intro_product'   => '',

			// Give as a gift.
			'giftable_category_ids' => [],

			// Store Credit.
			'store_credit'      => '1',
			'auto_store_credit' => '0',

			// BOGO.
			'bogo_enabled' => '1',

			// Analytics.
			'analytics_enabled'   => '1',
			'report_frequency'    => 'weekly',
			'report_recipients'   => '',
			'report_day_of_week'  => '1',
			'report_day_of_month' => '1',
			'report_time_of_day'  => '9',
			'report_last_sent'    => '',

			// Advanced.
			'cleanup_on_uninstall' => '0',
		];
	}

	/**
	 * Get all options or a single key.
	 *
	 * @param string|null $key Option key, or null for all.
	 * @return mixed
	 */
	public static function get( $key = null ) {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, [] );
			self::$cache = wp_parse_args( $saved, self::defaults() );
		}

		if ( null !== $key ) {
			return self::$cache[ $key ] ?? null;
		}

		return self::$cache;
	}

	/**
	 * Drop the request-scoped cache so the next get() re-reads the option.
	 */
	public static function invalidate_cache(): void {
		self::$cache = null;
	}

	/**
	 * Update a single key or merge an array.
	 *
	 * @param string|array $key   Option key or array of key-value pairs.
	 * @param mixed        $value Value when $key is a string.
	 */
	public static function set( $key, $value = null ) {
		$opts = self::get();

		if ( is_array( $key ) ) {
			$opts = array_merge( $opts, $key );
		} else {
			$opts[ $key ] = $value;
		}

		/*
		 * Bypass the registered sanitize callback for internal writes.
		 *
		 * sanitize() intentionally ignores license fields from request-driven updates
		 * (settings form). Activation, deactivation and the daily check also write
		 * through this helper, and WordPress runs the sanitize filter on every
		 * update_option() once register_setting() has run, which would silently
		 * drop the license fields.
		 */
		$sanitize_hook = 'sanitize_option_' . self::OPTION;
		$sanitize_cb   = [ __CLASS__, 'sanitize' ];
		$priority      = has_filter( $sanitize_hook, $sanitize_cb );

		if ( false !== $priority ) {
			remove_filter( $sanitize_hook, $sanitize_cb, (int) $priority );
		}

		update_option( self::OPTION, $opts );

		if ( false !== $priority ) {
			add_filter( $sanitize_hook, $sanitize_cb, (int) $priority );
		}

		self::$cache = null; // Invalidate cache.
	}

	/**
	 * Sanitize callback for settings save.
	 *
	 * @param array $input Raw POST input.
	 * @return array Sanitized options.
	 */
	public static function sanitize( array $input ): array {
		$clean = self::get();

		// Checkbox fields (present = 1, absent = 0).
		$checks = [
			'scheduled_delivery',
			'pdf_enabled',
			'store_credit',
			'auto_store_credit',
			'bogo_enabled',
			'analytics_enabled',
			'cleanup_on_uninstall',
		];
		foreach ( $checks as $f ) {
			$clean[ $f ] = isset( $input[ $f ] ) ? '1' : '0';
		}

		// Enum fields.
		if ( isset( $input['report_frequency'] ) && in_array( $input['report_frequency'], [ 'daily', 'weekly', 'monthly' ], true ) ) {
			$clean['report_frequency'] = $input['report_frequency'];
		}

		// Email recipients — sanitize each email.
		if ( isset( $input['report_recipients'] ) ) {
			$emails = array_map( 'trim', explode( ',', $input['report_recipients'] ) );
			$emails = array_filter( $emails, 'is_email' );
			$clean['report_recipients'] = implode( ', ', $emails );
		}

		// Day of week (1-7).
		if ( isset( $input['report_day_of_week'] ) ) {
			$day = absint( $input['report_day_of_week'] );
			$clean['report_day_of_week'] = (string) max( 1, min( 7, $day ) );
		}

		// Day of month (1-28).
		if ( isset( $input['report_day_of_month'] ) ) {
			$day = absint( $input['report_day_of_month'] );
			$clean['report_day_of_month'] = (string) max( 1, min( 28, $day ) );
		}

		// Time of day (0-23).
		if ( isset( $input['report_time_of_day'] ) ) {
			$hour = absint( $input['report_time_of_day'] );
			$clean['report_time_of_day'] = (string) min( 23, $hour );
		}

		// PDF design settings. Any change bumps the design version so stored PDFs regenerate.
		$design_changed = false;
		if ( isset( $input['pdf_logo_id'] ) ) {
			$logo = absint( $input['pdf_logo_id'] );
			$logo = $logo > 0 ? (string) $logo : '';
			if ( $logo !== (string) ( $clean['pdf_logo_id'] ?? '' ) ) {
				$design_changed = true;
			}
			$clean['pdf_logo_id'] = $logo;
		}
		foreach ( array_keys( \BgcwPro\Pdf\Designs::get() ) as $slug ) {
			$heading_key = 'pdf_heading_' . $slug;
			if ( isset( $input[ $heading_key ] ) ) {
				$val = sanitize_text_field( $input[ $heading_key ] );
				if ( $val !== (string) ( $clean[ $heading_key ] ?? '' ) ) {
					$design_changed = true;
				}
				$clean[ $heading_key ] = $val;
			}
			$color_key = 'pdf_color_' . $slug;
			if ( isset( $input[ $color_key ] ) ) {
				$val = sanitize_hex_color( $input[ $color_key ] );
				$val = $val ? $val : '';
				if ( '' !== $val && strtolower( $val ) === strtolower( \BgcwPro\Pdf\Designs::builtin_value( $slug, 'color' ) ) ) {
					$val = ''; // Same as the built-in default: no override.
				}
				if ( strtolower( $val ) !== strtolower( (string) ( $clean[ $color_key ] ?? '' ) ) ) {
					$design_changed = true;
				}
				$clean[ $color_key ] = $val;
			}
		}
		foreach ( [ 'pdf_heading_product', 'pdf_intro', 'pdf_intro_product' ] as $text_key ) {
			if ( isset( $input[ $text_key ] ) ) {
				$val = sanitize_textarea_field( $input[ $text_key ] );
				if ( $val !== (string) ( $clean[ $text_key ] ?? '' ) ) {
					$design_changed = true;
				}
				$clean[ $text_key ] = $val;
			}
		}
		if ( array_key_exists( 'giftable_category_ids', $input ) ) {
			$clean['giftable_category_ids'] = array_values( array_filter( array_map( 'absint', (array) $input['giftable_category_ids'] ) ) );
		} elseif ( ! empty( $input['giftable_categories_submitted'] ) ) {
			// The form was submitted with no category selected (browsers omit an empty multi-select).
			$clean['giftable_category_ids'] = [];
		}
		unset( $clean['giftable_categories_submitted'] );
		if ( $design_changed ) {
			$clean['pdf_design_version'] = (string) ( max( 1, (int) ( $clean['pdf_design_version'] ?? 1 ) ) + 1 );
		}

		// Legacy email theme options are no longer used.
		foreach ( [ 'email_themes', 'default_theme' ] as $legacy ) {
			unset( $clean[ $legacy ] );
		}
		foreach ( array_keys( $clean ) as $k ) {
			if ( 0 === strpos( (string) $k, 'theme_heading_' ) || 0 === strpos( (string) $k, 'theme_color_' ) ) {
				unset( $clean[ $k ] );
			}
		}

		// License fields are managed exclusively by the License class.
		// They are NOT accepted from POST input to prevent bypass.

		return $clean;
	}
}
