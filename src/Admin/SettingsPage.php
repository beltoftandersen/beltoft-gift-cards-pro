<?php

namespace GiftCardsPro\Admin;

use GiftCardsPro\Support\Options;
use GiftCardsPro\Licensing\License;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

	const GROUP     = 'wcgc_pro_settings_group';
	const PAGE_SLUG = 'wcgc-gift-cards-pro-settings';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'wcgc_admin_tabs', [ __CLASS__, 'register_tabs' ] );
		add_action( 'wcgc_admin_tab_pro-settings', [ __CLASS__, 'render_pro_settings_tab' ] );
		add_action( 'wcgc_admin_tab_license', [ __CLASS__, 'render_license_tab' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue admin assets on our page (always, regardless of license status).
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_wcgc-gift-cards' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wcgc-pro-admin', WCGC_PRO_URL . 'assets/css/admin.css', [], WCGC_PRO_VER );
	}

	/**
	 * Register Pro tabs on the free plugin's admin page.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public static function register_tabs( array $tabs ): array {
		$tabs['pro-settings'] = __( 'Pro Settings', 'smart-gift-cards-for-woocommerce-pro' );
		$tabs['license']      = __( 'License', 'smart-gift-cards-for-woocommerce-pro' );
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
			'wcgc_pro_scheduled_delivery',
			__( 'Scheduled Delivery', 'smart-gift-cards-for-woocommerce-pro' ),
			function () {
				echo '<p>' . esc_html__( 'Allow customers to schedule gift card delivery for a future date.', 'smart-gift-cards-for-woocommerce-pro' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		self::add_checkbox(
			'scheduled_delivery',
			__( 'Enable Scheduled Delivery', 'smart-gift-cards-for-woocommerce-pro' ),
			'wcgc_pro_scheduled_delivery',
			__( 'Allow customers to pick a future delivery date when purchasing a gift card.', 'smart-gift-cards-for-woocommerce-pro' )
		);

		// ── Email Themes Section ──
		add_settings_section(
			'wcgc_pro_email_themes',
			__( 'Email Themes', 'smart-gift-cards-for-woocommerce-pro' ),
			function () {
				echo '<p>' . esc_html__( 'Offer themed email designs for gift card delivery emails.', 'smart-gift-cards-for-woocommerce-pro' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		self::add_checkbox(
			'email_themes',
			__( 'Enable Email Themes', 'smart-gift-cards-for-woocommerce-pro' ),
			'wcgc_pro_email_themes',
			__( 'Allow customers to choose a themed design for the gift card email.', 'smart-gift-cards-for-woocommerce-pro' )
		);

		self::add_select(
			'default_theme',
			__( 'Default Theme', 'smart-gift-cards-for-woocommerce-pro' ),
			'wcgc_pro_email_themes',
			[
				'classic'     => __( 'Classic', 'smart-gift-cards-for-woocommerce-pro' ),
				'birthday'    => __( 'Birthday', 'smart-gift-cards-for-woocommerce-pro' ),
				'celebration' => __( 'Celebration', 'smart-gift-cards-for-woocommerce-pro' ),
				'thank-you'   => __( 'Thank You', 'smart-gift-cards-for-woocommerce-pro' ),
				'holiday'     => __( 'Holiday', 'smart-gift-cards-for-woocommerce-pro' ),
			]
		);

		// ── Store Credit Section ──
		add_settings_section(
			'wcgc_pro_store_credit',
			__( 'Store Credit', 'smart-gift-cards-for-woocommerce-pro' ),
			function () {
				echo '<p>' . esc_html__( 'Issue gift cards as store credit for refunds and account balances.', 'smart-gift-cards-for-woocommerce-pro' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		self::add_checkbox(
			'store_credit',
			__( 'Enable Store Credit', 'smart-gift-cards-for-woocommerce-pro' ),
			'wcgc_pro_store_credit',
			__( 'Enable store credit functionality backed by gift cards.', 'smart-gift-cards-for-woocommerce-pro' )
		);

		self::add_checkbox(
			'auto_store_credit',
			__( 'Auto-Create on Refund', 'smart-gift-cards-for-woocommerce-pro' ),
			'wcgc_pro_store_credit',
			__( 'Automatically create a store credit gift card when an order is refunded.', 'smart-gift-cards-for-woocommerce-pro' )
		);

		// ── BOGO Promotions Section ──
		add_settings_section(
			'wcgc_pro_bogo',
			__( 'BOGO Promotions', 'smart-gift-cards-for-woocommerce-pro' ),
			function () {
				echo '<p>' . esc_html__( 'Buy-one-get-one promotions for gift card products.', 'smart-gift-cards-for-woocommerce-pro' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		self::add_checkbox(
			'bogo_enabled',
			__( 'Enable BOGO Promotions', 'smart-gift-cards-for-woocommerce-pro' ),
			'wcgc_pro_bogo',
			__( 'Allow creating buy-one-get-one promotions for gift cards.', 'smart-gift-cards-for-woocommerce-pro' )
		);

		// ── Analytics Section ──
		add_settings_section(
			'wcgc_pro_analytics',
			__( 'Analytics', 'smart-gift-cards-for-woocommerce-pro' ),
			function () {
				echo '<p>' . esc_html__( 'Gift card usage reports delivered to your inbox on a schedule.', 'smart-gift-cards-for-woocommerce-pro' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		self::add_checkbox(
			'analytics_enabled',
			__( 'Enable Analytics', 'smart-gift-cards-for-woocommerce-pro' ),
			'wcgc_pro_analytics',
			__( 'Enable analytics dashboard and scheduled email reports.', 'smart-gift-cards-for-woocommerce-pro' )
		);

		add_settings_field( 'wcgc_pro_report_frequency', __( 'Report Frequency', 'smart-gift-cards-for-woocommerce-pro' ), function () {
			$val = Options::get( 'report_frequency' );
			$choices = [
				'daily'   => __( 'Daily', 'smart-gift-cards-for-woocommerce-pro' ),
				'weekly'  => __( 'Weekly', 'smart-gift-cards-for-woocommerce-pro' ),
				'monthly' => __( 'Monthly', 'smart-gift-cards-for-woocommerce-pro' ),
			];
			printf(
				'<select name="%s[report_frequency]" id="wcgc-pro-report-frequency">',
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
		}, self::PAGE_SLUG, 'wcgc_pro_analytics' );

		add_settings_field( 'wcgc_pro_report_recipients', __( 'Report Recipients', 'smart-gift-cards-for-woocommerce-pro' ), function () {
			$val = Options::get( 'report_recipients' );
			printf(
				'<input type="text" name="%s[report_recipients]" value="%s" class="regular-text" placeholder="%s" />',
				esc_attr( Options::OPTION ),
				esc_attr( $val ),
				esc_attr( get_option( 'admin_email' ) )
			);
			echo '<p class="description">' . esc_html__( 'Comma-separated email addresses. Leave blank to use the site admin email.', 'smart-gift-cards-for-woocommerce-pro' ) . '</p>';
		}, self::PAGE_SLUG, 'wcgc_pro_analytics' );

		add_settings_field( 'wcgc_pro_report_day_of_week', __( 'Day of Week', 'smart-gift-cards-for-woocommerce-pro' ), function () {
			$val  = Options::get( 'report_day_of_week' );
			$days = [
				1 => __( 'Monday', 'smart-gift-cards-for-woocommerce-pro' ),
				2 => __( 'Tuesday', 'smart-gift-cards-for-woocommerce-pro' ),
				3 => __( 'Wednesday', 'smart-gift-cards-for-woocommerce-pro' ),
				4 => __( 'Thursday', 'smart-gift-cards-for-woocommerce-pro' ),
				5 => __( 'Friday', 'smart-gift-cards-for-woocommerce-pro' ),
				6 => __( 'Saturday', 'smart-gift-cards-for-woocommerce-pro' ),
				7 => __( 'Sunday', 'smart-gift-cards-for-woocommerce-pro' ),
			];
			printf(
				'<select name="%s[report_day_of_week]" id="wcgc-pro-report-day-of-week">',
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
			echo '<p class="description">' . esc_html__( 'Used for weekly reports.', 'smart-gift-cards-for-woocommerce-pro' ) . '</p>';
		}, self::PAGE_SLUG, 'wcgc_pro_analytics' );

		add_settings_field( 'wcgc_pro_report_day_of_month', __( 'Day of Month', 'smart-gift-cards-for-woocommerce-pro' ), function () {
			$val = Options::get( 'report_day_of_month' );
			printf(
				'<select name="%s[report_day_of_month]" id="wcgc-pro-report-day-of-month">',
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
			echo '<p class="description">' . esc_html__( 'Used for monthly reports. Limited to 28 to ensure consistent delivery across all months.', 'smart-gift-cards-for-woocommerce-pro' ) . '</p>';
		}, self::PAGE_SLUG, 'wcgc_pro_analytics' );

		add_settings_field( 'wcgc_pro_report_time_of_day', __( 'Time of Day', 'smart-gift-cards-for-woocommerce-pro' ), function () {
			$val = Options::get( 'report_time_of_day' );
			printf(
				'<select name="%s[report_time_of_day]" id="wcgc-pro-report-time">',
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
				esc_html__( 'Uses your site timezone (%s).', 'smart-gift-cards-for-woocommerce-pro' ),
				esc_html( wp_timezone_string() )
			);
			echo '</p>';
		}, self::PAGE_SLUG, 'wcgc_pro_analytics' );

		// ── Advanced Section ──
		add_settings_section(
			'wcgc_pro_advanced',
			__( 'Advanced', 'smart-gift-cards-for-woocommerce-pro' ),
			'__return_null',
			self::PAGE_SLUG
		);

		self::add_checkbox(
			'cleanup_on_uninstall',
			__( 'Cleanup on Uninstall', 'smart-gift-cards-for-woocommerce-pro' ),
			'wcgc_pro_advanced',
			__( 'Delete all Pro plugin data when uninstalled.', 'smart-gift-cards-for-woocommerce-pro' )
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
					__( 'An active license is required for Pro Settings. <a href="%s">Enter your license key</a> to enable these features.', 'smart-gift-cards-for-woocommerce-pro' ),
					[ 'a' => [ 'href' => [] ] ]
				),
				esc_url( admin_url( 'admin.php?page=wcgc-gift-cards&tab=license' ) )
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
			submit_button();
			?>
		</form>

		<script>
		jQuery(function($) {
			function wcgcProToggleFrequencyFields() {
				var freq = $('#wcgc-pro-report-frequency').val();
				$('#wcgc-pro-report-day-of-week').closest('tr').toggle(freq === 'weekly');
				$('#wcgc-pro-report-day-of-month').closest('tr').toggle(freq === 'monthly');
			}
			$('#wcgc-pro-report-frequency').on('change', wcgcProToggleFrequencyFields);
			wcgcProToggleFrequencyFields();
		});
		</script>
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
		<div class="wcgc-pro-license-section" style="margin-top:16px;max-width:700px;">
			<h2><?php esc_html_e( 'License Key', 'smart-gift-cards-for-woocommerce-pro' ); ?></h2>

			<div class="wcgc-pro-license-status" style="margin-bottom:16px;">
				<?php if ( 'valid' === $status ) : ?>
					<span class="wcgc-pro-license-badge wcgc-pro-license-badge--active"><?php esc_html_e( 'Active', 'smart-gift-cards-for-woocommerce-pro' ); ?></span>
					<?php if ( $opts['license_expires'] && 'lifetime' !== $opts['license_expires'] ) : ?>
						<span class="wcgc-pro-license-expires">
							<?php
							printf(
								/* translators: %s: license expiration date */
								esc_html__( 'Expires: %s', 'smart-gift-cards-for-woocommerce-pro' ),
								esc_html( date_i18n( get_option( 'date_format' ), strtotime( $opts['license_expires'] ) ) )
							);
							?>
						</span>
					<?php elseif ( 'lifetime' === $opts['license_expires'] ) : ?>
						<span class="wcgc-pro-license-expires"><?php esc_html_e( 'Lifetime', 'smart-gift-cards-for-woocommerce-pro' ); ?></span>
					<?php endif; ?>
				<?php elseif ( 'expired' === $status ) : ?>
					<span class="wcgc-pro-license-badge wcgc-pro-license-badge--expired"><?php esc_html_e( 'Expired', 'smart-gift-cards-for-woocommerce-pro' ); ?></span>
				<?php else : ?>
					<span class="wcgc-pro-license-badge wcgc-pro-license-badge--inactive"><?php esc_html_e( 'Inactive', 'smart-gift-cards-for-woocommerce-pro' ); ?></span>
				<?php endif; ?>
			</div>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="wcgc-pro-license-key"><?php esc_html_e( 'License Key', 'smart-gift-cards-for-woocommerce-pro' ); ?></label>
					</th>
					<td>
						<input type="text"
							   id="wcgc-pro-license-key"
							   class="regular-text"
							   value="<?php echo esc_attr( $key ? str_repeat( "\u{2022}", max( 0, strlen( $key ) - 4 ) ) . substr( $key, -4 ) : '' ); ?>"
							   <?php echo $key ? 'readonly' : ''; ?>
							   placeholder="<?php esc_attr_e( 'Enter your license key', 'smart-gift-cards-for-woocommerce-pro' ); ?>"
							   style="margin-bottom:8px;" />

						<?php if ( $key ) : ?>
							<button type="button" id="wcgc-pro-deactivate-license" class="button">
								<?php esc_html_e( 'Deactivate', 'smart-gift-cards-for-woocommerce-pro' ); ?>
							</button>
						<?php else : ?>
							<button type="button" id="wcgc-pro-activate-license" class="button button-primary">
								<?php esc_html_e( 'Activate', 'smart-gift-cards-for-woocommerce-pro' ); ?>
							</button>
						<?php endif; ?>

						<span id="wcgc-pro-license-message" class="wcgc-pro-license-message"></span>
					</td>
				</tr>
			</table>

			<?php if ( $opts['license_last_checked'] ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: date and time of last license check */
						esc_html__( 'Last checked: %s', 'smart-gift-cards-for-woocommerce-pro' ),
						esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $opts['license_last_checked'] ) ) )
					);
					?>
				</p>
			<?php endif; ?>
		</div>

		<script>
		jQuery(function($) {
			var nonce = '<?php echo esc_js( wp_create_nonce( 'wcgc_pro_license' ) ); ?>';

			$('#wcgc-pro-activate-license').on('click', function() {
				var key = $('#wcgc-pro-license-key').val().trim();
				if (!key) return;

				var $btn = $(this).prop('disabled', true).text('<?php echo esc_js( __( 'Activating...', 'smart-gift-cards-for-woocommerce-pro' ) ); ?>');
				var $msg = $('#wcgc-pro-license-message');
				$msg.text('');

				$.post(ajaxurl, {
					action: 'wcgc_pro_activate_license',
					nonce: nonce,
					license_key: key
				}).done(function(response) {
					if (response.success) {
						$msg.text(response.data.message).css('color', '#00a32a');
						setTimeout(function() { location.reload(); }, 1000);
					} else {
						$msg.text(response.data ? response.data.message : '<?php echo esc_js( __( 'Activation failed.', 'smart-gift-cards-for-woocommerce-pro' ) ); ?>').css('color', '#d63638');
						$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Activate', 'smart-gift-cards-for-woocommerce-pro' ) ); ?>');
					}
				}).fail(function() {
					$msg.text('<?php echo esc_js( __( 'Request failed. Please refresh and try again.', 'smart-gift-cards-for-woocommerce-pro' ) ); ?>').css('color', '#d63638');
					$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Activate', 'smart-gift-cards-for-woocommerce-pro' ) ); ?>');
				});
			});

			$('#wcgc-pro-deactivate-license').on('click', function() {
				if (!confirm('<?php echo esc_js( __( 'Are you sure you want to deactivate this license?', 'smart-gift-cards-for-woocommerce-pro' ) ); ?>')) return;

				var $btn = $(this).prop('disabled', true).text('<?php echo esc_js( __( 'Deactivating...', 'smart-gift-cards-for-woocommerce-pro' ) ); ?>');
				var $msg = $('#wcgc-pro-license-message');
				$msg.text('');

				$.post(ajaxurl, {
					action: 'wcgc_pro_deactivate_license',
					nonce: nonce
				}).done(function(response) {
					if (response.success) {
						$msg.text(response.data.message).css('color', '#00a32a');
						setTimeout(function() { location.reload(); }, 500);
					} else {
						$msg.text(response.data ? response.data.message : '<?php echo esc_js( __( 'Deactivation failed.', 'smart-gift-cards-for-woocommerce-pro' ) ); ?>').css('color', '#d63638');
						$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Deactivate', 'smart-gift-cards-for-woocommerce-pro' ) ); ?>');
					}
				}).fail(function() {
					$msg.text('<?php echo esc_js( __( 'Request failed. Please refresh and try again.', 'smart-gift-cards-for-woocommerce-pro' ) ); ?>').css('color', '#d63638');
					$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Deactivate', 'smart-gift-cards-for-woocommerce-pro' ) ); ?>');
				});
			});
		});
		</script>
		<?php
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
		add_settings_field( "wcgc_pro_{$key}", $label, function () use ( $key, $desc ) {
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
		add_settings_field( "wcgc_pro_{$key}", $label, function () use ( $key, $choices ) {
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
