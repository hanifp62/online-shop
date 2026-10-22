// NFR-CICD-004: execute the actual producer with mocked Playwright/HTTP/files only.
import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../scripts/amadis/acceptance_isolated.cjs', import.meta.url), 'utf8');
const nativeRequire = createRequire(import.meta.url);
const privateMarker = 'synthetic-private@example.test';
const slug = 'amadis-demo-stock-37';
const productId = 17; // Deliberately not the first catalog card.

async function acceptance(scenario = 'normal') {
    const state = { report: null, errors: [], events: [], registrations: 0, logins: 0,
        credentialReads: 0, stock: 37, users: [], disposed: 0, closed: false };
    const carts = new Map();
    const emails = new Set();
    let pageNumber = 1, contexts = 0, orderId = 900;
    const reply = (status, body = {}, text = '', location = '/') => ({
        status: () => status, json: async () => structuredClone(body), text: async () => text,
        headers: () => ({ location }),
    });
    const key = user => scenario === 'prepared-mismatch' ? 1 : user;
    const items = user => {
        const item = carts.get(key(user));
        return item ? [{ product_id: item.productId, quantity: item.quantity, available: true,
            product_title: privateMarker }] : [];
    };
    const request = {
        async newContext() {
            const index = ++contexts;
            let user;
            const headersValid = options => {
                assert.equal(options.headers['X-CSRF-TOKEN'], 'session-csrf-' + index);
                assert.equal(options.headers.Accept, 'application/json');
            };
            return {
                async get(path, options) {
                    if (path === '/register' || path === '/login') {
                        return reply(200, {}, '<input name="_token" value="form-csrf">');
                    }
                    if (path === '/koleksi') {
                        return reply(200, {}, '<meta name="csrf-token" content="session-csrf-' + index + '">');
                    }
                    assert.equal(path, '/cart');
                    headersValid(options);
                    state.events.push('cart-read');
                    return reply(200, { items: items(user) });
                },
                async post(path, options) {
                    if (path === '/login') {
                        state.logins++;
                        user = 101;
                        return reply(302);
                    }
                    if (path === '/register') {
                        state.registrations++;
                        assert.equal(options.maxRedirects, 0);
                        assert.equal(options.form._token, 'form-csrf');
                        assert.ok(options.form.password === options.form.password_confirmation);
                        assert.ok(/(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^a-zA-Z0-9]).{8,}/.test(options.form.password));
                        assert.ok(!emails.has(options.form.email), 'Registration identities must differ.');
                        emails.add(options.form.email);
                        user = scenario === 'shared-identity' ? 101 : 100 + index;
                        return reply(302, {}, '', scenario === 'register-challenge' ? '/login/totp' : '/');
                    }
                    headersValid(options);
                    if (path === '/addresses') {
                        state.users.push(user);
                        return reply(201, { address: { id: index, user_id: user, line1: privateMarker } });
                    }
                    if (path === '/cart/items') {
                        state.events.push('cart-set');
                        carts.set(key(user), { productId: options.data.product_id, quantity: options.data.quantity });
                        return reply(200, { items: items(user) });
                    }
                    assert.equal(path, '/checkout');
                    assert.equal(options.data.address_id, index);
                    state.events.push('checkout');
                    if (scenario === 'checkout-403') return reply(403, { message: privateMarker });
                    const current = items(user)[0];
                    if (!current || current.quantity > state.stock) return reply(422, { message: privateMarker });
                    state.stock -= current.quantity;
                    carts.delete(key(user));
                    const quantity = scenario === 'success-mismatch' ? current.quantity + 1 : current.quantity;
                    return reply(201, { order: { id: ++orderId, user_id: scenario === 'order-owner-mismatch' ? 999 : user,
                        shipping_address: privateMarker, items: [{ ...current, quantity,
                            product_id: scenario === 'order-product-mismatch' ? 999 : current.product_id }] } });
                },
                async dispose() { state.disposed++; },
            };
        },
    };
    const trigger = { evaluate: async () => true, getAttribute: async () => '1' };
    const page = {
        route: async () => {},
        async goto(url) { pageNumber = Number(new URL(url).searchParams.get('page') || 1); return reply(200); },
        locator(selector) {
            if (selector === ':focus') return { getAttribute: async () => 'dialog-close-btn' };
            assert.equal(selector, '[data-quick-view]');
            return { first: () => trigger, evaluateAll: async callback => callback(
                Array.from({ length: pageNumber === 10 ? 4 : 8 }, (_, offset) => {
                    const id = (pageNumber - 1) * 8 + offset + 1;
                    const stockProduct = id === productId || (scenario === 'duplicate-product' && id === 16);
                    return { dataset: { productId: String(id),
                        slug: stockProduct && scenario !== 'missing-product' ? slug : 'other-' + id,
                        stock: String(stockProduct ? (scenario === 'wrong-stock' ? 36 : 37) : 11) } };
                })) };
        },
        keyboard: { press: async () => {} }, waitForFunction: async () => {},
        evaluate: async () => ({ tag: 'BUTTON', inside: true }),
    };
    const playwright = { request, chromium: { launch: async () => ({
        newPage: async () => page, close: async () => { state.closed = true; },
    }) } };
    const process = { env: { AM_PLAYWRIGHT: 'playwright-mock', AM_ACCEPTANCE_URL: 'http://127.0.0.1:8042',
        AMADIS_ROOT: '/synthetic', AM_ACCEPTANCE_OUTPUT: '/synthetic/browser-results.json' }, exitCode: 0 };
    const require = name => {
        if (name === 'playwright-mock') return playwright;
        if (name === 'node:fs') return {
            readFileSync() { state.credentialReads++; return JSON.stringify({ customer: { email: privateMarker, password: 'synthetic' } }); },
            writeFileSync(path, content) { state.report = JSON.parse(content); },
        };
        assert.ok(['node:assert/strict', 'node:crypto'].includes(name));
        return nativeRequire(name);
    };
    await runInNewContext(source, { require, process, URL, console: { error: message => state.errors.push(message) } },
        { timeout: 2000 });
    state.exitCode = process.exitCode;
    return state;
}

test('CICD004: public registration isolates carts and reports observed product/quantities only', async () => {
    const state = await acceptance();
    assert.equal(state.exitCode, 0);
    assert.equal(state.registrations, 3);
    assert.equal(state.logins, 0);
    assert.equal(state.credentialReads, 0);
    assert.equal(new Set(state.users).size, 3);
    assert.equal(state.stock, 0);
    assert.deepEqual(state.events, ['cart-set', 'cart-set', 'cart-set', 'cart-read', 'cart-read', 'cart-read',
        'checkout', 'checkout', 'checkout']);
    const report = state.report['AM-10'];
    assert.deepEqual(report.product, { id: productId, slug, stock: 37 });
    assert.equal(report.status, 'PASS_HTTP');
    assert.equal(new Set(report.requests.map(item => item.actor)).size, 3);
    for (const [index, item] of report.requests.entries()) {
        assert.match(item.actor, /^[a-f0-9]{64}$/);
        assert.deepEqual(item.preparedItems, [{ productId, quantity: [20, 20, 17][index] }]);
        assert.deepEqual(item.orderItems, index === 1 ? null : item.preparedItems);
    }
    assert.ok(!JSON.stringify(state.report).includes(privateMarker));
    assert.deepEqual(state.errors, []);
    assert.equal(state.disposed, 3);
    assert.equal(state.closed, true);
});

test('CICD004: actual producer report satisfies the Python gate contract', async t => {
    const state = await acceptance();
    assert.equal(state.exitCode, 0);
    const directory = mkdtempSync(join(tmpdir(), 'ci-browser-fixtures-'));
    t.after(() => rmSync(directory, { recursive: true, force: true }));
    const stamp = '2026-10-22T01:00:00Z';
    const steps = ['migrate', 'seed', 'provision', 'am01', 'am06_escape_phpunit', 'browser', 'am10_db'];
    writeFileSync(join(directory, 'browser-results.json'), JSON.stringify(state.report));
    writeFileSync(join(directory, 'invariants.json'), JSON.stringify({
        'AM-01_db_invariant': 1, 'AM-10_db_invariant': 1, browser_rc: 0, escape_rc: 0,
    }));
    writeFileSync(join(directory, 'commands.tsv'), 'step\tstart_utc\tend_utc\trc\tcommand\n'
        + steps.map(step => `${step}\t${stamp}\t${stamp}\t0\tsynthetic command\n`).join(''));
    const checker = fileURLToPath(new URL('../scripts/ci/check_browser_report.py', import.meta.url));
    const child = spawnSync('python3', ['-B', checker, directory], { encoding: 'utf8', timeout: 10000 });
    assert.equal(child.status, 0);
    assert.equal(child.stderr, '');
    assert.equal(child.stdout.trim().split('\n').length, 1);
    assert.deepEqual(JSON.parse(child.stdout), { status: 'PASS', counts: {
        commands: 7, pages: 10, products: 76, checkout_requests: 3, created: 2, rejected: 1, page_content_leaks: 0,
    } });
});

for (const scenario of ['shared-identity', 'prepared-mismatch', 'success-mismatch', 'order-owner-mismatch',
    'order-product-mismatch', 'missing-product', 'duplicate-product', 'wrong-stock', 'register-challenge', 'checkout-403']) {
    test('CICD004: rejects ' + scenario + ' without exposing HTTP bodies', async () => {
        const state = await acceptance(scenario);
        assert.equal(state.exitCode, 1);
        assert.notEqual(state.report?.['AM-10']?.status, 'PASS_HTTP');
        assert.ok(!JSON.stringify(state.report).includes(privateMarker));
        assert.deepEqual(state.errors, ['Acceptance failed; diagnostics withheld.']);
        if (['shared-identity', 'prepared-mismatch', 'register-challenge'].includes(scenario)) {
            assert.ok(!state.events.includes('checkout'));
        }
        if (['missing-product', 'duplicate-product', 'wrong-stock'].includes(scenario)) {
            assert.equal(state.registrations, 0);
        }
    });
}
