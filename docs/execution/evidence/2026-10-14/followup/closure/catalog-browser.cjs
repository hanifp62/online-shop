const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('/Users/hanifpriyambodo/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
const root = process.env.MANUAL_ROOT;
assert.equal(process.env.MANUAL_PORT, '8018');
assert.ok(root);
const base = 'http://127.0.0.1:8018';
(async () => {
    const creds = JSON.parse(fs.readFileSync(root + '/private-runtime/credentials.json'));
    const browser = await chromium.launch({ headless: true, executablePath: '/Users/hanifpriyambodo/Library/Caches/ms-playwright/chromium-1228/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing' });
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const blocked = [], errors = [], http = [];
    await ctx.route('**/*', route => {
        if (new URL(route.request().url()).origin !== base) {
            blocked.push(new URL(route.request().url()).origin);
            return route.abort();
        }
        return route.continue();
    });
    const page = await ctx.newPage();
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => { if (response.status() >= 400) http.push([new URL(response.url()).pathname, response.status()]); });
    const cart = async () => {
        const res = await ctx.request.get(base + '/cart', { headers: { Accept: 'application/json' } });
        assert.equal(res.status(), 200);
        return res.json();
    };
    try {
        await page.goto(base + '/login');
        await page.locator('#email').fill(creds.customer.email);
        await page.locator('#password').fill(creds.customer.password);
        await Promise.all([page.waitForURL(u => u.pathname !== '/login'), page.locator('button[type=submit]').click()]);
        await page.goto(base + '/');
        await page.waitForFunction(() => ['Tas belanja kosong.', 'Tas belanja diperbarui.'].includes(document.querySelector('[data-cart-status]')?.textContent));
        await page.locator('[data-product-card][data-product-id="1"] [data-card-add]').click();
        await page.waitForFunction(() => document.querySelector('.cart-count')?.textContent === '1');
        await page.locator('[data-open-cart]').first().click();
        await page.locator('[data-quantity="plus"]').evaluate(el => { el.click(); el.click(); el.click(); });
        await page.waitForFunction(() => document.querySelector('.quantity-control span')?.textContent === '4');
        assert.equal((await cart()).items[0].quantity, 4);
        await page.goto(base + '/koleksi');
        const form = page.locator('form[action$="/cart/items"]').filter({ has: page.locator('input[name="product_id"][value="1"]') }).first();
        assert.match(await form.locator('button').innerText(), /1/);
        assert.ok(await form.locator('button').getAttribute('aria-describedby'));
        assert.ok(await page.locator('#catalog-shop-status[role="status"][aria-live="polite"] a[href$="/belanja"]').count());
        await form.locator('button').click();
        await page.waitForFunction(() => document.querySelector('#catalog-feedback')?.textContent.includes('Jumlah 1'));
        assert.equal((await cart()).items[0].quantity, 1);
        await page.reload();
        await page.locator('[data-quick-view][data-product-id="1"]').click();
        assert.match(await page.locator('#dialog-replace-hint').innerText(), /menggantikan/);
        assert.match(await page.locator('#dialog-cart-form button[type=submit]').innerText(), /jumlah/i); // CSS text-transform uppercases rendered text
        await page.locator('#dialog-qty').fill('2');
        await page.locator('#dialog-cart-form button[type=submit]').click();
        await page.waitForFunction(() => document.querySelector('#catalog-feedback')?.textContent.includes('Jumlah 2'));
        assert.equal((await cart()).items[0].quantity, 2);
        assert.deepEqual(errors, []);
        assert.deepEqual(http, []);
        console.log(JSON.stringify({ status: 'PASS', card: '4 -> 1', dialog: '1 -> 2', statusLive: true, cartLink: true, pageErrors: errors, httpFailures: http, blockedExternalOrigins: [...new Set(blocked)] }));
    } finally { await browser.close(); }
})().catch(error => { console.error('FAIL:', error.stack); process.exitCode = 1; });
