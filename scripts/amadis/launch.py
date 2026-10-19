#!/usr/bin/env python3
"""Isolated Amadis demo runner. Never bootstraps the production app directly."""
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import zipfile
import stat
import shlex
import subprocess
import sys
from urllib.parse import quote, quote_plus
from urllib.request import Request, urlopen
from datetime import datetime, timezone

BASE = Path('/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode')
REPO = Path(__file__).resolve().parents[2]
R44_FIXTURE_SHA256 = '5845ec8a06a78a0a5f6f599bf4a97b45c096f187453550b7d1def97227860da5'
SEEDER = 'Database\\Seeders\\AmadisDemoSeeder'

# Only documented read-only diagnostics; other operations have dedicated launcher commands.
ARTISAN_OPTIONS = {'about': frozenset(), 'route:list': frozenset({'--json'})}

def fail(message):
    raise SystemExit('amadis: ' + message)

def timestamp():
    return datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')

SECRET_NAME = r'[\w.-]*(?:app[_-]?(?:key|previous[_-]?keys)|[_-]key|password|passwd|pass|token|secret|cookie|session|authorization|auth(?!or)|credentials?|api[_-]?key|db[_-]?url|redis[_-]?url|csrf|xsrf|private[_-]?key|privatekey)[\w.-]*'


# Short or generic CLI options that carry secrets (curl -u user:pass, --key, --auth).
SECRET_OPTIONS = {'p', 'u', 'user', 'pass', 'key', 'auth'}


def sensitive(name):
    return re.fullmatch(SECRET_NAME, name, re.I) is not None


def sanitized(text):
    # Decode JSON values, not whitespace tokens: escaped quotes and nested values matter.
    decoder = json.JSONDecoder()
    pattern = re.compile(r'"(?:[^"\\]|\\.)*"\s*:\s*')
    replacements = []
    for match in pattern.finditer(text):
        key = decoder.raw_decode(match.group())[0]
        if not sensitive(key):
            continue
        try:
            _, length = decoder.raw_decode(text[match.end():])
        except ValueError:
            continue
        replacements.append((match.end(), match.end() + length))
    end = 0
    chunks = []
    for start, stop in replacements:
        if start < end:
            continue
        chunks.extend((text[end:start], '"[REDACTED]"'))
        end = stop
    text = ''.join(chunks) + text[end:]
    # Headers may have transport prefixes; continuation lines may repeat that prefix.
    # Prefixed continuation: same prefix, then a fold or any line not shaped like a new header.
    text = re.sub(r'(?im)^([^\r\n]*?)(\b(?:set-cookie|cookie|authorization|proxy-authorization)[ \t]*:[ \t]*)[^\r\n]*'
                  r'(?:\r?\n(?:[ \t]+|\1(?<=[^\w\r\n])(?:[ \t]+|(?![\w-]+[ \t]*:)))[^\r\n]*)*',
                  lambda m: (m[1] or '') + m[2] + '[REDACTED]', text)
    # Auth schemes carry the secret after the keyword, including quoted values.
    text = re.sub(r'''(?i)(\b(?:Bearer|Basic)[ \t]+)(?:\\".*?\\"|"(?:[^"\\]|\\.)*"|'[^']*'|[^\s,;}]+)''', r'\1[REDACTED]', text)
    # PHP var_dump/print_r bracket keys.
    text = re.sub(r'''(?is)(\[\s*["']?''' + SECRET_NAME + r'''["']?\s*\]\s*=>\s*(?:\r?\n\s*)?(?:string\(\d+\)\s*)?)(?:"(?:[^"\\]|\\.)*"|'[^']*'|[^\s,;}]+)''', r'\1[REDACTED]', text)
    # Multiline or escaped PEM key blocks.
    text = re.sub(r'(?is)((?:private[_-]?key|privatekey)\s*[:=]\s*)-----BEGIN [^-]+-----(?:\\n|[\r\n]|.)*?-----END [^-]+-----', r'\1[REDACTED]', text)
    # Credentials embedded in URL userinfo, e.g. mysql://user:pass@host.
    text = re.sub(r'(?i)\b([a-z][a-z0-9+.-]*://)[^\s/@"\']*@', r'\1[REDACTED]@', text)
    value = r'''(?:"(?:[^"\\]|\\.)*"|'[^']*'|[^\s,;}]+)'''
    # Keys may be bare, quoted, or dotted; "=>" is PHP syntax handled separately below.
    text = re.sub(r'''(?i)(?<![\w.-])(["']?)(''' + SECRET_NAME + r''')\1([ \t]*(?:=(?!>)|:)[ \t]*)(''' + value + ')',
                  lambda m: m[1] + m[2] + m[1] + m[3] + ('"[REDACTED]"' if m[4].startswith('"') else "'[REDACTED]'" if m[4].startswith("'") else '[REDACTED]'), text)
    # PHP array syntax, e.g. tinker one-liners: "password"=>"value".
    text = re.sub(r'''(?i)(?<![\w.-])(["']?)(''' + SECRET_NAME + r''')\1(\s*=>\s*)(''' + value + ')',
                  lambda m: m[1] + m[2] + m[1] + m[3] + ('"[REDACTED]"' if m[4].startswith('"') else "'[REDACTED]'" if m[4].startswith("'") else '[REDACTED]'), text)
    text = re.sub(r'(?i)(--' + SECRET_NAME + r'[ \t]+)(' + value + ')', r'\1[REDACTED]', text)
    return text


def sanitized_command(argv):
    clean = []
    redact_next = False
    for arg in argv:
        if redact_next:
            clean.append('[REDACTED]')
            redact_next = False
        else:
            key, separator, _ = arg.partition('=')
            option = key.lstrip('-')
            # -p (mysql/redis-cli password) and -u (curl user:pass) carry secrets in attached form too.
            if arg[:2] in {'-p', '-u'} and not arg.startswith('--') and len(arg) > 2:
                clean.append(arg[:2] + '[REDACTED]')
            else:
                clean.append(key + '=[REDACTED]' if separator and (sensitive(option) or option in SECRET_OPTIONS) else sanitized(arg))
            redact_next = (arg.startswith('--') and '=' not in arg and (sensitive(arg[2:]) or arg[2:] in SECRET_OPTIONS)) or arg in {'-p', '-u'}
    return shlex.join(clean)

lock = None # exclusive per-root lock; children inherit it so the root stays claimed while they run

WRITABLE = ['evidence', 'bootstrap', 'storage/app/private', 'storage/app/public', 'storage/framework/cache/data',
            'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'uploads']
DIR_FLAGS = os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW


def open_dir(root, relative=''):
    """Open root/relative one component at a time; any symlink or non-directory fails closed."""
    try:
        fd = os.open(root, DIR_FLAGS)
        for part in Path(relative).parts:
            try:
                os.mkdir(part, 0o700, dir_fd=fd)
            except FileExistsError:
                pass
            child = os.open(part, DIR_FLAGS, dir_fd=fd)
            os.close(fd)
            fd = child
        return fd
    except OSError as error:
        fail(f'unsafe root path {relative or "."}: {error.strerror}')


def open_file(root, relative, flags, mode=0o600):
    path = Path(relative)
    directory = open_dir(root, path.parent)
    try:
        return os.open(path.name, flags | os.O_NOFOLLOW, mode, dir_fd=directory)
    except OSError as error:
        fail(f'unsafe root file {relative}: {error.strerror}')
    finally:
        os.close(directory)


def write_new(root, relative, text):
    with os.fdopen(open_file(root, relative, os.O_WRONLY | os.O_CREAT | os.O_EXCL), 'w') as handle:
        handle.write(text)


def read_file(root, relative):
    fd = open_file(root, relative, os.O_RDONLY)
    if not stat.S_ISREG(os.fstat(fd).st_mode) or os.fstat(fd).st_nlink != 1:
        os.close(fd)
        fail(f'unsafe root file {relative}: not a single-link regular file')
    with os.fdopen(fd) as handle:
        return handle.read()


def prepare(root):
    """Create writable folders and refuse links that PHP or the runner could follow outside root."""
    info = os.lstat(root)
    if not stat.S_ISDIR(info.st_mode) or info.st_uid != os.getuid() or info.st_mode & 0o022:
        fail('root must be an owner-only directory')
    for folder in WRITABLE:
        os.close(open_dir(root, folder))
    for top in ['evidence', 'bootstrap', 'storage', 'uploads']:
        for current, folders, files in os.walk(root / top):
            for name in folders + files:
                path = Path(current, name)
                info = os.lstat(path)
                if os.path.islink(path) or (not os.path.isdir(path) and info.st_nlink != 1):
                    fail(f'linked writable path refused: {path.relative_to(root)}')
    for name in ['database.sqlite', 'identity.json', '.lock', 'demo-key']:
        path = root / name
        if os.path.lexists(path) and (os.path.islink(path) or not path.is_file() or os.lstat(path).st_nlink != 1):
            fail(f'linked root file refused: {name}')
    os.close(open_file(root, 'database.sqlite', os.O_WRONLY | os.O_CREAT))


def redact_literal_secrets(text, env=None):
    sources = (env or {}, os.environ)
    for key in ('R44_ADMIN_PASSWORD', 'R44_TOTP_SECRET'):
        for source in sources:
            secret = source.get(key)
            if not secret:
                continue
            escaped = json.dumps(secret)[1:-1]
            variants = {
                secret,
                escaped,
                f'"{secret}"',
                f"'{secret}'",
                f'"{escaped}"',
                f"'{escaped}'",
                quote(secret, safe=''),
                quote_plus(secret),
            }
            for variant in sorted((v for v in variants if v), key=len, reverse=True):
                if variant[0] in {'"', "'"} and variant[-1] == variant[0]:
                    text = text.replace(variant, f'{variant[0]}[REDACTED]{variant[0]}')
                else:
                    text = text.replace(variant, '[REDACTED]')
    return text


def verify_r44_fixture():
    path = BASE / 'r44-final/fixture/seed-r44.php'
    try:
        fd = os.open(path, os.O_RDONLY | os.O_NOFOLLOW)
    except OSError as error:
        fail(f'unsafe r44 fixture {path}: {error.strerror}')
    try:
        info = os.fstat(fd)
        if (not stat.S_ISREG(info.st_mode) or stat.S_ISLNK(info.st_mode)
                or info.st_nlink != 1 or info.st_uid != os.getuid() or info.st_mode & 0o022):
            fail('r44 fixture must be an owner-only single-link regular file')
        with os.fdopen(fd, 'rb', closefd=False) as handle:
            data = handle.read()
    finally:
        os.close(fd)
    if hashlib.sha256(data).hexdigest() != R44_FIXTURE_SHA256:
        fail('r44 fixture sha256 mismatch')
    return data


def capture(root, label, argv, env, input_data=None):
    stem = 'evidence/' + datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ') + '-' + label + '-' + secrets.token_hex(3)
    start = timestamp()
    result = subprocess.run(argv, cwd=REPO, env=env, capture_output=True, text=input_data is None,
                            input=input_data, pass_fds=(lock.fileno(),),
                            **({'errors': 'replace'} if input_data is None else {}))
    stdout = result.stdout if input_data is None else result.stdout.decode(errors='replace')
    stderr = result.stderr if input_data is None else result.stderr.decode(errors='replace')
    stdout = sanitized(redact_literal_secrets(stdout, env))
    stderr = sanitized(redact_literal_secrets(stderr, env))
    metadata = {'start': start, 'finish': timestamp(), 'command': redact_literal_secrets(sanitized_command(argv), env), 'returncode': result.returncode}
    write_new(root, stem + '.json', json.dumps(metadata, indent=2) + '\n')
    write_new(root, stem + '.stdout', stdout)
    write_new(root, stem + '.stderr', stderr)
    print(stdout, end='')
    print(stderr, end='', file=sys.stderr)
    return result.returncode

def provision(root, env):
    """One-shot credentials for a fresh fixture; never capture raw child output."""
    import sqlite3
    with sqlite3.connect(f'file:{root / "database.sqlite"}?mode=ro', uri=True) as db:
        try:
            used = db.execute('select count(*) from orders').fetchone()[0]
        except sqlite3.Error:
            used = -1
    if used != 0:
        print(json.dumps({'status': 'refused'}))
        return 1  # fresh migrated fixture only: never touch roots with orders or without schema
    credentials = {role: {'email': f'amadis-{role}@example.test',
                          'password': secrets.token_urlsafe(32)}
                   for role in ('admin', 'customer')}
    write_new(root, 'private-runtime/credentials.json', json.dumps(credentials) + '\n')
    argv = ['php', 'scripts/amadis/artisan.php', 'amadis:provision']
    result = subprocess.run(argv, cwd=REPO, env=env, stdin=subprocess.DEVNULL,
                            capture_output=True, text=True, pass_fds=(lock.fileno(),))
    # Framework exceptions can quote SQL bindings. Discard everything except the success schema.
    output = {'status': 'failed'}
    if result.returncode == 0:
        try:
            value = json.loads(result.stdout)
            if (set(value) == {'status', 'admin_id', 'customer_id'} and value['status'] == 'provisioned'
                    and type(value['admin_id']) is int and type(value['customer_id']) is int):
                output = value
        except (ValueError, TypeError):
            pass
    record = {'command': sanitized_command(argv), 'returncode': result.returncode, 'result': output}
    write_new(root, 'evidence/provision-' + secrets.token_hex(6) + '.json', json.dumps(record) + '\n')
    print(json.dumps(output))
    return 0 if output['status'] == 'provisioned' else 1


PDF_PACKAGE = 'dompdf/dompdf'
PDF_VERSION = '3.1.6'
# Approved closure, pinned from official Packagist/GitHub metadata. Version, full-40 commit, repo slug and license.
PDF_PINS = {
    'dompdf/dompdf': {'version': '3.1.6', 'ref': '6d4b4eb8500f7a786da8868ba463a71b725a4005', 'slug': 'dompdf/dompdf', 'license': ['LGPL-2.1']},
    'dompdf/php-font-lib': {'version': '1.0.2', 'ref': 'a6e9a688a2a80016ac080b97be73d3e10c444c9a', 'slug': 'dompdf/php-font-lib', 'license': ['LGPL-2.1-or-later']},
    'dompdf/php-svg-lib': {'version': '1.0.2', 'ref': '8259ffb930817e72b1ff1caef5d226501f3dfeb1', 'slug': 'dompdf/php-svg-lib', 'license': ['LGPL-3.0-or-later']},
    'masterminds/html5': {'version': '2.11.0', 'ref': 'a1e7a2f88ee13635d86fc61cfbdf2306a76ddfc7', 'slug': 'Masterminds/html5-php', 'license': ['MIT']},
    'sabberworm/php-css-parser': {'version': '9.5.0', 'ref': 'f284e63b6e891e0c28631e54ba06c3ed102a9ef3', 'slug': 'MyIntervals/PHP-CSS-Parser', 'license': ['MIT']},
}
PDF_CLOSURE = set(PDF_PINS)
PACKAGIST = 'https://repo.packagist.org/p2/'
EXPECTED_HOOKS = {
    'post-autoload-dump': ['App\\Support\\ComposerHooks::postAutoloadDump'],
    'post-update-cmd': ['App\\Support\\ComposerHooks::postUpdate'],
    'pre-package-uninstall': ['App\\Support\\ComposerHooks::prePackageUninstall'],
}
FULL_SHA = re.compile(r'[0-9a-f]{40}')


def _pdf_json(path):
    try:
        value = json.loads(path.read_text())
    except (OSError, ValueError) as error:
        fail(f'cannot read {path.name}: {error}')
    if not isinstance(value, dict):
        fail(f'{path.name} must contain an object')
    return value


def _pdf_validate_project():
    composer = _pdf_json(REPO / 'composer.json')
    lock = _pdf_json(REPO / 'composer.lock')
    scripts = composer.get('scripts', {})
    for hook, expected in EXPECTED_HOOKS.items():
        if scripts.get(hook) != expected:
            fail(f'composer hook changed: {hook}')
    allow = composer.get('config', {}).get('allow-plugins', {})
    if not isinstance(allow, dict) or any(not isinstance(k, str) or v is not True for k, v in allow.items()):
        fail('allow-plugins must contain only explicit true entries')
    installed = _pdf_json(REPO / 'vendor/composer/installed.json')
    packages = lock.get('packages', []) + lock.get('packages-dev', [])
    installed_packages = installed.get('packages', [])
    if not isinstance(packages, list) or not isinstance(installed_packages, list):
        fail('invalid Composer package list')
    names = {p.get('name') for p in packages if isinstance(p, dict)}
    plugins = {p.get('name') for p in packages + installed_packages if isinstance(p, dict) and p.get('type') == 'composer-plugin'}
    if plugins - set(allow):
        fail('installed Composer plugin is not allowlisted')
    return composer, lock, names


def _pdf_state(packages):
    """name -> (version, immutable reference) for lock or installed.json package lists."""
    state = {}
    for p in packages:
        ref = (p.get('dist') or {}).get('reference') or (p.get('source') or {}).get('reference')
        state[p.get('name')] = (p.get('version'), ref)
    return state


def _pdf_vendor_matches_lock(lock, installed):
    if _pdf_state(lock.get('packages', []) + lock.get('packages-dev', [])) != _pdf_state(installed.get('packages', [])):
        fail('vendor/composer/installed.json does not match composer.lock; refusing install')


def _pdf_expand(versions):
    """Expand Composer p2 'composer/2.0' minified metadata: later entries carry only changes; '__unset' deletes."""
    current, expanded = {}, []
    for entry in versions:
        current = dict(current)
        for key, value in entry.items():
            if value == '__unset':
                current.pop(key, None)
            else:
                current[key] = value
        expanded.append(current)
    return expanded


def _pdf_fetch(name):
    try:
        request = Request(PACKAGIST + name + '.json', headers={'Accept': 'application/json'})
        with urlopen(request, timeout=15) as response:
            raw = response.read(4 * 1024 * 1024 + 1)
        if len(raw) > 4 * 1024 * 1024:
            raise ValueError('metadata too large')
        return json.loads(raw)
    except Exception as error:
        fail(f'Packagist metadata unavailable for {name}: {error}')


def _pdf_check_release(name, release, pin):
    """Shared by Packagist metadata and lock entries: identity, immutable refs, official URLs, license."""
    slug, ref = pin['slug'], pin['ref']
    source, dist = release.get('source') or {}, release.get('dist') or {}
    if (str(release.get('version')).lstrip('v') != pin['version'] or release.get('name') != name
            or release.get('type') != 'library' or release.get('license') != pin['license']
            or not FULL_SHA.fullmatch(ref) or source.get('reference') != ref or dist.get('reference') != ref
            or source.get('type') != 'git' or source.get('url') != f'https://github.com/{slug}.git'
            or dist.get('type') != 'zip' or dist.get('url') != f'https://api.github.com/repos/{slug}/zipball/{ref}'
            or dist.get('shasum') not in ('', None)):
        fail(f'{name} does not match approved version/source/dist/license')
    if not isinstance(release.get('require'), dict):
        fail(f'{name} requirements missing')
    for requirement in release['require']:
        if not (requirement == 'php' or requirement.startswith('ext-') or requirement in PDF_CLOSURE):
            fail(f'{name} requires unapproved package {requirement}')


def _pdf_metadata(name):
    pin = PDF_PINS[name]
    data = _pdf_fetch(name)
    if data.get('minified') != 'composer/2.0':
        fail(f'{name} metadata format is not composer/2.0')
    versions = data.get('packages', {}).get(name)
    if not isinstance(versions, list):
        fail(f'{name} metadata missing')
    matches = [r for r in _pdf_expand(versions) if r.get('version_normalized') == pin['version'] + '.0']
    if len(matches) != 1:
        fail(f'{name} approved version not found exactly once')
    _pdf_check_release(name, matches[0], pin)
    return matches[0]


def _pdf_read_bytes(root, relative):
    fd = open_file(root, relative, os.O_RDONLY)
    info = os.fstat(fd)
    if not stat.S_ISREG(info.st_mode) or info.st_nlink != 1:
        os.close(fd)
        fail(f'unsafe root file {relative}: not a single-link regular file')
    with os.fdopen(fd, 'rb') as handle:
        return handle.read()


def _pdf_write_bytes(root, relative, data, flags):
    with os.fdopen(open_file(root, relative, os.O_WRONLY | flags), 'wb') as handle:
        handle.write(data)


def _pdf_sha(data):
    return hashlib.sha256(data).hexdigest()


def _pdf_fingerprint():
    return {name: _pdf_sha(_pdf_read_bytes(REPO, name)) for name in ('composer.json', 'composer.lock', 'vendor/composer/installed.json')}


def _pdf_validate_candidate(old_json, old_lock, new_json, new_lock, releases):
    """Candidate must be the old project plus exactly the approved dependency delta; nothing else may move."""
    expected = dict(old_json)
    expected['require'] = dict(old_json.get('require', {}), **{PDF_PACKAGE: PDF_VERSION})
    if new_json != expected:
        fail('candidate composer.json differs from the approved dependency delta')
    if set(new_lock) != set(old_lock):
        fail('candidate lock changed its top-level shape')
    for key in set(old_lock) - {'packages', 'content-hash'}:
        if new_lock[key] != old_lock[key]:
            fail(f'candidate lock changed {key}')  # includes packages-dev, platform, aliases, stability, plugin-api-version
    if new_lock['content-hash'] == old_lock['content-hash']:
        fail('candidate lock content-hash did not change')
    old = {p['name']: p for p in old_lock['packages']}
    new = {p['name']: p for p in new_lock['packages']}
    if len(new) != len(new_lock['packages']) or set(new) - set(old) != PDF_CLOSURE or set(old) - set(new):
        fail('candidate lock does not add exactly the approved closure')
    if set(new) & {p['name'] for p in old_lock['packages-dev']}:
        fail('candidate lock duplicates a dev package')
    for name, package in old.items():
        if new[name] != package:
            fail(f'candidate lock changed existing package {name}')
    for name in PDF_CLOSURE:
        entry = new[name]
        if entry.get('type') == 'composer-plugin':
            fail(f'{name} is a Composer plugin')
        _pdf_check_release(name, entry, PDF_PINS[name])
        for field in ('version', 'source', 'dist', 'require', 'license', 'type'):
            if entry.get(field) != releases[name].get(field):
                fail(f'candidate lock {name}.{field} differs from Packagist metadata')
    return sorted(PDF_CLOSURE)


def _pdf_run(root, label, argv, cwd, env, pass_fds):
    start = timestamp()
    try:
        result = subprocess.run(argv, cwd=cwd, env=env, stdin=subprocess.DEVNULL, capture_output=True, text=True,
                                errors='replace', timeout=600, pass_fds=pass_fds)
    except subprocess.TimeoutExpired:
        fail(f'composer {label} timed out')
    stem = 'evidence/pdf-' + label + '-' + secrets.token_hex(6)
    write_new(root, stem + '.json', json.dumps({'start': start, 'finish': timestamp(), 'command': sanitized_command(argv),
                                                'cwd': str(cwd), 'returncode': result.returncode}, indent=2) + '\n')
    write_new(root, stem + '.stdout', sanitized(result.stdout or ''))
    write_new(root, stem + '.stderr', sanitized(result.stderr or ''))
    return result, stem


def _pdf_archives(cache, lock_entries):
    """Hash the downloaded zip per package. Composer's cache name is undocumented, so match on content, not path."""
    files = Path(cache, 'files')
    hashes = {}
    for name in PDF_PINS:
        # Composer 2 caches dists as files/<vendor>/<package>/<internal key>.zip; the key is not a public
        # contract, so take the package directory of this fresh private cache and require exactly one zip.
        directory = files / name
        if directory.is_symlink() or any(p.is_symlink() for p in directory.parents if files in p.parents):
            fail(f'{name} cache directory is linked')
        entries = sorted(directory.iterdir()) if directory.is_dir() else []
        found = [p for p in entries if not p.is_symlink() and p.is_file() and p.stat().st_nlink == 1
                 and p.stat().st_size > 0 and zipfile.is_zipfile(p)]
        if len(found) != 1 or len(entries) != 1:
            fail(f'{name} dist archive is missing or ambiguous in the private Composer cache ({len(found)} candidates)')
        digest = _pdf_sha(found[0].read_bytes())
        if not re.fullmatch(r'[0-9a-f]{64}', digest):
            fail(f'{name} dist archive hash is not a 64-character SHA-256')
        hashes[name] = {'cache_path': str(found[0].relative_to(cache)), 'local_sha256': digest,
                        'upstream_shasum': lock_entries[name]['dist'].get('shasum') or None}
    if set(hashes) != PDF_CLOSURE:
        fail('dist archive hashes do not cover exactly the approved closure')
    return hashes


def _pdf_candidate_hook_guard():
    # Composer runs project hooks and plugins with repository cwd and filesystem access.
    # A snapshot cannot bound arbitrary writes, including untracked user files and linked paths.
    fail('cannot protect arbitrary repository writes from Composer hooks/plugins while using real repo cwd; '
         'refusing before Composer, lock creation, or mutation')


def _pdf_tree(path):
    """Fingerprint regular vendor files and modes; reject links and special files before backup."""
    if path.is_symlink() or not path.is_dir():
        fail('vendor must be a real directory')
    result = {}
    for current, dirs, files in os.walk(path):
        for name in dirs + files:
            entry = Path(current, name)
            info = entry.lstat()
            if not (stat.S_ISDIR(info.st_mode) or stat.S_ISREG(info.st_mode)) or info.st_nlink != 1 and entry.is_file():
                fail('vendor contains linked or special entries')
            result[str(entry.relative_to(path))] = (stat.S_IMODE(info.st_mode),
                                                   _pdf_sha(entry.read_bytes()) if entry.is_file() else None)
    return result


def _pdf_transaction(root, before, candidate, install_and_verify):
    """Keep originals untouched in a same-filesystem backup until installation and verification finish."""
    if before != _pdf_fingerprint():
        fail('repository changed after validation; aborting before mutation')
    vendor_state = _pdf_tree(REPO / 'vendor')
    backup = REPO / ('.amadis-pdf-backup-' + secrets.token_hex(12))
    backup.mkdir(mode=0o700)
    moved = []
    try:
        shutil.copytree(REPO / 'vendor', backup / 'working-vendor')
        if vendor_state != _pdf_tree(backup / 'working-vendor') or vendor_state != _pdf_tree(REPO / 'vendor'):
            fail('vendor changed during backup')
        if before != _pdf_fingerprint():
            fail('repository changed during backup')
        for name in ('composer.json', 'composer.lock', 'vendor'):
            os.replace(REPO / name, backup / name)
            moved.append(name)
        os.replace(backup / 'working-vendor', REPO / 'vendor')
        for name, data in candidate.items():
            _pdf_write_bytes(REPO, name, data, os.O_CREAT | os.O_EXCL)
        result = install_and_verify()
    except BaseException:
        # Preserve failed output for diagnosis; never destroy the only copy of original vendor.
        for name in reversed(moved):
            target = REPO / name
            if os.path.lexists(target):
                os.replace(target, backup / ('failed-' + name))
            os.replace(backup / name, target)
        if moved and (before != _pdf_fingerprint() or vendor_state != _pdf_tree(REPO / 'vendor')):
            fail(f'rollback verification failed; preserve recovery directory {backup}')
        raise
    else:
        # Retain the backup for operator recovery; deleting it is not required for a successful install.
        write_new(root, 'evidence/pdf-backup-' + secrets.token_hex(6) + '.json',
                  json.dumps({'path': str(backup), 'status': 'committed'}) + '\n')
        return result


def pdf_dependency(root, env, mode):
    if mode not in {'dry-run', 'install'}:
        fail('pdf-dependency mode must be dry-run or install')
    fd = open_file(REPO, '.amadis-pdf-dependency.lock', os.O_RDWR | os.O_CREAT)
    with os.fdopen(fd, 'r+') as project_lock:
        info = os.fstat(fd)
        if (not stat.S_ISREG(info.st_mode) or info.st_nlink != 1 or info.st_uid != os.getuid()
                or stat.S_IMODE(info.st_mode) != 0o600):
            fail('unsafe project dependency lock')
        try:
            fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            fail('project dependency operation is busy')
        pass_fds = (lock.fileno(), project_lock.fileno())
        old_json, old_lock, names = _pdf_validate_project()
        if names & PDF_CLOSURE:
            fail('approved packages are already in composer.lock')
        _pdf_vendor_matches_lock(old_lock, _pdf_json(REPO / 'vendor/composer/installed.json'))
        before = _pdf_fingerprint()
        _pdf_candidate_hook_guard()
        releases = {name: _pdf_metadata(name) for name in PDF_PINS}

        private = 'composer-' + secrets.token_hex(12)
        os.close(open_dir(root, private + '/cache'))
        os.close(open_dir(root, private + '/candidate'))
        cache = root / private / 'cache'
        child_env = dict(env, COMPOSER_HOME=str(root / private), COMPOSER_CACHE_DIR=str(cache))
        original = {name: _pdf_read_bytes(REPO, name) for name in ('composer.json', 'composer.lock')}
        for name, data in original.items():
            _pdf_write_bytes(root, private + '/candidate/' + name, data, os.O_CREAT | os.O_EXCL)
        # Same inputs outside the repo; Composer authors the lock. Scripts, plugins and audit stay enabled.
        result, _ = _pdf_run(root, 'candidate', ['composer', 'require', f'{PDF_PACKAGE}:{PDF_VERSION}', '--no-install',
                                                 '--no-interaction', '--no-ansi', '--prefer-dist'],
                             root / private / 'candidate', child_env, pass_fds)
        if before != _pdf_fingerprint():
            fail('repository manifest/lock/installed.json changed during candidate generation')
        if result.returncode:
            return result.returncode
        candidate = {name: _pdf_read_bytes(root, private + '/candidate/' + name) for name in original}
        try:
            additions = _pdf_validate_candidate(old_json, old_lock, json.loads(candidate['composer.json']),
                                                json.loads(candidate['composer.lock']), releases)
        except ValueError as error:
            fail(f'candidate is not valid JSON: {error}')
        plan = {'before': before, 'candidate': {n: _pdf_sha(d) for n, d in candidate.items()}, 'additions': additions,
                'releases': {n: {k: r.get(k) for k in ('version', 'source', 'dist', 'license', 'require')} for n, r in releases.items()},
                'mode': mode}
        write_new(root, 'evidence/pdf-plan-' + secrets.token_hex(6) + '.json', json.dumps(plan, indent=2) + '\n')
        if mode == 'dry-run':
            print('PDF candidate validated; no repository file changed')
            return 0

        # Install inside a transaction: originals stay under a same-filesystem backup until the
        # staged install finishes and verification passes. Any failure restores all three paths.
        new_lock = json.loads(candidate['composer.lock'])
        entries = {p['name']: p for p in new_lock['packages']}

        def install_and_verify():
            installed, installed_stem = _pdf_run(root, 'install',
                                                 ['composer', 'install', '--no-interaction', '--no-ansi', '--prefer-dist'],
                                                 REPO, child_env, pass_fds)
            after = _pdf_fingerprint()
            post = _pdf_json(REPO / 'vendor/composer/installed.json')
            expected_state = _pdf_state(new_lock['packages'] + new_lock['packages-dev'])
            archives = _pdf_archives(cache, entries)
            verified = (installed.returncode == 0 and after['composer.json'] == plan['candidate']['composer.json']
                        and after['composer.lock'] == plan['candidate']['composer.lock']
                        and _pdf_state(post.get('packages', [])) == expected_state
                        and len(archives) == 5 and all(v['local_sha256'] for v in archives.values()))
            write_new(root, 'evidence/pdf-result-' + secrets.token_hex(6) + '.json', json.dumps({
                'returncode': installed.returncode, 'install_log': installed_stem, 'before': before,
                'after': after, 'verified': verified, 'archives': archives}, indent=2) + '\n')
            if not verified:
                raise SystemExit('PDF install verification failed')
            return 0

        try:
            return _pdf_transaction(root, before, candidate, install_and_verify)
        except SystemExit as error:
            print(f'PDF install rolled back: {error}', file=sys.stderr)
            return 1


def manifest(root):
    paths = subprocess.run(['git', 'ls-files', '-co', '--exclude-standard', '-z'], cwd=REPO, capture_output=True, check=True).stdout
    records = []
    for raw in paths.split(b'\0'):
        if not raw:
            continue
        relative = raw.decode('utf-8', 'surrogateescape')
        parts = Path(relative).parts
        # Skip secrets and user data; inventory source, tests, and product/config without reading .env.
        if any(part.startswith('.env') or part.lower() in {'secrets', 'credentials', 'uploads', 'storage', 'node_modules', 'vendor'} for part in parts):
            continue
        if not (parts[0] in {'app', 'bootstrap', 'config', 'database', 'routes', 'resources', 'public', 'prototype', 'tests', 'docs'}
                or relative in {'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'phpunit.xml'}):
            continue
        if parts[0] == 'database' and (len(parts) < 2 or parts[1] not in {'migrations', 'factories', 'seeders'}):
            continue
        if Path(relative).is_absolute() or '..' in parts:
            continue
        p = REPO / relative
        # Never follow directory links or hash a linked file outside the repository.
        if any(parent.is_symlink() for parent in p.parents if parent != REPO and REPO in parent.parents):
            continue
        if not p.resolve().is_relative_to(REPO):
            continue
        if p.is_symlink():
            records.append({'path': relative, 'symlink': sanitized(os.readlink(p))})
        elif p.is_file() and p.stat().st_nlink == 1:
            records.append({'path': relative, 'sha256': hashlib.sha256(p.read_bytes()).hexdigest()})
    return records

def main():
    args = sys.argv[1:]
    if not args or args[0] not in {'init', 'guard-test', 'artisan', 'snapshot', 'provision', 'test', 'migrate', 'seed', 'seed-r44', 'serve', 'pdf-dependency', 'composer-audit'}:
        fail('usage: launch.sh {init|guard-test|artisan COMMAND|snapshot|provision|test [ARGS]|migrate|seed|seed-r44|serve [PORT]|pdf-dependency dry-run|install|composer-audit --locked --format=json}')
    if args[0] == 'composer-audit' and args != ['composer-audit', '--locked', '--format=json']:
        fail('usage: launch.sh composer-audit --locked --format=json')
    if args[0] == 'pdf-dependency' and (len(args) != 2 or args[1] not in {'dry-run', 'install'}):
        fail('usage: launch.sh pdf-dependency dry-run|install')
    if args[0] == 'seed-r44' and len(args) != 1:
        fail('seed-r44 accepts no arguments; supply credentials through environment')
    if args[0] == 'seed':
        if len(args) > 2:
            fail('seed accepts only AmadisDemoSeeder')
        if len(args) == 2 and args[1].removeprefix('--class=') != SEEDER:
            fail('only AmadisDemoSeeder is allowed')
    if args[0] == 'artisan':
        if len(args) < 2:
            fail('artisan command required')
        if args[1] not in ARTISAN_OPTIONS:
            fail('artisan command not allowed')
        if any(value not in ARTISAN_OPTIONS[args[1]] for value in args[2:]):
            fail('artisan option not allowed')
    name = os.environ.get('AMADIS_ROOT')
    if name:
        root = Path(name)
        if root.parent != BASE or not re.fullmatch(r'amadis-[a-f0-9]{24}', root.name) or root.is_symlink():
            fail('AMADIS_ROOT must be a real direct child of approved root')
        if not (root / 'identity.json').is_file():
            fail('existing root lacks identity.json')
    else:
        root = BASE / ('amadis-' + secrets.token_hex(12))
        root.mkdir(mode=0o700)
    root = root.resolve(strict=True)
    # One command per root: concurrent runs rewrite bootstrap/config.php under each other. Lock lives until exit.
    prepare(root)
    global lock
    lock = os.fdopen(open_file(root, '.lock', os.O_WRONLY | os.O_CREAT | os.O_APPEND), 'a')
    try:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except BlockingIOError:
        fail('root is busy with another command; use a separate AMADIS_ROOT (run init) for parallel work')
    evidence = root / 'evidence'
    database = root / 'database.sqlite'
    if not os.path.lexists(root / 'identity.json'):
        identity = {'root': str(root), 'database': str(database), 'repo': str(REPO), 'git_head': subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=REPO, text=True).strip(), 'files': manifest(root)}
        write_new(root, 'identity.json', json.dumps(identity, indent=2) + '\n')
        status = subprocess.run(['git', 'status', '--short'], cwd=REPO, capture_output=True, text=True, check=True)
        write_new(root, 'evidence/git-status.txt', sanitized(status.stdout))
        versions = []
        for command in [['php', '-v'], ['python3', '-V'], ['node', '-v'], ['npm', '-v'], ['git', '--version'], ['composer', '--version', '--no-ansi']]:
            p = subprocess.run(command, cwd=REPO, capture_output=True, text=True)
            versions.append(f"{shlex.join(command)}: {(p.stdout or p.stderr).splitlines()[0] if p.returncode == 0 else 'unavailable'}")
        write_new(root, 'evidence/versions.txt', sanitized('\n'.join(versions) + '\n'))
    identity = json.loads(read_file(root, 'identity.json'))
    if identity.get('root') != str(root) or identity.get('database') != str(database):
        fail('root/database identity mismatch')
    if args[0] == 'composer-audit' and (identity.get('repo') != str(REPO) or Path.cwd() != REPO):
        fail('composer audit requires repository identity and cwd')
    # Stable root-local demo key preserves encrypted cookies across launcher restarts.
    if not os.path.lexists(root / 'demo-key'):
        write_new(root, 'demo-key', 'base64:' + __import__('base64').b64encode(secrets.token_bytes(32)).decode())
    port = args[1] if args[0] == 'serve' and len(args) > 1 else '8016'
    if args[0] == 'serve' and (not port.isdecimal() or int(port) not in range(1024, 65536) or int(port) in {8000, 8001}):
        fail('invalid or reserved port')
    env = {'PATH': os.environ.get('PATH', '/usr/bin:/bin'), 'HOME': str(root), 'TMPDIR': str(root),
           'AMADIS_ROOT': str(root), 'AMADIS_DATABASE': str(database), 'AMADIS_EVIDENCE_PATH': str(evidence),
           'APP_ENV': 'testing' if args[0] == 'test' else 'local', 'APP_DEBUG': 'false', 'APP_URL': f'http://127.0.0.1:{port}',
           'APP_KEY': read_file(root, 'demo-key'),
           'APP_PREVIOUS_KEYS': '', 'DB_CONNECTION': 'sqlite', 'DB_DATABASE': str(database), 'DB_URL': '',
           'CACHE_STORE': 'file', 'SESSION_DRIVER': 'file', 'FILESYSTEM_DISK': 'local',
           'QUEUE_CONNECTION': 'sync', 'QUEUE_FAILED_DRIVER': 'null', 'MAIL_MAILER': 'log',
           'LOG_CHANNEL': 'single', 'LARAVEL_STORAGE_PATH': str(root / 'storage'),
           'VIEW_COMPILED_PATH': str(root / 'storage/framework/views'),
           'APP_CONFIG_CACHE': str(root / 'bootstrap/config.php'),
           'APP_SERVICES_CACHE': str(root / 'bootstrap/services.php'),
           'APP_PACKAGES_CACHE': str(root / 'bootstrap/packages.php'),
           'APP_ROUTES_CACHE': str(root / 'bootstrap/routes.php'),
           'APP_EVENTS_CACHE': str(root / 'bootstrap/events.php')}
    if args[0] == 'init':
        print(root)
        return 0
    artisan = ['php', 'scripts/amadis/artisan.php']
    if args[0] == 'guard-test':
        bad = dict(env, AMADIS_ROOT=str(REPO), DB_DATABASE=str(REPO / 'database/database.sqlite'))
        p = subprocess.run(['php', '-r', 'require "scripts/amadis/guard.php"; amadisGuard();'], cwd=REPO, env=bad, capture_output=True)
        print('sentinel rejected (PASS)' if p.returncode else 'sentinel accepted (FAIL)')
        return 0 if p.returncode else 1
    if args[0] == 'test':
        env['AMADIS_TEST_MEMORY'] = '1'
        # Isolate throttle counters per test application, not in persistent demo cache.
        # phpunit.xml leaves APP_URL unset, so the suite expects Laravel's http://localhost default.
        env.update({'CACHE_STORE': 'array', 'SESSION_DRIVER': 'array', 'MAIL_MAILER': 'array',
                    'DB_DATABASE': ':memory:', 'APP_URL': 'http://localhost'})
    # Force a fresh cache each invocation: Laravel must not read repo .env or stale bootstrap/cache.
    bootstrap = open_dir(root, 'bootstrap')
    try:
        os.unlink('config.php', dir_fd=bootstrap) # unlink removes a link itself, never its target
    except FileNotFoundError:
        pass
    finally:
        os.close(bootstrap)
    status = capture(root, 'config', artisan + ['amadis:cache-config'], env)
    if status:
        return status
    command = args[0]
    if command == 'composer-audit':
        _pdf_validate_project()
        _pdf_vendor_matches_lock(_pdf_json(REPO / 'composer.lock'), _pdf_json(REPO / 'vendor/composer/installed.json'))
        before = _pdf_fingerprint()
        # Read-only audit: fixed argv, no install/update; hooks stay enabled and untouched in composer.json.
        status = capture(root, command, ['composer', 'audit', '--locked', '--format=json', '--no-interaction', '--no-ansi'], env)
        if _pdf_fingerprint() != before:
            fail('composer audit changed composer.json, composer.lock or installed.json')
        return status
    if command == 'pdf-dependency':
        return pdf_dependency(root, env, args[1])
    if command == 'migrate':
        return capture(root, command, artisan + ['migrate', '--force', '--database=sqlite'], env)
    if command == 'snapshot':
        if len(args) != 1:
            fail('snapshot accepts no arguments')
        return capture(root, command, artisan + ['amadis:lifecycle-snapshot'], env)
    if command == 'provision':
        if len(args) != 1:
            fail('provision accepts no arguments')
        return provision(root, env)
    if command == 'seed':
        return capture(root, command, artisan + ['db:seed', '--class=' + SEEDER, '--force', '--database=sqlite'], env)
    if command == 'seed-r44':
        # Credentials travel only through the child environment, never argv or evidence metadata.
        for key in ('R44_ADMIN_PASSWORD', 'R44_TOTP_SECRET'):
            if not os.environ.get(key):
                fail(f'{key} must be set in the environment')
            env[key] = os.environ[key]
        # Execute the verified bytes via stdin; the mutable source path is never reopened after hashing.
        return capture(root, command, ['php'], env, input_data=verify_r44_fixture())
    if command == 'test':
        return capture(root, command, artisan + ['test'] + args[1:], env)
    if command == 'artisan':
        return capture(root, command, artisan + args[1:], env)
    if command == 'serve':
        workers = args[2] if len(args) > 2 else '1'
        if workers not in {str(n) for n in range(1, 9)}:
            fail('workers must be 1..8')
        if workers != '1':
            env['PHP_CLI_SERVER_WORKERS'] = workers
        return capture(root, command, ['php', '-S', f'127.0.0.1:{port}', '-t', 'public', 'scripts/amadis/server.php'], env)
    fail('unreachable command: ' + command)

if __name__ == '__main__':
    raise SystemExit(main())
