#!/usr/bin/env python3
"""Offline Docker preflight for an independent guarded Amadis Composer stage.

Installation is intentionally unavailable until network egress can be confined and proved.
"""
import base64
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import stat
import subprocess
import sys

import importlib.util

_spec = importlib.util.spec_from_file_location('amadis_stage_launch', Path(__file__).with_name('launch.py'))
launch = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(launch)

# Matches the Mac host runtime (PHP 8.5.11 + Composer 2.10.3) and ships zip/unzip/git.
IMAGE = 'composer@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac'
SOURCE_TOP = ('app', 'bootstrap', 'config', 'database', 'routes', 'scripts', 'vendor', 'resources',
              'public', 'prototype', 'tests', 'docs', 'composer.json', 'composer.lock', 'artisan',
              'package.json', 'package-lock.json', 'phpunit.xml', 'vite.config.js')
REQUIRED = {'composer.json', 'composer.lock', 'vendor'}
# Runtime state, generated evidence, caches, credentials and private keys never enter the stage.
EXCLUDED_PATHS = {'bootstrap/cache', 'docs/execution/evidence', 'storage', 'node_modules'}
EXCLUDED_NAMES = {'__pycache__', '.git', '.phpunit.result.cache', 'auth.json', 'credentials.json',
                  'sessions', 'cache', 'logs'}
EXCLUDED_SUFFIXES = ('.sqlite', '.sqlite3', '.sqlite-wal', '.sqlite-shm', '.sqlite3-wal', '.sqlite3-shm',
                     '.db', '.sql', '.log', '.pem', '.key', '.p12', '.pfx')
OUTPUT_LIMIT = 64 * 1024
CATEGORIES = {0: 'passed', 124: 'timeout', 125: 'docker_unavailable', 126: 'not_executable', 127: 'docker_missing'}
# public/* links duplicate prototype/, which is copied as real files.
LINK_DUPLICATES = {'public/app.css', 'public/app.js', 'public/prototype'}


def copy_source(source, target):
    """Copy current source bytes independently; never dereference links or import runtime data."""
    target.mkdir(mode=0o700)

    def copy_entry(item, destination):
        relative = item.relative_to(source).as_posix()
        # vendor is third-party code: only secrets/state suffixes apply there, not generic directory names.
        vendored = relative.startswith('vendor/')
        if (relative in EXCLUDED_PATHS or relative in LINK_DUPLICATES or item.name.startswith('.env')
                or item.name.endswith(EXCLUDED_SUFFIXES) or (item.name in EXCLUDED_NAMES and not vendored)):
            return
        info = item.lstat()
        if stat.S_ISLNK(info.st_mode) or (stat.S_ISREG(info.st_mode) and info.st_nlink != 1):
            raise SystemExit(f'linked stage input refused: {relative}')
        if stat.S_ISDIR(info.st_mode):
            destination.mkdir(mode=0o700)
            for child in sorted(item.iterdir()):
                copy_entry(child, destination / child.name)
        elif stat.S_ISREG(info.st_mode):
            shutil.copyfile(item, destination)
            destination.chmod(0o700 if info.st_mode & 0o111 else 0o600)
        else:
            raise SystemExit(f'special stage input refused: {relative}')

    for name in SOURCE_TOP:
        if not (source / name).exists():
            if name in REQUIRED:
                raise SystemExit(f'missing stage input: {name}')
            continue
        copy_entry(source / name, target / name)


def install_gate(records, required):
    """Missing, crashed, or failed predicates never authorize installation."""
    return bool(required) and all(isinstance(records.get(name), dict)
                                  and records[name].get('status') == 'PASS'
                                  and records[name].get('returncode') == 0 for name in required)


def probe_results(raw, returncode, required):
    try:
        records = json.loads(raw) if returncode == 0 else {}
    except (ValueError, TypeError):
        records = {}
    if not isinstance(records, dict):
        records = {}
    return {name: records.get(name) if isinstance(records.get(name), dict) else
            {'status': 'FAIL', 'returncode': returncode or 1, 'error': 'missing or crashed probe'}
            for name in required}


def container_command(stage, image, args, root=None, env=(), network='none'):
    command = ['docker', 'run', '--rm', '--network', network, '--user', f'{os.getuid()}:{os.getgid()}',
               '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges:true', '--read-only',
               '--pids-limit', '128', '--memory', '2g', '--cpus', '2',
               '--tmpfs', '/tmp:rw,nosuid,nodev,size=256m,mode=1777',
               '--env', 'HOME=/tmp', '--env', 'COMPOSER_HOME=/tmp/composer']
    for name, value in env:
        command.extend(['--env', f'{name}={value}'])
    # Identical host and container paths: dirname(__DIR__,2), cwd and AMADIS_ROOT checks all hold legitimately.
    command.extend(['--mount', f'type=bind,src={stage},dst={stage},readonly=false'])
    if root is not None:
        command.extend(['--mount', f'type=bind,src={root},dst={root},readonly=false'])
    command.extend(['--workdir', str(stage / 'repo'), image, *args])
    return command


def stage_identity(stage, root, source):
    """Stage-truthful identity: repo is the stage copy; provenance of the source is recorded, never faked."""
    digest = hashlib.sha256()
    for path in sorted(p for p in (stage / 'repo').rglob('*') if p.is_file()):
        digest.update(path.relative_to(stage / 'repo').as_posix().encode())
        digest.update(path.read_bytes())
    head = subprocess.run(['git', 'rev-parse', 'HEAD'], cwd=source, capture_output=True, text=True, check=True)
    dirty = subprocess.run(['git', 'status', '--short'], cwd=source, capture_output=True, text=True, check=True)
    return {'root': str(root), 'database': str(root / 'database.sqlite'), 'repo': str(stage / 'repo'),
            'source_repo': str(source), 'source_git_head': head.stdout.strip(),
            'source_dirty': bool(dirty.stdout.strip()), 'stage_manifest_sha256': digest.hexdigest()}


def run_gate(root, label, argv):
    """Run one gate; always leave a redacted, bounded, categorized evidence record."""
    record = {'label': label, 'command': launch.sanitized_command(argv), 'start': launch.timestamp()}
    try:
        result = subprocess.run(argv, stdin=subprocess.DEVNULL, capture_output=True, text=True,
                                errors='replace', timeout=120, check=False)
        category = CATEGORIES.get(result.returncode, 'gate_failed')
        if result.returncode and 'Cannot connect to the Docker daemon' in result.stderr:
            category = 'docker_unavailable'
        record.update(returncode=result.returncode, category=category,
                      stdout=launch.sanitized(result.stdout[-OUTPUT_LIMIT:]),
                      stderr=launch.sanitized(result.stderr[-OUTPUT_LIMIT:]))
    except FileNotFoundError:
        record.update(returncode=127, category='docker_missing', stderr='docker executable not found')
    except PermissionError:
        record.update(returncode=126, category='not_executable', stderr='docker executable not executable')
    except subprocess.TimeoutExpired:
        record.update(returncode=124, category='timeout', stderr='gate timed out after 120s')
    record['finish'] = launch.timestamp()
    launch.write_new(root, f'evidence/{label}-{os.urandom(3).hex()}.json', json.dumps(record, indent=2) + '\n')
    return record


BOUNDARY_REQUIRED = {'allowed_proxy', 'forbidden_proxy', 'direct_egress', 'private_targets',
                     'sentinel_proxy', 'sentinel_direct_isolation', 'parallel_tls'}
# Boundaries plus the host-side gates every install must clear.
STAGE_REQUIRED = BOUNDARY_REQUIRED | {'topology', 'init', 'platform', 'platform-no-dev', 'sqlite',
                                      'artisan', 'source_modes', 'control_direct_egress', 'runtime_sentinel'}

PROBE_SCRIPT = r'''
import concurrent.futures
import errno
import json
import socket
import subprocess
import sys

proxy, sentinel, gateway = sys.argv[1:]
results = {}
def record(name, ok, detail):
    results[name] = {'status': 'PASS' if ok else 'FAIL', 'returncode': 0 if ok else 1, 'detail': detail}
def connect_status(target):
    with socket.create_connection((proxy, 8080), timeout=10) as conn:
        conn.sendall(('CONNECT ' + target + ' HTTP/1.1\r\nHost: ' + target + '\r\n\r\n').encode())
        return conn.recv(1024).decode(errors='replace').split('\r\n')[0]
def fetch(use_proxy=True):
    argv = ['curl', '--silent', '--show-error', '--fail', '--connect-timeout', '5', '--max-time', '30',
            '--proxy', 'http://' + proxy + ':8080' if use_proxy else '', '--noproxy', '',
            'https://repo.packagist.org/packages.json']
    p = subprocess.run(argv, capture_output=True, text=True, timeout=35)
    return {'returncode': p.returncode, 'bytes': len(p.stdout), 'stderr': p.stderr,
            'valid': p.returncode == 0 and 'packages' in json.loads(p.stdout)}
def check(name, fn):
    try:
        fn()
    except Exception as error:
        record(name, False, str(error))
def allowed():
    value = fetch()
    record('allowed_proxy', value['valid'], value)
def forbidden():
    value = connect_status('example.com:443')
    record('forbidden_proxy', ' 403 ' in value, value)
def direct():
    attempts = []
    for address in ('1.1.1.1', '8.8.8.8', '9.9.9.9'):
        try:
            with socket.create_connection((address, 443), timeout=5) as conn:
                attempts.append({'target': address + ':443', 'connected': True, 'peer': str(conn.getpeername())})
        except OSError as error:
            attempts.append({'target': address + ':443', 'connected': False,
                             'errno': error.errno, 'error': str(error)})
    blocked = {errno.ENETUNREACH, errno.EHOSTUNREACH, errno.ENETDOWN, errno.EACCES, errno.EPERM}
    record('direct_egress', all(not item['connected'] and item.get('errno') in blocked for item in attempts), attempts)
def private():
    values = {host: connect_status(host + ':443') for host in
              ['host.docker.internal', gateway, '10.0.0.1', '192.168.1.1', '169.254.169.254']}
    record('private_targets', all(' 403 ' in value for value in values.values()), values)
def sentinel_proxy():
    value = connect_status(sentinel + ':443')
    record('sentinel_proxy', ' 403 ' in value, value)
def sentinel_direct():
    # A refused CONNECT alone does not prove east-west isolation.
    try:
        with socket.create_connection((sentinel, 8081), timeout=5) as conn:
            content = conn.recv(1024).decode()
        record('sentinel_direct_isolation', False, {'reachable': True, 'content': content})
    except (TimeoutError, ConnectionRefusedError, OSError) as error:
        record('sentinel_direct_isolation', True, {'reachable': False, 'error': str(error)})
def parallel():
    with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
        values = list(pool.map(lambda _: fetch(), range(8)))
    record('parallel_tls', len(values) == 8 and all(v['valid'] for v in values), values)
for name, fn in [('allowed_proxy', allowed), ('forbidden_proxy', forbidden), ('direct_egress', direct),
                 ('private_targets', private), ('sentinel_proxy', sentinel_proxy),
                 ('sentinel_direct_isolation', sentinel_direct), ('parallel_tls', parallel)]:
    check(name, fn)
print(json.dumps(results))
'''

SENTINEL_SCRIPT = '''<?php
$server = stream_socket_server('tcp://0.0.0.0:8081', $errno, $error);
if (!$server) { fwrite(STDERR, $error); exit(1); }
while ($client = stream_socket_accept($server, -1)) {
    fwrite($client, "AMADIS-INTERNAL-SENTINEL\\n");
    fclose($client);
}
'''


def topology_valid(containers, networks, composer_network, sentinel_network):
    expected = {'proxy': {'bridge', composer_network, sentinel_network},
                'composer': {composer_network}, 'sentinel': {sentinel_network}}
    return (composer_network != sentinel_network
            and all(networks[name]['Internal'] is True for name in (composer_network, sentinel_network))
            and all(set(containers[kind]['NetworkSettings']['Networks']) == nets for kind, nets in expected.items())
            and all(not any((item['NetworkSettings']['Ports'] or {}).values())
                    and item['State']['Running'] for item in containers.values()))


def stage_env(root, key):
    """Match launch.py's isolated runtime without relaxing either PHP guard."""
    env = {'AMADIS_ROOT': str(root), 'AMADIS_DATABASE': str(root / 'database.sqlite'),
           'AMADIS_EVIDENCE_PATH': str(root / 'evidence'), 'APP_ENV': 'local', 'APP_DEBUG': 'false',
           'APP_URL': 'http://127.0.0.1:8016', 'APP_KEY': key, 'APP_PREVIOUS_KEYS': '',
           'DB_CONNECTION': 'sqlite', 'DB_DATABASE': str(root / 'database.sqlite'), 'DB_URL': '',
           'CACHE_STORE': 'file', 'SESSION_DRIVER': 'file', 'FILESYSTEM_DISK': 'local',
           'QUEUE_CONNECTION': 'sync', 'QUEUE_FAILED_DRIVER': 'null', 'MAIL_MAILER': 'log',
           'LOG_CHANNEL': 'single', 'LARAVEL_STORAGE_PATH': str(root / 'storage'),
           'VIEW_COMPILED_PATH': str(root / 'storage/framework/views')}
    for name in ('config', 'services', 'packages', 'routes', 'events'):
        env['APP_' + name.upper() + '_CACHE'] = str(root / 'bootstrap' / (name + '.php'))
    return list(env.items())


APPLY_FILES = ['composer.json', 'composer.lock', 'vendor/composer/installed.json',
               'vendor/composer/autoload_classmap.php', 'vendor/composer/autoload_files.php',
               'vendor/composer/autoload_namespaces.php', 'vendor/composer/autoload_psr4.php',
               'vendor/composer/autoload_static.php', 'vendor/composer/installed.php']
APPLY_DIRS = ['vendor/dompdf', 'vendor/masterminds', 'vendor/sabberworm']
# Only the approved package trees and Composer's generated maps may change.
APPLY_PREFIXES = tuple(path + '/' for path in APPLY_DIRS) + ('vendor/composer/',)


def tree_digest(base, relatives):
    """Content map of the given files and directory subtrees; links and special files are refused."""
    digest = {}
    for relative in relatives:
        path = base / relative
        if path.is_symlink():
            raise RuntimeError(f'linked path refused: {relative}')
        if path.is_file():
            digest[relative] = hashlib.sha256(path.read_bytes()).hexdigest()
        elif path.is_dir():
            for item in sorted(path.rglob('*')):
                if item.is_symlink():
                    raise RuntimeError(f'linked path refused: {item.relative_to(base)}')
                if item.is_file():
                    digest[str(item.relative_to(base))] = hashlib.sha256(item.read_bytes()).hexdigest()
    return digest


def unexpected_changes(before, after):
    """Any added, removed or rewritten path outside the approved apply set is a refusal."""
    return sorted(name for name in set(before) | set(after)
                  if before.get(name) != after.get(name)
                  and name not in APPLY_FILES and not name.startswith(APPLY_PREFIXES))


def lock_operations(text):
    match = re.search(r'Lock file operations: (\d+) installs?, (\d+) updates?, (\d+) removals?', text)
    return tuple(int(value) for value in match.groups()) if match else None


def apply_to_repo(repo, staged, backup, expected, verify):
    """Move originals aside, copy only the approved closure, and restore everything if verification fails."""
    if tree_digest(repo, APPLY_FILES + APPLY_DIRS) != expected:
        raise RuntimeError('repository drifted since the fingerprint precheck; refusing to apply')
    backup.mkdir(mode=0o700, parents=True)
    moved = []
    created_files = []
    try:
        for relative in APPLY_FILES + APPLY_DIRS:
            source = repo / relative
            if not os.path.lexists(source):
                continue
            target = backup / relative
            target.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
            shutil.copytree(source, target) if source.is_dir() else shutil.copyfile(source, target)
            moved.append(relative)
        for relative in APPLY_FILES + APPLY_DIRS:
            source, destination = staged / relative, repo / relative
            if not source.exists():
                continue
            if relative in APPLY_FILES and not os.path.lexists(destination):
                created_files.append(relative)
            destination.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
            if source.is_dir():
                shutil.rmtree(destination, ignore_errors=True)
                shutil.copytree(source, destination)
            else:
                shutil.copyfile(source, destination)
        if not verify():
            raise RuntimeError('host verification failed after apply')
    except BaseException:
        for relative in created_files:
            (repo / relative).unlink(missing_ok=True)
        for relative in APPLY_DIRS:
            path = repo / relative
            if path.is_dir() and relative not in moved:
                shutil.rmtree(path, ignore_errors=True)
        for relative in moved:
            source, destination = backup / relative, repo / relative
            if source.is_dir():
                shutil.rmtree(destination, ignore_errors=True)
                shutil.copytree(source, destination)
            else:
                shutil.copyfile(source, destination)
        if tree_digest(repo, APPLY_FILES + APPLY_DIRS) != expected:
            raise RuntimeError(f'rollback verification failed; recovery copy kept at {backup}')
        raise
    return True


def hooks_ran(text, root):
    """Composer only names scripts under -v; the root-local caches prove the hooks actually executed."""
    return (all(('> post-autoload-dump: App\\Support\\ComposerHooks::postAutoloadDump' in text,
                 '> post-update-cmd: App\\Support\\ComposerHooks::postUpdate' in text))
            and (root / 'bootstrap/packages.php').is_file())


def cleanup_commands(created):
    """Containers share names with their networks, so entries are typed; containers go first."""
    ordered = [e for e in reversed(created) if e[0] == 'container'] + [e for e in reversed(created) if e[0] == 'network']
    return [('cleanup-' + kind + '-' + name, ['docker', 'rm', '--force', name] if kind == 'container'
             else ['docker', 'network', 'rm', name]) for kind, name in ordered]


def install_stage(stage, root, evidence, run, network, boundary):
    required = set(STAGE_REQUIRED)
    if not install_gate(boundary, required):
        raise RuntimeError('install refused: incomplete preflight')
    (evidence / 'install-gate.json').write_text(json.dumps(boundary, indent=2))
    repo = stage / 'repo'
    old_json = launch._pdf_json(repo / 'composer.json')
    old_lock = launch._pdf_json(repo / 'composer.lock')
    before = tree_digest(repo, [p.name for p in repo.iterdir()])
    cache = root / 'composer-cache'
    cache.mkdir(mode=0o700)
    env = stage_env(root, launch.read_file(root, 'demo-key')) + [
        ('https_proxy', 'http://amadis-proxy:8080'), ('http_proxy', 'http://amadis-proxy:8080'),
        ('HTTPS_PROXY', 'http://amadis-proxy:8080'), ('HTTP_PROXY', 'http://amadis-proxy:8080'),
        ('NO_PROXY', ''), ('no_proxy', ''), ('COMPOSER_CACHE_DIR', str(cache))]
    (stage / 'metadata.py').write_text(METADATA_SCRIPT)
    releases = {}
    for name, pin in launch.PDF_PINS.items():
        rc, out, err = run('metadata-' + name.replace('/', '-'), container_command(
            stage, IMAGE, ['python3', str(stage / 'metadata.py'), name], root, env, network))
        if rc:
            raise RuntimeError(f'metadata {name}: rc={rc}: {err}')
        data = json.loads(out)
        if data.get('minified') != 'composer/2.0':
            raise RuntimeError('unexpected metadata format')
        matches = [r for r in launch._pdf_expand(data['packages'][name])
                   if r.get('version_normalized') == pin['version'] + '.0']
        if len(matches) != 1:
            raise RuntimeError('approved release missing or ambiguous: ' + name)
        launch._pdf_check_release(name, matches[0], pin)
        releases[name] = matches[0]
    rc, out, err = run('composer-require', container_command(stage, IMAGE,
        ['composer', 'require', 'dompdf/dompdf:3.1.6', '--no-interaction', '--no-ansi', '--prefer-dist', '-v'],
        root, env, network), timeout=600)
    records = {'install': {'status': 'PASS' if rc == 0 else 'FAIL', 'returncode': rc}}
    if rc:
        return records
    new_lock = launch._pdf_json(repo / 'composer.lock')
    additions = launch._pdf_validate_candidate(old_json, old_lock, launch._pdf_json(repo / 'composer.json'),
                                               new_lock, releases)
    launch._pdf_vendor_matches_lock(new_lock, launch._pdf_json(repo / 'vendor/composer/installed.json'))
    if lock_operations(out + err) != (5, 0, 0):
        raise RuntimeError('Composer did not report 5 installs, 0 updates, 0 removals')
    archives = launch._pdf_archives(cache, {p['name']: p for p in new_lock['packages']})
    for name, archive in archives.items():
        hash_rc, hash_out, hash_err = run('shasum-' + name.replace('/', '-'),
                                         ['shasum', '-a', '256', str(cache / archive['cache_path'])])
        if hash_rc or hash_out.split()[0] != archive['local_sha256']:
            raise RuntimeError('archive SHA-256 disagreement: ' + name)
    (evidence / 'archives.json').write_text(json.dumps(archives, indent=2))
    records['pins'] = {'status': 'PASS', 'returncode': 0, 'additions': additions, 'updates': 0, 'removals': 0}
    hooks = hooks_ran(out + err, root)
    records['hooks'] = {'status': 'PASS' if hooks else 'FAIL', 'returncode': 0 if hooks else 1}
    check = stage / 'check_dompdf.php'
    check.write_text('<?php require __DIR__."/repo/vendor/autoload.php"; '
                     'if (!class_exists(\\Dompdf\\Dompdf::class)) { exit(1); } echo "Dompdf loaded\\n";')
    rc, _, _ = run('dompdf-class-load', container_command(stage, IMAGE, ['php', str(check)], root, env))
    records['class_load'] = {'status': 'PASS' if rc == 0 else 'FAIL', 'returncode': rc}
    changes = unexpected_changes(before, tree_digest(repo, [p.name for p in repo.iterdir()]))
    records['change_scope'] = {'status': 'PASS' if not changes else 'FAIL', 'returncode': 1 if changes else 0,
                               'unexpected': changes}
    return records


METADATA_SCRIPT = r'''
import json, sys, urllib.request
name = sys.argv[1]
with urllib.request.urlopen('https://repo.packagist.org/p2/' + name + '.json', timeout=30) as response:
    sys.stdout.write(response.read(4 * 1024 * 1024 + 1).decode())
'''


CONTROL_SCRIPT = r'''
import json, socket, sys
results = []
for address in ('1.1.1.1', '8.8.8.8', '9.9.9.9'):
    try:
        with socket.create_connection((address, 443), timeout=5) as connection:
            results.append({'target': address + ':443', 'connected': True})
    except OSError as error:
        results.append({'target': address + ':443', 'connected': False, 'errno': error.errno})
print(json.dumps(results))
sys.exit(0 if any(item['connected'] for item in results) else 1)
'''

RUNTIME_SCRIPT = r'''
import errno, json, os, pathlib, sys
stage, root, source, uid, gid = sys.argv[1:]
checks = {}
for name, directory in [('stage', stage), ('root', root)]:
    path = pathlib.Path(directory) / 'runtime-access-sentinel'
    before = path.read_text()
    path.write_text(before + '-container')
    checks[name] = {'owner_uid': path.stat().st_uid, 'owner_gid': path.stat().st_gid,
                   'mode': oct(path.stat().st_mode & 0o777),
                   'read_write': path.read_text() == before + '-container'}
status = pathlib.Path('/proc/self/status').read_text()
security = {line.split(':')[0]: line.split(':')[1].strip() for line in status.splitlines()
            if line.startswith(('CapEff:', 'NoNewPrivs:'))}
try:
    pathlib.Path('/runtime-write-denied').write_text('must fail')
    readonly = False
except OSError as error:
    readonly = error.errno in (errno.EROFS, errno.EACCES)
mountinfo = pathlib.Path('/proc/self/mountinfo').read_text()
mounts = {line.split()[4]: line.split()[5] for line in mountinfo.splitlines()}
result = {'uid': os.getuid(), 'gid': os.getgid(), 'sentinels': checks, 'security': security,
          'rootfs_write_denied': readonly, 'source_visible': pathlib.Path(source).exists(),
          'mountinfo': mountinfo}
ok = (os.getuid() == int(uid) and os.getgid() == int(gid) and readonly
      and 'ro' in mounts['/'].split(',') and not result['source_visible']
      and all(value['read_write'] for value in checks.values())
      and all('rw' in mounts[directory].split(',') for directory in (stage, root))
      and int(security['CapEff'], 16) == 0 and security['NoNewPrivs'] == '1')
result['status'] = 'PASS' if ok else 'FAIL'
print(json.dumps(result))
sys.exit(0 if ok else 1)
'''


def boundary_preflight(install=False, runtime_only=False):
    """Collect real Linux boundaries and offline gates; never install on a failed boundary."""
    import time
    suffix = os.urandom(6).hex()
    root_suffix = os.urandom(12).hex()
    if runtime_only and install:
        raise ValueError('runtime-only probes cannot install')
    stage = launch.BASE / ('amadis-stage-' + suffix)
    root = launch.BASE / ('amadis-' + root_suffix)
    evidence = launch.REPO / 'docs/execution/evidence/2026-10-13' / ('stage-runtime' if runtime_only else 'pdf-stage') / suffix
    evidence.mkdir(parents=True, exist_ok=False)
    stage.mkdir(mode=0o700)
    root.mkdir(mode=0o700)
    composer_network = 'amadis-stage-composer-' + suffix
    sentinel_network = 'amadis-stage-sentinel-' + suffix
    names = {kind: 'amadis-stage-' + kind + '-' + suffix for kind in ('proxy', 'sentinel', 'composer')}
    created = []
    records = {}
    required = ({'topology', *BOUNDARY_REQUIRED, 'control_direct_egress', 'runtime_sentinel'}
                if runtime_only else set(STAGE_REQUIRED))
    if install:
        required |= {'install', 'pins', 'hooks', 'class_load', 'change_scope'}
    report = {'platform': 'Linux container', 'image': IMAGE, 'stage': str(stage), 'root': str(root),
              'predicates': records, 'applied_to_mac': False, 'install_gate': False}

    def run(label, argv, timeout=120, env=None):
        started = launch.timestamp()
        try:
            p = subprocess.run(argv, capture_output=True, text=True, errors='replace', timeout=timeout, env=env)
            rc, stdout, stderr = p.returncode, p.stdout, p.stderr
        except (OSError, subprocess.TimeoutExpired) as error:
            rc, stdout, stderr = 124 if isinstance(error, subprocess.TimeoutExpired) else 127, '', str(error)
        (evidence / (label + '.stdout')).write_text(launch.sanitized(stdout))
        (evidence / (label + '.stderr')).write_text(launch.sanitized(stderr))
        (evidence / (label + '.json')).write_text(json.dumps({'command': launch.sanitized_command(argv),
                                                            'returncode': rc, 'platform': 'Linux container',
                                                            'start': started, 'finish': launch.timestamp()}, indent=2))
        return rc, stdout, stderr

    def predicate(name, ok, rc=0, detail=None):
        records[name] = {'status': 'PASS' if ok else 'FAIL', 'returncode': rc if rc else (0 if ok else 1), 'detail': detail}

    def must(label, argv):
        rc, out, err = run(label, argv)
        if rc:
            raise RuntimeError(f'{label}: rc={rc}: {err}')
        return out

    def detached(kind, args, net, env=(), mount_root=None):
        command = container_command(stage, IMAGE, args, mount_root, env, net)
        command[command.index('--rm'):command.index('--rm') + 1] = ['--detach', '--name', names[kind]]
        must('start-' + kind, command)
        created.append(('container', names[kind]))

    try:
        copy_source(launch.REPO, stage / 'repo')
        identity = stage_identity(stage, root, launch.REPO)
        launch.write_new(root, 'identity.json', json.dumps(identity, indent=2) + '\n')
        modes = {str(p.relative_to(stage / 'repo')): oct(stat.S_IMODE(p.stat().st_mode))
                 for p in (stage / 'repo').rglob('*') if p.is_file() and p.stat().st_mode & 0o111}
        (evidence / 'source-executable-modes.json').write_text(json.dumps(modes, indent=2))
        predicate('source_modes', all((launch.REPO / name).stat().st_mode & 0o111 for name in modes)
                  and 'scripts/amadis/launch.sh' in modes, detail={'executable_count': len(modes)})
        (stage / 'probe.py').write_text(PROBE_SCRIPT)
        (stage / 'sentinel.php').write_text(SENTINEL_SCRIPT)
        for network in (composer_network, sentinel_network):
            must('network-create-' + network, ['docker', 'network', 'create', '--internal', network])
            created.append(('network', network))
        detached('proxy', ['php', 'scripts/amadis/connect_proxy.php'], 'bridge',
                 [('PROXY_LISTEN', '0.0.0.0:8080'), ('PROXY_ALLOW_NONLOOPBACK', '1')])
        for network in (composer_network, sentinel_network):
            must('network-connect-' + network,
                 ['docker', 'network', 'connect', '--alias', 'amadis-proxy', network, names['proxy']])
        detached('sentinel', ['php', str(stage / 'sentinel.php')], sentinel_network)
        detached('composer', ['sleep', '600'], composer_network, mount_root=root)
        time.sleep(1)
        inspection = json.loads(must('inspect-containers', ['docker', 'inspect', *names.values()]))
        netinfo = json.loads(must('inspect-network', ['docker', 'network', 'inspect', composer_network, sentinel_network]))
        bridge = json.loads(must('inspect-bridge', ['docker', 'network', 'inspect', 'bridge']))
        gateway = bridge[0]['IPAM']['Config'][0]['Gateway']
        by_name = {item['Name'].lstrip('/'): item for item in inspection}
        proxy = by_name[names['proxy']]
        composer = by_name[names['composer']]
        sentinel = by_name[names['sentinel']]
        containers = {'proxy': proxy, 'composer': composer, 'sentinel': sentinel}
        network_info = {item['Name']: item for item in netinfo}
        topology_ok = topology_valid(containers, network_info, composer_network, sentinel_network)
        predicate('topology', topology_ok, detail={'proxy_networks': sorted(proxy['NetworkSettings']['Networks']),
                                                    'composer_networks': sorted(composer['NetworkSettings']['Networks']),
                                                    'sentinel_networks': sorted(sentinel['NetworkSettings']['Networks'])})
        sentinel_ip = sentinel['NetworkSettings']['Networks'][sentinel_network]['IPAddress']
        rc, out, _ = run('boundary-probes', ['docker', 'exec', names['composer'], 'python3',
                                           str(stage / 'probe.py'), 'amadis-proxy', sentinel_ip, gateway])
        records.update(probe_results(out, rc, BOUNDARY_REQUIRED))
        # A reachable control container on the default bridge proves the host and its literal-IP
        # egress are live, so the composer probe's ENETUNREACH is a topology confinement, not a dead host.
        (stage / 'control.py').write_text(CONTROL_SCRIPT)
        rc, out, _ = run('control-direct-egress', container_command(stage, IMAGE,
                         ['python3', str(stage / 'control.py')], None, (), 'bridge'))
        control = json.loads(out) if rc == 0 else []
        predicate('control_direct_egress', rc == 0 and any(item.get('connected') for item in control), rc, control)
        # Runtime identity/mount/effective-access sentinel under the exact install restrictions.
        (stage / 'runtime.py').write_text(RUNTIME_SCRIPT)
        for directory, label in ((stage, 'stage'), (root, 'root')):
            (directory / 'runtime-access-sentinel').write_text(label)
        rc, out, _ = run('runtime-sentinel', ['docker', 'exec', names['composer'], 'python3',
                          str(stage / 'runtime.py'), str(stage), str(root), str(launch.REPO),
                          str(os.getuid()), str(os.getgid())])
        sentinel_result = json.loads(out) if rc == 0 else {}
        predicate('runtime_sentinel', rc == 0 and sentinel_result.get('status') == 'PASS', rc, sentinel_result)
        if runtime_only:
            # Runtime evidence is not an installation authorization.
            return 0 if install_gate(records, required) else 1
        # Docker Desktop maps host-owned bind mounts to uid 0 inside the container, so launch.prepare's
        # owner check can only hold on the host. The PHP guards do not inspect uid, so they stay intact.
        rc, err = 0, None
        try:
            launch.prepare(root)
            launch.write_new(root, 'demo-key', 'base64:' + base64.b64encode(os.urandom(32)).decode())
        except (Exception, SystemExit) as error:
            rc, err = 1, launch.sanitized(str(error))
        (evidence / 'init.json').write_text(json.dumps(
            {'command': 'host launch.prepare(root) + demo-key', 'returncode': rc,
             'platform': 'macOS host (bind-mount owner check)', 'error': err}, indent=2))
        predicate('init', rc == 0, rc, err)
        if rc == 0:
            env = stage_env(root, launch.read_file(root, 'demo-key'))
            for label, args in [('platform', ['composer', 'check-platform-reqs', '--lock']),
                                ('platform-no-dev', ['composer', 'check-platform-reqs', '--lock', '--no-dev']),
                                ('sqlite', ['php', '-m']),
                                ('artisan', ['php', 'scripts/amadis/artisan.php', '--version'])]:
                rc, out, err = run(label, container_command(stage, IMAGE, args, root, env))
                ok = rc == 0 and (label != 'sqlite' or {'pdo_sqlite', 'sqlite3'} <= set(out.splitlines()))
                predicate(label, ok, rc, err or None)
        else:
            for label in ('platform', 'platform-no-dev', 'sqlite', 'artisan'):
                predicate(label, False, rc, 'skipped: isolated host init failed')
        if install and install_gate(records, required - {'install', 'pins', 'hooks', 'class_load', 'change_scope'}):
            install_records = install_stage(stage, root, evidence, run, composer_network, records)
            records.update(install_records)
        report['install_gate'] = install_gate(records, required)
        report['ruling'] = ('Only the validated Composer closure may be applied after all boundaries, hooks, '
                            'archive hashes, and class-load checks pass. Cost if ignored: unreviewed executable '
                            'Composer code could modify project files or internal peers.')
    except (Exception, SystemExit) as error:
        report['error'] = launch.sanitized(str(error))
    finally:
        for name in required:
            if name not in records:
                predicate(name, False, detail='missing or crashed probe')
        for kind, name in created:
            if kind == 'container':
                run('logs-' + name, ['docker', 'logs', name])
        for label, argv in cleanup_commands(created):
            run(label, argv)
        report['install_gate'] = install_gate(records, required)
        report['returncode'] = 0 if report['install_gate'] else 1
        (evidence / 'report.json').write_text(json.dumps(report, indent=2) + '\n')
        for name in ('composer.lock', 'vendor/composer/installed.json'):
            path = stage / 'repo' / name
            if path.exists():
                shutil.copyfile(path, evidence / Path(name).name)
        print(json.dumps({'evidence': str(evidence), 'returncode': report['returncode'], 'predicates': records}, indent=2))
    return report['returncode']


def main(args=None):
    args = sys.argv[1:] if args is None else args
    if args == ['boundary-preflight']:
        return boundary_preflight()
    if args == ['stage-runtime']:
        return boundary_preflight(runtime_only=True)
    if args == ['stage-install']:
        return boundary_preflight(install=True)
    if args != ['preflight']:
        raise SystemExit('network egress for Composer/Packagist/GitHub not proven confined; install refused')
    stage = launch.BASE / ('amadis-stage-' + os.urandom(12).hex())
    root = launch.BASE / ('amadis-' + os.urandom(12).hex())
    stage.mkdir(mode=0o700)
    root.mkdir(mode=0o700)
    result = {'category': 'stage_failed', 'returncode': 1}
    try:
        copy_source(launch.REPO, stage / 'repo')
        identity = stage_identity(stage, root, launch.REPO)
        launch.write_new(root, 'identity.json', json.dumps(identity, indent=2) + '\n')
        for label, argv in (('init', ['python3', 'scripts/amadis/launch.py', 'init']),
                            ('platform', ['composer', 'check-platform-reqs', '--lock', '--no-ansi'])):
            record = run_gate(root, label, container_command(stage, IMAGE, argv, root, [('AMADIS_ROOT', str(root))]))
            result = {'gate': label, 'category': record['category'], 'returncode': record['returncode']}
            if record['returncode']:
                break
    except subprocess.CalledProcessError as error:
        result = {'category': 'git_unavailable', 'returncode': 3, 'error': launch.sanitized(str(error))}
    except SystemExit as error:
        result = {'category': 'stage_input_refused', 'returncode': 2, 'error': launch.sanitized(str(error))}
    finally:
        # Root and its evidence are kept for inspection; only the disposable source copy is removed.
        shutil.rmtree(stage, ignore_errors=True)
        launch.write_new(root, 'evidence/result.json', json.dumps(dict(result, root=str(root)), indent=2) + '\n')
        print(json.dumps(dict(result, root=str(root))))
    return result['returncode']


if __name__ == '__main__':
    raise SystemExit(main())
