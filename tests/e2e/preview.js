/**
 * Browser check for the live gift card preview (Pro).
 *
 * Run from the plugin root:
 *   npm i --no-save playwright && npx playwright install chromium
 *   BGCW_E2E_URL=https://your-site/product/gift-card/ node tests/e2e/preview.js
 * Screenshots are written to the current directory.
 */
const { chromium } = require('playwright');
const URL = process.env.BGCW_E2E_URL || 'https://test.chimkins.com/product/gift-card/';
(async () => {
  const browser = await chromium.launch();
  const out = [];
  const log = (ok, msg) => { out.push((ok ? '  ok   - ' : '  FAIL - ') + msg); };
  for (const [name, vp] of [['desktop', { width: 1280, height: 900 }], ['mobile', { width: 375, height: 800 }]]) {
    const ctx = await browser.newContext({ viewport: vp, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
    await page.goto(URL, { waitUntil: 'networkidle' });
    const card = page.locator('[data-bgcw-preview] .bgcw-card');
    log(await card.count() === 1, `${name}: preview card in DOM`);
    const lightbox = page.locator('#bgcw-pro-lightbox');
    log(await lightbox.isHidden(), `${name}: lightbox hidden on load`);
    await page.locator('[data-bgcw-preview]').screenshot({ path: `shot-${name}-picker.png` });

    // Fill the form first (the lightbox covers it while open).
    const number = page.locator('[data-bgcw-field="number"]');
    const before = await number.textContent();
    const btns = page.locator('.bgcw-amount-btn:not(.bgcw-custom-btn)');
    if (await btns.count() > 1) {
      await btns.nth(1).click();
      await page.waitForTimeout(100);
      const after = await number.textContent();
      const hidden = await page.locator('#bgcw_amount').inputValue();
      log(after !== before && after.replace(/\D/g, '') === hidden.replace(/\D/g, ''), `${name}: amount button updates card (${before} -> ${after})`);
    } else {
      log(false, `${name}: not enough amount buttons`);
    }
    const custom = page.locator('#bgcw_custom_amount');
    if (await custom.count()) {
      const customBtn = page.locator('.bgcw-custom-btn');
      if (await customBtn.count()) await customBtn.click();
      await custom.fill('1234.5');
      await page.waitForTimeout(150);
      const t = await number.textContent();
      const hiddenCustom = await page.locator('#bgcw_amount').inputValue();
      log(t.replace(/\D/g, '') === hiddenCustom.replace(/\D/g, ''), `${name}: custom amount follows hidden field (${hiddenCustom} -> ${t})`);
    }
    await page.fill('#bgcw_recipient_name', 'Ana Silva');
    await page.fill('#bgcw_message', 'Happy birthday Ana');
    await page.waitForTimeout(100);
    log((await page.locator('[data-bgcw-field="recipient_name"]').textContent()) === 'Ana Silva', `${name}: recipient name live`);
    log((await page.locator('[data-bgcw-field="message"]').textContent()).includes('Happy birthday Ana'), `${name}: message live`);
    log(await page.locator('input[name="bgcw_design_theme"]').count() === 0, `${name}: no design picker`);

    // Open the preview.
    await page.locator('.bgcw-pro-preview__open').click();
    await page.waitForTimeout(250);
    log(await lightbox.isVisible(), `${name}: lightbox opens`);
    log(await page.evaluate(() => document.activeElement && document.activeElement.classList.contains('bgcw-pro-lightbox__close')), `${name}: focus moves to close button`);
    const dims = await page.evaluate(() => {
      const stage = document.querySelector('.bgcw-pro-preview__stage');
      const r = stage.getBoundingClientRect();
      return { w: r.width, h: r.height, top: r.top, bottom: r.bottom, vh: window.innerHeight, vw: window.innerWidth };
    });
    log(Math.abs(dims.w / dims.h - 794 / 559) < 0.02, `${name}: stage has A5 landscape ratio (${Math.round(dims.w)}x${Math.round(dims.h)})`);
    log(dims.top >= 0 && dims.bottom <= dims.vh && dims.w <= dims.vw, `${name}: card fully inside viewport`);
    const headingColor = await page.evaluate(() => getComputedStyle(document.querySelector('#bgcw-pro-lightbox .bgcw-card__heading')).color);
    log(headingColor === 'rgb(31, 74, 54)', `${name}: heading uses the brand color (${headingColor})`);
    const heading = await page.locator('[data-bgcw-field="heading"]').textContent();
    log(heading.trim().length > 0, `${name}: heading updated (${heading.trim()})`);
    const fonts = await page.evaluate(() => document.fonts.check("16px BgcwSerif") && document.fonts.check("16px BgcwSans"));
    log(fonts, `${name}: card fonts loaded`);
    await page.screenshot({ path: `shot-${name}-lightbox.png` });

    await page.keyboard.press('Escape');
    await page.waitForTimeout(150);
    log(await lightbox.isHidden(), `${name}: Escape closes lightbox`);
    await page.locator('.bgcw-pro-preview__open').click();
    await page.waitForTimeout(150);
    await page.locator('.bgcw-pro-lightbox__backdrop').click({ position: { x: 5, y: 5 } });
    await page.waitForTimeout(150);
    log(await lightbox.isHidden(), `${name}: backdrop click closes lightbox`);
    log(errors.length === 0, `${name}: no JS errors` + (errors.length ? ' -> ' + errors.slice(0, 3).join(' | ') : ''));
    await ctx.close();
  }
  await browser.close();
  console.log(out.join('\n'));
  process.exit(out.some(l => l.includes('FAIL')) ? 1 : 0);
})().catch(e => { console.error('ERROR', e); process.exit(1); });
