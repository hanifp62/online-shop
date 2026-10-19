#!/usr/bin/env node
/** Isolated browser evidence. Application commands only run through launch.sh. */
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const {spawn, spawnSync} = require('node:child_process');
// Import-safe: unit tests require() this file without AMADIS_ROOT, browser or server.
const isMain = require.main === module;
const repo = path.resolve(__dirname, '../..');
const base = '/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode';
const port = 8016;
const origin = `http://127.0.0.1:${port}`;
const run = 'run-s0s5-' + new Date().toISOString().replace(/[-:.]/g, '');
let root = '', out = '';
if (isMain) {
  root = fs.realpathSync(process.env.AMADIS_ROOT || '');
  assert.equal(path.dirname(root), base);
  assert.match(path.basename(root), /^amadis-[a-f0-9]{24}$/);
  const identity = JSON.parse(fs.readFileSync(path.join(root, 'identity.json')));
  assert.equal(identity.root, root);
  assert.equal(identity.database, path.join(root, 'database.sqlite'));
  out = path.join(root, 'evidence', run);
  fs.mkdirSync(out, {mode: 0o700});
}
const records = [];
let server, browser, phase = 'setup', activePage;
function save(name, value) {
  fs.writeFileSync(path.join(out, name), JSON.stringify(value, null, 2) + '\n', {flag: 'wx', mode: 0o600});
}
function command(args) {
  const p = spawnSync('./scripts/amadis/launch.sh', args, {
    cwd: repo, env: {...process.env, AMADIS_ROOT: root}, encoding: 'utf8', timeout: 30000,
  });
  records.push({command: ['launch.sh', ...args], exit: p.status});
  assert.equal(p.status, 0, 'launcher failed');
  return p.stdout;
}
async function start() {
  // A listener must not exist before we create our own process group.
  try { await fetch(origin, {signal: AbortSignal.timeout(500)}); throw new Error('port occupied'); }
  catch (error) { if (error.message === 'port occupied') throw error; }
  server = spawn('./scripts/amadis/launch.sh', ['serve', String(port)], {
    cwd: repo, env: {...process.env, AMADIS_ROOT: root}, detached: true, stdio: 'ignore',
  });
  const pid = server.pid;
  records.push({command: ['launch.sh', 'serve', String(port)], pid});
  for (let n = 0; n < 80; n++) {
    assert.equal(server.exitCode, null, 'server exited');
    try { if ((await fetch(origin + '/koleksi', {signal: AbortSignal.timeout(1000)})).ok) return; }
    catch {}
    await new Promise(resolve => setTimeout(resolve, 100));
  }
  throw new Error('server not ready');
}
// Signals only the process group we spawned (detached => pgid == pid). No name-based kills.
// Only ESRCH proves the group is gone; EPERM means it still exists.
async function stopOwned(own, {kill = process.kill.bind(process), records: log = [], groupPolls = 100, pollMs = 50} = {}) {
  assert.ok(Number.isInteger(own.pid) && own.pid > 1, 'invalid owned process group');
  try { kill(-own.pid, 'SIGTERM'); } catch (error) { if (error.code !== 'ESRCH') throw error; }
  // The captured PHP child inherits the lock; wait for every member to exit.
  for (let n = 0; n < groupPolls; n++) {
    try { kill(-own.pid, 0); }
    catch (error) {
      if (error.code !== 'ESRCH') throw error;
      log.push({command: ['stop-owned-group', String(own.pid)], exit: 0});
      return;
    }
    await new Promise(resolve => setTimeout(resolve, pollMs));
  }
  throw new Error('owned process group did not stop');
}
async function stop() {
  if (!server) return;
  const own = server;
  server = undefined;
  await stopOwned(own, {records});
}
// Runs every step even if an earlier one rejects; returns the first failure (names only, no messages).
async function cleanup(steps, timeoutMs = 6000) {
  let failure = null;
  for (const [step, fn] of steps) {
    let timer;
    try {
      await Promise.race([
        Promise.resolve().then(fn),
        new Promise((_, reject) => { timer = setTimeout(() => reject(Object.assign(new Error('cleanup timeout'), {name: 'TimeoutError'})), timeoutMs); }),
      ]);
    } catch (error) { failure = failure ?? {step, error_type: error?.name ?? 'Error'}; }
    finally { clearTimeout(timer); }
  }
  return failure;
}
// Login pages may hold typed credentials; never screenshot them.
function isLoginContext(pathname, currentPhase) {
  return /login/.test(currentPhase || '') || /\/login$/.test(pathname || '');
}
// A failed cleanup must never be reported PASS, whatever the flow outcome.
function finalResult(outcome, cleanupError) {
  if (cleanupError) return {status: 'BLOCKED', phase: 'cleanup', ...cleanupError};
  return outcome ?? {status: 'BLOCKED', phase: 'unknown', error_type: 'Error'};
}
function snapshot(name, stock, status) {
  const raw = JSON.parse(command(['snapshot']));
  assert.deepEqual(Object.keys(raw).sort(), ['items', 'order_count', 'orders', 'product']);
  assert.equal(raw.product.stock, stock);
  assert.equal(raw.order_count, status ? 1 : 0);
  if (status) {
    assert.equal(raw.orders[0].status, status);
    assert.equal(Number(raw.orders[0].total), 3750000);
    assert.equal(raw.items[0].quantity, 3);
  }
  save(name + '-snapshot.json', raw);
  return raw;
}
async function shot(page, selector, name) {
  await page.locator(selector).screenshot({path: path.join(out, name + '.png'), animations: 'disabled'});
}
async function ready(page) {
  await page.waitForFunction(() => document.querySelector('#feedback')?.textContent === 'Tas server siap.');
}
async function catalogStock(page, expected) {
  await page.goto(origin + '/koleksi?q=AM-10');
  const card = page.locator('article.catalog-card', {has: page.locator('[data-slug="amadis-demo-stock-37"]')});
  const stockText = (await card.locator('.catalog-card-attrs', {hasText: 'Stok:'}).textContent()).trim();
  assert.equal(stockText, `Stok: ${expected}`);
  return stockText;
}
async function login(page, role, credentials) {
  await page.goto(origin + (role === 'admin' ? '/admin/login' : '/login'));
  await page.locator('#email').fill(credentials[role].email);
  await page.locator('#password').fill(credentials[role].password);
  await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')),
    page.locator('button[type="submit"]').click()]);
}
let outcome;
if (isMain) (async () => {
  const version = spawnSync('/opt/homebrew/bin/playwright-cli', ['--version'], {encoding:'utf8'});
  assert.equal(version.status, 0);
  assert.equal(version.stdout.trim(), '0.1.22');
  const credentialPath = path.join(root, 'private-runtime/credentials.json');
  const fd = fs.openSync(credentialPath, fs.constants.O_RDONLY | fs.constants.O_NOFOLLOW);
  const info = fs.fstatSync(fd);
  assert.equal(info.mode & 0o777, 0o600);
  assert.equal(info.nlink, 1);
  const credentials = JSON.parse(fs.readFileSync(fd, 'utf8'));
  fs.closeSync(fd);
  phase = 'S0';
  snapshot('S0', 37);
  await start();
  const executablePath = path.join(os.homedir(), 'Library/Caches/ms-playwright/chromium-1228/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing');
  const {chromium} = require('/opt/homebrew/lib/node_modules/@playwright/cli/node_modules/playwright');
  browser = await chromium.launch({executablePath, headless: true});
  const context = await browser.newContext({viewport: {width: 1365, height: 1000}, locale: 'id-ID'});
  await context.route('**/*', route => route.request().url().startsWith(origin + '/') ? route.continue() : route.abort());
  const page = activePage = await context.newPage();
  page.setDefaultTimeout(15000);
  await page.goto(origin + '/koleksi?q=AM-10');
  await page.locator('[data-slug="amadis-demo-stock-37"]').waitFor();
  const card = page.locator('article.catalog-card', {has: page.locator('[data-slug="amadis-demo-stock-37"]')});
  await card.scrollIntoViewIfNeeded();
  const stockText = (await card.locator('.catalog-card-attrs', {hasText: 'Stok:'}).textContent()).trim();
  assert.equal(stockText, 'Stok: 37');
  await page.screenshot({path: path.join(out, 'S0-catalog.png'), animations: 'disabled'});
  save('S0-ui.json', {status: 'PASS', rendered: stockText, snapshot_stock: 37});
  phase = 'S1-login';
  await login(page, 'customer', credentials);
  await page.goto(origin + '/koleksi?q=AM-10');
  await page.locator('[data-slug="amadis-demo-stock-37"]').click();
  await page.locator('#dialog-qty').fill('3');
  await page.locator('#dialog-cart-form button').click();
  await page.waitForFunction(() => document.body.textContent.includes('Tas server diperbarui. Jumlah 3'));
  await page.goto(origin + '/belanja');
  await ready(page);
  phase = 'S1-address';
  for (const [name, value] of Object.entries({label:'Alamat Uji', recipient_name:'Klien Sintetis', phone:'081200000000', line1:'Jalan Uji 1', city:'Jakarta', province:'DKI Jakarta', postal_code:'10110'})) {
    await page.locator(`#address-form [name="${name}"]`).fill(value);
  }
  await page.locator('#address-form button').click();
  await page.waitForFunction(() => document.querySelector('#feedback').textContent === 'Alamat tersimpan.');
  await shot(page, '#tas', 'S1-cart');
  const cartTotal = (await page.locator('#cart-total').textContent()).trim();
  const addressValue = await page.locator('#checkout-address').inputValue();
  assert.match(cartTotal, /3\.750\.000/);
  assert.match(addressValue, /^\d+$/);
  assert.equal(await page.locator('#tas input[type="number"]').first().inputValue(), '3');
  save('S1-ui.json', {authenticated:true, quantity:3, cart_total:cartTotal, own_address_selected:true});
  phase = 'S2-checkout';
  await page.locator('#checkout-button').click();
  await page.waitForFunction(() => /Pesanan #\d+ dibuat: pending/.test(document.querySelector('#feedback').textContent));
  const heading = await page.locator('#order-list h3').textContent();
  const orderId = Number(heading.match(/#(\d+)/)[1]);
  assert.match(await page.locator('#order-list').textContent(), /pending.*3\.750\.000/);
  await shot(page, '#pesanan', 'S2-pending');
  save('S2-ui.json', {order_id:orderId, status:'pending', quantity:3, total:3750000});
  await stop();
  const pending = snapshot('S2', 34, 'pending');
  assert.equal(pending.orders[0].id, orderId);
  await start();
  await catalogStock(page, 34);
  await page.goto(origin + '/belanja'); await ready(page);
  phase = 'S3-cancel';
  page.once('dialog', dialog => dialog.accept());
  await page.getByRole('button', {name:'Batalkan pesanan', exact:true}).click();
  await page.waitForFunction(() => document.querySelector('#feedback').textContent === 'Pesanan dibatalkan.');
  await shot(page, '#pesanan', 'S3-cancelled');
  const restoredStock = await catalogStock(page, 37);
  save('S3-catalog.json', {rendered: restoredStock, after_pending_render: 34});
  await stop(); snapshot('S3', 37, 'cancelled'); await start();
  await page.goto(origin + '/belanja'); await ready(page);
  phase = 'S4-replay';
  const replay = await page.evaluate(async id => {
    const r = await fetch(`/orders/${id}/cancel`, {method:'POST', headers:{'Accept':'application/json', 'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}});
    const value = await r.json();
    return {http_status:r.status, order_id:value.order?.id ?? null, status:value.order?.status ?? null};
  }, orderId);
  save('S4-replay.json', replay);
  assert.equal(replay.http_status, 200);
  assert.equal(replay.status, 'cancelled');
  await page.reload(); await ready(page);
  await shot(page, '#pesanan', 'S4-replay');
  await stop(); snapshot('S4', 37, 'cancelled'); await start();
  phase = 'S5-persistence';
  await page.reload(); await ready(page);
  assert.match(await page.locator('#order-list').textContent(), new RegExp(`Pesanan #${orderId}[\\s\\S]*Status: cancelled`));
  await shot(page, '#pesanan', 'S5-history');
  await login(page, 'admin', credentials);
  const panel = page.locator('#panel-pesanan');
  assert.match(await panel.textContent(), new RegExp(`#${orderId}[\\s\\S]*cancelled`));
  await shot(page, '#panel-pesanan', 'S5-admin');
  save('S5-ui.json', {order_id:orderId, status:'cancelled', refreshed:true, launcher_restarted:true, admin_visible:true});
  await stop(); snapshot('S5', 37, 'cancelled');
  // Fresh catalog after the restart must show restocked value, not a warmed stale render.
  await start();
  await page.goto(origin + '/koleksi?q=AM-10');
  const after = (await page.locator('article.catalog-card', {has: page.locator('[data-slug="amadis-demo-stock-37"]')}).locator('.catalog-card-attrs', {hasText: 'Stok:'}).textContent()).trim();
  assert.equal(after, 'Stok: 37');
  await page.screenshot({path: path.join(out, 'S5-catalog.png'), animations: 'disabled'});
  await stop();
  outcome = {status:'PASS', S0:'PASS', S1:'PASS', S2:'PASS', S3:'PASS', S4:'PASS', S5:'PASS', browser:await browser.version(), cli:'0.1.22'};
})().catch(async error => {
  // Never serialize Playwright errors: call logs can contain filled credentials.
  let where = null;
  try {
    where = new URL(activePage.url()).pathname;
    if (!isLoginContext(where, phase)) {
      await activePage.screenshot({path: path.join(out, 'failure.png')});
    }
  } catch {}
  outcome = {status:'BLOCKED', phase, error_type:error.name, path:where};
  process.exitCode = 1;
}).finally(async () => {
  const cleanupError = await cleanup([
    ['browser.close', () => browser?.close()],
    ['stop', stop],
  ]);
  if (cleanupError) process.exitCode = 1;
  const result = finalResult(outcome, cleanupError);
  save('result.json', result);
  save('commands.json', records);
  const hashes = {};
  for (const name of fs.readdirSync(out)) hashes[name] = crypto.createHash('sha256').update(fs.readFileSync(path.join(out,name))).digest('hex');
  save('sha256.json', hashes);
  const exit = process.exitCode || (cleanupError ? 1 : 0);
  console.log(JSON.stringify({evidence:out, exit, cleanupError: cleanupError ?? undefined}));
});

module.exports = {stopOwned, cleanup, isLoginContext, finalResult};
