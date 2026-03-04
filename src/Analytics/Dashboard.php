<?php

namespace BgcwPro\Analytics;

use BgcwPro\Support\Options;

defined( 'ABSPATH' ) || exit;

class Dashboard {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'bgcw_dashboard_after_stats', [ __CLASS__, 'render_pro_stats' ] );
	}

	/**
	 * Render Pro analytics stat cards on the dashboard.
	 *
	 * Hooked to `bgcw_dashboard_after_stats`.
	 */
	public static function render_pro_stats() {
		if ( '1' !== Options::get( 'analytics_enabled' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter.
		$range = isset( $_GET['bgcw_range'] ) ? absint( wp_unslash( $_GET['bgcw_range'] ) ) : 0;
		$stats = self::get_pro_stats( $range );

		$total_initial  = (float) $stats['total_revenue'];
		$redemption_pct = $total_initial > 0
			? round( ( $total_initial - (float) $stats['unredeemed_balance'] ) / $total_initial * 100, 1 )
			: 0;

		$cards = [
			__( 'Revenue', 'beltoft-gift-cards-for-woocommerce-pro' )              => wp_strip_all_tags( wc_price( $total_initial ) ),
			__( 'Redemption Rate', 'beltoft-gift-cards-for-woocommerce-pro' )      => $redemption_pct . '%',
			__( 'Scheduled Pending', 'beltoft-gift-cards-for-woocommerce-pro' )    => number_format_i18n( (int) $stats['scheduled_pending'] ),
			__( 'Store Credits Issued', 'beltoft-gift-cards-for-woocommerce-pro' ) => number_format_i18n( (int) $stats['store_credits_count'] ),
			__( 'BOGO Bonuses', 'beltoft-gift-cards-for-woocommerce-pro' )         => number_format_i18n( (int) $stats['bogo_uses'] ),
		];

		// Date range filter links.
		$base_url = admin_url( 'admin.php?page=bgcw-gift-cards&tab=dashboard' );
		$ranges   = [
			7   => __( '7d', 'beltoft-gift-cards-for-woocommerce-pro' ),
			30  => __( '30d', 'beltoft-gift-cards-for-woocommerce-pro' ),
			90  => __( '90d', 'beltoft-gift-cards-for-woocommerce-pro' ),
			0   => __( 'All Time', 'beltoft-gift-cards-for-woocommerce-pro' ),
		];
		?>
		<div class="bgcw-pro-analytics-header">
			<h3><?php esc_html_e( 'Pro Analytics', 'beltoft-gift-cards-for-woocommerce-pro' ); ?></h3>
			<div class="bgcw-pro-range-filter">
				<?php foreach ( $ranges as $days => $label ) :
					$url       = $days > 0 ? add_query_arg( 'bgcw_range', $days, $base_url ) : remove_query_arg( 'bgcw_range', $base_url );
					$is_active = ( $range === $days );
					?>
					<a href="<?php echo esc_url( $url ); ?>"
					   class="<?php echo $is_active ? 'active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="bgcw-stats-cards">
			<?php foreach ( $cards as $label => $value ) : ?>
				<div class="bgcw-stat-card">
					<div class="bgcw-stat-label"><?php echo esc_html( $label ); ?></div>
					<div class="bgcw-stat-value"><?php echo esc_html( $value ); ?></div>
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
		$cache_key = 'bgcw_pro_analytics';
		if ( $days > 0 ) {
			$cache_key .= '_' . $days;
		}

		$cached = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$gc_table        = $wpdb->prefix . 'bgcw_gift_cards';
		$scheduled_table = $wpdb->prefix . 'bgcw_scheduled_deliveries';
		$credits_table   = $wpdb->prefix . 'bgcw_store_credits';
		$bogo_table      = $wpdb->prefix . 'bgcw_bogo_rules';

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

		// Unredeemed balance: SUM of balance WHERE status IN ('active', 'expired') - for redemption rate calculation.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$unredeemed_balance = (float) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and validated date clause.
			"SELECT COALESCE(SUM(balance), 0) FROM {$gc_table} WHERE status IN ('active','expired'){$date_clause}"
		);

		// Scheduled pending count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$scheduled_pending = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and validated date clause.
			"SELECT COUNT(*) FROM {$scheduled_table} WHERE status = 'pending'{$date_clause}"
		);

		// Store credits count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$store_credits_count = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and validated date clause.
			"SELECT COUNT(*) FROM {$credits_table} WHERE 1=1{$date_clause}"
		);

		// BOGO total uses.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$bogo_uses = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and validated date clause.
			"SELECT COALESCE(SUM(uses_count), 0) FROM {$bogo_table} WHERE 1=1{$date_clause}"
		);

		$stats = [
			'total_revenue'      => $total_revenue,
			'unredeemed_balance' => $unredeemed_balance,
			'scheduled_pending'  => $scheduled_pending,
			'store_credits_count' => $store_credits_count,
			'bogo_uses'          => $bogo_uses,
		];

		set_transient( $cache_key, $stats, HOUR_IN_SECONDS );

		return $stats;
	}
}
