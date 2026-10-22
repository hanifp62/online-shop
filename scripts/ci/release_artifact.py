#!/usr/bin/env python3
"""CICD-05/08: artefak Laravel provider-neutral, tanpa deploy atau instalasi.

Dari root checkout BERSIH, setelah composer install --no-dev dan npm run build:
    python3 scripts/ci/release_artifact.py build --output DIRECTORY
    python3 scripts/ci/release_artifact.py verify --archive FILE --manifest FILE

DIRECTORY harus baru, di luar checkout, dengan parent yang sudah tersedia.
Output: release.tar.gz (USTAR + gzip) dan release-manifest.json (schema 1).
Source tracked dibandingkan dengan blob HEAD, bukan sekadar diberi label HEAD.
Tiga alias storefront tetap dimaterialisasi dari blob prototype commit yang sama;
git_path wajib merekam sumbernya. Symlink checkout tidak pernah diikuti.
Vendor/build adalah input hasil build, BUKAN byte source commit. Metadata Composer
2 harus production dan cocok dengan composer.lock; build Vite harus lengkap.
CI bertanggung jawab menjalankan install/build/gate pada checkout yang sama.

Verifier offline hanya membaca stream, tidak mengekstrak atau menjalankan isinya.
Format sengaja sempit: file reguler USTAR, tanpa direktori/link/PAX/sparse entries.
Urutan, timestamp, owner, permission dan gzip header dinormalisasi. Reproducibility
berlaku untuk input byte identik; toolchain Composer/npm sendiri di luar kontrak.
Hash/manifest bukan signature atau attestation: simpan keduanya melalui kanal CI
terpercaya. Allowlist bukan pemindai semua secret yang mungkin di-hardcode dalam
source. Gate secret CI tetap wajib. Runtime membuat storage/bootstrap/cache dan
menginjeksi konfigurasi lingkungan sendiri; artefak tidak membawa state tersebut.
"""
import argparse
from contextlib import contextmanager
import gzip
import hashlib
import io
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import sys
import tarfile
from typing import NoReturn
import zlib

ARCHIVE_NAME = 'release.tar.gz'
MANIFEST_NAME = 'release-manifest.json'
MAX_FILES = 50000
MAX_FILE_BYTES = 128 * 1024 * 1024
MAX_TOTAL_BYTES = 2 * 1024 * 1024 * 1024
MAX_ARCHIVE_BYTES = 1024 * 1024 * 1024
MAX_JSON_BYTES = 32 * 1024 * 1024
CHUNK = 1024 * 1024
ROOT_FILES = frozenset({
    'artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
    'bootstrap/app.php', 'bootstrap/providers.php',
})
REQUIRED = ROOT_FILES | {
    'public/index.php', 'vendor/autoload.php', 'vendor/composer/installed.json',
    'public/build/manifest.json',
}
METADATA = {'composer.lock', 'vendor/composer/installed.json', 'public/build/manifest.json'}
SOURCE_DIRS = {'app', 'config', 'routes', 'resources', 'lang', 'public'}
SOURCE_ALIASES = {
    'public/app.css': ('../prototype/app.css', 'prototype/app.css'),
    'public/app.js': ('../prototype/app.js', 'prototype/app.js'),
    'public/prototype': ('../prototype', 'prototype'),
}
OMIT_PARTS = {
    '.git', '.github', '.gitlab', '.svn', '.hg', 'node_modules', '__pycache__',
    'tests', 'test', 'docs', 'doc', 'ci', 'storage', 'uploads', 'backups',
}
SECRET_PARTS = {
    'auth.json', 'credentials.json', '.ssh', '.aws', '.gnupg', 'keys', 'secrets',
    '.netrc', '.npmrc', '.pypirc', '.git-credentials',
}
SECRET_SUFFIXES = ('.key', '.pem', '.p12', '.pfx', '.jks', '.keystore')
DATA_SUFFIXES = ('.sqlite', '.sqlite3', '.db', '.sql', '.dump', '.log', '.pyc')
SHA256 = re.compile(r'[0-9a-f]{64}\Z')
COMMIT = re.compile(r'(?:[0-9a-f]{40}|[0-9a-f]{64})\Z')
PACKAGE = re.compile(r'[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*\Z')


class ArtifactError(Exception):
    """Pesan tetap, tanpa isi file, nama sensitif, atau stderr subprocess."""


def require(condition, message) -> None:
    if not condition:
        raise ArtifactError(message)


def sensitive(parts):
    return any(p.startswith('.env') or p in SECRET_PARTS
               or any(p.startswith(secret + '.') for secret in SECRET_PARTS)
               or p.startswith(('id_rsa', 'id_ed25519', 'id_ecdsa', 'id_dsa'))
               or p.endswith(SECRET_SUFFIXES)
               or any(suffix + '.' in p for suffix in SECRET_SUFFIXES) for p in parts)


def excluded(name):
    parts = name.lower().split('/')
    return (sensitive(parts) or any(p in OMIT_PARTS or p == '.gitignore' for p in parts)
            or any(p.endswith(DATA_SUFFIXES) or any(s + '.' in p for s in DATA_SUFFIXES)
                   for p in parts)
            or name.lower().startswith('bootstrap/cache/')
            or name.lower() in {'bootstrap/cache', 'public/hot'})


def safe_path(name):
    require(isinstance(name, str) and 0 < len(name.encode('utf-8')) <= 255,
            'Unsafe path.')
    require(name.isprintable() and '\\' not in name and ':' not in name,
            'Unsafe path.')
    parts = name.split('/')
    require(all(p not in {'', '.', '..'} and not p.endswith((' ', '.')) for p in parts),
            'Unsafe path.')
    require(not any(re.fullmatch(r'(con|prn|aux|nul|com[1-9]|lpt[1-9])',
                                 p.split('.')[0], re.IGNORECASE) for p in parts), 'Unsafe path.')
    return parts


def origin(name):
    # Deny sensitive names BEFORE opening files or following filesystem metadata.
    if excluded(name):
        return None
    if name.startswith('vendor/'):
        return 'vendor'
    if name.startswith('public/build/'):
        return 'build'
    if (name in ROOT_FILES or name.startswith('database/migrations/')
            or ('/' in name and name.split('/')[0] in SOURCE_DIRS)):
        return 'git'
    return None


@contextmanager
def input_parent(root, name):
    parts = safe_path(name)
    require(not excluded(name), 'Excluded input.')
    # openat + O_NOFOLLOW also protects parent directories, not only the leaf.
    directory = os.open(root, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
    try:
        for part in parts[:-1]:
            child = os.open(part, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW, dir_fd=directory)
            os.close(directory)
            directory = child
        yield directory, parts[-1]
    finally:
        os.close(directory)


@contextmanager
def open_input(root, name):
    with input_parent(root, name) as (directory, leaf):
        fd = os.open(leaf, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK, dir_fd=directory)
        with os.fdopen(fd, 'rb') as stream:
            info = os.fstat(stream.fileno())
            require(stat.S_ISREG(info.st_mode) and info.st_nlink == 1,
                    'Input must be a regular file, without links.')
            yield stream


def read_input(root, name):
    with open_input(root, name) as stream:
        limit = MAX_JSON_BYTES if name in METADATA else MAX_FILE_BYTES
        require(os.fstat(stream.fileno()).st_size <= limit, 'Input exceeds size limit.')
        data = stream.read(limit + 1)
        require(len(data) <= limit, 'Input exceeds size limit.')
        return data


def json_data(data):
    require(len(data) <= MAX_JSON_BYTES, 'JSON exceeds size limit.')

    def pairs(items):
        result = {}
        for key, value in items:
            require(key not in result, 'Duplicate JSON key.')
            result[key] = value
        return result

    def invalid_constant(_):
        raise ArtifactError('Invalid JSON constant.')

    return json.loads(data, object_pairs_hook=pairs, parse_constant=invalid_constant)


def git(root, *args):
    env = {k: v for k, v in os.environ.items() if not k.startswith('GIT_')}
    env['LC_ALL'] = 'C'
    result = subprocess.run([
        'git', '--no-pager', '--no-optional-locks', '--no-replace-objects',
        '-c', 'core.fsmonitor=false', '-C', str(root), *args,
    ], stdout=subprocess.PIPE, stderr=subprocess.PIPE, env=env, timeout=30, check=False)
    require(result.returncode == 0, 'Git metadata command failed.')
    return result.stdout


def clean_commit(root, expected=None):
    top = git(root, 'rev-parse', '--show-toplevel').decode().strip()
    require(Path(top).resolve() == root, 'Build must run from the checkout root.')
    commit = git(root, 'rev-parse', '--verify', 'HEAD^{commit}').decode().strip()
    require(COMMIT.fullmatch(commit), 'Invalid source commit.')
    require(not git(root, 'status', '--porcelain=v1', '-z', '--untracked-files=all',
                    '--ignore-submodules=none'), 'Build requires a clean checkout (including untracked files).')
    require(expected is None or expected == commit, 'Source commit changed during build.')
    return commit


def mapped_source_path(name):
    require(name != 'public/prototype', 'Directory alias cannot be a file entry.')
    if name in SOURCE_ALIASES:
        return SOURCE_ALIASES[name][1]
    if name.startswith('public/prototype/'):
        return name[len('public/'):]
    return name


def check_alias(root, name, entry, commit):
    payload, _ = SOURCE_ALIASES[name]
    mode, object_type, oid = entry
    # Git's content address proves the literal payload without opening its target.
    require(mode == '120000' and object_type == 'blob'
            and oid == blob_hash(payload.encode(), commit), 'Unapproved source alias.')
    with input_parent(root, name) as (directory, leaf):
        info = os.stat(leaf, dir_fd=directory, follow_symlinks=False)
        require(stat.S_ISLNK(info.st_mode) and info.st_nlink == 1,
                'Source alias must be a single symlink.')
        require(os.readlink(leaf, dir_fd=directory) == payload, 'Source alias payload changed.')


def source_files(root, commit):
    tree = {}
    for item in git(root, 'ls-tree', '-r', '-z', '--full-tree', commit).split(b'\0'):
        if not item:
            continue
        header, raw_name = item.split(b'\t', 1)
        name = raw_name.decode('utf-8')
        if excluded(name):
            continue
        kind = origin(name)
        if kind is None and name != 'prototype' and not name.startswith('prototype/'):
            continue
        safe_path(name)
        mode, object_type, oid = header.decode('ascii').split()
        require(kind not in {'vendor', 'build'}, 'Generated vendor/build files must not be tracked.')
        require(COMMIT.fullmatch(oid) and len(oid) == len(commit), 'Invalid Git blob metadata.')
        require(name not in tree, 'Duplicate source path.')
        tree[name] = (mode, object_type, oid)
    result = {}

    def add(name, path):
        require(path in tree, 'Missing tracked source alias target.')
        require(origin(name) == 'git' and not excluded(path)
                and mapped_source_path(name) == path, 'Unapproved source mapping.')
        mode, object_type, oid = tree[path]
        require(object_type == 'blob' and mode in {'100644', '100755'},
                'Source links/submodules are not allowed.')
        require(name not in result, 'Duplicate source path.')
        result[name] = (0o755 if mode == '100755' else 0o644, oid, path)

    for name, entry in tree.items():
        if name in SOURCE_ALIASES:
            check_alias(root, name, entry, commit)
            require('prototype' not in tree, 'Source alias target must be a tracked directory.')
            target = SOURCE_ALIASES[name][1]
            if name == 'public/prototype':
                targets = [p for p in tree if p.startswith('prototype/')]
                require(targets, 'Missing tracked source alias target.')
                for path in targets:
                    add('public/' + path, path)
            else:
                add(name, target)
        elif origin(name) == 'git':
            add(name, name)
    return result


def generated_files(root, prefix):
    # scandir(fd) plus openat prevents traversal through directory symlinks.
    def walk(directory, relative):
        with os.scandir(directory) as iterator:
            entries = sorted(iterator, key=lambda e: e.name)
        for entry in entries:
            name = relative + '/' + entry.name
            if excluded(name):
                continue
            safe_path(name)
            info = entry.stat(follow_symlinks=False)
            if stat.S_ISDIR(info.st_mode):
                child = os.open(entry.name, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW,
                                dir_fd=directory)
                try:
                    yield from walk(child, name)
                finally:
                    os.close(child)
            else:
                require(stat.S_ISREG(info.st_mode) and info.st_nlink == 1,
                        'Generated input must be a regular file, without links.')
                yield name

    fd = os.open(root, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
    try:
        for part in prefix.split('/'):
            child = os.open(part, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW, dir_fd=fd)
            os.close(fd)
            fd = child
        result = set()
        for name in walk(fd, prefix):
            result.add(name)
            require(len(result) <= MAX_FILES, 'Too many input files.')
        return result
    finally:
        os.close(fd)


def check_paths(names):
    require(0 < len(names) <= MAX_FILES, 'Invalid file count.')
    folded = set()
    for name in names:
        safe_path(name)
        require(origin(name) is not None, 'Unexpected or excluded entry.')
        require(name.casefold() not in folded, 'Duplicate/colliding paths.')
        folded.add(name.casefold())
    for name in folded:
        parts = name.split('/')
        require(not any('/'.join(parts[:i]) in folded for i in range(1, len(parts))),
                'File/directory path collision.')
    require(REQUIRED <= set(names), 'Missing required release input.')


def package_map(packages):
    require(isinstance(packages, list), 'Invalid Composer package metadata.')
    result = {}
    for package in packages:
        require(isinstance(package, dict), 'Invalid Composer package metadata.')
        name = package.get('name')
        require(isinstance(name, str) and PACKAGE.fullmatch(name), 'Invalid Composer package name.')
        require(name not in result and isinstance(package.get('version'), str),
                'Duplicate/invalid Composer package.')
        result[name] = package
    return result


def check_composer(lock, installed, names):
    require(isinstance(lock, dict) and isinstance(installed, dict), 'Invalid Composer metadata.')
    require(installed.get('dev') is False, 'Composer production install --no-dev is required.')
    expected = package_map(lock.get('packages'))
    development = package_map(lock.get('packages-dev'))
    actual = package_map(installed.get('packages'))
    dev_names = installed.get('dev-package-names')
    if not isinstance(dev_names, list) or not all(isinstance(n, str) for n in dev_names):
        raise ArtifactError('Missing Composer development metadata.')
    require(actual.keys() == expected.keys() and not actual.keys() & development.keys()
            and not actual.keys() & set(dev_names), 'Installed packages do not match production lock.')
    bins = set()
    directories = set()
    for name, package in actual.items():
        locked = expected[name]
        require(package['version'] == locked['version'], 'Installed version differs from lock.')
        for kind in ('source', 'dist'):
            reference = locked.get(kind) or {}
            observed = package.get(kind) or {}
            require(isinstance(reference, dict) and isinstance(observed, dict)
                    and reference.get('reference') == observed.get('reference'),
                    'Installed reference differs from lock.')
        if locked.get('type') != 'metapackage':
            require(package.get('install-path') == '../' + name, 'Nonstandard Composer install path.')
            directories.add(name)
            require(any(p.startswith('vendor/' + name + '/') for p in names),
                    'Missing installed package files.')
        executables = locked.get('bin', [])
        require(isinstance(executables, list), 'Invalid Composer bin metadata.')
        for executable in executables:
            safe_path(executable)
            bins.add(executable.split('/')[-1])
            bins.add(executable.split('/')[-1] + '.bat')
    composer_files = {
        'ClassLoader.php', 'InstalledVersions.php', 'LICENSE', 'installed.json', 'installed.php',
        'autoload_classmap.php', 'autoload_files.php', 'autoload_namespaces.php',
        'autoload_psr4.php', 'autoload_real.php', 'autoload_static.php',
        'include_paths.php', 'platform_check.php',
    }
    for path in names:
        if not path.startswith('vendor/'):
            continue
        parts = path.split('/')
        allowed = (path == 'vendor/autoload.php'
                   or (len(parts) == 3 and parts[1] == 'composer' and parts[2] in composer_files)
                   or (len(parts) == 3 and parts[1] == 'bin' and parts[2] in bins)
                   or (len(parts) >= 4 and '/'.join(parts[1:3]) in directories))
        require(allowed, 'Unexpected vendor entry (stale/development package or binary).')


def check_vite(manifest, names):
    require(isinstance(manifest, dict) and manifest, 'Missing/invalid Vite build manifest.')
    for entry in manifest.values():
        require(isinstance(entry, dict) and isinstance(entry.get('file'), str), 'Invalid Vite entry.')
        assets = [entry['file']]
        for field in ('css', 'assets'):
            values = entry.get(field, [])
            require(isinstance(values, list), 'Invalid Vite asset references.')
            assets.extend(values)
        for asset in assets:
            safe_path(asset)
            require('public/build/' + asset in names, 'Missing/unsafe Vite asset.')
        for field in ('imports', 'dynamicImports'):
            values = entry.get(field, [])
            require(isinstance(values, list) and all(isinstance(v, str) and v in manifest for v in values),
                    'Missing Vite chunk reference.')


def check_metadata(data, names):
    check_composer(json_data(data['composer.lock']), json_data(data['vendor/composer/installed.json']), names)
    check_vite(json_data(data['public/build/manifest.json']), names)


def blob_hash(data, commit):
    algorithm = hashlib.sha1 if len(commit) == 40 else hashlib.sha256
    return algorithm(b'blob ' + str(len(data)).encode() + b'\0' + data).hexdigest()


def file_digest(stream, limit):
    digest = hashlib.sha256()
    size = 0
    while chunk := stream.read(CHUNK):
        size += len(chunk)
        require(size <= limit, 'Archive exceeds size limit.')
        digest.update(chunk)
    return size, digest.hexdigest()


def build(root, output):
    root = Path(root).resolve()
    commit = clean_commit(root)
    output = Path(output).absolute()
    require(not output.is_symlink(), 'Output must not be a symlink.')
    output = output.resolve()
    require(output != root and root not in output.parents and output not in root.parents,
            'Output must be outside the checkout and its inputs.')
    require(not output.exists() and output.parent.is_dir(), 'Output must be new with an existing parent.')
    source = source_files(root, commit)
    generated = generated_files(root, 'vendor') | generated_files(root, 'public/build')
    names = sorted(set(source) | generated)
    check_paths(names)
    # Only these safe metadata paths are read before rejecting stale/dev vendor.
    metadata = {name: read_input(root, name) for name in METADATA}
    check_metadata(metadata, names)
    files = []
    total = 0
    for name in names:
        path = source[name][2] if name in source else name
        data = read_input(root, path)
        entry = {'path': name, 'size': len(data), 'sha256': hashlib.sha256(data).hexdigest(),
                 'origin': origin(name), 'mode': 0o755 if name.startswith('vendor/bin/') else 0o644}
        if name in source:
            entry['mode'], entry['git_blob'], _ = source[name]
            require(blob_hash(data, commit) == entry['git_blob'], 'Source bytes differ from commit.')
            if path != name:
                entry['git_path'] = path
        total += len(data)
        require(total <= MAX_TOTAL_BYTES, 'Release exceeds total size limit.')
        require(name not in metadata or metadata[name] == data, 'Build metadata changed.')
        files.append(entry)
    clean_commit(root, commit)
    require(source == source_files(root, commit), 'Source mapping changed during build.')
    output.mkdir(mode=0o700)
    archive = output / ARCHIVE_NAME
    manifest_file = output / MANIFEST_NAME
    try:
        with archive.open('xb') as raw, gzip.GzipFile(fileobj=raw, mode='wb', filename='', mtime=0) as gz:
            with tarfile.open(fileobj=gz, mode='w|', format=tarfile.USTAR_FORMAT) as bundle:
                for entry in files:
                    data = read_input(root, entry.get('git_path', entry['path']))
                    require(len(data) == entry['size'] and hashlib.sha256(data).hexdigest() == entry['sha256'],
                            'Input changed during build.')
                    member = tarfile.TarInfo(entry['path'])
                    member.size, member.mode = entry['size'], entry['mode']
                    bundle.addfile(member, io.BytesIO(data))
        clean_commit(root, commit)
        require(source == source_files(root, commit), 'Source mapping changed during build.')
        require(generated == generated_files(root, 'vendor') | generated_files(root, 'public/build'),
                'Generated input set changed during build.')
        with open_input(output, ARCHIVE_NAME) as stream:
            size, digest = file_digest(stream, MAX_ARCHIVE_BYTES)
        manifest = {
            'schema': 1, 'release_id': commit + '-' + digest,
            'source': {'commit': commit, 'clean': True},
            'archive': {'name': ARCHIVE_NAME, 'format': 'tar.gz', 'size': size, 'sha256': digest},
            'locks': {name: next(e['sha256'] for e in files if e['path'] == name)
                      for name in ('composer.lock', 'package-lock.json')},
            'files': files,
        }
        encoded = (json.dumps(manifest, sort_keys=True, indent=2) + '\n').encode()
        require(len(encoded) <= MAX_JSON_BYTES, 'Manifest exceeds size limit.')
        with manifest_file.open('xb') as stream:
            stream.write(encoded)
        verify(archive, manifest_file)
        return manifest
    except BaseException:
        # Only files in the freshly created output directory belong to this build.
        archive.unlink(missing_ok=True)
        manifest_file.unlink(missing_ok=True)
        output.rmdir()
        raise


def exact_keys(value, keys):
    require(isinstance(value, dict) and value.keys() == set(keys), 'Invalid manifest schema.')


def valid_size(value, limit):
    return type(value) is int and 0 <= value <= limit


def manifest_entries(manifest):
    exact_keys(manifest, {'schema', 'release_id', 'source', 'archive', 'locks', 'files'})
    require(type(manifest['schema']) is int and manifest['schema'] == 1, 'Unsupported manifest schema.')
    source, archive = manifest['source'], manifest['archive']
    exact_keys(source, {'commit', 'clean'})
    require(isinstance(source['commit'], str) and COMMIT.fullmatch(source['commit'])
            and source['clean'] is True, 'Invalid source provenance.')
    exact_keys(archive, {'name', 'format', 'size', 'sha256'})
    require(archive['name'] == ARCHIVE_NAME and archive['format'] == 'tar.gz'
            and valid_size(archive['size'], MAX_ARCHIVE_BYTES)
            and isinstance(archive['sha256'], str) and SHA256.fullmatch(archive['sha256']),
            'Invalid archive metadata.')
    require(manifest['release_id'] == source['commit'] + '-' + archive['sha256'], 'Invalid release ID.')
    records = manifest['files']
    require(isinstance(records, list) and 0 < len(records) <= MAX_FILES, 'Invalid file count.')
    result = {}
    total = 0
    for entry in records:
        require(isinstance(entry, dict), 'Invalid file record.')
        name = entry.get('path')
        safe_path(name)
        path = mapped_source_path(name)
        keys = {'path', 'size', 'sha256', 'origin', 'mode'}
        if path != name:
            keys.add('git_path')
            require(entry.get('git_path') == path and not excluded(path)
                    and entry.get('origin') == 'git', 'Invalid source mapping.')
        exact_keys(entry, keys | ({'git_blob'} if entry.get('origin') == 'git' else set()))
        require(name not in result and origin(name) == entry['origin']
                and entry['origin'] in {'git', 'vendor', 'build'}, 'Unexpected/duplicate file record.')
        require(valid_size(entry['size'], MAX_FILE_BYTES) and type(entry['mode']) is int
                and entry['mode'] in {0o644, 0o755}
                and isinstance(entry['sha256'], str) and SHA256.fullmatch(entry['sha256']),
                'Invalid file integrity metadata.')
        if entry['origin'] == 'git':
            require(isinstance(entry['git_blob'], str) and COMMIT.fullmatch(entry['git_blob'])
                    and len(entry['git_blob']) == len(source['commit']), 'Invalid source blob.')
        total += entry['size']
        require(total <= MAX_TOTAL_BYTES, 'Release exceeds total size limit.')
        result[name] = entry
    check_paths(result)
    exact_keys(manifest['locks'], {'composer.lock', 'package-lock.json'})
    for name, digest in manifest['locks'].items():
        require(digest == result[name]['sha256'], 'Lock digest differs from file record.')
    return result


def verify(archive, manifest_file):
    archive, manifest_file = Path(archive).absolute(), Path(manifest_file).absolute()
    # External CLI input names are also checked before reading (e.g. --manifest .env).
    for path in (archive, manifest_file):
        require(not sensitive([p.lower() for p in path.parts]), 'Excluded verification input.')
    with open_input(manifest_file.parent, manifest_file.name) as stream:
        manifest = json_data(stream.read(MAX_JSON_BYTES + 1))
    expected = manifest_entries(manifest)
    metadata = {}
    seen = set()
    with open_input(archive.parent, archive.name) as raw:
        size, digest = file_digest(raw, MAX_ARCHIVE_BYTES)
        require(size == manifest['archive']['size'] and digest == manifest['archive']['sha256'],
                'Archive digest/size mismatch.')
        raw.seek(0)
        with gzip.GzipFile(fileobj=raw, mode='rb') as stream:
            while True:
                header = stream.read(tarfile.BLOCKSIZE)
                require(len(header) == tarfile.BLOCKSIZE, 'Truncated tar header.')
                if header == b'\0' * tarfile.BLOCKSIZE:
                    # Bound trailing padding too; reject hidden entries/concatenated data.
                    padding = stream.read(tarfile.RECORDSIZE + 1)
                    require(tarfile.BLOCKSIZE <= len(padding) <= tarfile.RECORDSIZE
                            and len(padding) % tarfile.BLOCKSIZE == 0
                            and not any(padding), 'Invalid trailing tar data.')
                    break
                require(header[257:265] == b'ustar\00000', 'Only USTAR entries are supported.')
                # Parse one header, never TarFile iteration: PAX/longname/sparse headers
                # must be rejected BEFORE tarfile could allocate/read their payload.
                member = tarfile.TarInfo.frombuf(header, 'utf-8', 'strict')
                safe_path(member.name)
                require(member.type == tarfile.REGTYPE and not member.linkname,
                        'Archive links/special/extension entries are not allowed.')
                require(member.name not in seen and member.name in expected,
                        'Unexpected/duplicate archive entry.')
                record = expected[member.name]
                require(member.size == record['size'] and member.mode == record['mode']
                        and member.uid == member.gid == member.mtime == 0
                        and member.uname == member.gname == ''
                        and member.devmajor == member.devminor == 0, 'Archive member metadata mismatch.')
                digest = hashlib.sha256()
                git_digest = hashlib.sha1() if len(manifest['source']['commit']) == 40 else hashlib.sha256()
                git_digest.update(b'blob ' + str(member.size).encode() + b'\0')
                captured = bytearray()
                remaining = member.size
                if member.name in METADATA:
                    require(remaining <= MAX_JSON_BYTES, 'JSON exceeds size limit.')
                while remaining:
                    data = stream.read(min(CHUNK, remaining))
                    require(data, 'Truncated archive member.')
                    digest.update(data)
                    git_digest.update(data)
                    if member.name in METADATA:
                        captured.extend(data)
                    remaining -= len(data)
                require(digest.hexdigest() == record['sha256'], 'File digest mismatch.')
                if record['origin'] == 'git':
                    require(git_digest.hexdigest() == record['git_blob'], 'Source blob mismatch.')
                padding_size = -member.size % tarfile.BLOCKSIZE
                padding = stream.read(padding_size)
                require(len(padding) == padding_size and not any(padding), 'Invalid member padding.')
                if member.name in METADATA:
                    metadata[member.name] = bytes(captured)
                seen.add(member.name)
    require(seen == expected.keys(), 'Missing archive entries.')
    check_metadata(metadata, seen)
    return manifest


class Parser(argparse.ArgumentParser):
    def error(self, message: str) -> NoReturn:
        raise ArtifactError('Invalid arguments; use --help.')


def main(argv=None):
    try:
        parser = Parser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
        commands = parser.add_subparsers(dest='command', required=True)
        builder = commands.add_parser('build', help='Build from a clean checkout; no install/deploy.')
        builder.add_argument('--output', type=Path, required=True)
        verifier = commands.add_parser('verify', help='Verify offline, without extraction.')
        verifier.add_argument('--archive', type=Path, required=True)
        verifier.add_argument('--manifest', type=Path, required=True)
        args = parser.parse_args(argv)
        if args.command == 'build':
            manifest = build(Path.cwd(), args.output)
        else:
            manifest = verify(args.archive, args.manifest)
        print(json.dumps({'status': 'ok', 'release_id': manifest['release_id'],
                          'archive': ARCHIVE_NAME, 'manifest': MANIFEST_NAME}, sort_keys=True))
        return 0
    except ArtifactError as error:
        print('Release artifact rejected: ' + str(error), file=sys.stderr)
    except (OSError, ValueError, TypeError, KeyError, EOFError, RecursionError, OverflowError,
            tarfile.TarError, subprocess.SubprocessError, zlib.error):
        # Never print raw exceptions: they may contain untrusted paths/file contents.
        print('Release artifact rejected: invalid input or I/O failure.', file=sys.stderr)
    return 1


if __name__ == '__main__':
    sys.exit(main())
