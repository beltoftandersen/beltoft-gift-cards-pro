/**
 * Browser check for "Give as a gift" on a giftable product page (Pro).
 *   BGCW_E2E_GIFT_URL=https://your-site/product/some-giftable-product/ node tests/e2e/gifting.js
 */
const { chromium } = require('playwright');
const URL = process.env.BGCW_E2E_GIFT_URL || 'https://test.chimkins.com/product/perfume-workshop/';
(async () => {
  const browser = await chromium.launch();
  const out = [];
  const log = (ok, msg) => out.push((ok ? '  ok   - ' : '  FAIL - ') + msg);
  for (const [name, vp] of [['desktop', { width: 1280, height: 900 }], ['mobile', { width: 375, height: 800 }]]) {
    const ctx = await browser.newContext({ viewport: vp, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.goto(URL, { waitUntil: 'networkidle' });
    const toggle = page.locator('#bgcw_gift');
    log(await toggle.count() === 1, `${name}: gift toggle present`);
    const fields = page.locator('.bgcw-pro-gift__fields');
    log(await fields.isHidden(), `${name}: fields hidden until toggled`);
    const button = page.locator('.single_add_to_cart_button');
    const originalText = (await button.textContent()).trim();
    await toggle.check();
    await page.waitForTimeout(150);
    log(await fields.isVisible(), `${name}: fields shown after toggle`);
    log((await button.textContent()).trim() !== originalText, `${name}: button text changes to gift (${(await button.textContent()).trim()})`);
    if (await page.locator('#bgcw_recipient_email').count()) {
      log(await page.locator('#bgcw_recipient_email').getAttribute('required') !== null, `${name}: recipient email required in gift mode`);
    } else {
      log(true, `${name}: recipient email field hidden by site filter (card goes to the buyer)`);
    }
    await page.fill('#bgcw_recipient_name', 'Ricardo');
    await page.fill('#bgcw_message', 'Enjoy the workshop!');
    await page.locator('.bgcw-pro-preview__open').click();
    await page.waitForTimeout(250);
    const lightbox = page.locator('#bgcw-pro-lightbox');
    log(await lightbox.isVisible(), `${name}: preview lightbox opens`);
    const card = page.locator('#bgcw-pro-lightbox .bgcw-card');
    log(/bgcw-card--product/.test(await card.getAttribute('class')), `${name}: product variant card`);
    log((await page.locator('[data-bgcw-field="product_name"]').textContent()).includes('Perfume Workshop'), `${name}: card names the product`);
    log((await page.locator('[data-bgcw-field="recipient_name"]').textContent()) === 'Ricardo', `${name}: recipient live in card`);
    log((await page.locator('[data-bgcw-field="message"]').textContent()).includes('Enjoy the workshop!'), `${name}: message live in card`);
    const dims = await page.evaluate(() => { const r = document.querySelector('.bgcw-pro-preview__stage').getBoundingClientRect(); return { w: r.width, h: r.height, vw: window.innerWidth, vh: window.innerHeight, top: r.top, bottom: r.bottom }; });
    log(Math.abs(dims.w / dims.h - 794 / 559) < 0.02 && dims.w <= dims.vw && dims.top >= 0 && dims.bottom <= dims.vh, `${name}: landscape card fits viewport (${Math.round(dims.w)}x${Math.round(dims.h)})`);
    await page.screenshot({ path: `shot-gift-${name}.png` });
    await page.keyboard.press('Escape');
    await page.waitForTimeout(100);
    await toggle.uncheck();
    await page.waitForTimeout(100);
    log(await fields.isHidden() && (await button.textContent()).trim() === originalText, `${name}: toggling off restores the form`);
    log(errors.length === 0, `${name}: no JS errors` + (errors.length ? ' -> ' + errors.slice(0, 2).join(' | ') : ''));
    await ctx.close();
  }
  await browser.close();
  console.log(out.join('\n'));
  process.exit(out.some(l => l.includes('FAIL')) ? 1 : 0);
})().catch(e => { console.error('ERROR', e); process.exit(1); });
