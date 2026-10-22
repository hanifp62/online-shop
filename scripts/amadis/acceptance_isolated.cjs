// AM-01/02/06/10 proposed acceptance mapping, not an official rubric.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { createHmac, randomBytes } = require('node:crypto');
const { chromium, request } = require(process.env.AM_PLAYWRIGHT);
const baseURL = process.env.AM_ACCEPTANCE_URL;

const output = process.env.AM_ACCEPTANCE_OUTPUT;
assert.match(baseURL, /^http:\/\/127\.0\.0\.1:\d+$/);
const results = {};
const stockSlug = 'amadis-demo-stock-37';
function checkedItems(items, productId, quantity) {
  assert(Array.isArray(items) && items.length === 1, 'Expected one cart/order item');
  const item = items[0];
  assert(Number.isSafeInteger(item.product_id) && item.product_id === productId, 'Cart/order product mismatch');
  assert(Number.isSafeInteger(item.quantity) && item.quantity === quantity, 'Cart/order quantity mismatch');
  return [{productId: item.product_id, quantity: item.quantity}];
}
(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.AM_CHROMIUM});
  const contexts = [];
  try {
    const page = await browser.newPage();
    await page.route('**/*', route => new URL(route.request().url()).origin === baseURL ? route.continue() : route.abort());
    const ids = [];
    const counts = [];
    const stockProducts = [];
    for (let n = 1; n <= 10; n++) {
      assert.equal((await page.goto(`${baseURL}/koleksi?page=${n}`, {waitUntil: 'domcontentloaded'})).status(), 200);
      const current = await page.locator('[data-quick-view]').evaluateAll(nodes => nodes.map(node => ({
        id: Number(node.dataset.productId), slug: node.dataset.slug, stock: Number(node.dataset.stock),
      })));
      counts.push(current.length);
      assert.equal(current.length, n === 10 ? 4 : 8);
      ids.push(...current.map(item => item.id));
      stockProducts.push(...current.filter(item => item.slug === stockSlug));
    }
    assert.equal(new Set(ids).size, 76);
    assert.equal(stockProducts.length, 1, 'Expected exactly one stock-37 product');
    const product = stockProducts[0];
    assert(Number.isSafeInteger(product.id) && product.id > 0 && product.stock === 37, 'Invalid stock-37 fixture');
    results['AM-02'] = {status: 'PASS', counts, uniqueProducts: new Set(ids).size};
    await page.goto(`${baseURL}/koleksi`, {waitUntil: 'domcontentloaded'});
    const trigger = page.locator('[data-quick-view]').first();

    // Reach the trigger using real keyboard navigation, not programmatic focus.
    for (let n = 0; n < 100 && !await trigger.evaluate(el => el === document.activeElement); n++) await page.keyboard.press('Tab');
    assert(await trigger.evaluate(el => el === document.activeElement));
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => document.querySelector('dialog').open);
    assert.equal(await page.locator(':focus').getAttribute('id'), 'dialog-close-btn');
    for (let n = 0; n < 12; n++) {
      await page.keyboard.press('Tab');
      const focus = await page.evaluate(() => ({tag: document.activeElement.tagName, inside: !!document.activeElement.closest('dialog')}));
      if (!focus.inside) (results.tabExceptions ??= []).push({step:n, tag:focus.tag});
    }
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => !document.querySelector('dialog').open);
    assert(await trigger.evaluate(el => el === document.activeElement));
    await page.keyboard.press('Space');
    await page.waitForFunction(() => document.querySelector('dialog').open);
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => !document.querySelector('dialog').open);
    assert(await trigger.evaluate(el => el === document.activeElement));
    // BODY means focus left the document for browser UI (native modal behaviour); any other outside element is a leak.
    const leaks = (results.tabExceptions ?? []).filter(e => e.tag !== 'BODY');
    results['AM-06-keyboard'] = {status: leaks.length ? 'FAIL' : 'PASS', enter: true, space: true, escape: true, closeButton: true, focusRestored: true, tabSteps: 12, focusToBrowserUi: (results.tabExceptions ?? []).length, pageContentLeaks: leaks};
    delete results.tabExceptions;
    const jobs = [];
    const actorSalt = randomBytes(32); // Never publish a raw user ID or a reversible identity fingerprint.
    for (const quantity of [20, 20, 17]) {
      const client = await request.newContext({baseURL});
      contexts.push(client);
      let response = await client.get('/register');
      assert.equal(response.status(), 200);
      let csrf = (await response.text()).match(/name="_token" value="([^"]+)"/)[1];
      const suffix = randomBytes(12).toString('hex');
      const password = 'Aa1!' + randomBytes(24).toString('hex');
      response = await client.post('/register', {form: {_token: csrf, name: 'Acceptance-' + suffix,
        email: 'acceptance-' + suffix + '@example.test', password, password_confirmation: password}, maxRedirects: 0});
      assert.equal(response.status(), 302);
      assert(new URL(response.headers().location, baseURL).href === new URL('/', baseURL).href, 'Registration did not complete');
      response = await client.get('/koleksi');
      assert.equal(response.status(), 200);
      csrf = (await response.text()).match(/name="csrf-token" content="([^"]+)"/)[1];
      const headers = {'X-CSRF-TOKEN': csrf, Accept: 'application/json'};
      response = await client.post('/addresses', {headers, data: {label:'Synthetic', recipient_name:'Acceptance', phone:'0800000000', line1:'Synthetic test address', city:'Jakarta', province:'DKI Jakarta', postal_code:'12345'}});
      assert.equal(response.status(), 201);
      const address = (await response.json()).address;
      assert(Number.isSafeInteger(address.id) && address.id > 0 && Number.isSafeInteger(address.user_id) && address.user_id > 0, 'Invalid authenticated address');
      const actor = createHmac('sha256', actorSalt).update(String(address.user_id)).digest('hex');
      assert(!jobs.some(job => job.actor === actor), 'Checkout identities must be independent');
      response = await client.post('/cart/items', {headers, data:{product_id:product.id, quantity}});
      assert.equal(response.status(), 200);
      checkedItems((await response.json()).items, product.id, quantity);
      jobs.push({client, headers, quantity, address: address.id, userId: address.user_id, actor});
    }
    // Read all carts AFTER all writes, BEFORE any checkout: per-write echoes cannot prove isolation.
    await Promise.all(jobs.map(async job => {
      const response = await job.client.get('/cart', {headers: job.headers});
      assert.equal(response.status(), 200);
      const cart = await response.json();
      job.preparedItems = checkedItems(cart.items, product.id, job.quantity);
      assert(cart.items[0].available === true, 'Prepared cart is unavailable');
    }));
    results['AM-10'] = {status: 'PENDING', product, requests: []};
    results['AM-10'].requests = await Promise.all(jobs.map(async (job, i) => {
      const start = new Date().toISOString();
      const response = await job.client.post('/checkout', {headers:job.headers, data:{address_id:job.address, idempotency_key:`acceptance-parallel-${i}`}, maxRedirects: 0});
      const status = response.status();
      assert([201, 422].includes(status), 'Unexpected checkout status');
      const body = await response.json();
      let orderId = null, orderItems = null;
      if (status === 201) {
        assert(body.order?.user_id === job.userId, 'Order identity mismatch');
        assert(Number.isSafeInteger(body.order.id) && body.order.id > 0, 'Invalid created order');
        orderId = body.order.id;
        orderItems = checkedItems(body.order.items, product.id, job.quantity);
      } else {
        assert(body.order == null, 'Rejected checkout returned an order');
      }
      return {quantity:job.quantity, actor:job.actor, preparedItems:job.preparedItems, orderItems,
        start, finish:new Date().toISOString(), httpStatus:status, orderId};
    }));
    const created = results['AM-10'].requests.filter(item => item.httpStatus === 201);
    assert(created.length > 0 && new Set(created.map(item => item.orderId)).size === created.length, 'Invalid created orders');
    assert(created.reduce((total, item) => total + item.orderItems[0].quantity, 0) <= product.stock, 'Stock exceeded');
    results['AM-10'].status = 'PASS_HTTP';
  } finally {
    fs.writeFileSync(output, JSON.stringify(results, null, 2)+'\n');
    for (const context of contexts) await context.dispose();
    await browser.close();
  }
})().catch(() => { console.error('Acceptance failed; diagnostics withheld.'); process.exitCode = 1; });
