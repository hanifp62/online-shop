// VERIFY-CLOSE: execute real harness helpers with mock browser/child boundaries only.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { runInNewContext } from 'node:vm';
import { webcrypto } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import complete from '../scripts/amadis/acceptance_react_complete.cjs';

const require = createRequire(import.meta.url);
const origin = 'http://127.0.0.1:8043';
const signedURL = origin + '/pengingat-keranjang/berhenti/7?signature=' + 'b'.repeat(64);
const privateFixture = { schema: 1, origin, customer_id: 7, order_id: 31, product_id: 17, unsubscribe_url: signedURL };
const credentials = { customer: { password: 'synthetic-customer-private' }, admin: { password: 'synthetic-admin-private' } };
const channelSource = readFileSync(new URL('../scripts/amadis/acceptance_react_fixture.cjs', import.meta.url), 'utf8');
const securitySource = readFileSync(new URL('../scripts/amadis/acceptance_react_security.cjs', import.meta.url), 'utf8');

function channel(child) {
    const module = { exports: {} }, calls = [];
    runInNewContext(channelSource, { module, __dirname: '/synthetic/harness', process: { env: {
        PATH: '/usr/bin', AMADIS_ROOT: '/synthetic/private', UNRELATED_SECRET: 'do-not-inherit',
    } }, require(name) {
        if (name === 'node:child_process') return { spawnSync(command, argv, options) {
            calls.push({ command, argv, options });
            return child(argv.at(-1));
        } };
        return require(name);
    } });
    return { load: module.exports, calls };
}
const reply = value => ({ status: 0, signal: null, stderr: '', stdout: JSON.stringify(value) });

test('Node captured stdout satisfies the private pipe transport guard', () => {
    const child = spawnSync('python3', ['-B', '-c',
        'import os,stat,sys; mode=os.fstat(sys.stdout.fileno()).st_mode; print(int(stat.S_ISFIFO(mode) or stat.S_ISSOCK(mode)))'],
    { encoding: 'utf8', timeout: 5000, stdio: ['ignore', 'pipe', 'pipe'] });
    assert.equal(child.status, 0);
    assert.equal(child.stdout, '1\n');
    assert.equal(child.stderr, '');
});

test('private channel pins argv, bounds child output and never forwards unrelated environment', () => {
    const runner = channel(phase => reply(phase === 'load' ? { status: 'PASS', fixture: privateFixture } : { status: 'PASS' }));
    const fixture = runner.load(origin);
    fixture.verify('review-after');
    assert.equal(fixture.unsubscribe_url, signedURL);
    assert(Object.isFrozen(fixture));
    for (const call of runner.calls) {
        assert.equal(call.command, 'python3');
        assert.deepEqual([...call.argv.slice(0, 2)], ['-B', '/synthetic/harness/acceptance_react_fixture.py']);
        assert.equal(call.options.timeout, 5000);
        assert.equal(call.options.maxBuffer, 16384);
        assert.deepEqual([...call.options.stdio], ['ignore', 'pipe', 'pipe']);
        assert.deepEqual(Object.keys(call.options.env).sort(), ['AMADIS_ROOT', 'AM_ACCEPTANCE_URL', 'PATH']);
        assert(!JSON.stringify(call.argv).includes('signature'));
    }
});

for (const [name, child] of [
    ['failed', { ...reply({ status: 'PASS', fixture: privateFixture }), status: 1 }],
    ['timeout', { ...reply({}), error: new Error(signedURL) }],
    ['signal', { ...reply({}), signal: 'SIGTERM' }],
    ['stderr', { ...reply({ status: 'PASS', fixture: privateFixture }), stderr: signedURL }],
    ['malformed', { ...reply({}), stdout: signedURL }],
    ['extra', reply({ status: 'PASS', fixture: privateFixture, secret: signedURL })],
    ['wrong-schema', reply({ status: 'PASS', fixture: { ...privateFixture, schema: true } })],
    ['wrong-origin', reply({ status: 'PASS', fixture: { ...privateFixture, origin: 'https://example.test' } })],
    ['wrong-user', reply({ status: 'PASS', fixture: { ...privateFixture, customer_id: 8 } })],
    ['wrong-url', reply({ status: 'PASS', fixture: { ...privateFixture, unsubscribe_url: signedURL + '&extra=1' } })],
]) {
    test('private channel rejects ' + name + ' with fixed non-secret error', () => {
        assert.throws(() => channel(() => child).load(origin), error => error.message === 'react_fixture_rejected'
            && !error.stack.includes(signedURL));
    });
}

async function reviewScenario(scenario = 'normal') {
    const phases = [], navigations = [], listeners = new Map();
    let saved = false, detached = false;
    const payload = { order_id: scenario === 'wrong-order' ? 32 : 31, product_id: scenario === 'wrong-product' ? 18 : 17,
        rating: 5, body: 'Synthetic acceptance review' };
    const request = { url: () => origin + '/reviews', method: () => 'POST', postDataJSON: () => payload };
    const response = { url: request.url, request: () => request, status: () => scenario === 'rejected' ? 422 : 201,
        json: async () => ({ review: { ...payload, user_id: scenario === 'wrong-owner' ? 8 : 7 } }) };
    const form = {
        waitFor: async options => { if (scenario === 'missing' || (options.state === 'detached' && !detached)) throw new Error('missing_form'); },
        count: async () => scenario === 'duplicate' ? 2 : 1,
        locator(selector) {
            if (selector === '[name="rating"]') return { selectOption: async value => assert.equal(value, '5') };
            if (selector === '[name="body"]') return { fill: async value => assert.equal(value, payload.body) };
            assert.equal(selector, 'button');
            return { click: async () => {
                listeners.get('request')(request);
                if (scenario === 'double-post') listeners.get('request')(request);
                detached = true; saved = scenario !== 'no-persistence';
            } };
        },
    };
    const customer = {
        locator: selector => { assert.equal(selector, '#order-list form:has([name="rating"])'); return form; },
        on: (name, fn) => listeners.set(name, fn), off: name => listeners.delete(name),
        waitForResponse: async predicate => { assert(predicate(response)); return response; },
    };
    const fixture = { ...privateFixture, verify(phase) { phases.push(phase); assert.equal(saved, phase === 'review-after'); } };
    try {
        await complete.completedOrderReview({ customer, origin, fixture, native: async (_, path) => navigations.push(path) });
    } finally {
        assert.equal(listeners.size, 0);
    }
    return { phases, navigations };
}

test('completed-order review uses real form submission, exact ownership and independent state oracle', async () => {
    assert.deepEqual(await reviewScenario(), { phases: ['review-before', 'review-after'], navigations: ['/belanja'] });
});
for (const scenario of ['missing', 'duplicate', 'wrong-order', 'wrong-product', 'wrong-owner', 'rejected', 'double-post', 'no-persistence']) {
    test('completed-order review fails closed: ' + scenario, async () => { await assert.rejects(reviewScenario(scenario)); });
}

async function signedScenario(scenario = 'normal') {
    const phases = [], navigations = [], listeners = new Map();
    let optedIn = true, completed = false, posts = 0;
    const keyBytes = new Uint8Array(32).fill(3), iv = new Uint8Array(12).fill(4);
    const key = await webcrypto.subtle.importKey('raw', keyBytes, 'AES-GCM', false, ['encrypt']);
    const clear = JSON.stringify({ component: 'Account/CartReminderUnsubscribe', encryptHistory: true,
        props: scenario === 'privacy-leak' ? { private: credentials.customer.password } : { done: false } });
    const cipher = new Uint8Array(await webcrypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, new TextEncoder().encode(clear)));
    const entry = new Uint8Array(iv.length + cipher.length); entry.set(iv); entry.set(cipher, iv.length);
    const module = { exports: {} };
    runInNewContext(securitySource, { module, require, URL, Uint8Array, ArrayBuffer, TextDecoder,
        crypto: webcrypto, history: { state: { page: entry.buffer } }, localStorage: {},
        sessionStorage: { getItem: () => JSON.stringify([...keyBytes]) } });
    const frame = {};
    const request = method => ({ url: () => signedURL, method: () => method, isNavigationRequest: () => true,
        frame: () => frame, headers: () => ({}) });
    const response = method => ({ request: () => request(method), status: () => scenario === 'post-403' ? 403 : 200,
        fromServiceWorker: () => false, headers: () => ({ 'cache-control': scenario === 'cacheable' ? 'public' : 'no-store, private' }) });
    const form = {
        count: async () => scenario === 'missing-form' ? 0 : 1,
        getAttribute: async name => name === 'method' ? (scenario === 'get-form' ? 'GET' : 'POST')
            : scenario === 'wrong-action' ? origin + '/akun' : signedURL,
        locator(selector) {
            if (selector === '[name="_token"]') return { count: async () => scenario === 'missing-csrf' ? 0 : 1 };
            assert.equal(selector, 'button[type="submit"]');
            return { click: async () => {
                posts += 1;
                listeners.get('request')(request('POST'));
                if (scenario === 'double-post') listeners.get('request')(request('POST'));
                if (scenario !== 'no-persistence') optedIn = false;
                completed = true;
            } };
        },
    };
    const customer = {
        on: (name, fn) => listeners.set(name, fn), off: name => listeners.delete(name), mainFrame: () => frame,
        evaluate: async (callback, values) => callback(values),
        waitForFunction: async callback => assert(callback()), waitForEvent: async name => assert.equal(name, 'domcontentloaded'),
        waitForResponse: async predicate => { const result = response('POST'); assert(predicate(result)); return result; },
        locator(selector) {
            if (selector === 'form[data-auth-form]') return form;
            if (selector === 'script[data-page="app"]') return { count: async () => 0 };
            if (selector === 'main form') return { count: async () => completed ? 0 : 1 };
            assert(['#app[data-react-boot="ready"]', 'main [role="status"]'].includes(selector));
            return { waitFor: async () => assert(completed) };
        },
    };
    const fixture = { ...privateFixture, verify(phase) {
        phases.push(phase);
        assert.equal(optedIn, phase !== 'unsubscribe-after');
    } };
    try {
        await module.exports.signedUnsubscribe({ customer, origin, fixture, credentials, native: async (_, path) => {
            navigations.push(path); completed = false;
            listeners.get('request')(request('GET'));
            if (scenario === 'get-mutates') optedIn = false;
            if (scenario === 'second-get-mutates' && posts === 1) optedIn = true;
            return response('GET');
        } });
    } finally { assert.equal(listeners.size, 0); }
    return { phases, navigations, posts };
}

test('signed unsubscribe traverses native GET/POST twice and checks persistence after each boundary', async () => {
    const result = await signedScenario();
    assert.equal(result.posts, 2);
    assert.deepEqual(result.navigations, [signedURL.slice(origin.length), signedURL.slice(origin.length)]);
    assert.deepEqual(result.phases, ['unsubscribe-before', 'unsubscribe-get', 'unsubscribe-after', 'unsubscribe-after', 'unsubscribe-after']);
});
for (const scenario of ['missing-form', 'get-form', 'wrong-action', 'missing-csrf', 'post-403', 'cacheable',
    'no-persistence', 'get-mutates', 'second-get-mutates', 'double-post', 'privacy-leak']) {
    test('signed unsubscribe fails closed: ' + scenario, async () => { await assert.rejects(signedScenario(scenario)); });
}

test('signed fixture is never injected into history, DOM, screenshots or diagnostic output', () => {
    const block = securitySource.split('async function signedUnsubscribe')[1].split('module.exports = async')[0];
    assert.doesNotMatch(block, /console\.|screenshot\(|setContent\(|addInitScript\(|pushState\(|replaceState\(|\.request\.(get|post)\(/);
    assert.doesNotMatch(securitySource, /a\[href\*="\/pengingat-keranjang/);
    const completeSource = readFileSync(new URL('../scripts/amadis/acceptance_react_complete.cjs', import.meta.url), 'utf8');
    assert.doesNotMatch(completeSource, /if \(await review\.count\(\)\)|unavailable\.push/);
    assert.doesNotMatch(securitySource, /unavailable\.push|unavailable\.splice/);
});
