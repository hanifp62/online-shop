const assert = require('node:assert/strict');

// Synthetic guest journeys only. Never return or capture tracking tokens.
async function localeSmoke(browser, origin, setPhase, save) {
  const {expect} = require('/opt/homebrew/lib/node_modules/@playwright/cli/node_modules/playwright/test');
  const results = [];
  for (const width of [1365, 390]) {
    for (const locale of ['en', 'fr', 'it', 'zh']) {
      setPhase(`guest-${locale}-${width}`);
      const context = await browser.newContext({viewport: {width, height: 1000}, locale});
      try {
        await context.route('**/*', route => route.request().url().startsWith(origin + '/') ? route.continue() : route.abort());
        const page = await context.newPage();
        page.setDefaultTimeout(15000);
        await page.goto(`${origin}/koleksi?q=AM-10&locale=${locale}`);
        await expect(page.locator('html')).toHaveAttribute('lang', locale);
        await page.locator('[data-slug="amadis-demo-stock-37"]').click();
        await page.locator('#dialog-qty').fill('1');
        const added = page.waitForResponse(r => r.request().method() === 'POST' && r.url().includes('/cart'));
        await page.locator('#dialog-cart-form button').click();
        assert.equal((await added).status(), 200);
        await page.goto(origin + '/belanja');
        await expect(page.locator('html')).toHaveAttribute('lang', locale);
        const copy = JSON.parse(await page.locator('#shop-copy').textContent());
        const statuses = JSON.parse(await page.locator('#shop-status-copy').textContent());
        await expect(page.locator('#feedback')).toHaveText(copy.js.ready);
        await expect(page.locator('#cart-total')).toContainText('1.250.000');
        await expect(page.locator('#tas input[type="number"]').first()).toHaveValue('1');
        const fields = {customer_name:'Synthetic Guest', customer_email:'guest@example.test', customer_phone:'081200000000', recipient_name:'Synthetic Guest', phone:'081200000000', line1:'Jalan Uji 1', city:'Jakarta', province:'DKI Jakarta', postal_code:'10110'};
        for (const [name, value] of Object.entries(fields)) await page.locator(`#guest-checkout-form [name="${name}"]`).fill(value);
        await page.locator('#guest-checkout-button').click();
        await expect(page.locator('#guest-order-confirmation h3')).toBeVisible();
        await expect(page.locator('#guest-order-confirmation')).toContainText(statuses.pending);
        await expect(page.locator('#guest-order-confirmation')).toContainText('1.250.000');
        await expect(page.locator('#guest-track-form [name="token"]')).toHaveValue(/^[a-f0-9]{64}$/);
        await page.locator('#guest-track-form button').click();
        await expect(page.locator('#feedback')).toHaveText(copy.js.order_found);
        await expect(page.locator('#guest-track-result')).toContainText(statuses.pending);
        await expect(page.locator('#guest-track-result')).toContainText('1.250.000');
        await page.locator('#guest-clear-token').click();
        await expect(page.locator('#guest-track-form [name="token"]')).toHaveValue('');
        await expect(page.locator('#guest-order-confirmation code')).toHaveCount(0);
        results.push({locale, width, status:'PASS', quantity:1, total:1250000, tracking:true, token_cleared:true});
        save(`guest-${locale}-${width}.json`, results.at(-1));
      } finally { await context.close(); }
    }
  }
  return results;
}
module.exports = {localeSmoke};
