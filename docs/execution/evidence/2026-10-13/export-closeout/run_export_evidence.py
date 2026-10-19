#!/usr/bin/env python3
"""Reproducible real capture -> files -> exporter CLI run. Prints evidence JSON to stdout.

Run from repo root: python3 docs/execution/evidence/2026-10-13/export-closeout/run_export_evidence.py
Uses a disposable amadis-<24hex> root and export dir under launch.BASE; both are removed at the end.
Limit: sanitization is pattern-based, not arbitrary secret detection.
"""
import datetime
import contextlib
import io
import hashlib
import importlib.util
import json
from pathlib import Path
import secrets
import shutil
import subprocess
import sys

REPO = Path(__file__).resolve().parents[5]
spec = importlib.util.spec_from_file_location('amadis_launch', REPO / 'scripts/amadis/launch.py')
launch = importlib.util.module_from_spec(spec)
spec.loader.exec_module(launch)
EXPORT = REPO / 'scripts/amadis/export_evidence.py'
SENTINEL = 'sentinel-' + secrets.token_hex(8)
MARKER = 'allowed-marker-' + secrets.token_hex(4)
sha = lambda b: hashlib.sha256(b).hexdigest()
now = lambda: datetime.datetime.now(datetime.timezone.utc).isoformat()

root = launch.BASE / ('amadis-' + secrets.token_hex(12))
dest = launch.BASE / ('export-' + secrets.token_hex(12))
root.mkdir(mode=0o700)
dest.mkdir(mode=0o700)
out = {'started': now(), 'sentinel_len': len(SENTINEL), 'commands': []}
try:
    launch.prepare(root)
    launch.write_new(root, 'identity.json', json.dumps(
        {'root': str(root), 'database': str(root / 'database.sqlite'), 'repo': str(REPO)}))
    script = f'import sys; print("APP_KEY={SENTINEL} {MARKER}"); print("API_TOKEN=Bearer {SENTINEL}", file=sys.stderr)'
    with open(root / '.lock', 'a') as held:
        launch.lock = held
        t = now()
        with contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
            rc = launch.capture(root, 'sentinel', [sys.executable, '-c', script], {})
        out['commands'].append({'step': 'launch.capture (real subprocess)', 'time': t, 'rc': rc})
    stem = next(p.stem for p in (root / 'evidence').glob('*.json'))
    for name, text in {stem + '.extra': SENTINEL, 'other.stdout': SENTINEL, 'x.log': SENTINEL}.items():
        launch.write_new(root, 'evidence/' + name, text)
    out['source_files'] = sorted(p.name for p in (root / 'evidence').iterdir())
    argv = [sys.executable, str(EXPORT), str(root), stem, str(dest)]
    t = now()
    r = subprocess.run(argv, cwd=REPO, capture_output=True, text=True)
    out['commands'].append({'step': 'export_evidence.py CLI', 'time': t, 'rc': r.returncode,
                            'stdout': r.stdout.strip(), 'stderr': r.stderr.strip()})
    files = {}
    for p in sorted(dest.iterdir()):
        b = p.read_bytes()
        files[p.name] = {'sha256': sha(b), 'bytes': len(b), 'mode': oct(p.stat().st_mode & 0o777),
                         'sentinel_present': SENTINEL.encode() in b, 'marker_present': MARKER.encode() in b}
    manifest = json.loads((dest / 'manifest.json').read_text())
    expected = {stem + s for s in ('.json', '.stdout', '.stderr')}
    out.update(exported=files, expected_allowlist=sorted(expected),
               exact_allowlist=set(files) == expected | {'manifest.json'},
               manifest_hashes_match=all(manifest['files'][n] == files[n]['sha256'] for n in expected),
               sentinel_absent_everywhere=not any(f['sentinel_present'] for f in files.values()),
               marker_in_stdout=files[stem + '.stdout']['marker_present'],
               forbidden_exported=sorted(set(files) & {stem + '.extra', 'other.stdout', 'x.log'}),
               exporter_source_sha256=sha((EXPORT).read_bytes()), launch_source_sha256=sha((REPO / 'scripts/amadis/launch.py').read_bytes()))
    out['pass'] = bool(out['exact_allowlist'] and out['manifest_hashes_match'] and out['sentinel_absent_everywhere']
                       and out['marker_in_stdout'] and not out['forbidden_exported'] and r.returncode == 0)
finally:
    shutil.rmtree(root, ignore_errors=True)
    shutil.rmtree(dest, ignore_errors=True)
    out['finished'] = now()
print(json.dumps(out, indent=2))
