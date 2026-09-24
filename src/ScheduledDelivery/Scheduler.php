<?php

namespace BgcwPro\ScheduledDelivery;

use BgcwPro\Support\Options;
use Bgcw\GiftCard\Repository;

defined( 'ABSPATH' ) || exit;

class Scheduler {

	/** Action Scheduler hook fired once per delivery at its exact time. */
	const ACTION = 'bgcw_pro_deliver_gift_card';
	const GROUP  = 'bgcw-pro';

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_filter( 'bgcw_should_send_email_now', [ __CLASS__, 'maybe_defer' ], 10, 3 );
		add_action( 'bgcw_gift_card_created', [ __CLASS__, 'schedule_delivery' ], 10, 2 );
		self::register_delivery_handler();
	}

	/**
	 * Register the Action Scheduler callback.
	 *
	 * Registered even without an active license (see Plugin::init) so a delivery that was
	 * paid for still goes out if the license lapses before its slot.
	 */
	public static function register_delivery_handler() {
		if ( ! has_action( self::ACTION, [ __CLASS__, 'deliver_row' ] ) ) {
			add_action( self::ACTION, [ __CLASS__, 'deliver_row' ] );
		}
	}

	/**
	 * Defer email delivery if a future scheduled date exists.
	 *
	 * @param bool            $should_send  Whether the email should be sent now.
	 * @param int             $gc_id        Gift card ID.
	 * @param \WC_Order|null  $order        Order object.
	 * @return bool
	 */
	public static function maybe_defer( $should_send, $gc_id, $order ) {
		if ( Options::get( 'scheduled_delivery' ) !== '1' ) {
			return $should_send;
		}

		if ( ! $order ) {
			return $should_send;
		}

		$delivery_info = self::get_delivery_info_from_order( $gc_id, $order );

		if ( empty( $delivery_info['date'] ) ) {
			return $should_send;
		}

		// If the scheduled date/time is in the future, defer the email.
		if ( self::is_future_local_datetime( $delivery_info['date'], $delivery_info['hour'] ) ) {
			return false;
		}

		return $should_send;
	}

	/**
	 * Schedule delivery if a delivery date is set on the order item.
	 *
	 * @param int            $gc_id Gift card ID.
	 * @param \WC_Order|null $order Order object.
	 */
	public static function schedule_delivery( $gc_id, $order ) {
		if ( Options::get( 'scheduled_delivery' ) !== '1' ) {
			return;
		}

		if ( ! $order ) {
			return;
		}

		$delivery_info = self::get_delivery_info_from_order( $gc_id, $order );

		if ( empty( $delivery_info['date'] ) ) {
			return;
		}

		$delivery_date = $delivery_info['date'];
		$delivery_hour = $delivery_info['hour'];

		// Only schedule if the date/time is in the future.
		if ( ! self::is_future_local_datetime( $delivery_date, $delivery_hour ) ) {
			return;
		}

		global $wpdb;

		$scheduled_date_utc = self::local_datetime_to_utc( $delivery_date, $delivery_hour );
		if ( empty( $scheduled_date_utc ) ) {
			return;
		}

		$now_utc = gmdate( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table with no WP API.
		$wpdb->insert(
			$wpdb->prefix . 'bgcw_scheduled_deliveries',
			[
				'gift_card_id'   => $gc_id,
				'order_id'       => $order->get_id(),
				'scheduled_date' => $scheduled_date_utc,
				'status'         => 'pending',
				'created_at'     => $now_utc,
			],
			[ '%d', '%d', '%s', '%s', '%s' ]
		);

		if ( $wpdb->insert_id ) {
			self::book( (int) $wpdb->insert_id, $scheduled_date_utc );
		}
	}

	/**
	 * Book (or re-book) the one Action Scheduler action that delivers a row at its exact time.
	 *
	 * @param int    $row_id             Delivery row ID.
	 * @param string $scheduled_date_utc MySQL datetime (UTC).
	 * @return bool
	 */
	public static function book( int $row_id, string $scheduled_date_utc ): bool {
		$ts = strtotime( $scheduled_date_utc . ' UTC' );
		if ( ! $ts || ! function_exists( 'as_schedule_single_action' ) ) {
			self::log( sprintf( 'Could not book delivery for row %d: Action Scheduler unavailable.', $row_id ) );
			return false;
		}

		as_unschedule_all_actions( self::ACTION, [ 'row_id' => $row_id ], self::GROUP );
		$action_id = as_schedule_single_action( $ts, self::ACTION, [ 'row_id' => $row_id ], self::GROUP );

		if ( ! $action_id ) {
			self::log( sprintf( 'Could not book delivery for row %d at %s UTC.', $row_id, $scheduled_date_utc ) );
			return false;
		}

		return true;
	}

	/**
	 * Cancel every booked delivery action (deactivation / uninstall).
	 */
	public static function unbook_all() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION, [], self::GROUP );
		}
	}

	/**
	 * Log to the WooCommerce logger under the bgcw-pro source.
	 */
	private static function log( string $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $message, [ 'source' => 'bgcw-pro' ] );
		}
	}

	/**
	 * Action Scheduler handler: send one scheduled delivery.
	 *
	 * Claims the row atomically, re-books it if the order's slot was moved to a later time,
	 * and releases the claim if sending throws so the row is not stranded.
	 *
	 * @param int $row_id Delivery row ID.
	 */
	public static function deliver_row( $row_id ) {
		global $wpdb;
		$row_id = (int) $row_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bgcw_scheduled_deliveries WHERE id = %d", $row_id ) );
		if ( ! $row || 'pending' !== $row->status ) {
			return;
		}

		$gc    = Repository::find( $row->gift_card_id );
		$order = wc_get_order( $row->order_id );
		if ( ! $gc || ! $order ) {
			self::set_status( $row_id, 'failed' );
			return;
		}

		// The order may have been edited after booking: if its slot is now later, re-book and keep waiting.
		$info = self::get_delivery_info_from_order( (int) $row->gift_card_id, $order );
		if ( ! empty( $info['date'] ) && self::is_future_local_datetime( $info['date'], $info['hour'] ) ) {
			$new_utc = self::local_datetime_to_utc( $info['date'], $info['hour'] );
			if ( $new_utc ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
				$wpdb->update( $wpdb->prefix . 'bgcw_scheduled_deliveries', [ 'scheduled_date' => $new_utc ], [ 'id' => $row_id ], [ '%s' ], [ '%d' ] );
				self::book( $row_id, $new_utc );
			}
			return;
		}

		// Atomic claim: only one runner may send this row.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$claimed = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}bgcw_scheduled_deliveries SET status = 'sending' WHERE id = %d AND status = 'pending'", $row_id ) );
		if ( 1 !== (int) $claimed ) {
			return;
		}

		try {
			do_action( 'bgcw_gift_card_created', (int) $row->gift_card_id, $order );
		} catch ( \Throwable $e ) {
			self::set_status( $row_id, 'pending' );
			self::log( sprintf( 'Delivery for row %d failed: %s', $row_id, $e->getMessage() ) );
			throw $e; // Action Scheduler records the failure.
		}

		self::set_status( $row_id, 'sent' );
	}

	/**
	 * Update a delivery row's status.
	 */
	private static function set_status( int $row_id, string $status ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->update( $wpdb->prefix . 'bgcw_scheduled_deliveries', [ 'status' => $status ], [ 'id' => $row_id ], [ '%s' ], [ '%d' ] );
	}

	/**
	 * Get the delivery date and hour from the order item that created a specific gift card.
	 *
	 * Looks through the order items to find one with a `_bgcw_delivery_date` meta
	 * that corresponds to the given gift card.
	 *
	 * @param int       $gc_id Gift card ID.
	 * @param \WC_Order $order Order object.
	 * @return array{date: string, hour: int} Delivery date in Y-m-d format + hour (0-23), or empty date if not set.
	 */
	private static function get_delivery_info_from_order( $gc_id, $order ) {
		$gift_card = Repository::find( $gc_id );
		if ( ! $gift_card ) {
			return [ 'date' => '', 'hour' => 9 ];
		}

		$fallback = [ 'date' => '', 'hour' => 9 ];

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product || 'gift-card' !== $product->get_type() ) {
				continue;
			}

			$delivery_date = $item->get_meta( '_bgcw_delivery_date' );
			if ( empty( $delivery_date ) ) {
				continue;
			}

			// Validate date format.
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $delivery_date ) ) {
				continue;
			}

			$delivery_hour = $item->get_meta( '_bgcw_delivery_hour' );
			$hour          = ( '' !== $delivery_hour && is_numeric( $delivery_hour ) ) ? absint( $delivery_hour ) : 9;
			$hour          = min( 23, $hour );

			if ( '' === $fallback['date'] ) {
				$fallback = [ 'date' => $delivery_date, 'hour' => $hour ];
			}

			if ( self::item_matches_gift_card( $item, $gift_card ) ) {
				return [ 'date' => $delivery_date, 'hour' => $hour ];
			}
		}

		return $fallback;
	}

	/**
	 * Determine whether a local delivery date + hour is in the future.
	 *
	 * @param string $delivery_date Date in Y-m-d format.
	 * @param int    $hour          Hour of the day (0-23).
	 * @return bool
	 */
	private static function is_future_local_datetime( $delivery_date, $hour ) {
		$tz           = wp_timezone();
		$delivery_obj = \DateTimeImmutable::createFromFormat(
			'Y-m-d H:i:s',
			$delivery_date . sprintf( ' %02d:00:00', $hour ),
			$tz
		);

		if ( ! $delivery_obj ) {
			return false;
		}

		$now_obj = new \DateTimeImmutable( 'now', $tz );
		return $delivery_obj > $now_obj;
	}

	/**
	 * Convert a site-local delivery date + hour to a UTC datetime string.
	 *
	 * @param string $delivery_date Date in Y-m-d format.
	 * @param int    $hour          Hour of the day (0-23).
	 * @return string
	 */
	private static function local_datetime_to_utc( $delivery_date, $hour ) {
		$local_dt = \DateTimeImmutable::createFromFormat(
			'Y-m-d H:i:s',
			$delivery_date . sprintf( ' %02d:00:00', $hour ),
			wp_timezone()
		);
		if ( ! $local_dt ) {
			return '';
		}

		return $local_dt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Best-effort match between a gift card record and an order item.
	 *
	 * @param \WC_Order_Item $item      Gift-card order item.
	 * @param object         $gift_card Gift card database row.
	 * @return bool
	 */
	public static function item_matches_gift_card( $item, $gift_card ) {
		$has_signal = false;

		$item_amount = (float) $item->get_meta( '_bgcw_amount' );
		if ( $item_amount > 0 ) {
			$has_signal = true;
			if ( abs( $item_amount - (float) $gift_card->initial_amount ) > 0.00001 ) {
				return false;
			}
		}

		$pairs = [
			[ (string) $item->get_meta( '_bgcw_recipient_email' ), (string) $gift_card->recipient_email ],
			[ (string) $item->get_meta( '_bgcw_recipient_name' ), (string) $gift_card->recipient_name ],
			[ (string) $item->get_meta( '_bgcw_sender_email' ), (string) $gift_card->sender_email ],
			[ (string) $item->get_meta( '_bgcw_sender_name' ), (string) $gift_card->sender_name ],
			[ (string) $item->get_meta( '_bgcw_message' ), (string) $gift_card->message ],
		];

		foreach ( $pairs as $pair ) {
			$item_value = trim( $pair[0] );
			$gc_value   = trim( $pair[1] );

			if ( '' === $item_value || '' === $gc_value ) {
				continue;
			}

			$has_signal = true;
			if ( strtolower( $item_value ) !== strtolower( $gc_value ) ) {
				return false;
			}
		}

		return $has_signal;
	}
}
