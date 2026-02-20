# Smart Gift Cards for WooCommerce - Pro

Premium add-on for [Smart Gift Cards for WooCommerce](https://wordpress.org/plugins/smart-gift-cards-for-woocommerce/). Scheduled delivery, email themes, store credit, bulk generation, BOGO promotions, and analytics.

## Features

- **Scheduled Delivery** — Let customers choose a future delivery date for their gift card. A date picker appears on the product page, and a cron job sends the email at the scheduled time.
- **Email Themes** — Five built-in email themes (Classic, Birthday, Celebration, Thank You, Holiday) with visual previews. Customers pick a theme on the product page; the gift card email uses the matching design.
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
- [Smart Gift Cards for WooCommerce](https://wordpress.org/plugins/smart-gift-cards-for-woocommerce/) (free) 1.2.0+

## Installation

1. Install and activate the free "Smart Gift Cards for WooCommerce" plugin.
2. Upload the `smart-gift-cards-for-woocommerce-pro` folder to `/wp-content/plugins/`.
3. Activate the plugin through the Plugins menu.
4. Go to **WooCommerce > Gift Cards > License** and enter your license key.
5. Configure features under the **Pro Settings** tab.

## WP-CLI Commands

```bash
# Generate a new license key
wp wcgc-pro license:generate --expires=2027-12-31

# List all license keys
wp wcgc-pro license:list

# Revoke a license key
wp wcgc-pro license:revoke --key=XXXX-XXXX-XXXX-XXXX

# Check current license status
wp wcgc-pro license:status
```

## Changelog

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
- Self-hosted license system with WP-CLI management.
