# PDF Gift Card — Design Spec

**Date:** 2026-09-19
**Plugin:** Beltoft Gift Cards Pro (target version 1.4.0)
**Depends on:** Beltoft Gift Cards (free) 1.5.1 for the `bgcw_gift_card_deleted` and `bgcw_my_account_card_actions` actions

## Goal

Deliver the gift card as a designed PDF attached to the delivery email, instead of a themed HTML email. The buyer sees a live preview of the actual card on the product page while choosing amount, design, recipient name, and message. The buyer can also download the PDF from My Account to print and hand over.

## Scope

In:
- Three card designs: Classic, Birthday, Celebration. New visual designs, replacing the current email themes.
- Live preview on the gift-card product page.
- PDF generation with dompdf from the same HTML template used for the preview.
- PDF attached to the delivery email; email body becomes a short neutral message.
- "Download PDF" on My Account → Gift Cards.
- Admin settings: on/off, logo, per-design color and heading, sample PDF per design.
- Regeneration when design settings change; cleanup when a card is deleted.
- WP-CLI style integration tests in Pro.

Out:
- QR code or barcode on the card.
- Buyer receiving the PDF by email.
- PDF on the order confirmation email.
- Custom uploaded background images.
- Thank You and Holiday themes (removed).

## Card content

- Store logo (admin-uploaded via media library; falls back to the theme's custom logo, then to store name as text) and store name.
- Design heading (per design, admin-overridable).
- Amount, formatted with WooCommerce currency settings.
- Gift card code.
- "To" (recipient name) and "From" (sender name).
- Personal message (optional; block hidden when empty).
- Footer strip: expiry date (or "No expiry"), one redemption sentence, shop URL.
- Page: A5 landscape, single page, print-friendly (no full-bleed dark backgrounds on the footer).

## Architecture

New module `src/Pdf/` in Pro. Static classes with `init()`, wired from `Plugin::init()` behind the license check, following the existing Pro pattern.

### `Pdf\CardRenderer`
- `render( string $design, array $data, array $opts = [] ): string` returns the card HTML.
- `$data` keys: `amount`, `currency`, `code`, `recipient_name`, `sender_name`, `message`, `expires_at`.
- `$opts`: `mode` = `preview` | `pdf`. Preview mode adds `data-bgcw-field` attributes on text nodes for JS and uses `<link>`-free inline `<style>`. PDF mode inlines the same CSS plus `@page` rules and `@font-face` for the bundled fonts.
- `placeholders(): array` returns the dummy data set used by preview and sample PDFs (masked code `GIFT-XXXX-XXXX`).
- Templates: `templates/pdf/_card.php` (structure) + `templates/pdf/card.css` (all styling, dompdf-safe subset: tables, absolute positioning, no flexbox/grid). Per-design differences are CSS variables written as a PHP array in `Pdf\Designs::get()` (color, color_light, bg, heading, decorative accent class), injected as inline values because dompdf does not support CSS custom properties.
- Fonts: two bundled open-licensed TTFs in `assets/fonts/` (display face + text face; final choice during frontend-design phase). Loaded by the browser via `@font-face` and by dompdf via `@font-face` with absolute file paths. Licenses included in `assets/fonts/LICENSE-*.txt`.

### `Pdf\Designs`
- `get(): array` returns the 3 designs with defaults merged with admin overrides (`pdf_color_{slug}`, `pdf_heading_{slug}`).
- `normalize( string $slug ): string` maps unknown or removed slugs (`thank-you`, `holiday`, empty) to `classic`.
- `version(): int` returns the design version counter (see Regeneration).

### `Pdf\PdfGenerator`
- Bundles dompdf under `lib/dompdf/` (committed vendor copy, no Composer at runtime; autoloader required from the module).
- `generate( object $gift_card ): string|WP_Error` renders HTML via `CardRenderer` in `pdf` mode, produces the PDF, writes it to `wp-content/uploads/bgcw-pdf/{gift_card_id}-{16 random hex}.pdf`, records it in the `bgcw_pro_pdf` table, and returns the absolute path.
- `get_or_generate( object $gift_card ): string|WP_Error` returns the stored file if present, readable, and `design_version` matches the current version; otherwise regenerates (deleting the stale file).
- `sample( string $design ): string|WP_Error` writes a sample PDF from placeholders to a temp file for admin preview; not recorded in the table.
- `delete_for_card( int $gift_card_id )` removes file and row.
- Directory setup on first use: create dir, write `index.php` and `.htaccess` (`Deny from all` / `Require all denied`). Nginx sites rely on the random filename component; the download endpoint streams the file, the URL is never exposed.
- dompdf options: `isRemoteEnabled` false (logo is copied to a local path via `get_attached_file`), `chroot` set to the plugin and uploads directories, `defaultPaperSize` A5 landscape, `dpi` 96.

### `Pdf\EmailAttachment`
- Hooks `woocommerce_email_attachments` (priority 10, 4 args). When `$email_id === 'BGCW_Gift_Card_Delivery'` and PDF is enabled, calls `PdfGenerator::get_or_generate( $email->gift_card )` and appends the path on success.
- Hooks `bgcw_email_template_html` at priority 20 (after `ThemeManager`, which is removed anyway) to swap to `templates/emails/pdf-notice.php`: short neutral email with sender name, optional message, "Your gift card is attached as a PDF", and the Shop Now link. Plain-text variant added alongside.
- On generation failure: attachment skipped, template not swapped (recipient still gets code inline), error logged via `wc_get_logger()` with source `bgcw-pro-pdf`.
- Scheduled delivery is unaffected: it re-fires `bgcw_gift_card_created`, which triggers the same email.

### `Pdf\Download`
- My Account: the free plugin's `Frontend\MyAccount` currently has no hooks. Free 1.5.1 adds `do_action( 'bgcw_my_account_card_actions', $card )` inside each card row of the Gift Cards page; Pro hooks it to output the "Download PDF" button.
- Endpoint: `admin-post.php?action=bgcw_pro_pdf_download&card={id}&_wpnonce=...` (logged-in users only). Access rule: card `customer_id` equals current user, or card `recipient_email` equals current user's email (case-insensitive). Streams `application/pdf` with `Content-Disposition: attachment; filename="gift-card-{code}.pdf"`.
- Admin sample: `admin-post.php?action=bgcw_pro_pdf_sample&design={slug}` requires `manage_woocommerce` and nonce.
- Product-page preview needs no endpoint: the card HTML is rendered inline at page load.

### Product page live preview (`Pdf\ProductPreview`, replaces `EmailThemes\ProductFields`)
- Hooks `bgcw_product_form_after_recipient_fields`: outputs the preview card (Classic, placeholders) and the 3 design swatches (radio `bgcw_design_theme`, same field name as today so cart/order meta handling is unchanged).
- Keeps the existing `bgcw_add_to_cart_data`, `woocommerce_get_item_data`, and `woocommerce_checkout_create_order_line_item` handling for `_bgcw_design_theme`.
- `assets/js/frontend.js`: on `input`/`change` of `#bgcw_amount`, `#bgcw_recipient_name`, `#bgcw_message`, and the design radios, update `[data-bgcw-field]` nodes and set `data-design` on the card root. Amount formatted client-side with localized params: currency symbol, position, decimals, thousand and decimal separators. Empty amount shows a dash. Sender name defaults to "You" placeholder text until checkout (sender name comes from billing at order time).
- Preview scales to container width via a fixed aspect-ratio wrapper. Footer strip is shown in preview too.

### Settings (existing Pro Settings tab)
Options added to `Support\Options::defaults()`:
- `pdf_enabled` = `'1'`
- `pdf_logo_id` = `''` (attachment ID)
- `pdf_color_classic|birthday|celebration` = `''`
- `pdf_heading_classic|birthday|celebration` = `''`
- `pdf_design_version` = `'1'` (internal; incremented by the sanitize callback when any `pdf_*` design option changes)
- Removed: `email_themes`, `default_theme`, `theme_heading_*`, `theme_color_*` (ignored if present in stored options; not deleted).

UI: on/off checkbox, logo media picker, per-design color and heading fields, "Download sample PDF" link per design.

### Data
New table `{prefix}bgcw_pro_pdf` via Pro `Installer` (bump Pro DB version):

| column | type |
|---|---|
| gift_card_id | bigint unsigned, primary key |
| file | varchar(255) |
| design | varchar(32) |
| design_version | int unsigned |
| generated_at | datetime |

### Regeneration
`get_or_generate` compares the row's `design_version` with `Options::get('pdf_design_version')`. Mismatch or missing file triggers regeneration. Nothing is regenerated eagerly.

### Cleanup
- Free plugin 1.5.1 adds `do_action( 'bgcw_gift_card_deleted', $id )` in `Repository::delete()`.
- Pro hooks it to `PdfGenerator::delete_for_card`.
- Pro uninstall (when free's `cleanup_on_uninstall` is respected by Pro's uninstall.php) drops the table and removes the `bgcw-pdf` directory.

### Design selection for non-order cards
Cards created manually, in bulk, or as store credit have no order item and therefore no `_bgcw_design_theme`. They use Classic.

## Removals
- `templates/emails/themes/*` and `EmailThemes\ThemeManager::swap_template` path. `ThemeManager` is deleted; `Designs` replaces `get_available_themes`.
- Pro readme feature "Email Themes" is replaced by "PDF Gift Cards" with the 3 designs.

## Error handling summary
| Failure | Behaviour |
|---|---|
| dompdf throws / cannot write | Email sends without PDF, themed swap skipped, logged |
| Logo attachment missing | Card falls back to store name text |
| Download by non-owner | 403 with WooCommerce notice-style message |
| Stored file missing | Regenerated on demand |
| Removed theme slug on old orders | Mapped to Classic |

## Testing
`tests/run.sh` in Pro, same WP-CLI eval-file style as the free plugin:
- Renderer: each design returns HTML containing heading, amount, code, names, message; empty message hides the block; unknown slug normalizes to Classic.
- Generator: produces a file starting with `%PDF`, row recorded; second call returns cached path; version bump regenerates and removes the old file.
- Attachment: filter appends the path for the gift card email ID only; on forced failure returns the original attachments.
- Download: owner by customer_id allowed, owner by recipient email allowed, stranger denied.
- Cleanup: `bgcw_gift_card_deleted` removes file and row.
Manual: live preview on product page at desktop and 375px width; print one sample PDF per design.

## Release notes (draft)
Pro 1.4.0: PDF gift cards with live preview and 3 new designs; email themes replaced; Download PDF in My Account. Requires free plugin 1.5.1.
Free 1.5.1: adds `bgcw_gift_card_deleted` and `bgcw_my_account_card_actions` actions.
