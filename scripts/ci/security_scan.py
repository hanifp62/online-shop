#!/usr/bin/env python3
"""NFR-CICD-003: scanner stdlib fail-closed untuk gate CI.

CLI: --mode secrets | --mode pii; root diturunkan dari lokasi skrip, bukan cwd.
Exit 0 = bersih, 1 = temuan, 2 = pemindaian tidak dapat dipercaya.
Git memilih tracked (termasuk modifikasi lokal) dan untracked non-ignored;
PII dibatasi ke app/. Semua .env* tidak dibaca. Secrets memindai payload
symlink Git, bukan target; PII menolak symlink. Binary memakai heuristik -I
(NUL pada 8000 byte pertama). Ini heuristik pola, bukan audit aliran data.
Stdout hanya path JSON-quoted (identitas hash untuk path tidak aman); stderr
hanya kode error statis, tanpa isi file/exception/argv.
"""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import sys
import xml.etree.ElementTree as ET


SECRET_PATTERN = (
    rb'sk' rb'_live|(?:SB-)?Mid-server-[A-Za-z0-9_-]{10,}'
    rb'|AKIA[0-9A-Z]{16}|-----BEGIN [A-Z ]*PRIVATE KEY'
    rb'|ghp_[A-Za-z0-9]{30,}|ngpk_[A-Za-z0-9]{10,}'
    rb'|base64:[A-Za-z0-9+/]{43}='
)
# Gabungan gate workflow lama (satu baris) dan guard variabel sensitif PHPUnit.
PII_PATTERN = (
    rb'Log::(?:info|warning|error|debug)\s*\([^\r\n]*(?:email|password|phone|address|token)'
    rb'|(?i:Log::(?:info|warning|error|debug)\s*\([^)]*\$(?:password|token|pin|cvv|secret)\b)'
)


# Pin sentinel PHPUnit yang sudah ada, bukan mempercayai sembarang nilai APP_KEY.
# Rotasi fixture memerlukan review fingerprint ini; nilai kunci tidak disimpan di sini.
PHPUNIT_KEY_SHA256 = 'f897f34498e0c48b2a40498c602dd0cd8bae7b26f3e5726f3f58ea47d1820b19'
PEM_FIXTURE_PATH = 'tests/python/test_amdis_launch_sanitization.py'
_PEM_HEADER = b'-----BEGIN ' + b'PRIVATE KEY'
_PEM_FIXTURE = (
    b'    def test_multiline_pem_body_is_redacted(self):\n'
    b'        self.assert_redacted(\n'
    b"            'private_key=" + _PEM_HEADER + b"-----\\nMIIpem-sentinel\\npem2-sentinel==\\n'\n"
    b"            '-----END PRIVATE KEY-----\\nnext', 'pem-sentinel', 'pem2-sentinel')\n"
    b'        self.assert_redacted(\n'
    b"            'private_key=" + _PEM_HEADER + b"-----\\\\nMIIescaped-pem-sentinel\\\\n-----END PRIVATE KEY-----',\n"
    b"            'escaped-pem-sentinel')\n"
)


class ScanError(Exception):
    """Hanya kode statis, bukan pesan exception dari file atau subprocess."""


class SafeParser(argparse.ArgumentParser):
    def error(self, message):
        raise ScanError('invalid-arguments')


def repository_root():
    return Path(__file__).resolve().parents[2]


def git_paths(root):
    command = ['git', '--no-pager', '--no-optional-locks', '-C', str(root)]
    try:
        top = subprocess.run(command + ['rev-parse', '--show-toplevel'],
                             capture_output=True, check=True, timeout=30)
        if top.stderr or os.fsdecode(top.stdout.rstrip(b'\n')) != str(root):
            raise ScanError('repository-root-error')
        result = subprocess.run(
            command + ['ls-files', '-z', '--cached', '--others', '--exclude-standard', '--'],
            capture_output=True, check=True, timeout=30,
        )
        # Git dapat exit 0 meski traversal untracked mengeluarkan warning I/O.
        if result.stderr:
            raise ScanError('git-list-error')
        listing = result.stdout
    except (OSError, subprocess.SubprocessError):
        raise ScanError('git-list-error') from None
    if not listing or not listing.endswith(b'\0'):
        raise ScanError('git-list-error')
    return sorted(set(os.fsdecode(path) for path in listing[:-1].split(b'\0')))


def candidate(path, mode):
    parts = path.split('/')
    if any(part in ('', '.', '..') for part in parts) or '\0' in path:
        raise ScanError('invalid-path')
    # Kebijakan boundary, bukan sentinel exemption. Jangan membuka .env* sama sekali.
    if any(part.startswith('.env') for part in parts):
        return False
    return mode == 'secrets' or parts[0] == 'app'


def read_file(root, path, *, link_payload=False):
    """Buka komponen relatif tanpa mengikuti symlink, termasuk parent directory."""
    directory_flags = os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW
    directory = os.open(root, directory_flags)
    try:
        parts = path.split('/')
        for part in parts[:-1]:
            child = os.open(part, directory_flags, dir_fd=directory)
            os.close(directory)
            directory = child
        if link_payload and stat.S_ISLNK(os.stat(parts[-1], dir_fd=directory, follow_symlinks=False).st_mode):
            # Git menyimpan teks link. Target tidak dibuka, termasuk .env/di luar repo.
            return os.fsencode(os.readlink(parts[-1], dir_fd=directory))
        descriptor = os.open(parts[-1], os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK,
                             dir_fd=directory)
        with os.fdopen(descriptor, 'rb') as source:
            if not stat.S_ISREG(os.fstat(source.fileno()).st_mode):
                raise ScanError('non-regular-file')
            prefix = source.read(8000)
            if b'\0' in prefix:
                return b''
            return prefix + source.read()
    finally:
        os.close(directory)


def exempt_spans(path, content, pattern):
    """Kecualikan offset match yang dikenal, tidak pernah satu file/baris penuh."""
    if path == PEM_FIXTURE_PATH and content.count(_PEM_FIXTURE) == 1:
        start = content.index(_PEM_FIXTURE)
        return {(start + match.start(), start + match.end())
                for match in pattern.finditer(_PEM_FIXTURE)}
    if path != 'phpunit.xml':
        return set()
    if b'<!DOCTYPE' in content or b'<!ENTITY' in content:
        raise ScanError('sentinel-context-error')
    try:
        document = ET.fromstring(content)
    except ET.ParseError:
        raise ScanError('sentinel-context-error') from None
    env = document.findall('./php/env')
    keys = [node for node in env if node.get('name') == 'APP_KEY']
    testing = [node for node in env if node.get('name') == 'APP_ENV']
    if (document.tag != 'phpunit' or len(keys) != 1 or len(testing) != 1
            or testing[0].get('value') != 'testing'
            or set(keys[0].attrib) != {'name', 'value'}):
        return set()
    key_value = keys[0].get('value')
    if key_value is None:
        return set()
    key = key_value.encode('utf-8')
    if hashlib.sha256(key).hexdigest() != PHPUNIT_KEY_SHA256:
        return set()
    tags = list(re.finditer(
        rb'^[ \t]*<env name="APP_KEY" value="([^"\r\n]+)"[ \t]*/>[ \t]*\r?$',
        content, re.MULTILINE,
    ))
    if len(tags) == 1 and tags[0].group(1) == key:
        return {tags[0].span(1)}
    return set()


def safe_path(path):
    # Nama file juga tidak tepercaya: jangan izinkan control/GHA injection atau token.
    if (not re.fullmatch(r'[A-Za-z0-9_./-]+', path)
            or re.search(SECRET_PATTERN, os.fsencode(path))):
        return 'path-sha256:' + hashlib.sha256(os.fsencode(path)).hexdigest()
    return path


def scan(root, mode):
    pattern = re.compile(SECRET_PATTERN if mode == 'secrets' else PII_PATTERN)
    paths = [path for path in git_paths(root) if candidate(path, mode)]
    if not paths:
        raise ScanError('empty-scan-scope')
    findings = []
    for path in sorted(set(paths)):
        try:
            content = read_file(root, path, link_payload=mode == 'secrets')
        except OSError:
            raise ScanError('file-read-error') from None
        matches = list(pattern.finditer(content))
        allowed = exempt_spans(path, content, pattern) if matches and mode == 'secrets' else set()
        if any(match.span() not in allowed for match in matches):
            findings.append(path)
    return findings


def main(argv=None):
    try:
        parser = SafeParser(prog='security_scan.py', description=__doc__, allow_abbrev=False)
        parser.add_argument('--mode', required=True, choices=('secrets', 'pii'))
        args = parser.parse_args(argv)
        findings = scan(repository_root(), args.mode)
        # Jangan cetak temuan parsial jika file berikutnya gagal dipindai.
        output = [json.dumps(safe_path(path), ensure_ascii=True) for path in findings]
        for path in output:
            print(path)
        return 1 if findings else 0
    except ScanError as error:
        print('security-scan: scan failed (' + str(error) + ')', file=sys.stderr)
        return 2
    except Exception:
        # Termasuk regex/decoding/internal error: jangan pernah echo pesan mentah.
        print('security-scan: scan failed (internal-error)', file=sys.stderr)
        return 2


if __name__ == '__main__':
    sys.exit(main())
