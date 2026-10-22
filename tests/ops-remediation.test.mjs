// REM-20261021 / FR-REM-006: isolated source contracts and stubbed gate decisions.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';

const read = path => readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');
const shell = code => {
    const result = spawnSync('/bin/bash', ['--noprofile', '--norc', '-c', code], {
        env: { PATH: '/usr/bin:/bin', HOME: '/nonexistent' }, timeout: 3000, encoding: 'utf8',
    });
    assert.ifError(result.error);
    return result;
};

test('OPS03: migration manifest has no fixed count; database boundaries remain strict', () => {
    const source = read('tests/TestCase.php');
    assert.doesNotMatch(source, /count\(\$expected\)\s*[!=<>]+\s*\d+/);
    for (const invariant of ["$applied !== $expected", "$engines !== ['innodb']", "'zafira_upgrade13_phpunit_20261015_0420'", "':memory:'", "isset($connection['read'])", "isset($connection['write'])", "getDriverName() !== 'mysql'", "getDatabaseName() !== $schema", "select database() as db", "RefreshDatabaseState::$migrated = true"]) {
        assert.ok(source.includes(invariant), `Missing boundary: ${invariant}`);
    }
    for (const command of ['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe']) {
        assert.ok(source.includes(`'${command}'`), `Missing destructive guard: ${command}`);
    }
});

test('OPS04: real lock fixture uses the approved 24-hex root format without weakening launcher', () => {
    const fixture = read('tests/python/test_amadis_lock.py');
    const launcher = read('scripts/amadis/launch.py');
    assert.ok(launcher.includes("re.fullmatch(r'amadis-[a-f0-9]{24}', root.name)"));
    const match = fixture.match(/self\.root\s*=\s*BASE\s*\/\s*\(['"]([^'"]+)['"]\s*\+\s*secrets\.token_hex\((\d+)\)\)/);
    assert.ok(match, 'Fixture root generator must be inspectable');
    assert.match(match[1] + 'a'.repeat(Number(match[2]) * 2), /^amadis-[a-f0-9]{24}$/);
});

test('OPS05: escaping failure reaches final acceptance decision (all other gates succeed)', () => {
    const source = read('scripts/amadis/acceptance_isolated.sh');
    const escape = source.match(/^step am06_escape_phpunit[^\n]*(?:\n(?!\s*$|scripts\/amadis\/launch.sh serve)[A-Z_]+=[^\n]*)?/m)?.[0];
    const decision = source.slice(source.lastIndexOf('\n[ ') + 1).trim();
    assert.ok(escape && decision.startsWith('[ '), 'Locate escape step and final decision');
    for (const rc of [0, 17]) {
        const result = shell(`step() { return ${rc}; }\nAM01=1; AM10=1; BROWSER_RC=0\n${escape}\n${decision}`);
        assert.equal(result.status === 0, rc === 0, `escape rc=${rc} must determine gate; stderr=${result.stderr}`);
    }
});

test('OPS05: anti-drift rejects status command failure, Pending, and accepts clean output', () => {
    const workflow = read('.github/workflows/ci.yml');
    const block = workflow.match(/- name: Gerbang anti-drift[^\n]*\n\s+run: \|\n([\s\S]*?)(?=\n      -)/)?.[1];
    assert.ok(block, 'Anti-drift step must exist');
    const code = block.split('\n').map(line => line.replace(/^          /, '')).join('\n');
    for (const [rc, output, expected] of [[0, 'Ran', true], [0, 'Pending', false], [19, '', false]]) {
        const result = shell(`set -eo pipefail\nphp() { printf '%s\\n' '${output}'; return ${rc}; }\ngrep() { printf 'unexpected grep diagnostic failure' >&2; return 2; }\n${code}`);
        assert.equal(result.status === 0, expected, `migrate:status rc=${rc}, output=${output}`);
        assert.doesNotMatch(result.stderr, /unexpected grep/, 'Shell match must not depend on grep succeeding');
    }
});

test('OPS06: acceptance cleanup never kills processes by port or broad command pattern', () => {
    assert.doesNotMatch(read('scripts/amadis/acceptance_isolated.sh'), /\bpkill\s+-f\b|\bfuser\b[^\n]*-k|\blsof\b[^\n]*\|[^\n]*\bkill\b/);
});

test('OPS08: declared Node range excludes 22.0–22.11 and matches lock root metadata', () => {
    const pkg = JSON.parse(read('package.json'));
    const lock = JSON.parse(read('package-lock.json'));
    assert.match(pkg.engines.node, /(?:>=|\^)22\.12(?:\.0)?/);
    assert.equal(lock.packages[''].engines.node, pkg.engines.node);
});

test('OPS09: protection requires independent approval and applies to admins', () => {
    const source = read('scripts/ci/protect-main.sh');
    const payload = JSON.parse(source.match(/payload='([\s\S]*?)'/)[1]);
    assert.ok(payload.required_pull_request_reviews.required_approving_review_count >= 1);
    assert.equal(payload.enforce_admins, true);
    assert.equal(payload.allow_force_pushes, false);
    assert.equal(payload.allow_deletions, false);
    assert.equal(payload.required_status_checks.strict, true);
    assert.ok(payload.required_status_checks.checks.some(check => check.context === 'php' && check.app_id === 15368));
});

test('FR-REM-006: auth-event retention is scheduled daily', () => {
    assert.match(read('routes/console.php'), /Schedule::command\(['"]auth-events:prune['"]\)->daily\(\)/);
});

test('OPS05 R1: invariant write, identity copy and checksum failure each block acceptance', () => {
    const source = read('scripts/amadis/acceptance_isolated.sh');
    const artifacts = source.slice(source.indexOf(`printf '{"AM-01_db_invariant"`));
    assert.ok(artifacts.startsWith('printf '));
    const directory = mkdtempSync(join(tmpdir(), 'rem-artifacts-'));
    try {
        for (const failure of ['none', 'write', 'copy', 'checksum']) {
            const result = shell(`set -u
OUT='${directory}'; ROOT='${directory}'
AM01=1; AM10=1; BROWSER_RC=0; ESCAPE_RC=0
printf() { ${failure === 'write' ? 'return 31' : 'builtin printf "$@"'}; }
cp() { return ${failure === 'copy' ? 32 : 0}; }
shasum() { return ${failure === 'checksum' ? 33 : 0}; }
${artifacts}`);
            assert.equal(result.status === 0, failure === 'none', `artifact fault=${failure}, stderr=${result.stderr}`);
        }
    } finally {
        rmSync(directory, { recursive: true, force: true });
    }
});

test('OPS05 R1: step log failure cannot be hidden by a successful command', () => {
    const source = read('scripts/amadis/acceptance_isolated.sh');
    const step = source.slice(source.indexOf('step() {'), source.indexOf('\nROOT='));
    const directory = mkdtempSync(join(tmpdir(), 'rem-step-'));
    try {
        const result = shell(`OUT='${directory}'; LOG='${directory}/commands.tsv'\n${step}\nprintf() { return 37; }\nstep probe true`);
        assert.notEqual(result.status, 0, 'commands.tsv write failure must fail the step');
        const redirect = shell(`OUT='${directory}/missing'; LOG='${directory}/commands.tsv'\n${step}\nstep probe true`);
        assert.notEqual(redirect.status, 0, 'command output artifact open failure must fail the step');
    } finally {
        rmSync(directory, { recursive: true, force: true });
    }
});

test('FR-REM-003 R1: console uses raw retention and delegates dead-letter resolution', () => {
    const source = read('routes/console.php');
    assert.ok(source.includes("$service->prune(config('backup.keep'))"));
    const replay = source.split("Artisan::command('payments:replay-dead-letters'")[1]
        .split("Artisan::command('payments:prune-dead-letters'")[0];
    assert.doesNotMatch(replay, /markResolved|update\(\[['"]resolved_at/);
    assert.match(replay, /PaymentDeadLetter::eligibleForReplay\(\)/, 'Selection must use centralized replay eligibility');
    assert.match(replay, /->limit\(PaymentDeadLetter::REPLAY_BATCH_SIZE\)/, 'Replay batch must remain bounded');
    assert.match(replay, /\$status\s*=\s*\$service->replay\(\$letter\)/, 'Service owns replay accounting and resolution');
    assert.doesNotMatch(replay, /\$service->handle\s*\(/, 'Console must not bypass centralized replay');
    assert.match(replay, /if\s*\(\$status\s*===\s*null\)\s*\{\s*return;/, 'Stale observations must not be counted as replayed');
    assert.match(replay, /\$current\s*=\s*\$letter->fresh\(\)/, 'Quarantine must inspect current state');
    assert.match(replay, /\$current\s*&&\s*\$current->resolved_at\s*===\s*null\s*&&\s*\$current->attempts\s*>=\s*PaymentDeadLetter::MAX_REPLAY_ATTEMPTS/, 'Quarantine reporting must require an unresolved hold at the shared attempt limit');
    assert.ok(source.includes('(new ConfigurationUrlParser)->parseConfiguration('));
    assert.ok(source.includes("($backupConnection['driver'] ?? null) === 'sqlite'"));
});

test('OPS04: CI executes Python regression tests', () => {
    assert.match(read('.github/workflows/ci.yml'), /python3 scripts\/ci\/run_python_tests\.py/);
    const gate = read('scripts/ci/run_python_tests.py');
    assert.match(gate, /discover\(/);
    assert.match(gate, /skipped/);
});
