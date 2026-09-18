<?php

namespace BgcwPro\Admin;

use BgcwPro\Support\Options;
use BgcwPro\Licensing\License;
use BgcwPro\Bogo\BogoManager;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

	const GROUP     = 'bgcw_pro_settings_group';
	const PAGE_SLUG = 'bgcw-gift-cards-pro-settings';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'bgcw_admin_tabs', [ __CLASS__, 'register_tabs' ] );
		add_action( 'bgcw_admin_tab_pro-settings', [ __CLASS__, 'render_pro_settings_tab' ] );
		add_action( 'bgcw_admin_tab_bulk-csv', [ __CLASS__, 'render_bulk_csv_tab' ] );
		add_action( 'bgcw_admin_tab_bogo', [ __CLASS__, 'render_bogo_tab' ] );
		add_action( 'bgcw_admin_tab_license', [ __CLASS__, 'render_license_tab' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue admin assets on our page (always, regardless of license status).
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_bgcw-gift-cards' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'bgcw-pro-admin', BGCW_PRO_URL . 'assets/css/admin.css', [], BGCW_PRO_VER );

		// Register a minimal handle for license tab inline script.
		wp_register_script( 'bgcw-pro-license', '', [ 'jquery' ], BGCW_PRO_VER, true );
		wp_enqueue_script( 'bgcw-pro-license' );
		wp_localize_script( 'bgcw-pro-license', 'bgcw_pro_license', [
			'nonce' => wp_create_nonce( 'bgcw_pro_license' ),
			'i18n'  => [
				'activating'          => __( 'Activating...', 'beltoft-gift-cards-pro' ),
				'activate'            => __( 'Activate', 'beltoft-gift-cards-pro' ),
				'activation_failed'   => __( 'Activation failed.', 'beltoft-gift-cards-pro' ),
				'deactivating'        => __( 'Deactivating...', 'beltoft-gift-cards-pro' ),
				'deactivate'          => __( 'Deactivate', 'beltoft-gift-cards-pro' ),
				'deactivation_failed' => __( 'Deactivation failed.', 'beltoft-gift-cards-pro' ),
				'request_failed'      => __( 'Request failed. Please refresh and try again.', 'beltoft-gift-cards-pro' ),
				'confirm_deactivate'  => __( 'Are you sure you want to deactivate this license?', 'beltoft-gift-cards-pro' ),
			],
		] );

		$license_js = "jQuery(function($) {"
			. "var p = bgcw_pro_license;"
			. "$('#bgcw-pro-activate-license').on('click', function() {"
			. "var key = $('#bgcw-pro-license-key').val().trim();"
			. "if (!key) return;"
			. "var $btn = $(this).prop('disabled', true).text(p.i18n.activating);"
			. "var $msg = $('#bgcw-pro-license-message');"
			. "$msg.text('');"
			. "$.post(ajaxurl, {action: 'bgcw_pro_activate_license', nonce: p.nonce, license_key: key})"
			. ".done(function(r) {"
			. "if (r.success) { $msg.text(r.data.message).css('color', '#00a32a'); setTimeout(function() { location.reload(); }, 1000); }"
			. "else { $msg.text(r.data ? r.data.message : p.i18n.activation_failed).css('color', '#d63638'); $btn.prop('disabled', false).text(p.i18n.activate); }"
			. "}).fail(function() {"
			. "$msg.text(p.i18n.request_failed).css('color', '#d63638'); $btn.prop('disabled', false).text(p.i18n.activate);"
			. "});"
			. "});"
			. "$('#bgcw-pro-deactivate-license').on('click', function() {"
			. "if (!confirm(p.i18n.confirm_deactivate)) return;"
			. "var $btn = $(this).prop('disabled', true).text(p.i18n.deactivating);"
			. "var $msg = $('#bgcw-pro-license-message');"
			. "$msg.text('');"
			. "$.post(ajaxurl, {action: 'bgcw_pro_deactivate_license', nonce: p.nonce})"
			. ".done(function(r) {"
			. "if (r.success) { $msg.text(r.data.message).css('color', '#00a32a'); setTimeout(function() { location.reload(); }, 500); }"
			. "else { $msg.text(r.data ? r.data.message : p.i18n.deactivation_failed).css('color', '#d63638'); $btn.prop('disabled', false).text(p.i18n.deactivate); }"
			. "}).fail(function() {"
			. "$msg.text(p.i18n.request_failed).css('color', '#d63638'); $btn.prop('disabled', false).text(p.i18n.deactivate);"
			. "});"
			. "});"
			. "});";
		wp_add_inline_script( 'bgcw-pro-license', $license_js );
	}

	/**
	 * Register Pro tabs on the free plugin's admin page.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public static function register_tabs( array $tabs ): array {
		$tabs['pro-settings'] = __( 'Pro Settings', 'beltoft-gift-cards-pro' );
		$tabs['bulk-csv']     = __( 'Bulk & CSV', 'beltoft-gift-cards-pro' );
		$tabs['bogo']         = __( 'BOGO Rules', 'beltoft-gift-cards-pro' );
		$tabs['license']      = __( 'License', 'beltoft-gift-cards-pro' );
		return $tabs;
	}

	/**
	 * Register settings with the WordPress Settings API.
	 */
	public static function register_settings() {
		register_setting( self::GROUP, Options::OPTION, [
			'type'              => 'array',
			'sanitize_callback' => [ Options::class, 'sanitize' ],
			'default'           => Options::defaults(),
			'show_in_rest'      => false,
		] );

		// ── Scheduled Delivery Section ──
		add_settings_section(
			'bgcw_pro_scheduled_delivery',
			__( 'Scheduled Delivery', 'beltoft-gift-cards-pro' ),
			function () {
				echo '<p>' . esc_html__( 'Allow customers to schedule gift card delivery for a future date.', 'beltoft-gift-cards-pro' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		self::add_checkbox(
			'scheduled_delivery',
			__( 'Enable Scheduled Delivery', 'beltoft-gift-cards-pro' ),
			'bgcw_pro_scheduled_delivery',
			__( 'Allow customers to pick a future delivery date when purchasing a gift card.', 'beltoft-gift-cards-pro' )
		);

		// ── Email Themes Section ──
		add_settings_section(
			'bgcw_pro_email_themes',
			__( 'Email Themes', 'beltoft-gift-cards-pro' ),
			function () {
				echo '<p>' . esc_html__( 'Offer themed email designs for gift card delivery emails.', 'beltoft-gift-cards-pro' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		self::add_checkbox(
			'email_themes',
			__( 'Enable Email Themes', 'beltoft-gift-cards-pro' ),
			'bgcw_pro_email_themes',
			__( 'Allow customers to choose a themed design for the gift card email.', 'beltoft-gift-cards-pro' )
		);

		self::add_select(
			'default_theme',
			__( 'Default Theme', 'beltoft-gift-cards-pro' ),
			'bgcw_pro_email_themes',
			[
				'classic'     => __( 'Classic', 'beltoft-gift-cards-pro' ),
				'birthday'    => __( 'Birthday', 'beltoft-gift-cards-pro' ),
				'celebration' => __( 'Celebration', 'beltoft-gift-cards-pro' ),
				'thank-you'   => __( 'Thank You', 'beltoft-gift-cards-pro' ),
				'holiday'     => __( 'Holiday', 'beltoft-gift-cards-pro' ),
			]
		);

		// ── Store Credit Section ──
		add_settings_section(
			'bgcw_pro_store_credit',
			__( 'Store Credit', 'beltoft-gift-cards-pro' ),
			function () {
				echo '<p>' . esc_html__( 'Issue gift cards as store credit for refunds and account balances.', 'beltoft-gift-cards-pro' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		self::add_checkbox(
			'store_credit',
			__( 'Enable Store Credit', 'beltoft-gift-cards-pro' ),
			'bgcw_pro_store_credit',
			__( 'Enable store credit functionality backed by gift cards.', 'beltoft-gift-cards-pro' )
		);

		self::add_checkbox(
			'auto_store_credit',
			__( 'Auto-Create on Refund', 'beltoft-gift-cards-pro' ),
			'bgcw_pro_store_credit',
			__( 'Automatically create a store credit gift card when an order is refunded.', 'beltoft-gift-cards-pro' )
		);

		// ── BOGO Promotions Section ──
		add_settings_section(
			'bgcw_pro_bogo',
			__( 'BOGO Promotions', 'beltoft-gift-cards-pro' ),
			function () {
				echo '<p>' . esc_html__( 'Buy-one-get-one promotions for gift card products.', 'beltoft-gift-cards-pro' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		self::add_checkbox(
			'bogo_enabled',
			__( 'Enable BOGO Promotions', 'beltoft-gift-cards-pro' ),
			'bgcw_pro_bogo',
			__( 'Allow creating buy-one-get-one promotions for gift cards.', 'beltoft-gift-cards-pro' )
		);

		// ── Analytics Section ──
		add_settings_section(
			'bgcw_pro_analytics',
			__( 'Analytics', 'beltoft-gift-cards-pro' ),
			function () {
				echo '<p>' . esc_html__( 'Gift card usage reports delivered to your inbox on a schedule.', 'beltoft-gift-cards-pro' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		self::add_checkbox(
			'analytics_enabled',
			__( 'Enable Analytics', 'beltoft-gift-cards-pro' ),
			'bgcw_pro_analytics',
			__( 'Enable analytics dashboard and scheduled email reports.', 'beltoft-gift-cards-pro' )
		);

		add_settings_field( 'bgcw_pro_report_frequency', __( 'Report Frequency', 'beltoft-gift-cards-pro' ), function () {
			$val = Options::get( 'report_frequency' );
			$choices = [
				'daily'   => __( 'Daily', 'beltoft-gift-cards-pro' ),
				'weekly'  => __( 'Weekly', 'beltoft-gift-cards-pro' ),
				'monthly' => __( 'Monthly', 'beltoft-gift-cards-pro' ),
			];
			printf(
				'<select name="%s[report_frequency]" id="bgcw-pro-report-frequency">',
				esc_attr( Options::OPTION )
			);
			foreach ( $choices as $value => $label ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $value ),
					selected( $val, $value, false ),
					esc_html( $label )
				);
			}
			echo '</select>';
		}, self::PAGE_SLUG, 'bgcw_pro_analytics' );

		add_settings_field( 'bgcw_pro_report_recipients', __( 'Report Recipients', 'beltoft-gift-cards-pro' ), function () {
			$val = Options::get( 'report_recipients' );
			printf(
				'<input type="text" name="%s[report_recipients]" value="%s" class="regular-text" placeholder="%s" />',
				esc_attr( Options::OPTION ),
				esc_attr( $val ),
				esc_attr( get_option( 'admin_email' ) )
			);
			echo '<p class="description">' . esc_html__( 'Comma-separated email addresses. Leave blank to use the site admin email.', 'beltoft-gift-cards-pro' ) . '</p>';
		}, self::PAGE_SLUG, 'bgcw_pro_analytics' );

		add_settings_field( 'bgcw_pro_report_day_of_week', __( 'Day of Week', 'beltoft-gift-cards-pro' ), function () {
			$val  = Options::get( 'report_day_of_week' );
			$days = [
				1 => __( 'Monday', 'beltoft-gift-cards-pro' ),
				2 => __( 'Tuesday', 'beltoft-gift-cards-pro' ),
				3 => __( 'Wednesday', 'beltoft-gift-cards-pro' ),
				4 => __( 'Thursday', 'beltoft-gift-cards-pro' ),
				5 => __( 'Friday', 'beltoft-gift-cards-pro' ),
				6 => __( 'Saturday', 'beltoft-gift-cards-pro' ),
				7 => __( 'Sunday', 'beltoft-gift-cards-pro' ),
			];
			printf(
				'<select name="%s[report_day_of_week]" id="bgcw-pro-report-day-of-week">',
				esc_attr( Options::OPTION )
			);
			foreach ( $days as $num => $label ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $num ),
					selected( $val, (string) $num, false ),
					esc_html( $label )
				);
			}
			echo '</select>';
			echo '<p class="description">' . esc_html__( 'Used for weekly reports.', 'beltoft-gift-cards-pro' ) . '</p>';
		}, self::PAGE_SLUG, 'bgcw_pro_analytics' );

		add_settings_field( 'bgcw_pro_report_day_of_month', __( 'Day of Month', 'beltoft-gift-cards-pro' ), function () {
			$val = Options::get( 'report_day_of_month' );
			printf(
				'<select name="%s[report_day_of_month]" id="bgcw-pro-report-day-of-month">',
				esc_attr( Options::OPTION )
			);
			for ( $i = 1; $i <= 28; $i++ ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $i ),
					selected( $val, (string) $i, false ),
					esc_html( $i )
				);
			}
			echo '</select>';
			echo '<p class="description">' . esc_html__( 'Used for monthly reports. Limited to 28 to ensure consistent delivery across all months.', 'beltoft-gift-cards-pro' ) . '</p>';
		}, self::PAGE_SLUG, 'bgcw_pro_analytics' );

		add_settings_field( 'bgcw_pro_report_time_of_day', __( 'Time of Day', 'beltoft-gift-cards-pro' ), function () {
			$val = Options::get( 'report_time_of_day' );
			printf(
				'<select name="%s[report_time_of_day]" id="bgcw-pro-report-time">',
				esc_attr( Options::OPTION )
			);
			for ( $h = 0; $h <= 23; $h++ ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $h ),
					selected( $val, (string) $h, false ),
					esc_html( date_i18n( get_option( 'time_format' ), mktime( $h, 0 ) ) )
				);
			}
			echo '</select>';
			echo '<p class="description">';
			printf(
				/* translators: %s: WordPress timezone string */
				esc_html__( 'Uses your site timezone (%s).', 'beltoft-gift-cards-pro' ),
				esc_html( wp_timezone_string() )
			);
			echo '</p>';
		}, self::PAGE_SLUG, 'bgcw_pro_analytics' );

		// ── Advanced Section ──
		add_settings_section(
			'bgcw_pro_advanced',
			__( 'Advanced', 'beltoft-gift-cards-pro' ),
			'__return_null',
			self::PAGE_SLUG
		);

		self::add_checkbox(
			'cleanup_on_uninstall',
			__( 'Cleanup on Uninstall', 'beltoft-gift-cards-pro' ),
			'bgcw_pro_advanced',
			__( 'Delete all Pro plugin data when uninstalled.', 'beltoft-gift-cards-pro' )
		);
	}

	// =========================================================================
	// TAB: Pro Settings
	// =========================================================================

	/**
	 * Render the Pro Settings tab content.
	 */
	public static function render_pro_settings_tab() {
		if ( ! License::is_active() ) {
			echo '<div class="notice notice-warning inline" style="margin-top:16px;"><p>';
			printf(
				wp_kses(
					/* translators: %s: license tab URL */
					__( 'An active license is required for Pro Settings. <a href="%s">Enter your license key</a> to enable these features.', 'beltoft-gift-cards-pro' ),
					[ 'a' => [ 'href' => [] ] ]
				),
				esc_url( admin_url( 'admin.php?page=bgcw-gift-cards&tab=license' ) )
			);
			echo '</p></div>';
			return;
		}

		settings_errors();
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( self::GROUP );
			do_settings_sections( self::PAGE_SLUG );
			?>

			<?php if ( Options::get( 'email_themes' ) === '1' ) : ?>
				<?php self::render_theme_customization(); ?>
			<?php endif; ?>

			<?php submit_button(); ?>
		</form>
		<?php
		}

	/**
	 * Render the email theme customization cards.
	 */
	private static function render_theme_customization() {
		$themes = \BgcwPro\EmailThemes\ThemeManager::get_available_themes();

		$defaults = [
			'classic'     => [ 'heading' => __( "You've received a gift card!", 'beltoft-gift-cards-pro' ), 'color' => '#6B4C9A' ],
			'birthday'    => [ 'heading' => __( 'Happy Birthday!', 'beltoft-gift-cards-pro' ), 'color' => '#E91E8C' ],
			'celebration' => [ 'heading' => __( 'Congratulations!', 'beltoft-gift-cards-pro' ), 'color' => '#E88700' ],
			'thank-you'   => [ 'heading' => __( 'Thank You!', 'beltoft-gift-cards-pro' ), 'color' => '#1A9E8F' ],
			'holiday'     => [ 'heading' => __( 'Happy Holidays!', 'beltoft-gift-cards-pro' ), 'color' => '#B22222' ],
		];
		?>
		<h2><?php esc_html_e( 'Theme Customization', 'beltoft-gift-cards-pro' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Customize the heading text and accent color for each email theme. Leave fields blank to use the defaults.', 'beltoft-gift-cards-pro' ); ?></p>

		<div class="bgcw-pro-theme-grid">
			<?php foreach ( $themes as $slug => $theme ) :
				$saved_heading = Options::get( 'theme_heading_' . $slug );
				$saved_color   = Options::get( 'theme_color_' . $slug );
				$color         = $saved_color ?: $defaults[ $slug ]['color'];
				$option_name   = Options::OPTION;
				?>
				<div class="bgcw-pro-theme-card">
					<div class="bgcw-pro-theme-card__preview" style="border-top: 3px solid <?php echo esc_attr( $color ); ?>;">
						<span style="display:block;width:120px;height:50px;border-radius:4px;background-color:<?php echo esc_attr( $color ); ?>;margin:0 auto 6px;"></span>
						<strong><?php echo esc_html( $theme['name'] ); ?></strong>
						<br />
						<em style="font-size:12px;color:#666;"><?php echo esc_html( $saved_heading ?: $defaults[ $slug ]['heading'] ); ?></em>
					</div>
					<div class="bgcw-pro-theme-card__fields">
						<label>
							<?php esc_html_e( 'Heading', 'beltoft-gift-cards-pro' ); ?>
							<input type="text"
								   name="<?php echo esc_attr( $option_name ); ?>[theme_heading_<?php echo esc_attr( $slug ); ?>]"
								   value="<?php echo esc_attr( $saved_heading ); ?>"
								   placeholder="<?php echo esc_attr( $defaults[ $slug ]['heading'] ); ?>"
								   class="widefat" />
						</label>
						<label class="bgcw-pro-theme-card__color">
							<?php esc_html_e( 'Accent Color', 'beltoft-gift-cards-pro' ); ?>
							<span class="bgcw-color-setting">
								<input type="color"
									   name="<?php echo esc_attr( $option_name ); ?>[theme_color_<?php echo esc_attr( $slug ); ?>]"
									   value="<?php echo esc_attr( $color ); ?>"
									   class="bgcw-color-picker" />
								<code><?php echo esc_html( $color ); ?></code>
							</span>
						</label>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	// =========================================================================
	// TAB: License
	// =========================================================================

	/**
	 * Render the License tab content.
	 */
	public static function render_license_tab() {
		$opts   = Options::get();
		$status = $opts['license_status'];
		$key    = $opts['license_key'];
		?>
		<div class="bgcw-pro-license-section">
			<h2><?php esc_html_e( 'License Key', 'beltoft-gift-cards-pro' ); ?></h2>

			<div class="bgcw-pro-license-status">
				<?php if ( 'valid' === $status ) : ?>
					<span class="bgcw-pro-license-badge bgcw-pro-license-badge--active"><?php esc_html_e( 'Active', 'beltoft-gift-cards-pro' ); ?></span>
					<?php if ( $opts['license_expires'] && 'lifetime' !== $opts['license_expires'] ) : ?>
						<span class="bgcw-pro-license-expires">
							<?php
							printf(
								/* translators: %s: license expiration date */
								esc_html__( 'Expires: %s', 'beltoft-gift-cards-pro' ),
								esc_html( date_i18n( get_option( 'date_format' ), strtotime( $opts['license_expires'] ) ) )
							);
							?>
						</span>
					<?php else : ?>
						<span class="bgcw-pro-license-expires"><?php esc_html_e( 'Managed by active subscription', 'beltoft-gift-cards-pro' ); ?></span>
					<?php endif; ?>
				<?php elseif ( 'inactive' === $status ) : ?>
					<span class="bgcw-pro-license-badge bgcw-pro-license-badge--expired"><?php esc_html_e( 'Inactive', 'beltoft-gift-cards-pro' ); ?></span>
					<span class="bgcw-pro-license-expires" style="color:#d63638;"><?php esc_html_e( 'Renew to receive updates', 'beltoft-gift-cards-pro' ); ?></span>
				<?php elseif ( 'domain_mismatch' === $status ) : ?>
					<span class="bgcw-pro-license-badge bgcw-pro-license-badge--expired"><?php esc_html_e( 'Domain Mismatch', 'beltoft-gift-cards-pro' ); ?></span>
					<span class="bgcw-pro-license-expires" style="color:#d63638;"><?php esc_html_e( 'Deactivate your old domain and reactivate here', 'beltoft-gift-cards-pro' ); ?></span>
				<?php elseif ( 'invalid_key' === $status ) : ?>
					<span class="bgcw-pro-license-badge bgcw-pro-license-badge--expired"><?php esc_html_e( 'Invalid Key', 'beltoft-gift-cards-pro' ); ?></span>
					<span class="bgcw-pro-license-expires" style="color:#d63638;"><?php esc_html_e( 'Please check and re-enter your license key', 'beltoft-gift-cards-pro' ); ?></span>
				<?php else : ?>
					<span class="bgcw-pro-license-badge bgcw-pro-license-badge--inactive"><?php esc_html_e( 'Not Activated', 'beltoft-gift-cards-pro' ); ?></span>
				<?php endif; ?>
			</div>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="bgcw-pro-license-key"><?php esc_html_e( 'License Key', 'beltoft-gift-cards-pro' ); ?></label>
					</th>
					<td>
						<?php
						// Only mask and lock the input when the license is actively valid.
						$is_locked  = $key && 'valid' === $status;
						$mask_value = '';
						if ( $is_locked && strlen( $key ) >= 4 ) {
							$mask_value = str_repeat( "\u{2022}", strlen( $key ) - 4 ) . substr( $key, -4 );
						} elseif ( $is_locked ) {
							$mask_value = str_repeat( "\u{2022}", strlen( $key ) );
						}
						?>
						<input type="text"
							   id="bgcw-pro-license-key"
							   class="regular-text"
							   value="<?php echo esc_attr( $is_locked ? $mask_value : '' ); ?>"
							   <?php echo $is_locked ? 'readonly' : ''; ?>
							   placeholder="<?php esc_attr_e( 'Enter your license key', 'beltoft-gift-cards-pro' ); ?>" />

						<?php if ( $is_locked ) : ?>
							<button type="button" id="bgcw-pro-deactivate-license" class="button">
								<?php esc_html_e( 'Deactivate', 'beltoft-gift-cards-pro' ); ?>
							</button>
						<?php else : ?>
							<button type="button" id="bgcw-pro-activate-license" class="button button-primary">
								<?php esc_html_e( 'Activate', 'beltoft-gift-cards-pro' ); ?>
							</button>
						<?php endif; ?>

						<span id="bgcw-pro-license-message" class="bgcw-pro-license-message"></span>
					</td>
				</tr>
			</table>

			<?php
			// Activations info.
			$max_activations = $opts['license_max_activations'] ?? '';
			if ( 'valid' === $status && '' !== $max_activations ) :
				?>
				<p class="description">
					<?php
					if ( 0 === (int) $max_activations ) {
						esc_html_e( 'Activations: Unlimited', 'beltoft-gift-cards-pro' );
					} else {
						printf(
							/* translators: %d: maximum number of site activations */
							esc_html__( 'Activations: Up to %d sites', 'beltoft-gift-cards-pro' ),
							(int) $max_activations
						);
					}
					?>
				</p>
			<?php endif; ?>

			<?php
			// Update available notice.
			$remote_ver = $opts['license_remote_version'] ?? '';
			if ( $remote_ver && version_compare( BGCW_PRO_VER, $remote_ver, '<' ) ) :
				?>
				<p class="description" style="color:#d63638;">
					<?php
					printf(
						/* translators: 1: new version number, 2: current version number */
						esc_html__( 'Update: Version %1$s available (you have %2$s)', 'beltoft-gift-cards-pro' ),
						esc_html( $remote_ver ),
						esc_html( BGCW_PRO_VER )
					);
					?>
				</p>
			<?php endif; ?>

			<?php if ( $opts['license_last_checked'] ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: date and time of last license check */
						esc_html__( 'Last checked: %s', 'beltoft-gift-cards-pro' ),
						esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $opts['license_last_checked'] ) ) )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	// =========================================================================
	// TAB: Bulk & CSV
	// =========================================================================

	/**
	 * Render the Bulk & CSV tab content.
	 */
	public static function render_bulk_csv_tab() {
		if ( ! License::is_active() ) {
			self::render_license_gate( __( 'Bulk generation and CSV tools require an active license.', 'beltoft-gift-cards-pro' ) );
			return;
		}
		?>
		<div class="bgcw-pro-settings-wrap">
			<div class="bgcw-pro-bulk-wrap">
				<h3><?php esc_html_e( 'Bulk Generate Gift Cards', 'beltoft-gift-cards-pro' ); ?></h3>
				<table class="form-table">
					<tr>
						<th><label for="bgcw-pro-bulk-quantity"><?php esc_html_e( 'Quantity', 'beltoft-gift-cards-pro' ); ?></label></th>
						<td><input type="number" id="bgcw-pro-bulk-quantity" min="1" max="500" step="1" value="10" class="small-text" /></td>
					</tr>
					<tr>
						<th><label for="bgcw-pro-bulk-amount"><?php esc_html_e( 'Amount', 'beltoft-gift-cards-pro' ); ?></label></th>
						<td><input type="number" id="bgcw-pro-bulk-amount" min="0.01" step="0.01" class="small-text" /></td>
					</tr>
					<tr>
						<th><label for="bgcw-pro-bulk-source"><?php esc_html_e( 'Source', 'beltoft-gift-cards-pro' ); ?></label></th>
						<td>
							<select id="bgcw-pro-bulk-source">
								<?php foreach ( \Bgcw\GiftCard\Source::manual_sources() as $bgcw_pro_source ) : ?>
									<option value="<?php echo esc_attr( $bgcw_pro_source ); ?>" <?php selected( $bgcw_pro_source, \Bgcw\GiftCard\Source::PROMOTION ); ?>><?php echo esc_html( \Bgcw\GiftCard\Source::label( $bgcw_pro_source ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Paid offline counts as a paid card at redemption; promotion and compensation count as free.', 'beltoft-gift-cards-pro' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="bgcw-pro-bulk-prefix"><?php esc_html_e( 'Code Prefix (optional)', 'beltoft-gift-cards-pro' ); ?></label></th>
						<td><input type="text" id="bgcw-pro-bulk-prefix" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="bgcw-pro-bulk-expiry"><?php esc_html_e( 'Expiry (days)', 'beltoft-gift-cards-pro' ); ?></label></th>
						<td><input type="number" id="bgcw-pro-bulk-expiry" min="0" step="1" value="0" class="small-text" /></td>
					</tr>
					<tr>
						<th><label for="bgcw-pro-bulk-name"><?php esc_html_e( 'Recipient Name (optional)', 'beltoft-gift-cards-pro' ); ?></label></th>
						<td><input type="text" id="bgcw-pro-bulk-name" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="bgcw-pro-bulk-email"><?php esc_html_e( 'Recipient Email (optional)', 'beltoft-gift-cards-pro' ); ?></label></th>
						<td><input type="email" id="bgcw-pro-bulk-email" class="regular-text" /></td>
					</tr>
				</table>
				<p>
					<button type="button" class="button button-primary" id="bgcw-pro-bulk-generate-btn"><?php esc_html_e( 'Generate Gift Cards', 'beltoft-gift-cards-pro' ); ?></button>
				</p>
				<div class="bgcw-pro-bulk-progress" aria-live="polite">
					<div class="progress-bar"><div class="progress-bar-fill" style="width:0%;"></div></div>
					<div class="progress-text"><?php esc_html_e( 'Preparing generation...', 'beltoft-gift-cards-pro' ); ?></div>
				</div>
			</div>

			<div class="bgcw-pro-csv-wrap">
				<div class="bgcw-pro-csv-box">
					<h3><?php esc_html_e( 'Export Gift Cards (CSV)', 'beltoft-gift-cards-pro' ); ?></h3>
					<p>
						<label for="bgcw-pro-export-status"><?php esc_html_e( 'Filter by status', 'beltoft-gift-cards-pro' ); ?></label><br />
						<select id="bgcw-pro-export-status">
							<option value=""><?php esc_html_e( 'All statuses', 'beltoft-gift-cards-pro' ); ?></option>
							<option value="active"><?php esc_html_e( 'Active', 'beltoft-gift-cards-pro' ); ?></option>
							<option value="disabled"><?php esc_html_e( 'Disabled', 'beltoft-gift-cards-pro' ); ?></option>
							<option value="expired"><?php esc_html_e( 'Expired', 'beltoft-gift-cards-pro' ); ?></option>
							<option value="redeemed"><?php esc_html_e( 'Redeemed', 'beltoft-gift-cards-pro' ); ?></option>
						</select>
					</p>
					<p>
						<button type="button" class="button" id="bgcw-pro-export-csv-btn"><?php esc_html_e( 'Download CSV', 'beltoft-gift-cards-pro' ); ?></button>
					</p>
				</div>

				<div class="bgcw-pro-csv-box">
					<h3><?php esc_html_e( 'Import Gift Cards (CSV)', 'beltoft-gift-cards-pro' ); ?></h3>
					<p>
						<input type="file" id="bgcw-pro-import-file" accept=".csv,.txt,text/csv,text/plain" />
					</p>
					<p>
						<button type="button" class="button" id="bgcw-pro-import-csv-btn"><?php esc_html_e( 'Import CSV', 'beltoft-gift-cards-pro' ); ?></button>
					</p>
					<p id="bgcw-pro-import-result" class="description" style="display:none;"></p>
				</div>
			</div>
		</div>
		<?php
	}

	// =========================================================================
	// TAB: BOGO Rules
	// =========================================================================

	/**
	 * Render the BOGO Rules tab content.
	 */
	public static function render_bogo_tab() {
		if ( ! License::is_active() ) {
			self::render_license_gate( __( 'BOGO promotions require an active license.', 'beltoft-gift-cards-pro' ) );
			return;
		}

		$rules = array_merge( BogoManager::get_rules( 'active' ), BogoManager::get_rules( 'inactive' ) );
		?>
		<div class="bgcw-pro-settings-wrap">
			<div class="bgcw-pro-bogo-rules">
				<p>
					<button type="button" class="button" id="bgcw-pro-add-bogo-btn"><?php esc_html_e( 'Add Rule', 'beltoft-gift-cards-pro' ); ?></button>
				</p>

				<div class="bgcw-pro-bogo-form">
					<input type="hidden" id="bgcw-pro-bogo-id" value="" />
					<table class="form-table">
						<tr>
							<th><label for="bgcw-pro-bogo-name"><?php esc_html_e( 'Rule Name', 'beltoft-gift-cards-pro' ); ?></label></th>
							<td><input type="text" id="bgcw-pro-bogo-name" class="regular-text" /></td>
						</tr>
						<tr>
							<th><label for="bgcw-pro-bogo-buy"><?php esc_html_e( 'Buy Amount', 'beltoft-gift-cards-pro' ); ?></label></th>
							<td><input type="number" id="bgcw-pro-bogo-buy" min="0" step="0.01" class="small-text" /></td>
						</tr>
						<tr>
							<th><label for="bgcw-pro-bogo-get"><?php esc_html_e( 'Get Amount', 'beltoft-gift-cards-pro' ); ?></label></th>
							<td><input type="number" id="bgcw-pro-bogo-get" min="0" step="0.01" class="small-text" /></td>
						</tr>
						<tr>
							<th><label for="bgcw-pro-bogo-min-qty"><?php esc_html_e( 'Minimum Quantity', 'beltoft-gift-cards-pro' ); ?></label></th>
							<td><input type="number" id="bgcw-pro-bogo-min-qty" min="1" step="1" value="1" class="small-text" /></td>
						</tr>
						<tr>
							<th><label for="bgcw-pro-bogo-max-uses"><?php esc_html_e( 'Maximum Uses (0 = unlimited)', 'beltoft-gift-cards-pro' ); ?></label></th>
							<td><input type="number" id="bgcw-pro-bogo-max-uses" min="0" step="1" value="0" class="small-text" /></td>
						</tr>
						<tr>
							<th><label for="bgcw-pro-bogo-starts"><?php esc_html_e( 'Starts At (optional)', 'beltoft-gift-cards-pro' ); ?></label></th>
							<td><input type="datetime-local" id="bgcw-pro-bogo-starts" /></td>
						</tr>
						<tr>
							<th><label for="bgcw-pro-bogo-ends"><?php esc_html_e( 'Ends At (optional)', 'beltoft-gift-cards-pro' ); ?></label></th>
							<td><input type="datetime-local" id="bgcw-pro-bogo-ends" /></td>
						</tr>
					</table>
					<p>
						<button type="button" class="button button-primary" id="bgcw-pro-save-bogo-btn"><?php esc_html_e( 'Save Rule', 'beltoft-gift-cards-pro' ); ?></button>
					</p>
				</div>

				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'beltoft-gift-cards-pro' ); ?></th>
							<th><?php esc_html_e( 'Buy', 'beltoft-gift-cards-pro' ); ?></th>
							<th><?php esc_html_e( 'Get', 'beltoft-gift-cards-pro' ); ?></th>
							<th><?php esc_html_e( 'Min Qty', 'beltoft-gift-cards-pro' ); ?></th>
							<th><?php esc_html_e( 'Uses', 'beltoft-gift-cards-pro' ); ?></th>
							<th><?php esc_html_e( 'Status', 'beltoft-gift-cards-pro' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'beltoft-gift-cards-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php if ( empty( $rules ) ) : ?>
						<tr>
							<td colspan="7"><?php esc_html_e( 'No BOGO rules yet.', 'beltoft-gift-cards-pro' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $rules as $rule ) : ?>
							<tr>
								<td><?php echo esc_html( $rule->name ); ?></td>
								<td><?php echo esc_html( wp_strip_all_tags( wc_price( (float) $rule->buy_amount ) ) ); ?></td>
								<td><?php echo esc_html( wp_strip_all_tags( wc_price( (float) $rule->get_amount ) ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (int) $rule->min_quantity ) ); ?></td>
								<td>
									<?php
									$uses_display = (int) $rule->uses_count;
									if ( (int) $rule->max_uses > 0 ) {
										$uses_display .= ' / ' . (int) $rule->max_uses;
									}
									echo esc_html( $uses_display );
									?>
								</td>
								<td><?php echo esc_html( $rule->status ); ?></td>
								<td>
									<button type="button" class="button-link-delete bgcw-pro-delete-bogo" data-id="<?php echo esc_attr( (int) $rule->id ); ?>">
										<?php esc_html_e( 'Delete', 'beltoft-gift-cards-pro' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a license gate notice for tabs that require an active license.
	 *
	 * @param string $message The message to display.
	 */
	private static function render_license_gate( $message ) {
		echo '<div class="notice notice-warning inline" style="margin-top:16px;"><p>';
		printf(
			'%s <a href="%s">%s</a>',
			esc_html( $message ),
			esc_url( admin_url( 'admin.php?page=bgcw-gift-cards&tab=license' ) ),
			esc_html__( 'Activate your license', 'beltoft-gift-cards-pro' )
		);
		echo '</p></div>';
	}

	// =========================================================================
	// Field Helpers
	// =========================================================================

	/**
	 * Register a checkbox settings field.
	 *
	 * @param string $key     Option key.
	 * @param string $label   Field label.
	 * @param string $section Section ID.
	 * @param string $desc    Description text.
	 */
	private static function add_checkbox( $key, $label, $section, $desc = '' ) {
		add_settings_field( "bgcw_pro_{$key}", $label, function () use ( $key, $desc ) {
			$val = Options::get( $key );
			printf(
				'<input type="checkbox" name="%s[%s]" value="1" %s />',
				esc_attr( Options::OPTION ),
				esc_attr( $key ),
				checked( $val, '1', false )
			);
			if ( $desc ) {
				printf( '<p class="description">%s</p>', esc_html( $desc ) );
			}
		}, self::PAGE_SLUG, $section );
	}

	/**
	 * Register a select settings field.
	 *
	 * @param string $key     Option key.
	 * @param string $label   Field label.
	 * @param string $section Section ID.
	 * @param array  $choices Associative array of value => display text.
	 */
	private static function add_select( $key, $label, $section, $choices ) {
		add_settings_field( "bgcw_pro_{$key}", $label, function () use ( $key, $choices ) {
			$val = Options::get( $key );
			printf( '<select name="%s[%s]">', esc_attr( Options::OPTION ), esc_attr( $key ) );
			foreach ( $choices as $value => $text ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $value ),
					selected( $val, $value, false ),
					esc_html( $text )
				);
			}
			echo '</select>';
		}, self::PAGE_SLUG, $section );
	}
}
