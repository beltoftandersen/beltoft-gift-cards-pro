<?php

namespace GiftCardsPro\StoreCredit;

use GiftCardsPro\Support\Options;
use GiftCardsPro\StoreCredit\CreditManager;

defined( 'ABSPATH' ) || exit;

class OrderHandler {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_filter( 'wcgc_refund_as_store_credit', [ __CLASS__, 'handle_refund' ], 10, 4 );
	}

	/**
	 * Handle refund as store credit when both options are enabled.
	 *
	 * Hooked to `wcgc_refund_as_store_credit` filter. When the store_credit
	 * feature AND auto_store_credit are both turned on, this intercepts the
	 * normal balance-restore flow and creates a new store-credit gift card
	 * for the customer instead.
	 *
	 * @param bool  $as_credit Whether to handle as store credit.
	 * @param int   $order_id  Order ID.
	 * @param int   $gc_id     Gift card ID.
	 * @param float $amount    Amount to refund.
	 * @return bool True if handled as store credit, original value otherwise.
	 */
	public static function handle_refund( $as_credit, $order_id, $gc_id, $amount ) {
		if ( Options::get( 'store_credit' ) !== '1' || Options::get( 'auto_store_credit' ) !== '1' ) {
			return $as_credit;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return $as_credit;
		}

		$customer_id = $order->get_customer_id();
		if ( ! $customer_id ) {
			return $as_credit;
		}

		$result = CreditManager::create_store_credit( $customer_id, $amount, $order_id );

		if ( $result ) {
			return true;
		}

		return $as_credit;
	}
}
