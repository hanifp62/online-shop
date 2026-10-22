// NFR-CICD-001..005: enforcement, immutable actions, and mandatory integration gates.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, mkdtempSync, writeFileSync, chmodSync, existsSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';

const read = path => readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');
const workflow = () => read('.github/workflows/ci.yml');

test('CICD: required php check waits for every mandatory job and rejects skipped jobs', () => {
    const gate = workflow().split('\n  php:\n')[1];
    assert.ok(gate);
    assert.match(gate, /needs: \[quality, mysql, browser, release\]/);
    assert.match(gate, /if: \$\{\{ always\(\) \}\}/);
    for (const name of ['quality', 'mysql', 'browser', 'release']) {
        assert.ok(gate.includes(`needs.${name}.result`));
        assert.ok(gate.includes(`test "$${name.toUpperCase()}" = success`));
    }
});

test('CICD: immutable Actions and least privilege on every checkout', () => {
    const source = workflow();
    const actions = [...source.matchAll(/uses:\s+([^\s]+)/g)].map(match => match[1]);
    assert.ok(actions.length >= 8);
    for (const action of actions) assert.match(action, /^[\w-]+\/[\w-]+@[a-f0-9]{40}$/);
    assert.equal([...source.matchAll(/uses: actions\/checkout@/g)].length,
        [...source.matchAll(/persist-credentials: false/g)].length);
    assert.match(source, /permissions:\n  contents: read/);
    assert.doesNotMatch(source, /pull_request_target|continue-on-error/);
});

test('CICD: scanners and test results use canonical fail-closed gates', () => {
    const source = workflow();
    for (const command of ['security_scan.py --mode secrets', 'security_scan.py --mode pii',
        'run_python_tests.py', 'check_test_report.py', '--summary-json', '--log-junit=']) {
        assert.ok(source.includes(command), command);
    }
    assert.doesNotMatch(source, /if grep -RInE/);
    assert.ok(source.includes('scripts/ci/requirements.txt'));
    assert.ok(source.includes('poppler-utils'));
});

test('CICD: MySQL restore, supervised browser, and verified artifact are mandatory', () => {
    const source = workflow();
    for (const command of ['mysql_integration.py --output', 'acceptance_isolated.sh',
        'check_browser_report.py', 'release_artifact.py build', 'release_artifact.py verify']) {
        assert.ok(source.includes(command), command);
    }
    assert.match(source, /needs: \[quality, mysql, browser\]/);
    assert.match(source, /if-no-files-found: error/);
    assert.doesNotMatch(source, /migrate:fresh|db:wipe/);
});

test('CICD: protection binds check to GitHub Actions and keeps independent review', () => {
    const payload = JSON.parse(read('scripts/ci/protect-main.sh').match(/payload='([\s\S]*?)'/)[1]);
    assert.deepEqual(payload.required_status_checks.checks, [{ context: 'php', app_id: 15368 }]);
    assert.equal(payload.required_status_checks.strict, true);
    assert.equal(payload.enforce_admins, true);
    assert.equal(payload.required_pull_request_reviews.require_last_push_approval, true);
    assert.equal(payload.required_pull_request_reviews.dismiss_stale_reviews, true);
    assert.equal(payload.required_pull_request_reviews.required_approving_review_count, 1);
});

function protection(env, reviewerId = null) {
    const directory = mkdtempSync(join(tmpdir(), 'ci-protection-'));
    const marker = join(directory, 'gh-called');
    const putMarker = join(directory, 'put-called');
    const stub = reviewerId === null ? 'exit 90' : `
case "$*" in
  'api user --jq .id') printf '7\\n' ;;
  *'/permission --jq '*) printf '${reviewerId}\\tadmin\\n' ;;
  *'/branches/main --jq '*) printf 'false\\n' ;;
  *'/check-runs --jq '*) printf '1\\n' ;;
  'api -X PUT '*) printf called > '${putMarker}' ;;
  *'/protection --jq '*) printf 'true\\n' ;;
  *) exit 90 ;;
esac`;
    writeFileSync(join(directory, 'gh'), `#!/bin/sh\nprintf called > '${marker}'\n${stub}\n`);
    chmodSync(join(directory, 'gh'), 0o700);
    try {
        const result = spawnSync('/bin/bash', [resolve('scripts/ci/protect-main.sh')], {
            cwd: resolve('.'), timeout: 3000, encoding: 'utf8',
            env: { PATH: `${directory}:/usr/bin:/bin`, HOME: directory, ...env },
        });
        assert.ifError(result.error);
        return { status: result.status, output: result.stdout, contactedGh: existsSync(marker), mutated: existsSync(putMarker) };
    } finally { rmSync(directory, { recursive: true, force: true }); }
}

test('CICD: protection defaults to offline preview without GitHub discovery', () => {
    for (const env of [{}, { DRY_RUN: '1' }]) {
        const result = protection(env);
        assert.equal(result.status, 0);
        assert.equal(result.contactedGh, false);
        assert.match(result.output, /hanifp62\/online-shop/);
    }
});

test('CICD: canonical account identity prevents case-variant self approval', () => {
    for (const login of ['fixture-owner', 'Fixture-Owner']) {
        const result = protection({ DRY_RUN: '0', APPLY: '1', REVIEWER_LOGIN: login }, '7');
        assert.notEqual(result.status, 0);
        assert.equal(result.mutated, false);
    }
    for (const identity of ['null', '', '0']) {
        const result = protection({ DRY_RUN: '0', APPLY: '1', REVIEWER_LOGIN: 'fixture-reviewer' }, identity);
        assert.notEqual(result.status, 0);
        assert.equal(result.mutated, false);
    }
    const independent = protection({ DRY_RUN: '0', APPLY: '1', REVIEWER_LOGIN: 'fixture-reviewer' }, '8');
    assert.equal(independent.status, 0);
    assert.equal(independent.mutated, true);
});

test('CICD: protection refuses mutation without explicit apply and reviewer', () => {
    for (const env of [{ DRY_RUN: '0' }, { DRY_RUN: '0', APPLY: '1' }, { REPO: '../unexpected' }]) {
        const result = protection(env);
        assert.notEqual(result.status, 0);
        assert.equal(result.contactedGh, false);
    }
});
