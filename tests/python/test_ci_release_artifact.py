"""CICD-05/08, NFR-CICD-005: fixture saja; Git/Composer/npm tidak dijalankan."""
from contextlib import redirect_stderr, redirect_stdout
import copy
import gzip
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import subprocess
import tarfile
import tempfile
import unittest
from unittest.mock import patch

SCRIPT = Path(__file__).resolve().parents[2] / 'scripts/ci/release_artifact.py'
spec = importlib.util.spec_from_file_location('ci_release_artifact', SCRIPT)
if spec is None:
    raise ImportError('Cannot create the release artifact module specification.')
loader = spec.loader
if loader is None:
    raise ImportError('The release artifact module specification has no loader.')
release = importlib.util.module_from_spec(spec)
loader.exec_module(release)


def blob(data):
    return hashlib.sha1(b'blob ' + str(len(data)).encode() + b'\0' + data).hexdigest()


class ReleaseArtifactTests(unittest.TestCase):
    def setUp(self):
        temporary = tempfile.TemporaryDirectory(prefix='release-fixture-')
        self.addCleanup(temporary.cleanup)
        self.base = Path(temporary.name).resolve()
        self.root = self.base / 'checkout'
        self.root.mkdir()
        self.output = self.base / 'output'
        self.commit = 'a' * 40
        self.status = b''
        self.tracked = {}
        self.commands = []
        self.package = {'name': 'acme/runtime', 'version': '1.2.3',
                        'dist': {'reference': 'release-ref'}, 'type': 'library'}
        self.lock = {'packages': [self.package], 'packages-dev': [
            {'name': 'acme/testing', 'version': '4.0.0'}]}
        self.installed = {'dev': False, 'dev-package-names': ['acme/testing'],
                          'packages': [dict(self.package, **{'install-path': '../acme/runtime'})]}
        sources = {
            'artisan': '<?php // CLI', 'bootstrap/app.php': '<?php // bootstrap',
            'bootstrap/providers.php': '<?php return [];', 'composer.json': '{}',
            'composer.lock': json.dumps(self.lock), 'package.json': '{}',
            'package-lock.json': '{"lockfileVersion": 3}',
            'app/Providers/AppServiceProvider.php': '<?php // provider',
            'config/app.php': '<?php // config', 'routes/web.php': '<?php // routes',
            'resources/views/home.blade.php': '<h1>Zafira</h1>',
            'lang/id/shop.php': '<?php // locale',
            'database/migrations/2026_create.php': '<?php // migration',
            'public/index.php': '<?php // front controller',
            'public/.htaccess': 'RewriteEngine On', 'public/logo.svg': '<svg/>',
        }
        for path, content in sources.items():
            self.put(path, content, tracked=True)
        self.put('vendor/autoload.php', '<?php // autoload')
        self.put('vendor/composer/installed.json', json.dumps(self.installed))
        self.put('vendor/acme/runtime/src/Runtime.php', '<?php // dependency')
        self.put('public/build/manifest.json', json.dumps({
            'resources/js/app.js': {'file': 'assets/app-abc.js', 'isEntry': True}}))
        self.put('public/build/assets/app-abc.js', 'console.log("fixture");')
        self.git = patch.object(release.subprocess, 'run', side_effect=self.fake_git)
        self.git.start()
        self.addCleanup(self.git.stop)

    def put(self, path, content, tracked=False, mode='100644'):
        target = self.root / path
        target.parent.mkdir(parents=True, exist_ok=True)
        data = content.encode() if isinstance(content, str) else content
        target.write_bytes(data)
        target.chmod(0o755 if mode == '100755' else 0o644)
        if tracked:
            self.tracked[path] = (mode, blob(data))
        return target

    def storefront_aliases(self):
        targets = {
            'prototype/app.css': b'body { color: navy; }',
            'prototype/app.js': b'console.log("storefront");',
            'prototype/index.html': b'<h1>Maison</h1>',
            'prototype/admin/index.html': b'<h1>Atelier</h1>',
            'prototype/assets/fonts/display.woff2': b'synthetic-font',
        }
        for path, data in targets.items():
            self.put(path, data, tracked=True)
        for name, payload in (
            ('public/app.css', '../prototype/app.css'),
            ('public/app.js', '../prototype/app.js'),
            ('public/prototype', '../prototype'),
        ):
            (self.root / name).symlink_to(payload)
            self.tracked[name] = ('120000', blob(payload.encode()))
        return targets

    def fake_git(self, argv, **kwargs):
        self.assertEqual(argv[0], 'git')
        self.assertIn('--no-pager', argv)
        args = tuple(argv[argv.index('-C') + 2:])
        self.commands.append(args)
        if args == ('rev-parse', '--show-toplevel'):
            stdout = str(self.root).encode() + b'\n'
        elif args == ('rev-parse', '--verify', 'HEAD^{commit}'):
            stdout = self.commit.encode() + b'\n'
        elif args == ('status', '--porcelain=v1', '-z', '--untracked-files=all', '--ignore-submodules=none'):
            stdout = self.status
        elif args == ('ls-tree', '-r', '-z', '--full-tree', self.commit):
            stdout = b''.join(
                f'{mode} {"commit" if mode == "160000" else "blob"} {oid}\t{path}'.encode() + b'\0'
                for path, (mode, oid) in sorted(self.tracked.items()))
        else:
            self.fail(f'Unexpected Git command: {args}')
        return subprocess.CompletedProcess(argv, 0, stdout, b'')

    def build(self):
        return release.build(self.root, self.output)

    def verify(self):
        return release.verify(self.output / 'release.tar.gz', self.output / 'release-manifest.json')

    def test_roundtrip_is_deterministic_and_binds_source_locks_and_generated_bytes(self):
        manifest = self.build()
        self.assertEqual(manifest['source']['commit'], self.commit)
        self.assertIs(manifest['source']['clean'], True)
        files = {entry['path']: entry for entry in manifest['files']}
        self.assertEqual(files['public/logo.svg']['origin'], 'git')
        self.assertEqual(manifest['schema'], 1)
        self.assertTrue(all('git_path' not in e for e in manifest['files']))
        self.assertEqual(files['public/build/assets/app-abc.js']['origin'], 'build')
        self.assertEqual(files['vendor/autoload.php']['origin'], 'vendor')
        for lock in ('composer.lock', 'package-lock.json'):
            self.assertEqual(manifest['locks'][lock], files[lock]['sha256'])
        self.assertEqual(self.verify(), manifest)
        second = self.base / 'second'
        release.build(self.root, second)
        for filename in ('release.tar.gz', 'release-manifest.json'):
            self.assertEqual((self.output / filename).read_bytes(), (second / filename).read_bytes())

    def test_storefront_aliases_materialize_tracked_regular_blobs_without_following_links(self):
        targets = self.storefront_aliases()
        self.put('prototype/app.js', targets['prototype/app.js'], tracked=True, mode='100755')
        expected = {'public/' + path: path for path in targets}
        expected.update({'public/app.css': 'prototype/app.css', 'public/app.js': 'prototype/app.js'})
        original = release.read_input

        def guarded(root, name):
            self.assertNotIn(name, expected, 'must open the Git source, not the alias')
            return original(root, name)

        with patch.object(release, 'read_input', side_effect=guarded):
            manifest = self.build()
        records = {e['path']: e for e in manifest['files']}
        self.assertEqual({p: e['git_path'] for p, e in records.items() if 'git_path' in e}, expected)
        self.assertNotIn('public/prototype', records)
        self.assertTrue(records.keys().isdisjoint(targets))
        for member, data in self.archive_items():
            self.assertEqual(member.type, tarfile.REGTYPE)
            self.assertEqual(member.linkname, '')
            if member.name in expected:
                target = expected[member.name]
                self.assertEqual(data, targets[target])
                self.assertEqual(records[member.name]['git_blob'], self.tracked[target][1])
                self.assertEqual(records[member.name]['origin'], 'git')
                self.assertEqual(member.mode, 0o755 if target == 'prototype/app.js' else 0o644)
        second = self.base / 'second'
        release.build(self.root, second)
        for filename in ('release.tar.gz', 'release-manifest.json'):
            self.assertEqual((self.output / filename).read_bytes(), (second / filename).read_bytes())
        for name in ('public/app.css', 'public/app.js', 'public/prototype'):
            self.assertTrue((self.root / name).is_symlink())
        self.root.rename(self.base / 'unavailable-checkout')
        with patch.object(release.subprocess, 'run', side_effect=AssertionError('offline')), \
             patch.object(tarfile.TarFile, 'extractall', side_effect=AssertionError('no extraction')):
            self.assertEqual(self.verify(), manifest)

    def test_storefront_alias_git_payload_and_checkout_payload_are_both_literal(self):
        self.storefront_aliases()
        for name in ('public/app.css', 'public/app.js', 'public/prototype'):
            link = self.root / name
            expected, metadata = os.readlink(link), self.tracked[name]
            payloads = ('../prototype/other.js', '/absolute', '../../escape',
                        expected + '\n', expected + '/../app.css', '../prototype/private.key')
            for payload in payloads:
                for change_git in (True, False):
                    with self.subTest(name=name, payload=payload, change_git=change_git):
                        if change_git:
                            self.tracked[name] = ('120000', blob(payload.encode()))
                        else:
                            link.unlink()
                            link.symlink_to(payload)
                        with patch.object(release, 'read_input', side_effect=AssertionError('no reads')), \
                             self.assertRaisesRegex(release.ArtifactError, 'alias'):
                            self.build()
                        self.assertFalse(self.output.exists())
                        self.tracked[name] = metadata
                        link.unlink()
                        link.symlink_to(expected)
            for mode in ('100644', '160000'):
                self.tracked[name] = (mode, metadata[1])
                with self.subTest(mode=mode), self.assertRaisesRegex(release.ArtifactError, 'alias'):
                    self.build()
            self.tracked[name] = metadata
        self.tracked['public/other.css'] = ('120000', blob(b'../prototype/app.css'))
        with self.assertRaisesRegex(release.ArtifactError, 'links/submodules'):
            self.build()

    def test_storefront_private_and_untracked_targets_are_excluded_before_open(self):
        self.storefront_aliases()
        forbidden = {'prototype/private.key', 'prototype/auth.json', 'prototype/.npmrc',
                     'prototype/docs/private.txt', 'prototype/uploads/photo.jpg',
                     'prototype/export.sql.gz', 'prototype/.gitignore', 'prototype/untracked.js'}
        for path in forbidden:
            self.put(path, 'SYNTHETIC-PRIVATE-TARGET', tracked=path != 'prototype/untracked.js')
        (self.root / 'prototype/keys').symlink_to(self.base / 'unreadable')
        self.tracked['prototype/keys'] = ('120000', blob(b'private-link'))
        original = release.read_input

        def guarded(root, name):
            self.assertNotIn(name, forbidden | {'prototype/keys'})
            return original(root, name)

        with patch.object(release, 'read_input', side_effect=guarded):
            manifest = self.build()
        self.assertTrue({e['path'] for e in manifest['files']}.isdisjoint(
            {'public/' + path for path in forbidden | {'prototype/keys'}}))
        self.assertNotIn(b'SYNTHETIC-PRIVATE-TARGET', gzip.decompress((self.output / 'release.tar.gz').read_bytes()))
        self.verify()

    def test_storefront_manifest_mapping_is_required_and_strictly_allowlisted(self):
        self.storefront_aliases()
        original = self.build()
        for record in original['files']:
            if 'git_path' not in record:
                continue
            for path in (None, '', '/prototype/app.css', '../prototype/app.css',
                         'prototype/missing.css', 'prototype/private.key',
                         'prototype/app.css/../app.css', 'public/app.css', [],
                         'prototype/app.js' if record['git_path'] != 'prototype/app.js' else 'prototype/app.css'):
                with self.subTest(name=record['path'], path=path):
                    manifest = copy.deepcopy(original)
                    entry = next(e for e in manifest['files'] if e['path'] == record['path'])
                    if path is None:
                        del entry['git_path']
                    else:
                        entry['git_path'] = path
                    self.save_manifest(manifest)
                    with self.assertRaisesRegex(release.ArtifactError, 'source mapping'):
                        self.verify()
        for name in ('public/logo.svg', 'public/prototype', 'public/other.css',
                     'vendor/autoload.php', 'public/build/assets/app-abc.js'):
            with self.subTest(name=name):
                manifest = copy.deepcopy(original)
                record = next(e for e in manifest['files'] if e['path'] == 'public/app.css')
                record['path'] = name
                self.save_manifest(manifest)
                with self.assertRaises(release.ArtifactError):
                    self.verify()

    def test_storefront_verifier_binds_target_blob_not_alias_blob_or_only_sha256(self):
        self.storefront_aliases()
        original = self.build()
        for name in ('public/app.css', 'public/app.js', 'public/prototype/admin/index.html'):
            with self.subTest(name=name):
                manifest = copy.deepcopy(original)
                entry = next(e for e in manifest['files'] if e['path'] == name)
                entry['git_blob'] = blob(b'../prototype')
                self.save_manifest(manifest)
                with self.assertRaisesRegex(release.ArtifactError, 'Source blob mismatch'):
                    self.verify()
        self.save_manifest(original)
        items = self.archive_items()
        for member, data in items:
            if member.name == 'public/app.css':
                changed = b'X' + data[1:]
                entry = next(e for e in original['files'] if e['path'] == member.name)
                entry['sha256'] = hashlib.sha256(changed).hexdigest()
        self.save_manifest(original)
        self.repack([(member, b'X' + data[1:] if member.name == 'public/app.css' else data)
                     for member, data in items])
        with self.assertRaisesRegex(release.ArtifactError, 'Source blob mismatch'):
            self.verify()

    def test_storefront_targets_must_exist_and_match_their_tracked_blobs(self):
        targets = self.storefront_aliases()
        for name in ('prototype/app.css', 'prototype/admin/index.html'):
            with self.subTest(name=name):
                self.put(name, b'changed')
                with self.assertRaisesRegex(release.ArtifactError, 'differ from commit'):
                    self.build()
                (self.root / name).unlink()
                with self.assertRaises(FileNotFoundError):
                    self.build()
                self.put(name, targets[name])
                self.assertFalse(self.output.exists())
        del self.tracked['prototype/app.css']
        with patch.object(release, 'read_input', side_effect=AssertionError('no untracked reads')), \
             self.assertRaisesRegex(release.ArtifactError, 'Missing tracked'):
            self.build()
        for name in list(self.tracked):
            if name.startswith('prototype/') or name in {'public/app.css', 'public/app.js'}:
                del self.tracked[name]
        with self.assertRaisesRegex(release.ArtifactError, 'Missing tracked'):
            self.build()

    def test_storefront_nested_git_symlinks_and_gitlinks_are_rejected_before_reads(self):
        self.storefront_aliases()
        for name in ('prototype/app.css', 'prototype/assets/link', 'prototype'):
            original = self.tracked.get(name)
            for mode in ('120000', '160000'):
                with self.subTest(name=name, mode=mode):
                    self.tracked[name] = (mode, blob(b'../outside'))
                    with patch.object(release, 'read_input', side_effect=AssertionError('no reads')), \
                         self.assertRaises(release.ArtifactError):
                        self.build()
                    self.assertFalse(self.output.exists())
            if original is None:
                del self.tracked[name]
            else:
                self.tracked[name] = original

    def test_storefront_target_leaf_and_parent_symlinks_hardlinks_and_fifo_fail_closed(self):
        targets = self.storefront_aliases()
        for name in ('prototype/app.css', 'prototype/admin', 'prototype'):
            with self.subTest(name=name):
                target, saved = self.root / name, self.base / 'saved-target'
                target.rename(saved)
                target.symlink_to(saved)
                with self.assertRaises(OSError):
                    self.build()
                target.unlink()
                saved.rename(target)
                self.assertFalse(self.output.exists())
        target, extra = self.root / 'prototype/app.css', self.base / 'hardlink'
        os.link(target, extra)
        with self.assertRaisesRegex(release.ArtifactError, 'without links'):
            self.build()
        extra.unlink()
        target.unlink()
        os.mkfifo(target)
        with self.assertRaisesRegex(release.ArtifactError, 'regular file'):
            self.build()
        self.assertFalse(self.output.exists())
        target.unlink()
        self.put('prototype/app.css', targets['prototype/app.css'])

    def test_storefront_alias_entry_and_safe_parent_are_checked_without_following(self):
        self.storefront_aliases()
        for name in ('public/app.css', 'public/app.js', 'public/prototype'):
            link = self.root / name
            payload = os.readlink(link)
            link.unlink()
            with self.assertRaises(FileNotFoundError):
                release.source_files(self.root, self.commit)
            link.write_text(payload)
            with self.assertRaisesRegex(release.ArtifactError, 'single symlink'):
                release.source_files(self.root, self.commit)
            link.unlink()
            link.symlink_to(payload)
        original_open, original_readlink = release.os.open, release.os.readlink

        def nofollow(path, flags, *args, **kwargs):
            self.assertTrue(flags & os.O_NOFOLLOW)
            return original_open(path, flags, *args, **kwargs)

        def readlink(leaf, *, dir_fd=None):
            self.assertIn(leaf, {'app.css', 'app.js', 'prototype'})
            self.assertIsInstance(dir_fd, int)
            return original_readlink(leaf, dir_fd=dir_fd)

        with patch.object(release.os, 'open', side_effect=nofollow), \
             patch.object(release.os, 'readlink', side_effect=readlink):
            release.source_files(self.root, self.commit)
        public, saved = self.root / 'public', self.base / 'saved-public'
        public.rename(saved)
        public.symlink_to(saved)
        with patch.object(release.os, 'readlink', side_effect=AssertionError('unsafe parent')), \
             self.assertRaises(OSError):
            release.source_files(self.root, self.commit)

    def test_storefront_alias_and_mapping_changes_are_rechecked_before_and_after_archive(self):
        self.storefront_aliases()
        original = release.clean_commit
        for phase in (2, 3):
            for name in ('public/app.css', 'public/app.js', 'public/prototype', 'prototype/added.js'):
                calls = 0
                link = self.root / name
                payload = os.readlink(link) if name.startswith('public/') else None

                def mutate(root, expected=None, *, phase=phase, payload=payload, name=name, link=link):
                    nonlocal calls
                    calls += 1
                    if calls == phase:
                        if payload is None:
                            self.put(name, b'new tracked descendant', tracked=True)
                        else:
                            link.unlink()
                            link.symlink_to('../prototype/other')
                    return original(root, expected)

                with self.subTest(phase=phase, name=name), \
                     patch.object(release, 'clean_commit', side_effect=mutate), \
                     self.assertRaisesRegex(release.ArtifactError, 'changed'):
                    self.build()
                self.assertFalse(self.output.exists())
                link.unlink()
                if payload is None:
                    del self.tracked[name]
                else:
                    link.symlink_to(payload)

    def test_dirty_tree_fails_before_file_reads_or_output_creation(self):
        for status in (b' M app/A.php\0', b'M  config/app.php\0', b'?? scratch.txt\0'):
            with self.subTest(status=status):
                self.status = status
                with patch.object(release, 'read_input', side_effect=AssertionError('must not read')), \
                     self.assertRaisesRegex(release.ArtifactError, 'clean'):
                    self.build()
                self.assertFalse(self.output.exists())

    def test_clean_status_does_not_hide_modified_source_bytes(self):
        self.put('app/Providers/AppServiceProvider.php', '<?php // hidden change')
        with self.assertRaisesRegex(release.ArtifactError, 'commit'):
            self.build()
        self.assertFalse(self.output.exists())

    def test_development_vendor_is_rejected(self):
        self.installed['dev'] = True
        self.put('vendor/composer/installed.json', json.dumps(self.installed))
        with self.assertRaisesRegex(release.ArtifactError, 'production'):
            self.build()
        self.assertFalse(self.output.exists())

    def load_manifest(self):
        return json.loads((self.output / 'release-manifest.json').read_bytes())

    def save_manifest(self, manifest):
        (self.output / 'release-manifest.json').write_text(json.dumps(manifest))

    def refresh_archive_digest(self):
        data = (self.output / 'release.tar.gz').read_bytes()
        manifest = self.load_manifest()
        digest = hashlib.sha256(data).hexdigest()
        manifest['archive'].update(size=len(data), sha256=digest)
        manifest['release_id'] = manifest['source']['commit'] + '-' + digest
        self.save_manifest(manifest)

    def archive_items(self):
        items = []
        with tarfile.open(self.output / 'release.tar.gz', 'r:gz') as bundle:
            for member in bundle:
                stream = bundle.extractfile(member)
                if stream is None:
                    self.fail('Expected a readable file member in the fixture archive.')
                with stream:
                    items.append((member, stream.read()))
        return items

    def repack(self, items):
        with (self.output / 'release.tar.gz').open('wb') as raw, \
             gzip.GzipFile(fileobj=raw, mode='wb', mtime=0, filename='') as gz, \
             tarfile.open(fileobj=gz, mode='w', format=tarfile.USTAR_FORMAT) as bundle:
            for member, data in items:
                bundle.addfile(member, io.BytesIO(data))
        self.refresh_archive_digest()

    def run_cli(self, *args):
        stdout, stderr = io.StringIO(), io.StringIO()
        with redirect_stdout(stdout), redirect_stderr(stderr), patch.object(Path, 'cwd', return_value=self.root):
            code = release.main(list(args))
        return code, stdout.getvalue(), stderr.getvalue()

    def test_cli_build_and_offline_verify_without_extraction_or_subprocess(self):
        code, output, error = self.run_cli('build', '--output', str(self.output))
        self.assertEqual((code, error), (0, ''))
        self.assertEqual(json.loads(output)['archive'], 'release.tar.gz')
        before = sorted(str(p.relative_to(self.base)) for p in self.base.rglob('*'))
        with patch.object(release.subprocess, 'run', side_effect=AssertionError('offline')), \
             patch.object(tarfile.TarFile, 'extract', side_effect=AssertionError('no extraction')), \
             patch.object(tarfile.TarFile, 'extractall', side_effect=AssertionError('no extraction')):
            code, output, error = self.run_cli('verify', '--archive', str(self.output / 'release.tar.gz'),
                                             '--manifest', str(self.output / 'release-manifest.json'))
        self.assertEqual((code, error), (0, ''))
        self.assertEqual(json.loads(output)['status'], 'ok')
        self.assertEqual(before, sorted(str(p.relative_to(self.base)) for p in self.base.rglob('*')))

    def test_exclusions_are_applied_before_open_including_tracked_sensitive_names(self):
        forbidden = [
            '.env', '.env.example', 'app/.env.production', 'config/auth.json',
            'resources/keys/private.key', 'app/credentials.json', 'public/private.pem',
            'public/private.key.backup', 'public/private.pem.old', 'config/auth.json.bak',
            'vendor/acme/runtime/.npmrc', 'vendor/acme/runtime/.netrc',
            'public/export.sql.gz', 'database/database.sqlite', 'database/ci.sqlite',
            'database/migrations/backup.sql', 'storage/uploads/customer.json',
            'public/storage/customer.jpg', 'public/uploads/customer.jpg', 'public/hot',
            'bootstrap/cache/config.php', 'bootstrap/cache/services.php',
            'node_modules/thing/index.js', '.git/config', '.github/workflows/ci.yml',
            'tests/fixture.php', 'docs/notes.md', 'scripts/ci/helper.py',
            'vendor/acme/runtime/.env.test', 'vendor/acme/runtime/auth.json',
            'vendor/acme/runtime/tests/Fixture.php', 'vendor/acme/runtime/docs/readme.md',
            'public/build/.env.production', 'public/build/keys/test.key',
        ]
        for path in forbidden:
            self.put(path, 'SYNTHETIC-EXCLUDED-CONTENT', tracked=not path.startswith(('vendor/', 'public/build/')))
        self.put('public/untracked.svg', 'not committed')
        original = release.read_input
        opened = []

        def guarded(root, name):
            self.assertNotIn(name, forbidden)
            self.assertNotEqual(name, 'public/untracked.svg')
            opened.append(name)
            return original(root, name)

        with patch.object(release, 'read_input', side_effect=guarded):
            manifest = self.build()
        paths = {e['path'] for e in manifest['files']}
        self.assertTrue(paths.isdisjoint(forbidden))
        self.assertNotIn('public/untracked.svg', paths)
        self.assertIn('public/.htaccess', paths)
        self.assertIn('database/migrations/2026_create.php', opened)
        self.assertNotIn(b'SYNTHETIC-EXCLUDED-CONTENT', gzip.decompress((self.output / 'release.tar.gz').read_bytes()))

    def test_excluded_symlink_directory_is_not_followed(self):
        (self.root / 'public/storage').symlink_to(self.base / 'not-readable', target_is_directory=True)
        (self.root / 'vendor/acme/runtime/tests').symlink_to(self.base / 'not-readable', target_is_directory=True)
        self.build()
        self.verify()

    def test_output_must_be_new_and_outside_inputs_even_through_parent_alias(self):
        for output in (self.root, self.root / 'public/build/out', self.root / 'vendor/out',
                       self.root / 'app/out', self.root / 'release', self.base):
            with self.subTest(output=output), self.assertRaisesRegex(release.ArtifactError, 'outside'):
                release.build(self.root, output)
        alias = self.base / 'alias'
        alias.symlink_to(self.root / 'public', target_is_directory=True)
        with self.assertRaisesRegex(release.ArtifactError, 'outside'):
            release.build(self.root, alias / 'output')
        self.output.mkdir()
        marker = self.output / 'existing.txt'
        marker.write_text('preserve')
        with self.assertRaisesRegex(release.ArtifactError, 'new'):
            self.build()
        self.assertEqual(marker.read_text(), 'preserve')

    def test_source_symlink_gitlink_and_filesystem_link_are_rejected(self):
        path = 'app/Providers/AppServiceProvider.php'
        for mode in ('120000', '160000'):
            with self.subTest(mode=mode):
                self.tracked[path] = (mode, 'f' * 40)
                with self.assertRaisesRegex(release.ArtifactError, 'links/submodules'):
                    self.build()
        self.tracked[path] = ('100644', 'f' * 40)
        (self.root / path).unlink()
        (self.root / path).symlink_to(self.base / 'unreadable')
        with self.assertRaises(OSError):
            self.build()
        self.assertFalse(self.output.exists())

    def test_generated_symlinks_hardlinks_and_special_files_are_rejected(self):
        target = self.root / 'public/build/assets/link.js'
        target.symlink_to(self.base / 'unreadable')
        with self.assertRaisesRegex(release.ArtifactError, 'without links'):
            self.build()
        target.unlink()
        os.link(self.root / 'public/build/assets/app-abc.js', target)
        with self.assertRaisesRegex(release.ArtifactError, 'without links'):
            self.build()
        target.unlink()
        os.mkfifo(target)
        with self.assertRaisesRegex(release.ArtifactError, 'regular file'):
            self.build()
        self.assertFalse(self.output.exists())

    def test_generated_parent_symlink_is_rejected_without_following(self):
        original = self.root / 'public/build'
        original.rename(self.base / 'generated')
        original.symlink_to(self.base / 'generated', target_is_directory=True)
        with self.assertRaises(OSError):
            self.build()
        self.assertFalse(self.output.exists())

    def test_stale_or_tampered_production_metadata_is_rejected(self):
        variants = []
        for key, value in (('version', '0.0.0'), ('dist', {'reference': 'other'}),
                           ('install-path', '../../outside')):
            data = copy.deepcopy(self.installed)
            data['packages'][0][key] = value
            variants.append(data)
        variants += [dict(self.installed, packages=[]),
                     dict(self.installed, packages=self.installed['packages'] * 2),
                     dict(self.installed, packages=self.installed['packages'] + self.lock['packages-dev']),
                     dict(self.installed, **{'dev-package-names': ['acme/runtime']})]
        variants.extend(dict(self.installed, **{'dev-package-names': value})
                        for value in (None, 'acme/testing', 1, {}, ['acme/testing', None]))
        for data in variants:
            with self.subTest(data=data):
                self.put('vendor/composer/installed.json', json.dumps(data))
                with self.assertRaises(release.ArtifactError):
                    self.build()
                self.assertFalse(self.output.exists())

    def test_unlisted_vendor_package_or_binary_is_rejected_before_it_is_read(self):
        original = release.read_input
        for path in ('vendor/acme/testing/Test.php', 'vendor/bin/phpunit',
                     'vendor/composer/rogue/payload.php'):
            with self.subTest(path=path):
                self.put(path, 'must not be read')

                def guarded(root, name):
                    self.assertNotEqual(name, path)
                    return original(root, name)

                with patch.object(release, 'read_input', side_effect=guarded), \
                     self.assertRaisesRegex(release.ArtifactError, 'Unexpected vendor'):
                    self.build()
                (self.root / path).unlink()

    def test_production_binary_and_committed_executable_have_normalized_modes(self):
        self.lock['packages'][0]['bin'] = ['bin/runtime']
        self.installed['packages'][0]['bin'] = ['bin/runtime']
        self.put('composer.lock', json.dumps(self.lock), tracked=True)
        self.put('vendor/composer/installed.json', json.dumps(self.installed))
        self.put('vendor/acme/runtime/bin/runtime', '#!/usr/bin/env php')
        self.put('vendor/bin/runtime', '#!/usr/bin/env php')
        self.put('artisan', '<?php // CLI', tracked=True, mode='100755')
        manifest = self.build()
        files = {entry['path']: entry for entry in manifest['files']}
        self.assertEqual(files['artisan']['mode'], 0o755)
        self.assertEqual(files['vendor/bin/runtime']['mode'], 0o755)
        self.verify()

    def test_missing_required_input_and_incomplete_vite_output_fail(self):
        self.tracked.pop('bootstrap/providers.php')
        with self.assertRaisesRegex(release.ArtifactError, 'Missing required'):
            self.build()
        self.put('bootstrap/providers.php', '<?php return [];', tracked=True)
        (self.root / 'public/build/assets/app-abc.js').unlink()
        with self.assertRaisesRegex(release.ArtifactError, 'Vite asset'):
            self.build()
        self.assertFalse(self.output.exists())

    def test_vite_unsafe_paths_and_missing_chunk_references_fail(self):
        for entry in ({'file': '../secret'}, {'file': '/tmp/secret'},
                      {'file': 'assets/app-abc.js', 'css': ['missing.css']},
                      {'file': 'assets/app-abc.js', 'imports': ['missing-chunk']}):
            with self.subTest(entry=entry):
                self.put('public/build/manifest.json', json.dumps({'app': entry}))
                with self.assertRaises(release.ArtifactError):
                    self.build()

    def test_tracked_generated_files_are_rejected(self):
        for path in ('vendor/autoload.php', 'public/build/assets/app-abc.js'):
            self.tracked[path] = ('100644', 'b' * 40)
            with self.subTest(path=path), self.assertRaisesRegex(release.ArtifactError, 'must not be tracked'):
                self.build()
            del self.tracked[path]

    def test_source_or_generated_bytes_changing_during_build_leave_no_artifact(self):
        self.storefront_aliases()
        original = release.read_input
        for path in ('public/logo.svg', 'public/build/assets/app-abc.js', 'prototype/admin/index.html'):
            reads = 0

            def mutate(root, name):
                nonlocal reads
                data = original(root, name)
                if name == path:
                    reads += 1
                    if reads == 2:
                        return data + b'changed'
                return data

            with self.subTest(path=path), patch.object(release, 'read_input', side_effect=mutate), \
                 self.assertRaisesRegex(release.ArtifactError, 'changed during build'):
                self.build()
            self.assertFalse(self.output.exists())

    def test_commit_change_at_postflight_removes_partial_output(self):
        original = release.clean_commit
        calls = 0

        def mutate(root, expected=None):
            nonlocal calls
            calls += 1
            if calls == 3:
                self.commit = 'b' * 40
            return original(root, expected)

        with patch.object(release, 'clean_commit', side_effect=mutate), \
             self.assertRaisesRegex(release.ArtifactError, 'commit changed'):
            self.build()
        self.assertFalse(self.output.exists())

    def test_git_failure_is_fail_closed_and_does_not_print_subprocess_output(self):
        with patch.object(release.subprocess, 'run', return_value=subprocess.CompletedProcess(
                [], 2, b'SYNTHETIC-SENSITIVE-OUTPUT', b'SYNTHETIC-SENSITIVE-ERROR')):
            code, stdout, stderr = self.run_cli('build', '--output', str(self.output))
        self.assertEqual(code, 1)
        self.assertEqual(stdout, '')
        self.assertNotIn('SYNTHETIC', stderr)
        self.assertLess(len(stderr), 240)
        self.assertFalse(self.output.exists())

    def test_archive_tamper_is_rejected(self):
        self.build()
        path = self.output / 'release.tar.gz'
        data = bytearray(path.read_bytes())
        data[len(data) // 2] ^= 1
        path.write_bytes(data)
        with self.assertRaisesRegex(release.ArtifactError, 'Archive digest'):
            self.verify()

    def test_file_tamper_is_rejected_even_with_refreshed_archive_hash(self):
        self.build()
        items = self.archive_items()
        member, data = items[-1]
        items[-1] = (member, b'X' + data[1:])
        self.repack(items)
        with self.assertRaisesRegex(release.ArtifactError, 'File digest'):
            self.verify()

    def test_missing_duplicate_and_unexpected_archive_entries(self):
        self.build()
        items = self.archive_items()
        extra = tarfile.TarInfo('app/Unexpected.php')
        for changed, message in ((items[:-1], 'Missing archive'),
                                 (items + [items[0]], 'duplicate'),
                                 (items + [(extra, b'')], 'Unexpected')):
            with self.subTest(message=message):
                self.repack(changed)
                with self.assertRaisesRegex(release.ArtifactError, message):
                    self.verify()

    def test_archive_paths_cannot_traverse_or_use_platform_aliases(self):
        self.build()
        items = self.archive_items()
        for name in ('../escape', '/absolute', 'C:/drive', 'public/../escape',
                     'public//double', 'public/./dot', 'public/back\\slash',
                     'public/space ', 'public/CON.txt', 'public/control\nfile'):
            with self.subTest(name=name):
                self.repack(items + [(tarfile.TarInfo(name), b'')])
                with self.assertRaisesRegex(release.ArtifactError, 'Unsafe path'):
                    self.verify()
        self.assertFalse((self.base / 'escape').exists())

    def test_tar_links_special_types_and_extensions_rejected_before_payload_read(self):
        self.storefront_aliases()
        self.build()
        for name in ('public/logo.svg', 'public/app.css', 'public/prototype/index.html'):
            for kind in (tarfile.SYMTYPE, tarfile.LNKTYPE, tarfile.DIRTYPE, tarfile.FIFOTYPE,
                         tarfile.CHRTYPE, tarfile.BLKTYPE, tarfile.XHDTYPE,
                         tarfile.XGLTYPE, tarfile.GNUTYPE_LONGNAME, tarfile.GNUTYPE_SPARSE):
                with self.subTest(name=name, kind=kind):
                    member = tarfile.TarInfo(name)
                    member.type = kind
                    if kind in (tarfile.SYMTYPE, tarfile.LNKTYPE):
                        member.linkname = '../../outside'
                    member.size = release.MAX_FILE_BYTES + 1
                    data = member.tobuf(format=tarfile.USTAR_FORMAT)
                    (self.output / 'release.tar.gz').write_bytes(gzip.compress(data, mtime=0))
                    self.refresh_archive_digest()
                    with self.assertRaisesRegex(release.ArtifactError, 'links/special/extension'):
                        self.verify()

    def test_manifest_cannot_authorize_excluded_entries_even_if_self_consistent(self):
        self.build()
        original = self.load_manifest()
        for name in ('app/.env', 'config/auth.json', 'public/secret.key', 'public/uploads/data.json',
                     'bootstrap/cache/config.php', 'storage/file', 'tests/fixture.php', 'node_modules/a.js'):
            with self.subTest(name=name):
                manifest = copy.deepcopy(original)
                manifest['files'].append({'path': name, 'size': 0, 'sha256': hashlib.sha256(b'').hexdigest(),
                                          'mode': 0o644, 'origin': 'git', 'git_blob': blob(b'')})
                self.save_manifest(manifest)
                with self.assertRaises(release.ArtifactError):
                    self.verify()

    def test_manifest_schema_collisions_digests_and_required_files_are_checked(self):
        self.build()
        original = self.load_manifest()
        variants = []
        data = copy.deepcopy(original)
        data['locks']['composer.lock'] = '0' * 64
        variants.append(data)
        data = copy.deepcopy(original)
        data['files'][0]['git_blob'] = '0' * 40
        variants.append(data)
        data = copy.deepcopy(original)
        data['files'].append(copy.deepcopy(data['files'][0]))
        variants.append(data)
        data = copy.deepcopy(original)
        record = copy.deepcopy(next(e for e in data['files'] if e['path'] == 'public/logo.svg'))
        record['path'] = 'public/Logo.svg'
        data['files'].append(record)
        variants.append(data)
        data = copy.deepcopy(original)
        record = copy.deepcopy(record)
        record['path'] = 'public/logo.svg/child'
        data['files'].append(record)
        variants.append(data)
        data = copy.deepcopy(original)
        data['files'] = [e for e in data['files'] if e['path'] != 'artisan']
        variants.append(data)
        for changes in ({'schema': 2}, {'schema': True}, {'unexpected': 'value'},
                        {'source': {'commit': 'invalid', 'clean': True}},
                        {'source': {'commit': self.commit, 'clean': False}}):
            data = copy.deepcopy(original)
            data.update(changes)
            variants.append(data)
        for data in variants:
            with self.subTest(index=variants.index(data)):
                self.save_manifest(data)
                with self.assertRaises(release.ArtifactError):
                    self.verify()

    def test_manifest_duplicate_json_keys_rejected(self):
        self.build()
        path = self.output / 'release-manifest.json'
        data = path.read_text().rstrip()
        path.write_text(data[:-1] + ', "schema": 1}')
        with self.assertRaisesRegex(release.ArtifactError, 'Duplicate JSON'):
            self.verify()

    def test_declared_oversize_member_rejected_before_body(self):
        self.build()
        member = tarfile.TarInfo('public/logo.svg')
        member.size = release.MAX_TOTAL_BYTES + 1
        (self.output / 'release.tar.gz').write_bytes(gzip.compress(member.tobuf(format=tarfile.USTAR_FORMAT), mtime=0))
        self.refresh_archive_digest()
        with self.assertRaisesRegex(release.ArtifactError, 'metadata mismatch'):
            self.verify()

    def test_limits_cover_file_count_input_size_json_and_trailing_padding(self):
        with patch.object(release, 'MAX_FILES', 2), self.assertRaisesRegex(release.ArtifactError, 'file'):
            self.build()
        self.put('public/build/assets/large.js', b'x' * 1025)
        with patch.object(release, 'MAX_FILE_BYTES', 1024), self.assertRaisesRegex(release.ArtifactError, 'size limit'):
            self.build()
        (self.root / 'public/build/assets/large.js').unlink()
        self.build()
        with patch.object(release, 'MAX_JSON_BYTES', 8), self.assertRaisesRegex(release.ArtifactError, 'JSON'):
            self.verify()
        path = self.output / 'release.tar.gz'
        path.write_bytes(path.read_bytes() + gzip.compress(b'\0' * (tarfile.RECORDSIZE + 1), mtime=0))
        self.refresh_archive_digest()
        with self.assertRaisesRegex(release.ArtifactError, 'trailing'):
            self.verify()

    def test_unaligned_or_nonzero_tar_trailing_data_is_rejected(self):
        self.build()
        path = self.output / 'release.tar.gz'
        original = path.read_bytes()
        for suffix in (b'\0', b'unexpected trailing entry'):
            with self.subTest(suffix=suffix):
                path.write_bytes(original + gzip.compress(suffix, mtime=0))
                self.refresh_archive_digest()
                with self.assertRaisesRegex(release.ArtifactError, 'trailing'):
                    self.verify()

    def test_archive_member_permissions_are_verified(self):
        self.build()
        items = self.archive_items()
        items[0][0].mode = 0o777
        self.repack(items)
        with self.assertRaisesRegex(release.ArtifactError, 'metadata mismatch'):
            self.verify()

    def test_sensitive_or_unexpected_header_is_rejected_without_reading_body(self):
        self.build()
        for name in ('app/.env', 'public/uploads/customer.json', 'app/Unexpected.php'):
            with self.subTest(name=name):
                member = tarfile.TarInfo(name)
                member.size = release.MAX_TOTAL_BYTES
                (self.output / 'release.tar.gz').write_bytes(gzip.compress(
                    member.tobuf(format=tarfile.USTAR_FORMAT), mtime=0))
                self.refresh_archive_digest()
                with self.assertRaisesRegex(release.ArtifactError, 'Unexpected'):
                    self.verify()

    def test_verify_rejects_symlink_container_files(self):
        self.build()
        for filename in ('release.tar.gz', 'release-manifest.json'):
            with self.subTest(filename=filename):
                path = self.output / filename
                real = self.base / filename
                path.rename(real)
                path.symlink_to(real)
                with self.assertRaises(OSError):
                    self.verify()
                path.unlink()
                real.rename(path)

    def test_cli_errors_are_bounded_and_never_echo_untrusted_content(self):
        self.build()
        manifest = self.output / 'release-manifest.json'
        manifest.write_text('SYNTHETIC-SECRET-' * 10000)
        code, stdout, stderr = self.run_cli('verify', '--archive', str(self.output / 'release.tar.gz'),
                                          '--manifest', str(manifest))
        self.assertEqual((code, stdout), (1, ''))
        self.assertLess(len(stderr), 240)
        self.assertNotIn('SYNTHETIC', stderr)
        self.assertNotIn('Traceback', stderr)
        code, _, stderr = self.run_cli('invalid-' * 10000)
        self.assertEqual(code, 1)
        self.assertLess(len(stderr), 240)

    def test_verify_refuses_sensitive_cli_filenames_without_opening_them(self):
        for name in ('.env', 'auth.json', 'private.key'):
            with self.subTest(name=name), patch.object(release, 'open_input') as opened:
                code, _, _ = self.run_cli('verify', '--archive', str(self.base / 'archive.tar.gz'),
                                         '--manifest', str(self.base / name))
                self.assertEqual(code, 1)
                opened.assert_not_called()

    def test_sha256_git_object_format_is_supported(self):
        self.storefront_aliases()
        self.commit = 'a' * 64
        for name, (mode, _) in self.tracked.items():
            data = os.readlink(self.root / name).encode() if mode == '120000' else (self.root / name).read_bytes()
            oid = hashlib.sha256(b'blob ' + str(len(data)).encode() + b'\0' + data).hexdigest()
            self.tracked[name] = (mode, oid)
        manifest = self.build()
        self.assertEqual(manifest['source']['commit'], self.commit)
        self.verify()


if __name__ == '__main__':
    unittest.main(verbosity=2)
