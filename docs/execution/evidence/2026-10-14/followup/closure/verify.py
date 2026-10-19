#!/usr/bin/env python3
"""Execute isolated closure checks; operational configuration is never loaded."""
import hashlib
import json
import os
from pathlib import Path
import shutil
from run import run, OUT

REPO = OUT.parents[5]
# Every rerun needs its own root and its own evidence directory; nothing is reused or overwritten.
ROOT = Path(os.environ['CLOSURE_ROOT'])
RUN = os.environ['CLOSURE_RUN']
BASE = Path('/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode')
if not ROOT.exists() or not ROOT.is_dir() or ROOT.parent != BASE or ROOT.resolve() != ROOT or not ROOT.name.startswith('zafira-manual-'):
    raise ValueError('Invalid isolated root')
if not RUN.startswith('checked-') or Path(RUN).name != RUN:
    raise ValueError('Invalid evidence directory')
env = os.environ.copy()
for key in list(env):
    if key.startswith(('APP_', 'DB_', 'DATABASE_', 'LARAVEL_', 'SESSION_', 'CACHE_', 'QUEUE_', 'MAIL_', 'REDIS_', 'MEMCACHED_', 'AWS_', 'FILESYSTEM_', 'BROADCAST_', 'LOG_', 'AMADIS_', 'PHP_', 'VIEW_', 'ADMIN_SEED_', 'MANUAL_CUSTOMER_')) or key in ('PHPRC', 'XDEBUG_CONFIG', 'XDEBUG_MODE'):
        del env[key]
env.update(MANUAL_ROOT=str(ROOT), MANUAL_PORT='8018')

def hashes(base):
    paths = []
    for directory in ('app', 'resources', 'routes', 'tests', 'config', 'database/migrations', 'scripts/manual'):
        paths.extend(p for p in (base / directory).rglob('*') if p.is_file())
    return {str(p.relative_to(base)): hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted(paths)}


def manifest_hashes():
    result = {}
    for line in (ROOT / 'private-runtime/source-manifest.sha256').read_text().splitlines():
        digest, rel = line.split('  ', 1)
        result[rel.removeprefix('./')] = digest
    return result


def require_returncode(label, command, cwd, command_env):
    status = run(label, command, cwd, command_env)
    if status != 0:
        raise RuntimeError(f'{label} returned {status}; see recorded stdout/stderr')

if __name__ == '__main__':
    # Preserve every prior attempt; all new evidence goes into a fresh directory.
    OUT = OUT / RUN
    OUT.mkdir(exist_ok=False)
    import run as recorder
    recorder.OUT = OUT
    manifest = manifest_hashes()
    (OUT / 'setup-manifest.sha256').write_bytes((ROOT / 'private-runtime/source-manifest.sha256').read_bytes())
    comparisons = []
    for rel, expected in manifest.items():
        values = {}
        for name, base in [('repo', REPO), ('isolated', ROOT)]:
            path = base / rel
            if not path.is_file():
                raise RuntimeError(f'Manifest path missing in {name}: {rel}')
            values[name] = hashlib.sha256(path.read_bytes()).hexdigest()
        comparisons.append(dict(path=rel, expected=expected, **values, match=all(v == expected for v in values.values())))
    (OUT / 'setup-match.json').write_text(json.dumps(comparisons, indent=2))
    if not comparisons or not all(row['match'] for row in comparisons):
        raise RuntimeError('Setup manifest mismatch')
    before = hashes(REPO)
    isolated = hashes(ROOT)
    (OUT / 'isolated-pre.json').write_text(json.dumps(isolated, indent=2))
    if before != isolated:
        raise RuntimeError('Relevant repo/isolated source mismatch')
    (OUT / 'source-pre.json').write_text(json.dumps(before, indent=2))
    for label, cmd in [('php-version', ['php', '--version']), ('node-version', ['node', '--version']), ('npm-version', ['npm', '--version'])]:
        require_returncode(label, cmd, ROOT, env)
    require_returncode('preflight', ['python3', 'scripts/manual/preflight.py', 'fixture'], ROOT, env)
    require_returncode('fixture-1', ['bash', 'scripts/manual/fixture.sh'], ROOT, env)
    require_returncode('fixture-2', ['bash', 'scripts/manual/fixture.sh'], ROOT, env)
    # PHPUnit overrides local disposable DB with in-memory SQLite; only isolated .env can be read.
    testenv = env | {'APP_ENV': 'testing', 'APP_URL': 'http://localhost', 'DB_CONNECTION': 'sqlite', 'DB_DATABASE': ':memory:', 'DB_URL': '', 'CACHE_STORE': 'array', 'SESSION_DRIVER': 'array'}
    require_returncode('php-tests', ['php', 'vendor/bin/phpunit'], ROOT, testenv)
    tests = sorted(str(p.relative_to(ROOT)) for p in (ROOT / 'tests').glob('*.test.mjs'))
    require_returncode('node-tests', ['node', '--test', *tests], ROOT, env)
    shutil.copytree(REPO / 'node_modules', ROOT / 'node_modules', dirs_exist_ok=True)
    require_returncode('build', ['npm', 'run', 'build'], ROOT, env)
    after = hashes(REPO)
    isolated_after = hashes(ROOT)
    (OUT / 'source-post.json').write_text(json.dumps(after, indent=2))
    (OUT / 'isolated-post.json').write_text(json.dumps(isolated_after, indent=2))
    if before != after or before != isolated_after:
        raise RuntimeError('Source drift during closure')
    returncodes = {
        name: json.loads((OUT / f'{name}.json').read_text())['returncode']
        for name in ('php-version', 'node-version', 'npm-version', 'preflight', 'fixture-1', 'fixture-2', 'php-tests', 'node-tests', 'build')
    }
    (OUT / 'returncodes.json').write_text(json.dumps(returncodes, indent=2))
    if any(code != 0 for code in returncodes.values()):
        raise RuntimeError('A required subprocess returned non-zero')
    (OUT / 'profile.json').write_text(json.dumps({
        'root': str(ROOT), 'port': 8018, 'database': 'isolated SQLite; PHPUnit :memory:',
        'operational_env_read': False, 'relevant_source_files': len(before),
        'setup_manifest_entries': len(manifest), 'setup_manifest_mismatches': 0,
        'node_test_files': len(tests), 'node_test_paths': tests, 'drift': 0,
    }, indent=2))
