<?php

namespace GiftCardsPro\StoreCredit;

use GiftCards\GiftCard\CodeGenerator;
use GiftCards\GiftCard\Repository;
use GiftCards\GiftCard\TransactionRepository;

defined( 'ABSPATH' ) || exit;

class CreditManager {

	/**
	 * Create a store-credit gift card for a customer.
	 *
	 * @param int   $customer_id WordPress user ID.
	 * @param float $amount      Credit amount.
	 * @param int   $order_id    Original order ID (refund source).
	 * @return int|false Gift card ID on success, false on failure.
	 */
	public static function create_store_credit( $customer_id, $amount, $order_id ) {
		$amount = round( (float) $amount, 2 );
		if ( $amount <= 0 || $customer_id <= 0 ) {
			return false;
		}

		$user = get_userdata( $customer_id );
		if ( ! $user ) {
			return false;
		}

		$code = CodeGenerator::generate();

		$gc_id = Repository::insert( [
			'code'            => $code,
			'initial_amount'  => $amount,
			'balance'         => $amount,
			'currency'        => get_woocommerce_currency(),
			'sender_name'     => __( 'Store Credit', 'smart-gift-cards-for-woocommerce-pro' ),
			'sender_email'    => get_option( 'admin_email' ),
			'recipient_name'  => $user->display_name,
			'recipient_email' => $user->user_email,
			'message'         => __( 'Store credit from refund', 'smart-gift-cards-for-woocommerce-pro' ),
			'order_id'        => $order_id,
			'customer_id'     => $customer_id,
			'status'          => 'active',
			'expires_at'      => null,
		] );

		if ( ! $gc_id ) {
			return false;
		}

		// Record initial credit transaction.
		TransactionRepository::insert( [
			'gift_card_id'  => $gc_id,
			'order_id'      => $order_id,
			'type'          => 'credit',
			'amount'        => $amount,
			'balance_after' => $amount,
			'note'          => sprintf(
				/* translators: %d: order ID */
				__( 'Store credit issued from refund on order #%d', 'smart-gift-cards-for-woocommerce-pro' ),
				$order_id
			),
		] );

		// Record in store credits table.
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table with no WP API.
		$wpdb->insert(
			$wpdb->prefix . 'wcgc_store_credits',
			[
				'gift_card_id'      => $gc_id,
				'original_order_id' => $order_id,
				'customer_id'       => $customer_id,
				'amount'            => $amount,
				'created_at'        => current_time( 'mysql', true ),
			],
			[ '%d', '%d', '%d', '%f', '%s' ]
		);

		return $gc_id;
	}
}
