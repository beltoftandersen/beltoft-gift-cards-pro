<?php

namespace GiftCardsPro\ScheduledDelivery;

use GiftCardsPro\Support\Options;
use GiftCards\GiftCard\Repository;

defined( 'ABSPATH' ) || exit;

class Scheduler {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_filter( 'wcgc_should_send_email_now', [ __CLASS__, 'maybe_defer' ], 10, 3 );
		add_action( 'wcgc_gift_card_created', [ __CLASS__, 'schedule_delivery' ], 10, 2 );
		add_action( 'wcgc_pro_process_scheduled_deliveries', [ __CLASS__, 'process_scheduled' ] );
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

		$delivery_date = self::get_delivery_date_from_order( $gc_id, $order );

		if ( empty( $delivery_date ) ) {
			return $should_send;
		}

		$today = wp_date( 'Y-m-d' );

		// If the scheduled date is in the future, defer the email.
		if ( $delivery_date > $today ) {
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

		$delivery_date = self::get_delivery_date_from_order( $gc_id, $order );

		if ( empty( $delivery_date ) ) {
			return;
		}

		$today = wp_date( 'Y-m-d' );

		// Only schedule if the date is in the future.
		if ( $delivery_date <= $today ) {
			return;
		}

		global $wpdb;

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table with no WP API.
		$wpdb->insert(
			$wpdb->prefix . 'wcgc_scheduled_deliveries',
			[
				'gift_card_id'   => $gc_id,
				'order_id'       => $order->get_id(),
				'scheduled_date' => $delivery_date . ' 00:00:00',
				'status'         => 'pending',
				'created_at'     => $now,
			],
			[ '%d', '%d', '%s', '%s', '%s' ]
		);
	}

	/**
	 * Process scheduled deliveries (cron handler).
	 *
	 * Finds all pending deliveries where the scheduled date has passed,
	 * triggers the gift card created action for each, and marks them as sent.
	 */
	public static function process_scheduled() {
		if ( Options::get( 'scheduled_delivery' ) !== '1' ) {
			return;
		}

		global $wpdb;

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, cron job.
		$pending = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wcgc_scheduled_deliveries WHERE status = %s AND scheduled_date <= %s ORDER BY scheduled_date ASC LIMIT 50",
				'pending',
				$now
			)
		);

		if ( empty( $pending ) ) {
			return;
		}

		foreach ( $pending as $row ) {
			$gc    = Repository::find( $row->gift_card_id );
			$order = wc_get_order( $row->order_id );

			if ( ! $gc || ! $order ) {
				// Mark as failed if the gift card or order no longer exists.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
				$wpdb->update(
					$wpdb->prefix . 'wcgc_scheduled_deliveries',
					[ 'status' => 'failed' ],
					[ 'id' => $row->id ],
					[ '%s' ],
					[ '%d' ]
				);
				continue;
			}

			/**
			 * Trigger the gift card created action to resend the email.
			 *
			 * The maybe_defer filter will not block this because
			 * the scheduled_date has already passed.
			 */
			do_action( 'wcgc_gift_card_created', (int) $row->gift_card_id, $order );

			// Mark as sent.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
			$wpdb->update(
				$wpdb->prefix . 'wcgc_scheduled_deliveries',
				[ 'status' => 'sent' ],
				[ 'id' => $row->id ],
				[ '%s' ],
				[ '%d' ]
			);
		}
	}

	/**
	 * Get the delivery date from the order item that created a specific gift card.
	 *
	 * Looks through the order items to find one with a `_wcgc_delivery_date` meta
	 * that corresponds to the given gift card.
	 *
	 * @param int       $gc_id Gift card ID.
	 * @param \WC_Order $order Order object.
	 * @return string Delivery date in Y-m-d format, or empty string if not set.
	 */
	private static function get_delivery_date_from_order( $gc_id, $order ) {
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product || 'gift-card' !== $product->get_type() ) {
				continue;
			}

			$delivery_date = $item->get_meta( '_wcgc_delivery_date' );
			if ( empty( $delivery_date ) ) {
				continue;
			}

			// Validate date format.
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $delivery_date ) ) {
				continue;
			}

			return $delivery_date;
		}

		return '';
	}
}
