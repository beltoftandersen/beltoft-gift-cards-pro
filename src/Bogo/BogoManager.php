<?php

namespace GiftCardsPro\Bogo;

use GiftCardsPro\Support\Options;

defined( 'ABSPATH' ) || exit;

class BogoManager {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		if ( is_admin() ) {
			add_action( 'wp_ajax_wcgc_pro_save_bogo_rule', [ __CLASS__, 'ajax_save_rule' ] );
			add_action( 'wp_ajax_wcgc_pro_delete_bogo_rule', [ __CLASS__, 'ajax_delete_rule' ] );
		}
	}

	/**
	 * AJAX handler: save (create or update) a BOGO rule.
	 */
	public static function ajax_save_rule() {
		check_ajax_referer( 'wcgc_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'smart-gift-cards-for-woocommerce-pro' ) ] );
		}

		$data = [
			'id'           => isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0,
			'name'         => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'buy_amount'   => isset( $_POST['buy_amount'] ) ? (float) $_POST['buy_amount'] : 0,
			'get_amount'   => isset( $_POST['get_amount'] ) ? (float) $_POST['get_amount'] : 0,
			'min_quantity'  => isset( $_POST['min_quantity'] ) ? absint( $_POST['min_quantity'] ) : 1,
			'max_uses'     => isset( $_POST['max_uses'] ) ? absint( $_POST['max_uses'] ) : 0,
			'status'       => isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'active',
			'starts_at'    => isset( $_POST['starts_at'] ) ? sanitize_text_field( wp_unslash( $_POST['starts_at'] ) ) : '',
			'ends_at'      => isset( $_POST['ends_at'] ) ? sanitize_text_field( wp_unslash( $_POST['ends_at'] ) ) : '',
		];

		$result = self::save_rule( $data );

		if ( $result ) {
			wp_send_json_success( [ 'id' => $result ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Failed to save rule.', 'smart-gift-cards-for-woocommerce-pro' ) ] );
		}
	}

	/**
	 * AJAX handler: delete a BOGO rule.
	 */
	public static function ajax_delete_rule() {
		check_ajax_referer( 'wcgc_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'smart-gift-cards-for-woocommerce-pro' ) ] );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid rule ID.', 'smart-gift-cards-for-woocommerce-pro' ) ] );
		}

		$result = self::delete_rule( $id );

		if ( $result ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( [ 'message' => __( 'Failed to delete rule.', 'smart-gift-cards-for-woocommerce-pro' ) ] );
		}
	}

	/**
	 * Get BOGO rules by status.
	 *
	 * @param string $status Rule status (default 'active').
	 * @return array
	 */
	public static function get_rules( $status = 'active' ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wcgc_bogo_rules WHERE status = %s ORDER BY created_at DESC",
				$status
			)
		);
	}

	/**
	 * Get a single BOGO rule by ID.
	 *
	 * @param int $id Rule ID.
	 * @return object|null
	 */
	public static function get_rule( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wcgc_bogo_rules WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Insert or update a BOGO rule.
	 *
	 * @param array $data Rule data.
	 * @return int|false Rule ID on success, false on failure.
	 */
	public static function save_rule( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wcgc_bogo_rules';

		$allowed_statuses = [ 'active', 'inactive' ];
		$status           = isset( $data['status'] ) && in_array( $data['status'], $allowed_statuses, true )
			? $data['status']
			: 'active';

		$row = [
			'name'         => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
			'buy_amount'   => isset( $data['buy_amount'] ) ? round( (float) $data['buy_amount'], 2 ) : 0,
			'get_amount'   => isset( $data['get_amount'] ) ? round( (float) $data['get_amount'], 2 ) : 0,
			'min_quantity'  => isset( $data['min_quantity'] ) ? max( 1, absint( $data['min_quantity'] ) ) : 1,
			'max_uses'     => isset( $data['max_uses'] ) ? absint( $data['max_uses'] ) : 0,
			'status'       => $status,
			'starts_at'    => ! empty( $data['starts_at'] ) ? sanitize_text_field( $data['starts_at'] ) : null,
			'ends_at'      => ! empty( $data['ends_at'] ) ? sanitize_text_field( $data['ends_at'] ) : null,
		];

		$formats = [ '%s', '%f', '%f', '%d', '%d', '%s', '%s', '%s' ];

		$id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;

		if ( $id > 0 ) {
			// Update existing rule.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
			$result = $wpdb->update( $table, $row, [ 'id' => $id ], $formats, [ '%d' ] );
			return false !== $result ? $id : false;
		}

		// Insert new rule.
		$row['created_at'] = current_time( 'mysql', true );
		$formats[]         = '%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table with no WP API.
		$result = $wpdb->insert( $table, $row, $formats );
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Delete a BOGO rule.
	 *
	 * @param int $id Rule ID.
	 * @return bool
	 */
	public static function delete_rule( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		return (bool) $wpdb->delete(
			$wpdb->prefix . 'wcgc_bogo_rules',
			[ 'id' => absint( $id ) ],
			[ '%d' ]
		);
	}

	/**
	 * Find BOGO rules that match the given gift card total and count.
	 *
	 * Returns active rules whose requirements are met, within their
	 * date window, and under usage limits.
	 *
	 * @param float $total_gc_amount Total gift card amount in the order.
	 * @param int   $gc_count        Number of gift card items.
	 * @return array Matching rule objects.
	 */
	public static function get_matching_rules( $total_gc_amount, $gc_count ) {
		global $wpdb;

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table with dynamic conditions.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wcgc_bogo_rules
				WHERE status = 'active'
				AND buy_amount <= %f
				AND min_quantity <= %d
				AND (starts_at IS NULL OR starts_at <= %s)
				AND (ends_at IS NULL OR ends_at >= %s)
				AND (max_uses = 0 OR uses_count < max_uses)
				ORDER BY buy_amount DESC",
				$total_gc_amount,
				$gc_count,
				$now,
				$now
			)
		);
	}

	/**
	 * Increment the usage counter for a BOGO rule.
	 *
	 * @param int $rule_id Rule ID.
	 * @return bool
	 */
	public static function increment_usage( $rule_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table atomic increment.
		$rows = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}wcgc_bogo_rules SET uses_count = uses_count + 1 WHERE id = %d",
				$rule_id
			)
		);

		return false !== $rows && $rows > 0;
	}
}
