// NFR-FE-003: native React tests are mandatory alongside the existing CI gates.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const workflow = readFileSync(new URL('../.github/workflows/ci.yml', import.meta.url), 'utf8');

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
    assert.ok(wrapper.indexOf('step browser python3') < wrapper.indexOf("(root / 'summary.json').open('x')"));
    assert.doesNotMatch(wrapper, /\|\|\s*true|continue-on-error/);
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
