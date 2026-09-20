# Beltoft Gift Cards for WooCommerce - Pro

Premium add-on for [Beltoft Gift Cards for WooCommerce](https://wordpress.org/plugins/beltoft-gift-cards/) — scheduled delivery, PDF gift cards, store credit, bulk generation, BOGO promotions, and analytics.

- Stable version: 1.5.0
- Requires: WordPress 5.8+, PHP 7.4+, WooCommerce 6.0+ (tested up to WordPress 7.1)
- Requires: Beltoft Gift Cards for WooCommerce (free) 1.6.0+
- Author: beltoft.net
- License: GPLv2 or later

## Overview

This plugin extends the free Beltoft Gift Cards for WooCommerce with advanced features for gift card management: scheduled delivery, PDF gift cards, store credit on refund, bulk generation, BOGO promotions, and analytics.

A valid license key is required to activate Pro features.

## Features

- **Scheduled Delivery** — Let customers choose a future delivery date for their gift card. A date picker appears on the product page, and a cron job sends the email at the scheduled time.
- **PDF Gift Cards** — The gift card is delivered as a designed PDF attached to the email, ready to print. Add your logo, heading and color. Customers can preview the card on the product page while they choose the amount and message, and download the PDF from My Account.
- **Give as a Gift** — Sell any product as a gift. The buyer pays now; the recipient gets a gift card locked to that product and redeems it with one click, choosing the date or options themselves. Enable per product or per category.
- **Store Credit on Refund** — When an order paid with a gift card is refunded, automatically create a store credit gift card for the customer instead of restoring the original card balance.
- **Bulk Generation** — Generate up to 500 gift cards at once from the admin panel. Set amount, prefix, expiry, and optional recipient details.
- **CSV Import / Export** — Export all gift cards to CSV with status filtering. Import gift cards from CSV with validation and error reporting.
- **BOGO Promotions** — Create "Buy One, Get One" rules (e.g., "Buy a $100 gift card, get a $10 bonus"). Rules support date ranges, usage limits, and minimum quantities.
- **Analytics Dashboard** — Pro stats on the Gift Cards dashboard: revenue, outstanding liability, redemption rate, pending deliveries, store credits issued, and BOGO bonuses given. Date range filtering (7d, 30d, 90d, all time).
- **Scheduled Reports** — Receive daily, weekly, or monthly CSV reports by email summarizing all gift card activity.

## Requirements

- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.4+
- [Beltoft Gift Cards for WooCommerce](https://wordpress.org/plugins/beltoft-gift-cards/) (free) 1.6.0+
- PHP extensions mbstring, dom and gd (needed for PDF generation)

## Installation

1. Install and activate the free "Beltoft Gift Cards for WooCommerce" plugin.
2. Upload the `beltoft-gift-cards-pro` folder to `/wp-content/plugins/`.
3. Activate the plugin through the Plugins menu.
4. Go to **WooCommerce > Gift Cards > License** and enter your license key.
5. Configure features under the **Pro Settings** tab.

## Changelog

### 1.5.0

- Requires the free plugin 1.6.0 or newer.
- Added: "Give as a gift" on any product. Enable it per product or per category; the buyer pays the product price and the recipient receives a gift card locked to that product, redeemable with one click from the email. Ideal for workshops and experiences, where the recipient picks the date.
- Changed: New card layout based on a printed voucher: landscape, white, one brand color, Onest typeface, with From/To, an editable text and a box holding the amount (or the gifted product) and the code.
- Added: Editable card texts in Pro Settings ({store} and {product} placeholders), separate heading and text for product gift cards.

### 1.4.3

- Changed: One card design (Classic) instead of three. The design picker is removed; heading, main color and logo remain adjustable in Pro Settings.

### 1.4.2

- Fixed: Portuguese translations for the PDF card and preview strings.

### 1.4.1

- Changed: The gift card PDF is now A5 portrait with a redesigned layout.
- Changed: The product page shows the design swatches with a "Preview your card" link that opens the live preview in a lightbox, instead of an always-visible card.

### 1.4.0

- Requires the free plugin 1.5.1 or newer.
- Added: PDF gift cards. The card is generated as an A5 PDF and attached to the delivery email; the email itself is now a short notice.
- Added: Live preview of the card on the product page, updating with amount, design, recipient name and message.
- Added: Three new card designs (Classic, Birthday, Celebration) with logo, heading and color settings and sample downloads.
- Added: "Download PDF" on the My Account gift cards page for buyers and recipients.
- Changed: Email themes are replaced by the PDF designs. Orders that chose Thank You or Holiday use Classic.

### 1.3.0

- Requires the free plugin 1.5.0 or newer.
- Added: Every Pro-created gift card now records its source. Store credit from refunds is `paid_offline` (paid), BOGO bonus cards are `promotion` (free).
- Added: Source dropdown on Bulk Generate (Paid offline / Promotion / Compensation) and an optional `source` column on CSV import; CSV export includes the source.
- Changed: On upgrade, existing store-credit cards are reclassified as `paid_offline`. Existing BOGO bonus cards were classified as `order` by the free plugin's backfill; correct them via the free plugin's REST API (`PATCH /wc-bgcw/v1/gift-cards/{id}` with `source=promotion`) if needed.

### 1.2.1

- Tested with WordPress 7.0.

### 1.2.0

- Replaced local license validation with remote license server (beltoft.net).
- Added auto-updater with deferred signed download URLs.
- License reactivates on plugin reactivation, frees slot on deactivation.
- Added `Requires Plugins` header for WooCommerce and free plugin dependency.
- Updated license tab UI with domain mismatch, invalid key, and update available notices.
- Removed WP-CLI license commands (now managed via license server).

### 1.1.0

- Renamed plugin slug and folder to `beltoft-gift-cards-pro`.
- Renamed text domain to `beltoft-gift-cards-pro`.
- Replaced inline license tab script with `wp_add_inline_script()`.
- Moved inline styles to external CSS.
- Replaced `unlink()` with `wp_delete_file()` in report cleanup.
- Updated author to beltoft.net.

### 1.0.0
- Initial release.
- Scheduled delivery with date picker and cron-based email sending.
- Five built-in email themes with visual product page picker.
- Store credit on refund for gift card payments.
- Bulk gift card generation (up to 500 at once).
- CSV import and export with status filtering.
- BOGO promotion rules with date ranges and usage limits.
- Analytics dashboard with Pro stat cards and date range filtering.
- Scheduled CSV reports (daily/weekly/monthly) via email.
- License system with activation and validation.
