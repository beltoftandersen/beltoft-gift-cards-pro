=== Beltoft Gift Cards for WooCommerce - Pro ===
Contributors: christian198521
Tags: woocommerce, gift cards, gift certificate, store credit, voucher
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 6.0
Stable tag: 1.5.12
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Premium add-on for Beltoft Gift Cards: PDF gift cards, scheduled delivery, gifting any product, store credit, bulk generation, and more.

== Description ==

**Beltoft Gift Cards for WooCommerce - Pro** adds extra features to the free [Beltoft Gift Cards for WooCommerce](https://wordpress.org/plugins/beltoft-gift-cards/) plugin. You need the free plugin and a Pro license key.

= Features =

**For your customers**

* **PDF gift cards** — The gift card arrives as a nicely designed PDF, ready to print or forward. Customers see a live preview while they choose the amount and message, and can download the PDF from My Account. Add your own logo, heading and colour.
* **Scheduled delivery** — Customers choose the date and hour the gift card should arrive, for example on a birthday morning.
* **Give as a gift** — Let customers buy any product as a present. The recipient gets a gift card for that product and redeems it when it suits them. Turn it on for single products or whole categories.

**For you**

* **Bulk generation** — Create up to 500 gift cards in one go, for example for a promotion or an event.
* **CSV import and export** — Download your gift cards as a spreadsheet, or upload a spreadsheet to create many cards at once.
* **BOGO promotions** — Reward buyers with a bonus, such as "Buy a $100 gift card, get $10 extra". Set start and end dates, usage limits and a minimum quantity.
* **Store credit on refund** — Optionally refund orders paid with a gift card as a new gift card for the customer, instead of putting the money back on the original card.
* **Sales overview** — Extra numbers on the Gift Cards dashboard: revenue, how much of it has been used, scheduled deliveries still waiting, store credit issued and BOGO bonuses given. View the last 7, 30 or 90 days, or all time.
* **Email reports** — Receive a gift card report as a spreadsheet by email every day, week or month.

= Requirements =

* WordPress 5.8+
* WooCommerce 6.0+
* PHP 7.4+
* [Beltoft Gift Cards for WooCommerce](https://wordpress.org/plugins/beltoft-gift-cards/) (free) 1.6.0+
* PHP extensions mbstring, dom and gd (needed for PDF gift cards)

== Installation ==

1. Install and activate the free "Beltoft Gift Cards for WooCommerce" plugin.
2. Upload the `beltoft-gift-cards-pro` folder to `/wp-content/plugins/` and activate it.
3. Go to **WooCommerce > Gift Cards > License** and enter your license key.
4. Turn features on or off under **WooCommerce > Gift Cards > Pro Settings**.

== Frequently Asked Questions ==

= Do I need the free plugin? =
Yes. The free Beltoft Gift Cards for WooCommerce (1.6.0 or newer) must be installed and active. Pro builds on top of it.

= Where do I get a license key? =
You receive it when you buy Pro. Enter it under WooCommerce > Gift Cards > License.

= What happens if my license expires? =
The Pro features switch off, and your gift cards keep working through the free plugin. Scheduled gift cards that customers have already paid for are still delivered on time.

= How does scheduled delivery work? =
On the gift card product page the customer picks a date and an hour. The gift card email is sent at that time.

= How do PDF gift cards work? =
The recipient gets a short email with the gift card attached as a PDF. The buyer can also download it from My Account. You can change the logo, heading and colour under Pro Settings.

= How does "Give as a gift" work? =
Tick "Can be given as a gift" on a product, or choose whole categories under Pro Settings. The customer buys the product as a gift, and the recipient gets a gift card that can only be used for that product.

= What should my CSV file look like for import? =
One gift card per row. Only an `amount` column is required. You can also add `recipient_name`, `recipient_email`, `message`, `expiry_days` or `expires_at`, and `source`. Cards with a recipient email are sent to that person. An exported file can be imported again.

= How does store credit on refund work? =
Turn on "Enable Store Credit" and "Auto-Create on Refund" under Pro Settings. When an order paid with a gift card is refunded, the customer gets a new gift card for that amount instead of the money going back on the original card. It is off by default.

== For Developers ==

Filters: `bgcw_pro_pdf_designs` (add or change PDF designs), `bgcw_pro_pdf_redeem_text` (change the redeem text on the PDF), `bgcw_pro_product_is_giftable` (decide in code which products can be given as a gift).

== Changelog ==

= 1.5.12 =
* Improved: Readme rewritten in plain language. It now explains that store credit on refund is off by default, that customers pick the delivery hour as well as the date, and what happens when the license expires.

= 1.5.11 =
* Fixed: Scheduled deliveries are sent even if the license lapses before the slot; an order whose delivery date is moved later is re-booked instead of being skipped; a failed send is retried rather than silently marked as sent; booked deliveries are cancelled on deactivation and uninstall.

= 1.5.10 =
* Changed: Scheduled gift cards are sent at their exact delivery time through WooCommerce's Action Scheduler (WooCommerce → Status → Scheduled Actions). The hourly check, which could delay delivery by up to 59 minutes, is removed.

= 1.5.9 =
* Fixed: "Download PDF" in My Account now uses a front-end link. On sites that protect /wp-admin/ with a login proxy or SSO, customers were sent to that login instead of receiving the PDF.

= 1.5.8 =
* Fixed: Two-line headings on the PDF card (e.g. the Portuguese default) were cut off. Already generated PDFs are refreshed the next time they are sent or downloaded.

= 1.5.7 =
* Fixed: A scheduled delivery whose order slot was moved to a later time is kept pending with the new time, instead of being marked as sent early.

= 1.5.6 =
* Improved: Complete European Portuguese (pt_PT) translation, including the license tab, card texts and preview.

= 1.5.5 =
* Fixed: License activation did not persist. The activation reported success, but the settings sanitizer discarded the stored license on save, so the "enter your license key" notice stayed and Pro features remained locked.
* Note: 1.5.4 moved the license script to a file; the cause described there was incomplete, this release fixes the actual problem.

= 1.5.4 =
* Fixed: The license Activate and Deactivate buttons did nothing on WordPress 7.1.2. The license script is now a regular asset file.

= 1.5.3 =
* Improved: Update notices appear as soon as a new version is available.

= 1.5.2 =
* Minor fixes and improvements.

= 1.5.1 =
* Fixed: Gift amount uses the product's stored price and the hidden carrier product is non-taxable, so the buyer pays exactly the product price and the card zeroes the product at redemption.
* Fixed: Clearing all giftable categories now saves; the carrier product recovers if set to draft; recipient field visibility filters match the free plugin's validation.
* Fixed: Redemption instructions printed on the card again; preview and PDF truncate the message at the same length; gift button label works with PDF cards disabled.

= 1.5.0 =
* Requires the free plugin 1.6.0 or newer.
* Added: "Give as a gift" on any product. Enable it per product or per category; the buyer pays the product price and the recipient receives a gift card locked to that product, redeemable with one click from the email. Ideal for workshops and experiences, where the recipient picks the date.
* Changed: New card layout based on a printed voucher: landscape, white, one brand color, Onest typeface, with From/To, an editable text and a box holding the amount (or the gifted product) and the code.
* Added: Editable card texts in Pro Settings ({store} and {product} placeholders), separate heading and text for product gift cards.

= 1.4.3 =
* Changed: One card design (Classic) instead of three. The design picker is removed; heading, main color and logo remain adjustable in Pro Settings.

= 1.4.2 =
* Fixed: Portuguese translations for the PDF card and preview strings.

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
