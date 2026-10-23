// NFR-FE-003: native React tests are mandatory alongside the existing CI gates.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';

const workflow = readFileSync(new URL('../.github/workflows/ci.yml', import.meta.url), 'utf8');
const complete = (await import('../scripts/amadis/acceptance_react_complete.cjs')).default;
const security = (await import('../scripts/amadis/acceptance_react_security.cjs')).default;

test('VERIFY-CLOSE: exactly 31 ordered mandatory stages and no optional escape hatch', () => {
    assert.equal(complete.MANDATORY_ORDER.length, 31);
    assert.equal(new Set(complete.MANDATORY_ORDER).size, 31);
    assert.deepEqual(complete.OPTIONAL_STAGES, []);
    assert.deepEqual(security.OPTIONAL_STAGES, []);
    const checks = complete.MANDATORY_ORDER;
    assert.equal(checks.indexOf('shop-account-completed-order-review'), 17);
    assert.equal(checks.indexOf('signed-unsubscribe-native-confirm-complete'), 23);
    assert.equal(checks[30], 'console-clean');
    const source = readFileSync(new URL('../scripts/amadis/acceptance_react_complete.cjs', import.meta.url), 'utf8');
    assert.deepEqual([...source.matchAll(/start\(STAGES\[(\d+)\]\)/g)].map(match => Number(match[1])),
        Array.from({ length: 11 }, (_, index) => index));
});

function assertReactGate(source) {
    const quality = source.match(/^  quality:\n([\s\S]*?)(?=^  [\w-]+:|$(?![\s\S]))/m)?.[1];
    assert.ok(quality, 'The existing quality job must exist');
    const jobSettings = quality.split('    steps:\n')[0];
    assert.doesNotMatch(jobSettings, /^    (?:if|continue-on-error):/m,
        'The quality job must not be conditional or tolerate failures');
    const steps = quality.split(/^      - /m).slice(1);
    const install = steps.findIndex(step => /^run: npm ci\s*$/.test(step.trim()));
    assert.ok(install >= 0, 'Install locked dependencies before React tests');
    const react = steps.filter(step => /npm run test:react/.test(step));
    assert.equal(react.length, 1, 'Exactly one mandatory React gate must exist in quality');
    assert.match(react[0], /^name: Test React\n        run: npm run test:react\s*$/,
        'React gate must run the exact command without conditions, wrappers or failure suppression');
    assert.equal(steps[install + 1], react[0], 'Run React tests immediately after npm ci');
    assert.match(quality, /run: node --test\s*$/m, 'Preserve the existing Node test gate');
}

test('NFR-FE-003: quality runs mandatory React tests after npm ci', () => {
    assertReactGate(workflow);
});

test('NFR-FE-003: native browser proof runs serially under the existing owned-server supervisor', () => {
    const browser = workflow.split('  browser:\n')[1].split('  release:\n')[0];
    assert.match(browser, /bash scripts\/amadis\/acceptance_react\.sh "\$RUNNER_TEMP\/react-browser-evidence"\n/);
    assert.match(browser, /react-browser-evidence\/summary\.json/);
    const wrapper = readFileSync(new URL('../scripts/amadis/acceptance_react.sh', import.meta.url), 'utf8');
    assert.match(wrapper, /set -euo pipefail/);
    assert.match(wrapper, /step browser python3 scripts\/amadis\/acceptance_server\.py/);
    const publication = 'python3 scripts/ci/check_react_browser_report.py publish "$OUT"';
    assert.ok(wrapper.includes(publication));
    assert.ok(wrapper.indexOf('step browser python3') < wrapper.indexOf(publication));
    assert.match(wrapper, /step provision scripts\/amadis\/launch\.sh provision-react "\$PORT"/);
    const checker = readFileSync(new URL('../scripts/ci/check_react_browser_report.py', import.meta.url), 'utf8');
    assert.ok(checker.includes("(directory / 'summary.json').open('x')"));
    assert.doesNotMatch(wrapper, /\|\|\s*true|continue-on-error/);
});

test('VERIFY-CLOSE: Python report verifier and browser parent share the exact ordered contract', () => {
    const result = spawnSync('python3', ['-B', '-c',
        'import json,sys; sys.path.insert(0,"scripts/ci"); from check_react_browser_report import MANDATORY; print(json.dumps(MANDATORY))'],
    { cwd: new URL('../', import.meta.url), encoding: 'utf8', timeout: 10000 });
    assert.equal(result.status, 0);
    assert.equal(result.stderr, '');
    assert.deepEqual(JSON.parse(result.stdout), complete.MANDATORY_ORDER);
    const parent = readFileSync(new URL('../scripts/amadis/acceptance_react.cjs', import.meta.url), 'utf8');
    assert.ok(parent.indexOf('fixtureChannel(origin)') < parent.indexOf('await chromium.launch'));
    assert.match(parent, /assert\.deepEqual\(checks, completeFlows\.MANDATORY_ORDER\.slice\(0, -1\)\)/);
    assert.match(parent, /assert\.deepEqual\(unavailable, \[\]\)/);
    assert.doesNotMatch(parent, /checks\.filter|optional\.every/);
});

test('NFR-FE-003: contract rejects optional or failure-suppressed React gates', () => {
    const gate = '      - name: Test React\n        run: npm run test:react\n';
    assert.ok(workflow.includes(gate), 'The real gate must exist before testing bypass mutations');
    for (const replacement of [
        '',
        gate.replace('        run:', '        continue-on-error: true\n        run:'),
        gate.replace('        run:', '        if: ${{ false }}\n        run:'),
        gate.replace('test:react\n', 'test:react || true\n'),
        gate.replace('test:react\n', 'test:react; exit 0\n'),
        gate.replace('test:react\n', 'test:react --if-present\n'),
    ]) {
        assert.throws(() => assertReactGate(workflow.replace(gate, replacement)));
    }
    for (const setting of ['if: ${{ false }}', 'continue-on-error: true']) {
        assert.throws(() => assertReactGate(workflow.replace('  quality:\n', `  quality:\n    ${setting}\n`)));
    }
});
