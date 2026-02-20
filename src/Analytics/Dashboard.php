<?php

namespace GiftCardsPro\Analytics;

use GiftCardsPro\Support\Options;

defined( 'ABSPATH' ) || exit;

class Dashboard {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'wcgc_dashboard_after_stats', [ __CLASS__, 'render_pro_stats' ] );
	}

	/**
	 * Render Pro analytics stat cards on the dashboard.
	 *
	 * Hooked to `wcgc_dashboard_after_stats`.
	 */
	public static function render_pro_stats() {
		if ( '1' !== Options::get( 'analytics_enabled' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter.
		$range = isset( $_GET['wcgc_range'] ) ? absint( wp_unslash( $_GET['wcgc_range'] ) ) : 0;
		$stats = self::get_pro_stats( $range );

		$total_initial  = (float) $stats['total_revenue'];
		$total_balance  = (float) $stats['outstanding_liability'];
		$redemption_pct = $total_initial > 0
			? round( ( $total_initial - (float) $stats['unredeemed_balance'] ) / $total_initial * 100, 1 )
			: 0;

		$cards = [
			__( 'Revenue', 'smart-gift-cards-for-woocommerce-pro' )              => wp_strip_all_tags( wc_price( $total_initial ) ),
			__( 'Outstanding Liability', 'smart-gift-cards-for-woocommerce-pro' ) => wp_strip_all_tags( wc_price( $total_balance ) ),
			/* translators: %s: percentage value */
			__( 'Redemption Rate', 'smart-gift-cards-for-woocommerce-pro' )       => $redemption_pct . '%',
			__( 'Scheduled Pending', 'smart-gift-cards-for-woocommerce-pro' )     => number_format_i18n( (int) $stats['scheduled_pending'] ),
			__( 'Store Credits Issued', 'smart-gift-cards-for-woocommerce-pro' )  => number_format_i18n( (int) $stats['store_credits_count'] ),
			__( 'BOGO Bonuses', 'smart-gift-cards-for-woocommerce-pro' )          => number_format_i18n( (int) $stats['bogo_uses'] ),
		];

		// Date range filter links.
		$base_url = admin_url( 'admin.php?page=wcgc-gift-cards&tab=dashboard' );
		$ranges   = [
			7   => __( '7d', 'smart-gift-cards-for-woocommerce-pro' ),
			30  => __( '30d', 'smart-gift-cards-for-woocommerce-pro' ),
			90  => __( '90d', 'smart-gift-cards-for-woocommerce-pro' ),
			0   => __( 'All Time', 'smart-gift-cards-for-woocommerce-pro' ),
		];
		?>
		<h3 style="margin-top:24px;margin-bottom:8px;">
			<?php esc_html_e( 'Pro Analytics', 'smart-gift-cards-for-woocommerce-pro' ); ?>
		</h3>

		<div style="margin-bottom:12px;">
			<?php foreach ( $ranges as $days => $label ) :
				$url       = $days > 0 ? add_query_arg( 'wcgc_range', $days, $base_url ) : remove_query_arg( 'wcgc_range', $base_url );
				$is_active = ( $range === $days );
				?>
				<a href="<?php echo esc_url( $url ); ?>"
				   style="text-decoration:none;padding:4px 10px;margin-right:4px;border:1px solid <?php echo $is_active ? '#2271b1' : '#c3c4c7'; ?>;border-radius:3px;background:<?php echo $is_active ? '#2271b1' : '#fff'; ?>;color:<?php echo $is_active ? '#fff' : '#2271b1'; ?>;font-size:13px;">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</div>

		<div class="wcgc-stats-cards" style="display:flex;gap:16px;margin:16px 0;flex-wrap:wrap;">
			<?php foreach ( $cards as $label => $value ) : ?>
				<div class="wcgc-stat-card">
					<div class="wcgc-stat-label"><?php echo esc_html( $label ); ?></div>
					<div class="wcgc-stat-value"><?php echo esc_html( $value ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Get Pro analytics statistics.
	 *
	 * @param int $days Number of days to filter. 0 = all time.
	 * @return array Associative array of statistics.
	 */
	public static function get_pro_stats( $days = 0 ) {
		$cache_key = 'wcgc_pro_analytics';
		if ( $days > 0 ) {
			$cache_key .= '_' . $days;
		}

		$cached = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$gc_table        = $wpdb->prefix . 'wcgc_gift_cards';
		$scheduled_table = $wpdb->prefix . 'wcgc_scheduled_deliveries';
		$credits_table   = $wpdb->prefix . 'wcgc_store_credits';
		$bogo_table      = $wpdb->prefix . 'wcgc_bogo_rules';

		$date_clause = '';
		if ( $days > 0 ) {
			$date_clause = $wpdb->prepare( ' AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)', $days );
		}

		// Revenue: SUM of initial_amount for all gift cards.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, no WP API.
		$total_revenue = (float) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and validated date clause.
			"SELECT COALESCE(SUM(initial_amount), 0) FROM {$gc_table} WHERE 1=1{$date_clause}"
		);

		// Outstanding liability: SUM of balance WHERE status = 'active'.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$outstanding_liability = (float) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and validated date clause.
			"SELECT COALESCE(SUM(balance), 0) FROM {$gc_table} WHERE status = 'active'{$date_clause}"
		);

		// Unredeemed balance: SUM of balance WHERE status IN ('active', 'expired') - for redemption rate calculation.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$unredeemed_balance = (float) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and validated date clause.
			"SELECT COALESCE(SUM(balance), 0) FROM {$gc_table} WHERE status IN ('active','expired'){$date_clause}"
		);

		// Scheduled pending count.
		$scheduled_date_clause = '';
		if ( $days > 0 ) {
			$scheduled_date_clause = $wpdb->prepare( ' AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)', $days );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$scheduled_pending = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and validated date clause.
			"SELECT COUNT(*) FROM {$scheduled_table} WHERE status = 'pending'{$scheduled_date_clause}"
		);

		// Store credits count.
		$credits_date_clause = '';
		if ( $days > 0 ) {
			$credits_date_clause = $wpdb->prepare( ' AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)', $days );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$store_credits_count = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and validated date clause.
			"SELECT COUNT(*) FROM {$credits_table} WHERE 1=1{$credits_date_clause}"
		);

		// BOGO total uses.
		$bogo_date_clause = '';
		if ( $days > 0 ) {
			$bogo_date_clause = $wpdb->prepare( ' AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)', $days );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$bogo_uses = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and validated date clause.
			"SELECT COALESCE(SUM(uses_count), 0) FROM {$bogo_table} WHERE 1=1{$bogo_date_clause}"
		);

		$stats = [
			'total_revenue'         => $total_revenue,
			'outstanding_liability' => $outstanding_liability,
			'unredeemed_balance'    => $unredeemed_balance,
			'scheduled_pending'     => $scheduled_pending,
			'store_credits_count'   => $store_credits_count,
			'bogo_uses'             => $bogo_uses,
		];

		set_transient( $cache_key, $stats, HOUR_IN_SECONDS );

		return $stats;
	}
}
