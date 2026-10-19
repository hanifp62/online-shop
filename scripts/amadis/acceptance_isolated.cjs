// AM-01/02/06/10 proposed acceptance mapping, not an official rubric.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium, request } = require(process.env.AM_PLAYWRIGHT);
const baseURL = process.env.AM_ACCEPTANCE_URL;
const root = process.env.AMADIS_ROOT;
const output = process.env.AM_ACCEPTANCE_OUTPUT;
assert.match(baseURL, /^http:\/\/127\.0\.0\.1:\d+$/);
const results = {};
(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.AM_CHROMIUM});
  const contexts = [];
  try {
    const page = await browser.newPage();
    await page.route('**/*', route => new URL(route.request().url()).origin === baseURL ? route.continue() : route.abort());
    const ids = [];
    const counts = [];
    for (let n = 1; n <= 10; n++) {
      assert.equal((await page.goto(`${baseURL}/koleksi?page=${n}`, {waitUntil: 'domcontentloaded'})).status(), 200);
      const current = await page.locator('[data-quick-view]').evaluateAll(nodes => nodes.map(node => node.dataset.productId));
      counts.push(current.length);
      assert.equal(current.length, n === 10 ? 4 : 8);
      ids.push(...current);
    }
    assert.equal(new Set(ids).size, 76);
    results['AM-02'] = {status: 'PASS', counts, uniqueProducts: new Set(ids).size};
    await page.goto(`${baseURL}/koleksi`, {waitUntil: 'domcontentloaded'});
    const trigger = page.locator('[data-quick-view]').first();
    const productId = Number(await trigger.getAttribute('data-product-id'));
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
    const credential = JSON.parse(fs.readFileSync(`${root}/private-runtime/credentials.json`)).customer;
    const jobs = [];
    for (const quantity of [20, 20, 17]) {
      const client = await request.newContext({baseURL});
      contexts.push(client);
      let response = await client.get('/login');
      let csrf = (await response.text()).match(/name="_token" value="([^"]+)"/)[1];
      response = await client.post('/login', {form: {...credential, _token: csrf}, maxRedirects: 0});
      assert.equal(response.status(), 302);
      response = await client.get('/koleksi');
      csrf = (await response.text()).match(/name="csrf-token" content="([^"]+)"/)[1];
      const headers = {'X-CSRF-TOKEN': csrf, Accept: 'application/json'};
      response = await client.post('/addresses', {headers, data: {label:'Synthetic', recipient_name:'Acceptance', phone:'0800000000', line1:'Synthetic test address', city:'Jakarta', province:'DKI Jakarta', postal_code:'12345'}});
      assert.equal(response.status(), 201);
      const address = (await response.json()).address.id;
      assert.equal((await client.post('/cart/items', {headers, data:{product_id:productId, quantity}})).status(), 200);
      jobs.push({client, headers, quantity, address});
    }
    results['AM-10'] = {status: 'PENDING', requests: await Promise.all(jobs.map(async (job, i) => {
      const start = new Date().toISOString();
      const response = await job.client.post('/checkout', {headers:job.headers, data:{address_id:job.address, idempotency_key:`acceptance-parallel-${i}`}});
      const status = response.status();
      const body = await response.json();
      return {quantity:job.quantity, start, finish:new Date().toISOString(), httpStatus:status, orderId:body.order?.id ?? null};
    }))};
    assert(results['AM-10'].requests.every(r => [201,422].includes(r.httpStatus)), 'Unexpected checkout status');
    results['AM-10'].status = 'PASS_HTTP';
  } finally {
    fs.writeFileSync(output, JSON.stringify(results, null, 2)+'\n');
    for (const context of contexts) await context.dispose();
    await browser.close();
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
