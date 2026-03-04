=== Beltoft Gift Cards for WooCommerce - Pro ===
Contributors: chimkinsit
Tags: woocommerce, gift cards, gift certificate, store credit, voucher
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
WC requires at least: 6.0
WC tested up to: 9.6
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Premium add-on for Beltoft Gift Cards for WooCommerce. Adds scheduled delivery, email themes, store credit, bulk generation, and more.

== Description ==

**Beltoft Gift Cards for WooCommerce - Pro** is a premium add-on that extends the free [Beltoft Gift Cards for WooCommerce](https://wordpress.org/plugins/beltoft-gift-cards-for-woocommerce/) plugin with advanced features for gift card management.

= Features =

* **Scheduled Delivery** — Let customers choose a future delivery date for their gift card. A date picker appears on the product page, and a cron job sends the email at the scheduled time.
* **Email Themes** — Five built-in email themes (Classic, Birthday, Celebration, Thank You, Holiday) with visual previews. Customers pick a theme on the product page; the gift card email uses the matching design.
* **Store Credit on Refund** — When an order paid with a gift card is refunded, automatically create a store credit gift card for the customer instead of restoring the original card balance.
* **Bulk Generation** — Generate up to 500 gift cards at once from the admin panel. Set amount, prefix, expiry, and optional recipient details.
* **CSV Import / Export** — Export all gift cards to CSV with status filtering. Import gift cards from CSV with validation and error reporting.
* **BOGO Promotions** — Create "Buy One, Get One" rules (e.g., "Buy a $100 gift card, get a $10 bonus"). Rules support date ranges, usage limits, and minimum quantities.
* **Analytics Dashboard** — Pro stats on the Gift Cards dashboard: revenue, outstanding liability, redemption rate, pending deliveries, store credits issued, and BOGO bonuses given. Date range filtering (7d, 30d, 90d, all time).
* **Scheduled Reports** — Receive daily, weekly, or monthly CSV reports by email summarizing all gift card activity.

= Requirements =

* WordPress 5.8+
* WooCommerce 6.0+
* PHP 7.4+
* [Beltoft Gift Cards for WooCommerce](https://wordpress.org/plugins/beltoft-gift-cards-for-woocommerce/) (free) 1.2.0+

== Installation ==

1. Install and activate the free "Beltoft Gift Cards for WooCommerce" plugin.
2. Upload the `beltoft-gift-cards-for-woocommerce-pro` folder to `/wp-content/plugins/`.
3. Activate the plugin through the Plugins menu.
4. Go to WooCommerce > Gift Cards > License and enter your license key.
5. Configure features under the "Pro Settings" tab.

== Frequently Asked Questions ==

= Do I need the free plugin? =

Yes. Beltoft Gift Cards for WooCommerce (free) must be installed and activated. The Pro add-on extends the free plugin's functionality.

= How do I get a license key? =

License keys are distributed when you purchase the Pro add-on. Enter your key under WooCommerce > Gift Cards > License.

= Can customers choose a delivery date? =

Yes. When Scheduled Delivery is enabled, a date picker appears on the gift card product page. The email is sent at the chosen date.

= How do email themes work? =

When Email Themes is enabled, a visual theme picker appears on the product page. The customer selects a theme, and the gift card email uses the matching design with themed colors and header image.

= What happens to store credit on refund? =

When Store Credit is enabled and an order paid with a gift card is refunded, the refund amount is issued as a new store credit gift card assigned to the customer, instead of restoring the original card balance.

== Changelog ==

= 1.0.0 =
* Initial release.
* Scheduled delivery with date picker and cron-based email sending.
* Five built-in email themes with visual product page picker.
* Store credit on refund for gift card payments.
* Bulk gift card generation (up to 500 at once).
* CSV import and export with status filtering.
* BOGO promotion rules with date ranges and usage limits.
* Analytics dashboard with Pro stat cards and date range filtering.
* Scheduled CSV reports (daily/weekly/monthly) via email.
* Self-hosted license system with WP-CLI management.
