#!/usr/bin/env python3
"""Read-only final source gate; writes evidence and launcher-owned disposable runtime only."""
import datetime
import hashlib
import json
import os
from pathlib import Path
import shlex
import subprocess
import sys
import time
import xml.etree.ElementTree as ET

REPO = Path(__file__).resolve().parents[5]
OUT = Path(__file__).resolve().parent
BASE = Path('/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode')
PYTHON = str(BASE / 'ft-venv/bin/python3')
SOURCE_DIRS = ('app', 'bootstrap', 'config', 'database/migrations', 'database/factories', 'database/seeders', 'routes', 'resources', 'public', 'prototype', 'tests', 'scripts')
SOURCE_FILES = ('composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'phpunit.xml', 'vite.config.js', '.amadis-pdf-dependency.lock')

def stamp():
    return datetime.datetime.now(datetime.timezone.utc).isoformat()

def save(name, value):
    (OUT / name).write_text(json.dumps(value, indent=2) + '\n')

def digest(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

def inventory(dirs, files=()):
    result = {}
    for directory in dirs:
        for p in sorted((REPO / directory).rglob('*')):
            rel = p.relative_to(REPO).as_posix()
            if any(x in {'cache', '__pycache__', 'build', 'storage', 'uploads', 'credentials'} or x.startswith('.env') for x in p.relative_to(REPO).parts):
                continue
            if p.is_symlink():
                result[rel] = {'symlink': os.readlink(p)}
            elif p.is_file():
                result[rel] = digest(p)
    for name in files:
        p = REPO / name
        if p.is_file():
            result[name] = digest(p)
    return result

baseline = inventory(SOURCE_DIRS, SOURCE_FILES)
save('source-before.json', baseline)
deps = inventory(('vendor', 'node_modules'))
save('dependencies-before.json', deps)
save('harness-hashes.json', {n: digest(REPO / n) for n in ('scripts/amadis/launch.sh', 'scripts/amadis/launch.py', 'scripts/amadis/artisan.php', 'scripts/amadis/guard.php', 'scripts/amadis/server.php', 'scripts/amadis/browser-lifecycle.cjs', str(Path(__file__).relative_to(REPO)))})
preserved = ['amadis-d183833c94dbbd1638368827', 'amadis-4ce30337e17a9a7fa8cfcb4e']
preserved_before = {n: {f: digest(BASE/n/f) for f in ('identity.json', 'database.sqlite') if (BASE/n/f).is_file()} for n in preserved}
save('preserved-before.json', preserved_before)
results = []
root = None

def stable():
    current = inventory(SOURCE_DIRS, SOURCE_FILES)
    if current != baseline:
        save('source-change.json', {k: {'before': baseline.get(k), 'after': current.get(k)} for k in sorted(set(current)|set(baseline)) if current.get(k) != baseline.get(k)})
        raise RuntimeError('SOURCE_CHANGED: gate stopped')

def run(label, argv, env=None, timeout=300):
    stable()
    (OUT / (label+'.cmd')).write_text(shlex.join(argv)+'\n')
    start = stamp()
    t = time.monotonic()
    with (OUT / (label+'.stdout')).open('wb') as stdout, (OUT / (label+'.stderr')).open('wb') as stderr:
        p = subprocess.run(argv, cwd=REPO, env=env, stdout=stdout, stderr=stderr, timeout=timeout)
    record = {'label': label, 'command': argv, 'start': start, 'finish': stamp(), 'seconds': time.monotonic()-t, 'returncode': p.returncode}
    (OUT / (label+'.rc')).write_text(str(p.returncode)+'\n')
    save(label+'.json', record)
    results.append(record)
    save('commands.json', results)
    stable()
    print(label, p.returncode, flush=True)
    return p.returncode

try:
    run('git-status-before', ['git', 'status', '--porcelain=v1', '--untracked-files=all'])
    run('fonttools-version', [PYTHON, '-c', 'import fontTools,sys; print(fontTools.version); print(sys.version); assert fontTools.version == "4.66.1"'])
    if run('init', ['scripts/amadis/launch.sh', 'init']) != 0:
        raise RuntimeError('init failed')
    root = Path((OUT/'init.stdout').read_text().strip())
    assert root.parent == BASE and root.name.startswith('amadis-') and root.name not in preserved
    (OUT/'root.txt').write_text(str(root)+'\n')
    env = dict(os.environ, AMADIS_ROOT=str(root))
    for label, args in [('migrate', ['migrate']), ('seed', ['seed']), ('provision', ['provision'])]:
        if run(label, ['scripts/amadis/launch.sh', *args], env) != 0:
            raise RuntimeError(label+' failed')
    for label, args in [('php-test-standard', []), ('php-test-random', ['--order-by=random', '--random-order-seed=20261013'])]:
        junit = root/'evidence'/(label+'.xml')
        run(label, ['scripts/amadis/launch.sh', 'test', *args, '--log-junit='+str(junit)], env, 600)
        if junit.exists():
            (OUT/(label+'.xml')).write_bytes(junit.read_bytes())
    prior = REPO/'docs/execution/evidence/2026-10-13/final-gate/node-tests.cmd'
    run('node-tests', shlex.split(prior.read_text()), timeout=300)
    run('python-tests', [PYTHON, '-m', 'unittest', 'discover', '-s', 'tests/python'], timeout=600)
    eight = ['app/Support/ComposerHooks.php', 'app/Http/Controllers/AdminOrderController.php', 'tests/Unit/ComposerHooksTest.php', 'tests/Feature/AdminOrderReportTest.php', 'scripts/amadis/server.php', 'scripts/amadis/connect_proxy.php', 'scripts/amadis/artisan.php', 'scripts/amadis/pdf-ui-fixture.php']
    run('pint', ['vendor/bin/pint', '--test', *eight])
    run('build', ['npm', 'run', 'build'])
    run('diff-check', ['git', 'diff', '--check'])
    rc = run('port8016-before', ['lsof', '-nP', '-iTCP:8016', '-sTCP:LISTEN'])
    if rc != 1 or (OUT/'port8016-before.stdout').stat().st_size:
        raise RuntimeError('8016 occupied or lsof unavailable; no bypass')
    run('s0s5', ['node', 'scripts/amadis/browser-lifecycle.cjs'], env, 300)
    run('port8016-after', ['lsof', '-nP', '-iTCP:8016', '-sTCP:LISTEN'])
    run('git-status-after', ['git', 'status', '--porcelain=v1', '--untracked-files=all'])
    save('source-after.json', inventory(SOURCE_DIRS, SOURCE_FILES))
    after_deps = inventory(('vendor', 'node_modules'))
    save('dependencies-after.json', after_deps)
    after_preserved = {n: {f: digest(BASE/n/f) for f in ('identity.json', 'database.sqlite') if (BASE/n/f).is_file()} for n in preserved}
    save('preserved-after.json', after_preserved)
    save('integrity.json', {'source_unchanged': inventory(SOURCE_DIRS, SOURCE_FILES) == baseline, 'dependencies_unchanged': after_deps == deps, 'preserved_fixtures_unchanged': after_preserved == preserved_before})
except Exception as exc:
    save('blocked.json', {'time': stamp(), 'error_type': type(exc).__name__, 'message': str(exc)})
    raise
