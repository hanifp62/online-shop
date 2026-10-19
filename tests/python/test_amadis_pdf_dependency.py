import copy
import importlib.util
import tempfile
import unittest
from pathlib import Path
from unittest.mock import MagicMock, patch

spec = importlib.util.spec_from_file_location('amadis_launch_pdf', Path(__file__).resolve().parents[2] / 'scripts/amadis/launch.py')
launch = importlib.util.module_from_spec(spec)
spec.loader.exec_module(launch)


class AmadisPdfDependencyTests(unittest.TestCase):
    def test_cli_validates_args_before_bootstrap(self):
        for args in (['pdf-dependency'], ['pdf-dependency', 'install', '--no-scripts']):
            with self.subTest(args=args), patch.object(launch, 'prepare', side_effect=AssertionError('bootstrap reached')):
                with patch('sys.argv', ['launch.py', *args]), self.assertRaises(SystemExit) as cm:
                    launch.main()
                self.assertIn('usage: launch.sh pdf-dependency dry-run|install', str(cm.exception))

    def test_hooks_are_exact(self):
        _, _, names = launch._pdf_validate_project()
        self.assertIn('laravel/framework', names)
        with patch.object(launch, '_pdf_json', side_effect=[
            {'scripts': {'post-autoload-dump': ['Changed::hook']}}, {}
        ]), self.assertRaises(SystemExit) as cm:
            launch._pdf_validate_project()
        self.assertIn('composer hook changed', str(cm.exception))

    def test_rejects_plugin_not_allowlisted(self):
        installed = {'packages': [{'name': 'evil/plugin', 'type': 'composer-plugin'}]}
        with patch.object(launch, '_pdf_json', side_effect=[
            {'scripts': launch.EXPECTED_HOOKS, 'config': {'allow-plugins': {}}},
            {'packages': [], 'packages-dev': []}, installed
        ]), self.assertRaises(SystemExit) as cm:
            launch._pdf_validate_project()
        self.assertIn('installed Composer plugin is not allowlisted', str(cm.exception))

    def test_minifier_expands_and_unsets(self):
        versions = [
            {'version_normalized': '3.1.6.0', 'name': 'dompdf/dompdf', 'license': ['LGPL-2.1'], 'dist': {'reference': 'a' * 40}},
            {'version_normalized': '3.1.7.0', 'name': 'dompdf/dompdf', 'license': ['MIT'], 'dist': {'reference': 'b' * 40}},
            {'version_normalized': '3.1.8.0', 'name': 'dompdf/dompdf', 'license': '__unset'},
        ]
        expanded = launch._pdf_expand(versions)
        self.assertEqual(expanded[0]['license'], ['LGPL-2.1'])
        self.assertEqual(expanded[1]['license'], ['MIT'])
        self.assertNotIn('license', expanded[2])
        self.assertEqual(expanded[1]['name'], 'dompdf/dompdf')

    @staticmethod
    def _release(name):
        pin = launch.PDF_PINS[name]
        slug, ref = pin['slug'], pin['ref']
        return {'name': name, 'version': pin['version'], 'type': 'library', 'license': pin['license'],
                'source': {'type': 'git', 'url': f'https://github.com/{slug}.git', 'reference': ref},
                'dist': {'type': 'zip', 'url': f'https://api.github.com/repos/{slug}/zipball/{ref}', 'reference': ref, 'shasum': ''},
                'require': {'php': '^8.1'}}

    def _candidate(self):
        old_json = {'require': {'php': '^8.2'}}
        old_lock = {'packages': [{'name': 'a/b', 'version': '1.0.0'}], 'packages-dev': [], 'content-hash': 'old',
                    'platform': {'php': '^8.2'}}
        releases = {n: self._release(n) for n in launch.PDF_PINS}
        new_json = {'require': {'php': '^8.2', 'dompdf/dompdf': '3.1.6'}}
        new_lock = copy.deepcopy(dict(old_lock, **{'content-hash': 'new',
                                                   'packages': old_lock['packages'] + list(releases.values())}))
        return old_json, old_lock, new_json, new_lock, releases

    def test_candidate_accepts_exact_closure(self):
        self.assertEqual(launch._pdf_validate_candidate(*self._candidate()), sorted(launch.PDF_CLOSURE))

    def test_candidate_rejects_drift(self):
        cases = {
            'existing package moved': lambda j, l: l['packages'][0].update(version='2.0.0'),
            'extra package': lambda j, l: l['packages'].append({'name': 'x/y', 'version': '1.0.0'}),
            'platform changed': lambda j, l: l.update(platform={'php': '^8.3'}),
            'manifest drift': lambda j, l: j['require'].update({'x/y': '*'}),
            'source url swapped': lambda j, l: l['packages'][1]['source'].update(url='https://evil.example/x.git'),
            'short ref': lambda j, l: l['packages'][1]['dist'].update(reference='abc'),
            'plugin type': lambda j, l: l['packages'][1].update(type='composer-plugin'),
        }
        for label, mutate in cases.items():
            with self.subTest(label):
                old_json, old_lock, new_json, new_lock, releases = self._candidate()
                mutate(new_json, new_lock)
                with self.assertRaises(SystemExit):
                    launch._pdf_validate_candidate(old_json, old_lock, new_json, new_lock, releases)

    def setUp(self):
        launch.lock = MagicMock()
        launch.lock.fileno.return_value = 90

    def tearDown(self):
        launch.lock = None

    def test_release_rejects_unapproved_requirement(self):
        release = self._release('dompdf/dompdf')
        release['require']['evil/pkg'] = '*'
        with self.assertRaises(SystemExit):
            launch._pdf_check_release('dompdf/dompdf', release, launch.PDF_PINS['dompdf/dompdf'])

    def test_install_requires_candidate_generation(self):
        repo = Path(tempfile.mkdtemp()).resolve()
        with patch.object(launch, 'REPO', repo), \
             patch.object(launch, '_pdf_validate_project', side_effect=SystemExit('candidate guard')), \
             patch('subprocess.run') as run, self.assertRaises(SystemExit) as cm:
            launch.pdf_dependency(Path(tempfile.mkdtemp()), {}, 'install')
        run.assert_not_called()
        self.assertIn('candidate guard', str(cm.exception))

    def test_candidate_hook_context_fails_before_composer(self):
        repo = Path(tempfile.mkdtemp()).resolve()
        (repo / 'vendor/composer').mkdir(parents=True)
        (repo / 'vendor/composer/installed.json').write_text('{"packages": []}')
        root = Path(tempfile.mkdtemp())
        with patch.object(launch, 'REPO', repo), \
             patch.object(launch, '_pdf_validate_project', return_value=({}, {}, set())), \
             patch.object(launch, '_pdf_vendor_matches_lock', return_value=None), \
             patch.object(launch, '_pdf_fingerprint', return_value={}), \
             patch.object(launch, '_pdf_candidate_hook_guard', side_effect=SystemExit('hook context unavailable')), \
             patch('subprocess.run') as run, self.assertRaises(SystemExit) as cm:
            launch.pdf_dependency(root, {'PATH': '/usr/bin'}, 'dry-run')
        run.assert_not_called()
        self.assertIn('hook context unavailable', str(cm.exception))

    def test_transaction_rolls_back_manifests_and_vendor(self):
        root = Path(tempfile.mkdtemp())
        repo = Path(tempfile.mkdtemp()).resolve()
        (repo / 'vendor/composer').mkdir(parents=True)
        (repo / 'composer.json').write_bytes(b'{"a":1}')
        (repo / 'composer.lock').write_bytes(b'{"b":2}')
        (repo / 'vendor/composer/installed.json').write_bytes(b'{"packages":[]}')
        (repo / 'vendor/keep.php').write_bytes(b'<?php')
        candidate = {'composer.json': b'{"changed":true}', 'composer.lock': b'{"changed":true}'}

        def mutate_then_fail():
            (repo / 'vendor/keep.php').unlink()
            (repo / 'vendor/new.php').write_bytes(b'x')
            self.assertEqual((repo / 'composer.json').read_bytes(), candidate['composer.json'])
            raise RuntimeError('install failed')

        with patch.object(launch, 'REPO', repo):
            before = launch._pdf_fingerprint()
            tree = launch._pdf_tree(repo / 'vendor')
            with self.assertRaises(RuntimeError):
                launch._pdf_transaction(root, before, candidate, mutate_then_fail)
            self.assertEqual(launch._pdf_fingerprint(), before)
            self.assertEqual(launch._pdf_tree(repo / 'vendor'), tree)
        self.assertEqual((repo / 'composer.json').read_bytes(), b'{"a":1}')
        self.assertEqual((repo / 'composer.lock').read_bytes(), b'{"b":2}')
        self.assertTrue((repo / 'vendor/keep.php').exists())
        self.assertFalse((repo / 'vendor/new.php').exists())

    def test_transaction_rolls_back_verification_mismatch(self):
        root = Path(tempfile.mkdtemp())
        repo = Path(tempfile.mkdtemp()).resolve()
        (repo / 'vendor/composer').mkdir(parents=True)
        for name in ('composer.json', 'composer.lock', 'vendor/composer/installed.json'):
            (repo / name).write_bytes(b'{}')
        (repo / 'vendor/keep.php').write_bytes(b'original')

        def mismatch():
            (repo / 'composer.lock').write_bytes(b'{"unexpected":true}')
            (repo / 'vendor/composer/installed.json').write_bytes(b'{"unexpected":true}')
            raise SystemExit('verification mismatch')

        with patch.object(launch, 'REPO', repo):
            before = launch._pdf_fingerprint()
            vendor_before = launch._pdf_tree(repo / 'vendor')
            with self.assertRaises(SystemExit):
                launch._pdf_transaction(root, before, {'composer.json': b'{"new":true}',
                                                        'composer.lock': b'{"new":true}'}, mismatch)
            self.assertEqual(launch._pdf_fingerprint(), before)
            self.assertEqual(launch._pdf_tree(repo / 'vendor'), vendor_before)

    def test_transaction_rolls_back_partial_manifest_swap(self):
        root = Path(tempfile.mkdtemp())
        repo = Path(tempfile.mkdtemp()).resolve()
        (repo / 'vendor/composer').mkdir(parents=True)
        for name in ('composer.json', 'composer.lock', 'vendor/composer/installed.json'):
            (repo / name).write_bytes(b'{}')
        replace = launch.os.replace

        def interrupt(source, target):
            if source == repo / 'composer.lock':
                raise OSError('simulated second rename failure')
            return replace(source, target)

        with patch.object(launch, 'REPO', repo):
            before = launch._pdf_fingerprint()
            with patch.object(launch.os, 'replace', side_effect=interrupt), self.assertRaises(OSError):
                launch._pdf_transaction(root, before, {'composer.json': b'{"new":true}'}, lambda: None)
            self.assertEqual(launch._pdf_fingerprint(), before)
            self.assertTrue((repo / 'vendor/composer/installed.json').is_file())

    def test_transaction_rejects_changed_repo_before_mutation(self):
        root = Path(tempfile.mkdtemp())
        repo = Path(tempfile.mkdtemp()).resolve()
        (repo / 'vendor/composer').mkdir(parents=True)
        for name, data in (('composer.json', b'{}'), ('composer.lock', b'{}'), ('vendor/composer/installed.json', b'{}')):
            (repo / name).write_bytes(data)
        with patch.object(launch, 'REPO', repo):
            before = launch._pdf_fingerprint()
            (repo / 'composer.json').write_bytes(b'{"drift":1}')
            called = []
            with self.assertRaises(SystemExit):
                launch._pdf_transaction(root, before, {}, lambda: called.append(1))
        self.assertEqual(called, [])
        self.assertEqual((repo / 'composer.json').read_bytes(), b'{"drift":1}')
        self.assertEqual(list(repo.glob('.amadis-pdf-backup-*')), [])

    def test_archives_require_five_unambiguous_nonempty_sha256(self):
        cache = Path(tempfile.mkdtemp())
        files = cache / 'files'
        files.mkdir(parents=True)
        lock_entries = {}
        for name, pin in launch.PDF_PINS.items():
            pkg_dir = files / name.split('/')[0] / name.split('/')[1]
            pkg_dir.mkdir(parents=True)
            zip_path = pkg_dir / 'opaque-composer-cache-key.zip'
            with launch.zipfile.ZipFile(zip_path, 'w') as archive:
                archive.writestr('package/README.md', name)
            lock_entries[name] = {'dist': {'shasum': ''}}

        # Valid 5 archives
        archives = launch._pdf_archives(cache, lock_entries)
        self.assertEqual(len(archives), 5)
        for name, entry in archives.items():
            self.assertEqual(len(entry['local_sha256']), 64)
            self.assertIn('cache_path', entry)

        # Missing archive fails
        missing_cache = Path(tempfile.mkdtemp())
        with self.assertRaises(SystemExit) as cm:
            launch._pdf_archives(missing_cache, lock_entries)
        self.assertIn('missing or ambiguous', str(cm.exception))

        # Ambiguous archive fails
        extra_pkg = files / 'dompdf' / 'dompdf' / 'extra.zip'
        extra_pkg.write_bytes(b'extra')
        with self.assertRaises(SystemExit) as cm:
            launch._pdf_archives(cache, lock_entries)
        self.assertIn('missing or ambiguous', str(cm.exception))

    def test_candidate_generate_validate_bind(self):
        root = Path(tempfile.mkdtemp())
        repo = Path(tempfile.mkdtemp()).resolve()
        (repo / 'vendor/composer').mkdir(parents=True)
        (repo / 'vendor/composer/installed.json').write_text('{"packages": []}')
        old_json = {'require': {'php': '^8.2'}}
        old_lock = {'packages': [{'name': 'php', 'version': '8.2'}], 'packages-dev': [], 'content-hash': 'old'}
        additions = sorted(launch.PDF_CLOSURE)
        fake_res = MagicMock()
        fake_res.returncode = 0
        fake_res.stdout = ''
        fake_res.stderr = ''
        with patch.object(launch, 'REPO', repo), \
             patch.object(launch, '_pdf_validate_project', return_value=(old_json, old_lock, {'php'})), \
             patch.object(launch, '_pdf_fingerprint', return_value={'composer.json': 'x', 'composer.lock': 'y', 'vendor/composer/installed.json': 'z'}), \
             patch.object(launch, '_pdf_vendor_matches_lock', return_value=None), \
             patch.object(launch, '_pdf_metadata', return_value={'version': '3.1.6'}), \
             patch.object(launch, '_pdf_candidate_hook_guard', return_value=None), \
             patch.object(launch, '_pdf_run', return_value=(fake_res, 'stem')), \
             patch.object(launch, '_pdf_read_bytes', return_value=b'{}'), \
             patch.object(launch, '_pdf_validate_candidate', return_value=additions), \
             patch.object(launch, '_pdf_archives', return_value={}):
            self.assertEqual(launch.pdf_dependency(root, {'PATH': '/usr/bin'}, 'dry-run'), 0)


if __name__ == '__main__':
    unittest.main()
