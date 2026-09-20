=== Beltoft Gift Cards for WooCommerce - Pro ===
Contributors: christian198521
Tags: woocommerce, gift cards, gift certificate, store credit, voucher
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 6.0
WC tested up to: 9.6
Stable tag: 1.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Premium add-on for Beltoft Gift Cards: PDF gift cards with live preview, scheduled delivery, store credit, bulk generation, and more.

== Description ==

**Beltoft Gift Cards for WooCommerce - Pro** is a premium add-on that extends the free [Beltoft Gift Cards for WooCommerce](https://wordpress.org/plugins/beltoft-gift-cards/) plugin with advanced features for gift card management.

= Features =

* **Scheduled Delivery** — Let customers choose a future delivery date for their gift card. A date picker appears on the product page, and a cron job sends the email at the scheduled time.
* **PDF Gift Cards** — The gift card is delivered as a designed PDF attached to the email, ready to print. Three designs (Classic, Birthday, Celebration) with your logo, custom heading and color. Customers see a live preview of the card on the product page while they choose the amount, design and message, and can download the PDF from My Account.
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
* [Beltoft Gift Cards for WooCommerce](https://wordpress.org/plugins/beltoft-gift-cards/) (free) 1.5.1+
* PHP extensions mbstring, dom and gd (needed for PDF generation)

== Installation ==

1. Install and activate the free "Beltoft Gift Cards for WooCommerce" plugin.
2. Upload the `beltoft-gift-cards-pro` folder to `/wp-content/plugins/`.
3. Activate the plugin through the Plugins menu.
4. Go to WooCommerce > Gift Cards > License and enter your license key.
5. Configure features under the "Pro Settings" tab.

== Frequently Asked Questions ==

= Do I need the free plugin? =

Yes. Beltoft Gift Cards for WooCommerce (free) version 1.5.1 or newer must be installed and activated. The Pro add-on extends the free plugin's functionality.

= How do I get a license key? =

License keys are distributed when you purchase the Pro add-on. Enter your key under WooCommerce > Gift Cards > License.

= Can customers choose a delivery date? =

Yes. When Scheduled Delivery is enabled, a date picker appears on the gift card product page. The email is sent at the chosen date.

= How do PDF gift cards work? =

When PDF Gift Cards is enabled, the product page shows a live preview of the card and three designs to choose from. After payment the recipient receives a short email with the card attached as a PDF, and the buyer can download the PDF from My Account. Change the logo, heading and main color under Pro Settings.

= What happens to store credit on refund? =

When Store Credit is enabled and an order paid with a gift card is refunded, the refund amount is issued as a new store credit gift card assigned to the customer, instead of restoring the original card balance.

== Changelog ==

= 1.4.1 =
* Changed: The gift card PDF is now A5 portrait with a redesigned layout.
* Changed: The product page shows the design swatches with a "Preview your card" link that opens the live preview in a lightbox, instead of an always-visible card.

= 1.4.0 =
* Requires the free plugin 1.5.1 or newer.
* Added: PDF gift cards. The card is generated as an A5 PDF and attached to the delivery email; the email itself is now a short notice.
* Added: Live preview of the card on the product page, updating with amount, design, recipient name and message.
* Added: Three new card designs (Classic, Birthday, Celebration) with logo, heading and color settings and sample downloads.
* Added: "Download PDF" on the My Account gift cards page for buyers and recipients.
* Changed: Email themes are replaced by the PDF designs. Orders that chose Thank You or Holiday use Classic.

= 1.3.0 =
* Requires the free plugin 1.5.0 or newer.
* Added: Every Pro-created gift card now records its source. Store credit from refunds is paid_offline (paid), BOGO bonus cards are promotion (free).
* Added: Source dropdown on Bulk Generate (Paid offline / Promotion / Compensation) and an optional "source" column on CSV import; CSV export includes the source.
* Changed: On upgrade, existing store-credit cards are reclassified as paid_offline. Existing BOGO bonus cards were classified as "order" by the free plugin's backfill; correct them via the free plugin's REST API (PATCH /wc-bgcw/v1/gift-cards/{id} with source=promotion) if needed.

= 1.2.1 =
- Tested with WordPress 7.0.

= 1.2.0 =
* Replaced local license validation with remote license server (beltoft.net).
* Added auto-updater with deferred signed download URLs.
* License reactivates on plugin reactivation, frees slot on deactivation.
* Added `Requires Plugins` header for WooCommerce and free plugin dependency.
* Updated license tab UI with domain mismatch, invalid key, and update available notices.
* Removed WP-CLI license commands (now managed via license server).

= 1.1.0 =
* Renamed plugin slug and folder to `beltoft-gift-cards-pro`.
* Renamed text domain to `beltoft-gift-cards-pro`.
* Replaced inline license tab script with `wp_add_inline_script()`.
* Moved inline styles to external CSS.
* Replaced `unlink()` with `wp_delete_file()` in report cleanup.
* Updated author to beltoft.net.

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
* License system with activation and validation.
