<?php

namespace GiftCardsPro\Bogo;

use GiftCardsPro\Support\Options;
use GiftCardsPro\Bogo\BogoManager;
use GiftCards\GiftCard\CodeGenerator;
use GiftCards\GiftCard\Repository;
use GiftCards\GiftCard\TransactionRepository;

defined( 'ABSPATH' ) || exit;

class CartHandler {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'wcgc_gift_card_created', [ __CLASS__, 'check_bogo' ], 10, 2 );
	}

	/**
	 * Check BOGO rules after a gift card is created from an order.
	 *
	 * @param int            $gc_id Gift card ID.
	 * @param \WC_Order|null $order Order object, or null for manual creation.
	 */
	public static function check_bogo( $gc_id, $order ) {
		// Skip if BOGO is disabled.
		if ( Options::get( 'bogo_enabled' ) !== '1' ) {
			return;
		}

		// Skip manual creation (no order).
		if ( null === $order ) {
			return;
		}

		// Idempotency: skip if this order already had BOGO processed.
		if ( $order->get_meta( '_wcgc_bogo_processed' ) ) {
			return;
		}

		// Calculate total gift card amount and count from order items.
		$total_gc_amount = 0.0;
		$gc_count        = 0;

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product || 'gift-card' !== $product->get_type() ) {
				continue;
			}

			$amount = (float) $item->get_meta( '_wcgc_amount' );
			if ( $amount <= 0 ) {
				continue;
			}

			$qty              = max( 1, (int) $item->get_quantity() );
			$total_gc_amount += $amount * $qty;
			$gc_count        += $qty;
		}

		if ( $gc_count <= 0 || $total_gc_amount <= 0 ) {
			return;
		}

		// Find matching BOGO rules.
		$rules = BogoManager::get_matching_rules( $total_gc_amount, $gc_count );
		if ( empty( $rules ) ) {
			return;
		}

		// Apply the first (best) matching rule.
		$rule       = $rules[0];
		$get_amount = round( (float) $rule->get_amount, 2 );

		if ( $get_amount <= 0 ) {
			return;
		}

		// Generate bonus gift card.
		$code         = CodeGenerator::generate();
		$buyer_email  = $order->get_billing_email();
		$buyer_name   = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

		$bonus_gc_id = Repository::insert( [
			'code'            => $code,
			'initial_amount'  => $get_amount,
			'balance'         => $get_amount,
			'currency'        => $order->get_currency(),
			'sender_name'     => trim( $buyer_name ),
			'sender_email'    => $buyer_email,
			'recipient_name'  => trim( $buyer_name ),
			'recipient_email' => $buyer_email,
			'message'         => sprintf(
				/* translators: %s: BOGO rule name */
				__( 'Bonus gift card from promotion: %s', 'smart-gift-cards-for-woocommerce-pro' ),
				$rule->name
			),
			'order_id'        => $order->get_id(),
			'customer_id'     => $order->get_customer_id(),
			'status'          => 'active',
			'expires_at'      => null,
		] );

		if ( ! $bonus_gc_id ) {
			return;
		}

		// Record initial credit transaction.
		TransactionRepository::insert( [
			'gift_card_id'  => $bonus_gc_id,
			'order_id'      => $order->get_id(),
			'type'          => 'credit',
			'amount'        => $get_amount,
			'balance_after' => $get_amount,
			'note'          => sprintf(
				/* translators: %s: BOGO rule name */
				__( 'BOGO bonus from rule: %s', 'smart-gift-cards-for-woocommerce-pro' ),
				$rule->name
			),
		] );

		// Trigger delivery email for the bonus card.
		/**
		 * Fires after a gift card is created.
		 *
		 * @param int            $bonus_gc_id Bonus gift card ID.
		 * @param \WC_Order|null $order       Order object.
		 */
		do_action( 'wcgc_gift_card_created', $bonus_gc_id, $order );

		// Increment rule usage.
		BogoManager::increment_usage( $rule->id );

		// Mark order as BOGO-processed to prevent duplicates.
		$order->update_meta_data( '_wcgc_bogo_processed', '1' );
		$order->save();
	}
}
