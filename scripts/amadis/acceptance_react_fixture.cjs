// The load response is a private pipe, never a report or a browser-injected object.
const { spawnSync } = require('node:child_process');
const path = require('node:path');
const PHASES = new Set(['load', 'review-before', 'review-after', 'unsubscribe-before', 'unsubscribe-get', 'unsubscribe-after']);
function invoke(phase, origin) {
    try {
        if (!PHASES.has(phase)) throw new Error();
        const child = spawnSync('python3', ['-B', path.join(__dirname, 'acceptance_react_fixture.py'), phase], {
            env: { PATH: process.env.PATH, AMADIS_ROOT: process.env.AMADIS_ROOT, AM_ACCEPTANCE_URL: origin },
            encoding: 'utf8', timeout: 5000, maxBuffer: 16384, stdio: ['ignore', 'pipe', 'pipe'],
        });
        if (child.status !== 0 || child.error || child.signal || child.stderr !== '') throw new Error();
        const result = JSON.parse(child.stdout);
        const keys = Object.keys(result).sort().join(',');
        if (result.status !== 'PASS' || keys !== (phase === 'load' ? 'fixture,status' : 'status')) throw new Error();
        return result;
    } catch {
        // SQL bindings, signed URLs, child diagnostics and JSON parse snippets stay private.
        throw new Error('react_fixture_rejected');
    }
}
module.exports = function fixtureChannel(origin) {
    const fixture = invoke('load', origin).fixture;
    if (!fixture || Object.keys(fixture).sort().join(',') !== 'customer_id,order_id,origin,product_id,schema,unsubscribe_url'
        || fixture.schema !== 1 || fixture.origin !== origin
        || !['customer_id', 'product_id', 'order_id'].every(key => Number.isSafeInteger(fixture[key]) && fixture[key] > 0)
        || typeof fixture.unsubscribe_url !== 'string') throw new Error('react_fixture_rejected');
    const prefix = origin + '/pengingat-keranjang/berhenti/' + fixture.customer_id + '?signature=';
    if (!fixture.unsubscribe_url.startsWith(prefix) || !/^[a-f0-9]{64}$/.test(fixture.unsubscribe_url.slice(prefix.length))) {
        throw new Error('react_fixture_rejected');
    }
    return Object.freeze({ ...fixture, verify: phase => { invoke(phase, origin); } });
};
