# PDF Gift Card Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver gift cards as a designed PDF (3 designs) attached to the delivery email, with a live card preview on the product page and a Download PDF button in My Account.

**Architecture:** One HTML card template rendered twice: in the browser (live preview, JS updates text nodes) and server-side to PDF via bundled dompdf. New Pro module `src/Pdf/` (Designs, CardRenderer, PdfGenerator, EmailAttachment, Download, ProductPreview). Free plugin gains two actions.

**Tech Stack:** PHP 7.4+ (WordPress plugin), dompdf 3.1.6 (bundled in `lib/dompdf`), vanilla JS, WP-CLI eval-file tests in the `wp_app` container.

**Spec:** `docs/superpowers/specs/2026-09-19-pdf-gift-card-design.md`

## Global Constraints

- Free plugin release 1.5.1 must ship before Pro 1.4.0; Pro header `Requires` text and `readme.txt` state free 1.5.1+.
- Text domain `beltoft-gift-cards-pro`; every user-facing string translatable.
- No inline `<script>`; CSS in `assets/css`, JS in `assets/js`. Preview card CSS may be an inline `<style>` block only because dompdf needs the same CSS embedded in the document.
- CSS in `templates/pdf/card.css` must be dompdf-safe: tables, absolute/relative positioning, no flexbox, no grid, no CSS variables.
- Every new directory gets an `index.php` guard (`<?php // Silence is golden.`).
- Plugin Check for both plugins must show no new findings versus baseline. PHP lint all files.
- No `Co-Authored-By` trailer in commits (project rule).
- Design slug field name stays `bgcw_design_theme`, order item meta stays `_bgcw_design_theme`.
- Designs: `classic`, `birthday`, `celebration`. Anything else normalizes to `classic`.

---

### Task 1: Free plugin 1.5.1 — two extension actions

**Files:**
- Modify: `../beltoft-gift-cards/src/GiftCard/Repository.php` (`delete()`)
- Modify: `../beltoft-gift-cards/src/Frontend/MyAccount.php` (card row, actions cell)
- Modify: `../beltoft-gift-cards/beltoft-gift-cards.php`, `readme.txt`, `README.md`, `CLAUDE.md` (hooks list)
- Test: `../beltoft-gift-cards/tests/test-hooks-151.php`

**Interfaces:**
- Produces: `do_action( 'bgcw_gift_card_deleted', int $id )` fired after a successful row delete. `do_action( 'bgcw_my_account_card_actions', object $gc )` fired inside each card row next to the Show code button.

- [ ] Write `tests/test-hooks-151.php`: create a manual card (`source => 'compensation'`, `send_email => false`), register a listener on `bgcw_gift_card_deleted` that records the id, call `Repository::delete( $id )`, assert listener received `$id`. Render My Account HTML for a fake user via `MyAccount::render()` output buffering is heavy; instead assert with `has_action`-free approach: register listener on `bgcw_my_account_card_actions` that echoes `<!--hook-->`, set current user to admin, capture `ob_start(); MyAccount::render(); ob_get_clean()`, assert string contains `<!--hook-->` when the admin owns a card (create one with `customer_id` = admin).
- [ ] Run `tests/run.sh`, expect the new file FAIL.
- [ ] Implement: in `Repository::delete()` capture result, `if ( $ok ) { do_action( 'bgcw_gift_card_deleted', (int) $id ); }`. In `MyAccount.php` after the transactions toggle button: `do_action( 'bgcw_my_account_card_actions', $gc );` with a docblock.
- [ ] Run `tests/run.sh`, expect PASS. Plugin Check + lint.
- [ ] Bump free to 1.5.1 (header, `BGCW_VERSION`, readme stable tag + changelog, README.md), add hooks to CLAUDE.md hooks list. Commit `Release 1.5.1: add gift card deleted and My Account actions hooks`, tag `1.5.1`, push, `gh release create`, wait for WP.org deploy success.

### Task 2: Bundle dompdf and fonts

**Files:**
- Create: `lib/dompdf/` (contents of dompdf-3.1.6.zip: `src/`, `lib/`, `vendor/`, `autoload.inc.php`, `LICENSE.LGPL`), `lib/index.php`
- Create: `assets/fonts/` with two OFL fonts as TTF + `LICENSE-*.txt` + `index.php`
- Create: `src/Pdf/index.php`, `src/Pdf/Loader.php`

**Interfaces:**
- Produces: `BgcwPro\Pdf\Loader::load(): bool` requires dompdf autoloader once; returns false if missing.

- [ ] Download `https://github.com/dompdf/dompdf/releases/download/v3.1.6/dompdf-3.1.6.zip` into scratchpad, unzip to `lib/dompdf`. Remove `lib/dompdf/lib/fonts/*` except the DejaVu fonts and `installed-fonts.json` (keep size down).
- [ ] Fonts: download Playfair Display (display) and Inter (text) TTF from Google Fonts GitHub sources; store `assets/fonts/PlayfairDisplay-Bold.ttf`, `Inter-Regular.ttf`, `Inter-SemiBold.ttf` plus OFL license texts.
- [ ] `Loader::load()`:
```php
public static function load(): bool {
	static $loaded = null;
	if ( null !== $loaded ) { return $loaded; }
	$file = BGCW_PRO_PATH . 'lib/dompdf/autoload.inc.php';
	if ( ! file_exists( $file ) ) { return $loaded = false; }
	require_once $file;
	return $loaded = class_exists( '\Dompdf\Dompdf' );
}
```
- [ ] Test `tests/test-pdf-loader.php`: assert `Loader::load()` true and `class_exists('\Dompdf\Dompdf')`.
- [ ] Add Pro `tests/bootstrap.php` + `tests/run.sh` copied from free (path changed to the Pro plugin, prefix functions `bgcwp_`). Commit `Bundle dompdf 3.1.6 and card fonts`.

### Task 3: Designs and CardRenderer (visual design pass)

**Files:**
- Create: `src/Pdf/Designs.php`, `src/Pdf/CardRenderer.php`
- Create: `templates/pdf/_card.php`, `templates/pdf/card.css`, `templates/pdf/index.php`
- Test: `tests/test-pdf-renderer.php`

**Interfaces:**
- `Designs::get(): array` — `[ slug => [ 'name', 'heading', 'color', 'color_light', 'bg', 'ink' ] ]`, admin overrides `pdf_color_{slug}`, `pdf_heading_{slug}` applied.
- `Designs::normalize( $slug ): string`.
- `Designs::version(): int` from option `pdf_design_version`.
- `CardRenderer::render( string $design, array $data, string $mode = 'preview' ): string` — `$data` keys `amount, currency, code, recipient_name, sender_name, message, expires_at`. Output for `preview` is a `<div class="bgcw-card" data-design="...">` fragment with `<style>` once; for `pdf` a full HTML document with `@page`, `@font-face`.
- `CardRenderer::placeholders(): array`.
- `CardRenderer::store(): array` — `logo_path`, `logo_url`, `store_name`, `shop_url`, `redeem_text`.

- [ ] Write test: for each design, `render()` contains heading, formatted amount (`wc_price` stripped of tags), code, names, message; empty message removes `bgcw-card__message`; `normalize('holiday') === 'classic'`; `render(..., 'pdf')` starts with `<!DOCTYPE html>` and contains `@page`.
- [ ] Run, expect FAIL.
- [ ] Use the frontend-design skill to design the card (A5 landscape ratio 210:148). Implement `_card.php` + `card.css` + classes. Amount via `wp_strip_all_tags( wc_price( $amount, [ 'currency' => $currency ] ) )` decoded with `html_entity_decode`.
- [ ] Run tests, PASS. Lint. Commit `Add PDF card designs and renderer`.

### Task 4: Installer table + PdfGenerator

**Files:**
- Modify: `src/Support/Installer.php` (add `bgcw_pro_pdf` table, bump DB version), `uninstall.php` (drop table, remove dir)
- Create: `src/Pdf/PdfGenerator.php`
- Test: `tests/test-pdf-generator.php`

**Interfaces:**
- `PdfGenerator::generate( object $gc ): string|\WP_Error`
- `PdfGenerator::get_or_generate( object $gc ): string|\WP_Error`
- `PdfGenerator::sample( string $design ): string|\WP_Error` (temp file path)
- `PdfGenerator::delete_for_card( int $gift_card_id ): void`
- `PdfGenerator::design_for_card( object $gc ): string` (order item `_bgcw_design_theme` → normalize; no order → classic)
- `PdfGenerator::dir(): string` uploads dir path, ensures `index.php`/`.htaccess`.

- [ ] Test: create manual card; `generate()` returns path whose file starts with `%PDF`; row exists in table with `design_version` = current; `get_or_generate()` returns same path; bump `pdf_design_version` option → `get_or_generate()` returns a different path and old file gone; `delete_for_card()` removes file and row; `sample('birthday')` returns readable file.
- [ ] Implement. dompdf options: `isRemoteEnabled=false`, `chroot=[BGCW_PRO_PATH, uploads basedir]`, `defaultFont='inter'`? (fonts referenced via `@font-face` in CSS with `file://` absolute paths), `setPaper('a5','landscape')`, `dpi=96`. Wrap in try/catch → `WP_Error( 'bgcw_pdf_failed', $e->getMessage() )` and `wc_get_logger()->error(..., ['source'=>'bgcw-pro-pdf'])`.
- [ ] Hook `bgcw_gift_card_deleted` → `delete_for_card` (in `PdfGenerator::init()`).
- [ ] Run tests PASS. Commit `Add PDF generator and storage table`.

### Task 5: Email attachment + neutral email

**Files:**
- Create: `src/Pdf/EmailAttachment.php`, `templates/emails/pdf-notice.php`, `templates/emails/plain/pdf-notice.php`
- Test: `tests/test-pdf-email.php`

**Interfaces:**
- `EmailAttachment::attach( array $attachments, string $email_id, $object, $email ): array`
- `EmailAttachment::swap_template( string $template, $gift_card ): string` (priority 20 on `bgcw_email_template_html`).

- [ ] Test: with a manual card, `apply_filters( 'woocommerce_email_attachments', [], 'BGCW_Gift_Card_Delivery', $gc, (object)['gift_card'=>$gc] )` returns one `.pdf` path; with other email id returns `[]`; with `pdf_enabled=0` returns `[]`; `swap_template('emails/gift-card-delivery.php', $gc)` returns `emails/pdf-notice.php` and `woocommerce_locate_template` resolves to Pro path.
- [ ] Implement; email copy: "{sender} sent you a gift card", optional message, "Your gift card is attached to this email as a PDF. Print it or show the code at checkout.", code shown as fallback too, Shop Now button.
- [ ] Run PASS. Commit `Attach PDF to gift card email`.

### Task 6: Download endpoints + My Account button

**Files:**
- Create: `src/Pdf/Download.php`
- Test: `tests/test-pdf-download.php`

**Interfaces:**
- `Download::url_for_card( object $gc ): string` (nonce `bgcw_pro_pdf_{id}`)
- `Download::can_access( object $gc, int $user_id ): bool`
- `Download::handle_download()` on `admin_post_bgcw_pro_pdf_download`
- `Download::handle_sample()` on `admin_post_bgcw_pro_pdf_sample`
- `Download::render_button( object $gc )` on `bgcw_my_account_card_actions`

- [ ] Test `can_access`: owner via customer_id true, via recipient_email true (case-insensitive), stranger false, guest (0) false.
- [ ] Implement; stream with `nocache_headers()`, `Content-Type: application/pdf`, `Content-Length`, `readfile`. Errors: `wp_die( esc_html__(...), 403 )`.
- [ ] Commit `Add PDF download for My Account and admin samples`.

### Task 7: Product page live preview; remove email themes

**Files:**
- Create: `src/Pdf/ProductPreview.php`
- Delete: `src/EmailThemes/*`, `templates/emails/themes/*`
- Modify: `assets/js/frontend.js`, `assets/css/frontend.css`, `src/Plugin.php`
- Test: `tests/test-pdf-preview.php`

- [ ] Test: `ProductPreview::add_cart_data([], $pid)` with `$_POST['bgcw_design_theme']='holiday'` stores `classic`; with `birthday` stores `birthday`. Rendered picker HTML contains 3 radios and a `.bgcw-card`.
- [ ] Implement `ProductPreview` (moves cart/order-item meta logic from `EmailThemes\ProductFields`), localized params `bgcw_pro_pdf` with currency format (`get_woocommerce_currency_symbol`, `get_option('woocommerce_currency_pos')`, `wc_get_price_decimal_separator`, `wc_get_price_thousand_separator`, `wc_get_price_decimals`, `wc_get_price_format` sprintf pattern) and design palette map. JS updates `[data-bgcw-field]` and `data-design`; swatch selection state.
- [ ] Update `Plugin::init()` and enqueue; remove EmailThemes usage. Commit `Live card preview on product page; remove email themes`.

### Task 8: Settings, options, readme, versions, release

**Files:**
- Modify: `src/Support/Options.php`, `src/Admin/SettingsPage.php`, `assets/js/admin.js` (media picker), `readme.txt`, `README.md`, `beltoft-gift-cards-pro.php`, `CLAUDE.md` (Pro, if exists)

- [ ] Options defaults/sanitize: `pdf_enabled`, `pdf_logo_id`, `pdf_color_*`, `pdf_heading_*`, `pdf_design_version` (bumped in sanitize when any pdf design field changed). Remove theme options from defaults/sanitize.
- [ ] SettingsPage: "PDF Gift Cards" section replacing "Email Themes": enable checkbox, logo picker (`wp_enqueue_media`, button + preview + hidden id), per-design color + heading, sample links.
- [ ] Readme + README: feature text, changelog 1.4.0, requirement free 1.5.1. Version 1.4.0 in header + `BGCW_PRO_VER`. Plugin Check both modes, lint, `tests/run.sh`.
- [ ] Commit `Release 1.4.0: PDF gift cards`. Tag 1.4.0, push, GitHub release (check Updater for asset expectations first).

### Task 9: Code review and fixes
- [ ] Run the code-review skill on the Pro diff since `2a116ce` at high effort; apply recommended fixes; re-run tests + Plugin Check; amend release if needed (new patch tag 1.4.1 if already published).
