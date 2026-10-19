#!/usr/bin/env python3
"""Read-only audit of docs/execution/evidence/2026-10-13/pdf-ui. Writes JSON to stdout only.

Reproduce: python3 docs/execution/evidence/2026-10-13/export-closeout/audit_pdf_ui.py
Limits: the secret scan is pattern-based (launch.sanitized + regexes below); it cannot prove
absence of arbitrary secrets. PNG pixels are not OCR'd here; visual review is separate.
"""
import hashlib
import importlib.util
import json
from pathlib import Path
import re
import struct
import zlib

REPO = Path(__file__).resolve().parents[5]
BUNDLE = REPO / 'docs/execution/evidence/2026-10-13/pdf-ui'
PDF_SHA = 'ad3b849ce4add80d44096ace6d40a22fd3adba0b353636dea205268dd18925bc'

spec = importlib.util.spec_from_file_location('amadis_launch', REPO / 'scripts/amadis/launch.py')
launch = importlib.util.module_from_spec(spec)
spec.loader.exec_module(launch)

# Exact allowlist: every file must be listed with its class; anything else is a finding.
ALLOWLIST = {
    'README.md': 'narrative',
    'commands.json': 'run-metadata',
    'download.json': 'run-metadata',
    'result.json': 'run-metadata',
    'sha256.json': 'run-metadata',
    'extracted-text.txt': 'pdf-derivative',
    'orders-20261013.pdf': 'primary-artifact',
    'render/page-1.png': 'pdf-derivative',
    'render/page-2.png': 'pdf-derivative',
    'history/pdf-download-20261012T201907688Z/commands.json': 'history-failed-run',
    'history/pdf-download-20261012T201907688Z/result.json': 'history-failed-run',
    'history/pdf-download-20261012T201907688Z/sha256.json': 'history-failed-run',
    'history/pdf-download-20261012T201942273Z/failure.png': 'history-failed-run',
    'history/pdf-download-20261012T202006740Z/commands.json': 'history-failed-run',
    'history/pdf-download-20261012T202006740Z/result.json': 'history-failed-run',
    'history/pdf-download-20261012T202006740Z/sha256.json': 'history-failed-run',
    'history/pdf-download-20261012T202348360Z/commands.json': 'history-failed-run',
    'history/pdf-download-20261012T202348360Z/failure.png': 'history-failed-run',
    'history/pdf-download-20261012T202348360Z/result.json': 'history-failed-run',
    'history/pdf-download-20261012T202348360Z/sha256.json': 'history-failed-run',
}
# Credential-shaped content. Fixture emails (pdf-fiction-N@example.test) are not secrets.
SECRET_PATTERNS = {
    'app_key_base64': rb'base64:[A-Za-z0-9+/]{40,}={0,2}',
    'bearer': rb'(?i)bearer\s+[A-Za-z0-9._~+/-]{12,}',
    'pem': rb'-----BEGIN [A-Z ]*PRIVATE KEY-----',
    'url_userinfo': rb'[a-z][a-z0-9+.-]*://[^\s/@:]+:[^\s/@]+@',
    'laravel_session_cookie': rb'(?i)(laravel_session|xsrf-token)=[A-Za-z0-9%]{20,}',
    'aws_key_id': rb'AKIA[0-9A-Z]{16}',
    'credential_email': rb'amadis-(admin|customer)@example\.test',
    'env_path': rb'(?<![\w.])\.env\b',
    'home_path': rb'/Users/[A-Za-z0-9_.-]+',
}


def sha(data):
    return hashlib.sha256(data).hexdigest()


def secret_hits(data):
    hits = {name: len(re.findall(p, data)) for name, p in SECRET_PATTERNS.items()}
    try:
        text = data.decode('utf-8')
        hits['launch_sanitizer_changes'] = int(launch.sanitized(text) != text)
    except UnicodeDecodeError:
        pass
    return {k: v for k, v in hits.items() if v}


def png_chunks(data):
    assert data[:8] == b'\x89PNG\r\n\x1a\n', 'not a PNG'
    pos, chunks, meta, ihdr = 8, [], [], None
    while pos < len(data):
        length, kind = struct.unpack('>I4s', data[pos:pos + 8])
        body = data[pos + 8:pos + 8 + length]
        kind = kind.decode('latin-1')
        chunks.append(kind)
        if kind == 'IHDR':
            w, h, depth, color = struct.unpack('>IIBB', body[:10])
            ihdr = {'width': w, 'height': h, 'bit_depth': depth, 'color_type': color}
        if kind in {'tEXt', 'iTXt', 'zTXt', 'eXIf', 'iCCP'}:
            meta.append({'chunk': kind, 'key': body.split(b'\0', 1)[0].decode('latin-1'), 'bytes': length})
        pos += 12 + length
    return {'ihdr': ihdr, 'chunk_types': sorted(set(chunks)), 'metadata_chunks': meta}


def pdf_inspect(data):
    streams, inflated = [], []
    for m in re.finditer(rb'stream\r?\n(.*?)\r?\nendstream', data, re.S):
        raw = m.group(1)
        try:
            body = zlib.decompress(raw)
            inflated.append(body)
            streams.append({'bytes': len(raw), 'inflated': len(body)})
        except zlib.error:
            inflated.append(raw)
            streams.append({'bytes': len(raw), 'inflated': None})
    names = sorted({n.decode() for n in re.findall(rb'/([A-Za-z][A-Za-z0-9]+)', data)})
    active = ['JavaScript', 'JS', 'OpenAction', 'AA', 'Launch', 'EmbeddedFile', 'EmbeddedFiles',
              'URI', 'SubmitForm', 'ImportData', 'RichMedia', 'XFA', 'AcroForm', 'Metadata']
    info = re.search(rb'/Producer\s*\(([^)]*)\)', data)
    all_bytes = data + b''.join(inflated)
    return {
        'header': data[:8].decode('latin-1').strip(),
        'eof_marker': data.rstrip().endswith(b'%%EOF'),
        'incremental_updates': data.count(b'%%EOF'),
        'objects': len(re.findall(rb'\d+ 0 obj', data)),
        'streams': streams,
        'active_or_hidden_keys_present': [k for k in active if k in names],
        'fonts': sorted({f.decode() for f in re.findall(rb'/BaseFont\s*/([^\s/>\]]+)', data)}),
        'font_keys': [k for k in ('Type0', 'CIDFontType2', 'FontFile2', 'ToUnicode') if k in names],
        'images': names.count('Image') + len(re.findall(rb'/Subtype\s*/Image', data)),
        'producer': info.group(1).decode('latin-1') if info else None,
        'secret_hits_raw_and_inflated': secret_hits(all_bytes),
    }


def main():
    files = sorted(str(p.relative_to(BUNDLE)) for p in BUNDLE.rglob('*') if p.is_file() or p.is_symlink())
    report = {'bundle': str(BUNDLE.relative_to(REPO)), 'files': {}, 'unexpected': [], 'missing': []}
    report['unexpected'] = sorted(set(files) - set(ALLOWLIST))
    report['missing'] = sorted(set(ALLOWLIST) - set(files))
    for name in files:
        path = BUNDLE / name
        info = path.lstat()
        data = path.read_bytes()
        entry = {'class': ALLOWLIST.get(name, 'UNLISTED'), 'bytes': len(data), 'sha256': sha(data),
                 'symlink': path.is_symlink(), 'nlink': info.st_nlink, 'mode': oct(info.st_mode & 0o777),
                 'secret_hits': secret_hits(data)}
        if name.endswith('.json'):
            entry['json_keys'] = sorted(json.loads(data)[0] if isinstance(json.loads(data), list) and json.loads(data) else
                                        (json.loads(data) if isinstance(json.loads(data), dict) else {}))
        if name.endswith('.png'):
            entry['png'] = png_chunks(data)
        if name.endswith('.pdf'):
            entry['pdf'] = pdf_inspect(data)
            entry['sha256_matches_expected'] = entry['sha256'] == PDF_SHA
        report['files'][name] = entry
    # Cross-checks: recorded hashes in sha256.json files must match bytes on disk.
    checks = {}
    for manifest in [n for n in files if n.endswith('sha256.json')]:
        recorded = json.loads((BUNDLE / manifest).read_bytes())
        folder = Path(manifest).parent
        for name, digest in recorded.items():
            target = str(folder / name) if str(folder) != '.' else name
            checks[f'{manifest}:{name}'] = ('match' if target in report['files'] and report['files'][target]['sha256'] == digest
                                           else 'absent-from-bundle' if target not in report['files'] else 'MISMATCH')
    report['recorded_hash_checks'] = checks
    text = (BUNDLE / 'extracted-text.txt').read_text(encoding='utf-8')
    rows = re.findall(r'^#(\d+) \|', text, re.M)
    report['extracted_text_checks'] = {
        'rows': len(rows), 'row_ids_1_to_48': rows == [str(i) for i in range(1, 49)],
        'cancelled': text.count('| CANCELLED |'), 'cjk_name_count': text.count('王小明'),
        'blank_fallback_rows': len(re.findall(r'Pelanggan: - \|', text)),
        'summary_total_line': 'Nilai pesanan non-batal: Rp 4.960.000' in text,
        'pages_footer': re.findall(r'Halaman \d dari \d', text),
    }
    print(json.dumps(report, indent=2, ensure_ascii=False))


if __name__ == '__main__':
    main()
