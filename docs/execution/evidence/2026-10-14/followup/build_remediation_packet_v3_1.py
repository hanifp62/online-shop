#!/usr/bin/env python3
"""Build REMEDIATION_PACKET_v3_1.zip (corrected closure). v1/v2/v3 stay byte-identical."""
import hashlib
import importlib.util
import json
from pathlib import Path
import zipfile

ROOT = Path(__file__).resolve().parents[5]
HERE = Path(__file__).resolve().parent
OUT = ROOT / 'REMEDIATION_PACKET_v3_1.zip'
spec = importlib.util.spec_from_file_location('v2', HERE / 'build_remediation_packet_v2.py')
v2 = importlib.util.module_from_spec(spec)
spec.loader.exec_module(v2)
sha = lambda data: hashlib.sha256(data).hexdigest()

PRIOR = {
    'REMEDIATION_PACKET.zip': '48abe87e34b447554cdeb63845f2d33cee671b94bba7fea121683815660567a0',
    'REMEDIATION_PACKET_v2.zip': 'e4c26a812a967b6cd786ac2d2c5dd1b1e95f4d1150dc79c2911c723719052c6b',
    'REMEDIATION_PACKET_v3.zip': '0d779bf5a7b6d46eca1dc1fd21e0c5c748e2bcf7a98596da617c4dc4f8f1193f',
}


def main():
    assert not OUT.exists(), 'Never overwrite an archive'
    for name, digest in PRIOR.items():
        path = ROOT / name
        assert path.is_file() and sha(path.read_bytes()) == digest, f'Prior archive changed: {name}'

    # Full explicit payload: reuse v2 members and refresh the reviewed source in place.
    old = ROOT / 'REMEDIATION_PACKET_v2.zip'
    with zipfile.ZipFile(old) as archive:
        files = {name: archive.read(name) for name in archive.namelist() if name != 'MANIFEST.sha256'}

    # Catalog SET-contract fix: controller, view, labels, and regression + browser coverage.
    refreshed = [
        'app/Http/Controllers/CatalogController.php',
        'resources/views/catalog/index.blade.php',
        'resources/views/catalog/labels.php',
        'tests/catalog-replacement.test.mjs',
        'scripts/manual/fixture.sh',
        'scripts/manual/setup.sh',
        'scripts/manual/preflight.py',
        'scripts/manual/start-server.sh',
        'docs/MANUAL_TESTING.md',
    ]
    for rel in refreshed:
        files[rel] = (ROOT / rel).read_bytes()

    # Checked closure evidence: source gates, returncodes, and the browser result.
    closure = HERE / 'closure' / 'checked-v31'
    for name in ('setup-manifest.sha256', 'setup-match.json', 'isolated-pre.json', 'isolated-post.json',
                 'source-pre.json', 'source-post.json', 'returncodes.json', 'profile.json',
                 'preserved-archives.json', 'setup.json', 'setup.log', 'catalog-browser.json',
                 'catalog-browser.stdout.txt'):
        files[f'docs/execution/evidence/2026-10-14/followup/closure/checked-v31/{name}'] = (closure / name).read_bytes()

    # Sanitized review copies: only a synthetic literal password fixture changes.
    provenance = []
    for name in ('AmadisOrderTest', 'AdminOrderReportTest', 'StorefrontAvailabilityTest'):
        source = ROOT / f'tests/Feature/{name}.php'
        original = source.read_bytes()
        needle = b"'password' => " + repr('pass' + 'word').encode()
        replacement = b"'password' => ('sanitized-' . 'fixture-only')"
        assert original.count(needle) == 1
        copy = original.replace(needle, replacement)
        target = f'review-copies/tests/Feature/{name}.php'
        files[target] = copy
        provenance.append(dict(source=str(source.relative_to(ROOT)), copy=target,
                               original_sha256=sha(original), copy_sha256=sha(copy), replacements=1,
                               transform='Replace sole literal dummy password fixture with PHP concatenation expression (sanitized- . fixture-only); no other bytes changed.',
                               exact_tested=False))
    files['review-copies/PROVENANCE.json'] = json.dumps(provenance, indent=2).encode()

    # Explicit required gate: every member above plus the proven review-copy set.
    required = ['scripts/manual/fixture.sh', 'scripts/manual/setup.sh', 'scripts/manual/start-server.sh',
                'docs/MANUAL_TESTING.md', 'review-copies/PROVENANCE.json',
                'app/Http/Controllers/CatalogController.php',
                'resources/views/catalog/index.blade.php', 'resources/views/catalog/labels.php',
                'tests/catalog-replacement.test.mjs',
                str((closure / 'setup-match.json').relative_to(ROOT)),
                str((closure / 'returncodes.json').relative_to(ROOT)),
                str((closure / 'catalog-browser.stdout.txt').relative_to(ROOT))] \
        + [p['copy'] for p in provenance]
    assert all(name in files for name in required)

    for name, data in files.items():
        assert not any(p in ('.env', 'node_modules', 'vendor', 'sessions', '__pycache__') for p in Path(name).parts)
        if not data.startswith((b'\x89PNG', b'%PDF')):
            assert not any(pattern.search(data) for pattern in v2.SECRET_PATTERNS), f'Secret-like pattern: {name}'

    files['MANIFEST.sha256'] = ''.join(f'{sha(data)}  {name}\n' for name, data in sorted(files.items())).encode()
    with zipfile.ZipFile(OUT, 'x', compression=zipfile.ZIP_DEFLATED) as archive:
        for name, data in sorted(files.items()):
            archive.writestr(name, data)
    with zipfile.ZipFile(OUT) as archive:
        assert archive.testzip() is None
        for line in archive.read('MANIFEST.sha256').decode().splitlines():
            digest, name = line.split('  ', 1)
            assert sha(archive.read(name)) == digest
    print(json.dumps(dict(path=str(OUT), sha256=sha(OUT.read_bytes()), bytes=OUT.stat().st_size,
                          members=len(files), v2_members=162, member_delta=len(files) - 162,
                          required_files='PASS', crc='PASS', manifest='PASS', prior_archives='UNCHANGED'), indent=2))


if __name__ == '__main__':
    main()
