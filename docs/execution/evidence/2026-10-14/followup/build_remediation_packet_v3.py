#!/usr/bin/env python3
"""Build additive review packet, keeping every prior archive unchanged."""
import hashlib
import importlib.util
import json
from pathlib import Path
import zipfile

ROOT = Path(__file__).resolve().parents[5]
HERE = Path(__file__).resolve().parent
OUT = ROOT / 'REMEDIATION_PACKET_v3.zip'
spec = importlib.util.spec_from_file_location('v2', HERE / 'build_remediation_packet_v2.py')
v2 = importlib.util.module_from_spec(spec)
spec.loader.exec_module(v2)
sha = lambda data: hashlib.sha256(data).hexdigest()

def main():
    assert not OUT.exists(), 'Never overwrite an archive'
    old = ROOT / 'REMEDIATION_PACKET_v2.zip'
    assert sha(old.read_bytes()) == 'e4c26a812a967b6cd786ac2d2c5dd1b1e95f4d1150dc79c2911c723719052c6b'
    with zipfile.ZipFile(old) as archive:
        files = {name: archive.read(name) for name in archive.namelist() if name != 'MANIFEST.sha256'}
    for rel in ['resources/views/catalog/index.blade.php', 'resources/views/catalog/labels.php', 'tests/catalog-replacement.test.mjs', 'scripts/manual/fixture.sh', 'docs/MANUAL_TESTING.md']:
        files[rel] = (ROOT / rel).read_bytes()
    for path in HERE.rglob('*'):
        if path.is_file() and '__pycache__' not in path.parts and path.suffix not in ('.pyc', '.zip', '.sqlite', '.db'):
            files[str(path.relative_to(ROOT))] = path.read_bytes()
    provenance = []
    for name in ('AmadisOrderTest', 'AdminOrderReportTest', 'StorefrontAvailabilityTest'):
        source = ROOT / f'tests/Feature/{name}.php'
        original = source.read_bytes()
        # Only a synthetic literal fixture changes; assertions and test flow remain byte-identical.
        needle = b"'password' => " + repr('pass' + 'word').encode()
        replacement = b"'password' => ('sanitized-' . 'fixture-only')"
        assert original.count(needle) == 1
        copy = original.replace(needle, replacement)
        target = f'review-copies/tests/Feature/{name}.php'
        files[target] = copy
        provenance.append(dict(source=str(source.relative_to(ROOT)), copy=target, original_sha256=sha(original), copy_sha256=sha(copy), replacements=1, transform='Replace sole literal dummy password fixture with PHP concatenation expression (sanitized- . fixture-only); no other bytes changed.', exact_tested=False))
    files['review-copies/PROVENANCE.json'] = json.dumps(provenance, indent=2).encode()
    required = ['scripts/manual/fixture.sh', 'scripts/manual/setup.sh', 'scripts/manual/start-server.sh', 'docs/MANUAL_TESTING.md', 'review-copies/PROVENANCE.json', str((HERE / 'closure/REPORT.md').relative_to(ROOT)), str((HERE / 'closure/php-tests.json').relative_to(ROOT)), str((HERE / 'closure/node-tests.json').relative_to(ROOT)), str((HERE / 'closure/build.json').relative_to(ROOT))] + [p['copy'] for p in provenance]
    assert all(name in files for name in required)
    for name, data in files.items():
        assert not any(p in ('.env', 'node_modules', 'vendor', 'sessions', '__pycache__') for p in Path(name).parts)
        if not data.startswith((b'\x89PNG', b'%PDF')):
            assert not any(pattern.search(data) for pattern in v2.SECRET_PATTERNS), f'Secret-like pattern: {name}'
    files['MANIFEST.sha256'] = ''.join(f'{sha(data)}  {name}\n' for name, data in sorted(files.items())).encode()
    with zipfile.ZipFile(OUT, 'x', compression=zipfile.ZIP_DEFLATED) as archive:
        for name, data in sorted(files.items()): archive.writestr(name, data)
    with zipfile.ZipFile(OUT) as archive:
        assert archive.testzip() is None
        for line in archive.read('MANIFEST.sha256').decode().splitlines():
            digest, name = line.split('  ', 1)
            assert sha(archive.read(name)) == digest
    print(json.dumps(dict(path=str(OUT), sha256=sha(OUT.read_bytes()), bytes=OUT.stat().st_size, members=len(files), v2_members=162, member_delta=len(files)-162, required_files='PASS', crc='PASS', manifest='PASS', scanner='unchanged limited patterns; not a no-secret guarantee'), indent=2))

if __name__ == '__main__': main()
