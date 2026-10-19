"""Idempotent: re-hash allowlisted audit files into EVIDENCE_INDEX.json and rebuild AUDIT_PACKET.zip."""
import datetime, hashlib, json, pathlib, zipfile

D = pathlib.Path(__file__).resolve().parent.parent
E = D / 'evidence'
allow = [D / n for n in ['ARCHITECTURE_AS_IS.md', 'AUDIT_HANDOFF.md', 'GAP_MATRIX.csv', 'SOURCE_EXCERPTS.md', 'TEST_RESULTS.md']]
allow += sorted(p for p in E.iterdir() if p.is_file() and p.suffix in {'.py', '.json', '.txt', '.md'})
# Runtime evidence (reviewed, non-PII only). Add by filename; excludes credentials, DB, env, raw logs and the raw PDF.
runtime_names = json.loads((E / 'reviewed-runtime-files.json').read_text())
assert all(n.startswith(('runtime-20261014/', 'isolated-acceptance-20261014/')) and '..' not in pathlib.PurePosixPath(n).parts for n in runtime_names)
allow += [E / n for n in runtime_names]
assert all(p.is_file() and p.suffix in {'.png', '.json', '.md', '.txt', '.log', '.tsv'} for p in allow if 'runtime-20261014/' in p.as_posix() or 'isolated-acceptance-20261014/' in p.as_posix())
assert not any(p.name in {'fixture-identity.json', 'credentials.json', 'database.sqlite'} for p in allow)
assert all(p.suffix != '.pdf' for p in allow)  # Raw fixture report withheld from archive.
idx = json.loads((D / 'EVIDENCE_INDEX.json').read_text())
idx['audit_metadata']['updated_at'] = datetime.datetime.now().astimezone().isoformat()
idx['files'] = {p.relative_to(D).as_posix(): {'size': p.stat().st_size, 'sha256': hashlib.sha256(p.read_bytes()).hexdigest()} for p in allow}
(D / 'EVIDENCE_INDEX.json').write_text(json.dumps(idx, ensure_ascii=False, indent=2) + '\n')
allow.append(D / 'EVIDENCE_INDEX.json')
with zipfile.ZipFile(D / 'AUDIT_PACKET.zip', 'w', compression=zipfile.ZIP_DEFLATED) as z:
    for p in allow:
        z.write(p, p.relative_to(D).as_posix())
# Self-check: every ZIP entry equals the on-disk file; index hashes match.
with zipfile.ZipFile(D / 'AUDIT_PACKET.zip') as z:
    assert z.testzip() is None
    for n in z.namelist():
        assert z.read(n) == (D / n).read_bytes(), n
        assert 'AKUN_LOKAL' not in n and not n.endswith(('.env', '.env.manual')), n
for rel, meta in idx['files'].items():
    assert hashlib.sha256((D / rel).read_bytes()).hexdigest() == meta['sha256'], rel
print('entries', len(allow), 'zip_sha256', hashlib.sha256((D / 'AUDIT_PACKET.zip').read_bytes()).hexdigest(), 'zip_size', (D / 'AUDIT_PACKET.zip').stat().st_size)
