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
    log(await card.count() === 1, `${name}: preview card present`);
    await page.waitForTimeout(300);
    const dims = await page.evaluate(() => {
      const stage = document.querySelector('.bgcw-pro-preview__stage');
      const scale = document.querySelector('.bgcw-pro-preview__scale');
      const r = stage.getBoundingClientRect();
      return { w: r.width, h: r.height, transform: scale.style.transform, cardVisible: scale.getBoundingClientRect().width };
    });
    const ratio = dims.h / dims.w;
    log(Math.abs(ratio - 559 / 794) < 0.02 && dims.w > 100, `${name}: stage scaled to A5 ratio (${Math.round(dims.w)}x${Math.round(dims.h)}, ${dims.transform})`);
    log(dims.w <= vp.width, `${name}: preview fits viewport width`);
    await page.locator('[data-bgcw-preview]').screenshot({ path: `shot-${name}-initial.png` });

    const number = page.locator('[data-bgcw-field="number"]');
    const before = await number.textContent();
    const btns = page.locator('.bgcw-amount-btn:not(.bgcw-custom-btn)');
    const nBtns = await btns.count();
    if (nBtns > 1) {
      await btns.nth(1).click();
      await page.waitForTimeout(100);
      const after = await number.textContent();
      const hidden = await page.locator('#bgcw_amount').inputValue();
      log(after !== before && after.replace(/\D/g, '').startsWith(hidden.replace(/\D/g, '').replace(/0+$/, '').slice(0, 2)), `${name}: amount button updates preview (${before} -> ${after}, hidden=${hidden})`);
    } else {
      log(false, `${name}: not enough amount buttons (${nBtns}); dropdown mode?`);
    }
    const custom = page.locator('#bgcw_custom_amount');
    if (await custom.count()) {
      const customBtn = page.locator('.bgcw-custom-btn');
      if (await customBtn.count()) await customBtn.click();
      await custom.fill('1234.5');
      await page.waitForTimeout(150);
      const t = await number.textContent();
      // The free plugin rounds custom amounts to whole numbers; the preview must show that value.
      const hiddenCustom = await page.locator('#bgcw_amount').inputValue();
      log(t.replace(/\D/g, '') === hiddenCustom.replace(/\D/g, ''), `${name}: custom amount follows hidden field (${hiddenCustom} -> ${t})`);
    }
    await page.fill('#bgcw_recipient_name', 'Ana Silva');
    await page.fill('#bgcw_message', 'Happy birthday Ana');
    await page.waitForTimeout(100);
    log((await page.locator('[data-bgcw-field="recipient_name"]').textContent()) === 'Ana Silva', `${name}: recipient name live`);
    log((await page.locator('[data-bgcw-field="message"]').textContent()) === 'Happy birthday Ana', `${name}: message live`);
    await page.fill('#bgcw_message', '');
    await page.waitForTimeout(100);
    const ph = await page.locator('[data-bgcw-field="message"]').textContent();
    log(ph.length > 0 && ph !== 'Happy birthday Ana', `${name}: empty message shows placeholder (${ph})`);

    await page.locator('input[name="bgcw_design_theme"][value="birthday"]').check({ force: true });
    await page.waitForTimeout(100);
    const cls = await card.getAttribute('class');
    const heading = await page.locator('[data-bgcw-field="heading"]').textContent();
    const panelBg = await page.evaluate(() => getComputedStyle(document.querySelector('.bgcw-card__panel')).backgroundColor);
    log(/bgcw-card--birthday/.test(cls) && !/bgcw-card--classic/.test(cls), `${name}: design class switched (${cls})`);
    log(panelBg === 'rgb(228, 87, 123)', `${name}: panel color is birthday coral (${panelBg})`);
    log(heading.trim().length > 0, `${name}: heading updated (${heading.trim()})`);
    const selected = await page.locator('.bgcw-pro-design.is-selected .bgcw-pro-design__name').textContent();
    log(selected.trim().length > 0, `${name}: swatch selection state (${selected.trim()})`);
    const fonts = await page.evaluate(() => document.fonts.check("16px BgcwSerif") && document.fonts.check("16px BgcwSans"));
    log(fonts, `${name}: card fonts loaded`);
    await page.locator('[data-bgcw-preview]').screenshot({ path: `shot-${name}-birthday.png` });
    log(errors.length === 0, `${name}: no JS errors` + (errors.length ? ' -> ' + errors.slice(0, 3).join(' | ') : ''));
    await ctx.close();
  }
  await browser.close();
  console.log(out.join('\n'));
  process.exit(out.some(l => l.includes('FAIL')) ? 1 : 0);
})().catch(e => { console.error('ERROR', e); process.exit(1); });
