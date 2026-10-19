import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import subprocess
import sys
import unittest
from unittest import mock

REPO = Path(__file__).resolve().parents[2]
BASE = Path('/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode')
EXPORT = REPO / 'scripts/amadis/export_evidence.py'

spec = importlib.util.spec_from_file_location('amadis_launch', REPO / 'scripts/amadis/launch.py')
launch = importlib.util.module_from_spec(spec)
spec.loader.exec_module(launch)
if str(REPO / 'scripts/amadis') not in sys.path:
    sys.path.insert(0, str(REPO / 'scripts/amadis'))

SECRET = 'unique-export-sentinel-7d3a9e'
MARKER = 'visible-export-marker'


class ExportTests(unittest.TestCase):
    def setUp(self):
        self.root = BASE / ('amadis-' + secrets.token_hex(12))
        self.root.mkdir(mode=0o700)
        self.addCleanup(shutil.rmtree, self.root, ignore_errors=True)
        self.destination = BASE / ('export-' + secrets.token_hex(12))
        self.destination.mkdir(mode=0o700)
        self.addCleanup(shutil.rmtree, self.destination, ignore_errors=True)
        launch.prepare(self.root)
        launch.write_new(self.root, 'identity.json', json.dumps({
            'root': str(self.root), 'database': str(self.root / 'database.sqlite'), 'repo': str(REPO)}))

    def real_capture(self):
        """Run a real child process through launch.capture; no mocks, no fake subprocess."""
        script = (f'import sys; print("APP_KEY={SECRET} {MARKER}"); '
                  f'print("API_TOKEN=Bearer {SECRET}", file=sys.stderr)')
        with open(self.root / '.lock', 'a') as held:
            original = launch.lock
            launch.lock = held
            try:
                status = launch.capture(self.root, 'sentinel', [sys.executable, '-c', script], {})
            finally:
                launch.lock = original
        self.assertEqual(status, 0)
        metadata = [p.name for p in (self.root / 'evidence').iterdir() if p.name.endswith('.json')]
        self.assertEqual(len(metadata), 1)
        return re.fullmatch(r'(\d{8}T\d{6}Z-sentinel-[0-9a-f]{6})\.json', metadata[0])[1]

    def run_export(self, stem, destination=None):
        argv = [sys.executable, str(EXPORT), str(self.root), stem, str(destination or self.destination)]
        return subprocess.run(argv, cwd=REPO, capture_output=True, text=True, timeout=60)

    def test_cli_export_copies_exactly_the_capture_triplet_and_manifest(self):
        stem = self.real_capture()
        expected = {stem + suffix for suffix in ('.json', '.stdout', '.stderr')}
        result = self.run_export(stem)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual({p.name for p in self.destination.iterdir()}, expected | {'manifest.json'})
        manifest = json.loads((self.destination / 'manifest.json').read_text())
        self.assertEqual(set(manifest['files']), expected)
        self.assertEqual(manifest['stem'], stem)

    def test_exported_bundle_hides_sentinel_keeps_marker_and_matches_manifest_hashes(self):
        stem = self.real_capture()
        self.assertEqual(self.run_export(stem).returncode, 0)
        manifest = json.loads((self.destination / 'manifest.json').read_text())
        # Enumerate and read every byte of the bundle, manifest included.
        for path in sorted(self.destination.iterdir()):
            data = path.read_bytes()
            self.assertNotIn(SECRET.encode(), data, path.name)
            self.assertEqual(path.stat().st_mode & 0o777, 0o600, path.name)
            if path.name != 'manifest.json':
                self.assertEqual(manifest['files'][path.name], hashlib.sha256(data).hexdigest())
        self.assertIn(MARKER, (self.destination / (stem + '.stdout')).read_text())
        self.assertIn('[REDACTED]', (self.destination / (stem + '.stderr')).read_text())

    def test_planted_forbidden_files_are_never_exported(self):
        stem = self.real_capture()
        planted = {stem + '.extra': f'APP_KEY={SECRET}', 'other.stdout': SECRET,
                   stem + '.stdout.bak': SECRET, 'x.log': SECRET}
        for name, text in planted.items():
            launch.write_new(self.root, 'evidence/' + name, text)
        self.assertEqual(self.run_export(stem).returncode, 0)
        exported = {p.name for p in self.destination.iterdir()}
        self.assertEqual(exported & set(planted), set())
        combined = b''.join(p.read_bytes() for p in self.destination.iterdir())
        self.assertNotIn(SECRET.encode(), combined)

    def test_path_tricks_and_bad_stems_are_refused(self):
        self.real_capture()
        for stem in ['../identity.json', 'a/b', '20261013T000000Z-sentinel-abc', '',
                     '20261013T000000Z-sentinel-abcdef\n', '.', '*']:
            with self.subTest(stem=stem):
                result = self.run_export(stem)
                self.assertEqual(result.returncode, 1)
                self.assertEqual(list(self.destination.iterdir()), [])

    def test_missing_capture_triplet_member_is_refused(self):
        stem = self.real_capture()
        (self.root / 'evidence' / (stem + '.stderr')).unlink()
        result = self.run_export(stem)
        self.assertEqual(result.returncode, 1)
        self.assertIn('refused', result.stderr)
        self.assertEqual(list(self.destination.iterdir()), [])

    def test_symlinked_capture_file_is_not_followed(self):
        stem = self.real_capture()
        outside = self.root / 'outside-secret.txt'
        outside.write_text(SECRET)
        target = self.root / 'evidence' / (stem + '.stdout')
        target.unlink()
        target.symlink_to(outside)
        result = self.run_export(stem)
        self.assertEqual(result.returncode, 1)
        self.assertEqual(list(self.destination.iterdir()), [])

    def test_identity_mismatch_is_refused(self):
        stem = self.real_capture()
        (self.root / 'identity.json').unlink()
        launch.write_new(self.root, 'identity.json', json.dumps({
            'root': '/elsewhere', 'database': str(self.root / 'database.sqlite'), 'repo': str(REPO)}))
        result = self.run_export(stem)
        self.assertEqual(result.returncode, 1)
        self.assertIn('refused', result.stderr)

    def test_non_empty_or_world_readable_destination_is_refused(self):
        stem = self.real_capture()
        (self.destination / 'pre-existing').write_text('x')
        self.assertEqual(self.run_export(stem).returncode, 1)
        (self.destination / 'pre-existing').unlink()
        self.destination.chmod(0o755)
        self.addCleanup(self.destination.chmod, 0o700)
        self.assertEqual(self.run_export(stem).returncode, 1)

    def test_destination_outside_approved_base_is_refused(self):
        stem = self.real_capture()
        outside = REPO / ('.amadis-export-test-' + secrets.token_hex(6))
        outside.mkdir(mode=0o700)
        self.addCleanup(shutil.rmtree, outside, ignore_errors=True)
        result = self.run_export(stem, destination=outside)
        self.assertEqual(result.returncode, 1)
        self.assertEqual(list(outside.iterdir()), [])

    def test_destination_inside_root_is_refused(self):
        stem = self.real_capture()
        inside = self.root / 'evidence'
        self.assertEqual(self.run_export(stem, destination=inside).returncode, 1)

    def test_export_leaves_source_evidence_unchanged(self):
        stem = self.real_capture()
        before = {p.name: (hashlib.sha256(p.read_bytes()).hexdigest(), p.stat().st_mode & 0o777)
                  for p in sorted((self.root / 'evidence').iterdir())}
        self.assertEqual(self.run_export(stem).returncode, 0)
        after = {p.name: (hashlib.sha256(p.read_bytes()).hexdigest(), p.stat().st_mode & 0o777)
                 for p in sorted((self.root / 'evidence').iterdir())}
        self.assertEqual(before, after)

    def test_wrong_cwd_is_refused(self):
        stem = self.real_capture()
        argv = [sys.executable, str(EXPORT), str(self.root), stem, str(self.destination)]
        result = subprocess.run(argv, cwd=BASE, capture_output=True, text=True, timeout=60)
        self.assertEqual(result.returncode, 1)
        self.assertEqual(list(self.destination.iterdir()), [])

    def test_hardlinked_capture_file_is_refused(self):
        stem = self.real_capture()
        os.link(self.root / 'evidence' / (stem + '.stdout'), self.root / 'evidence' / 'alias')
        self.assertEqual(self.run_export(stem).returncode, 1)
        self.assertEqual(list(self.destination.iterdir()), [])

    def test_raw_secret_in_capture_file_is_resanitized_on_export(self):
        stem = self.real_capture()
        path = self.root / 'evidence' / (stem + '.stdout')
        path.write_text(f'PASSWORD={SECRET} {MARKER}\n')
        self.assertEqual(self.run_export(stem).returncode, 0)
        exported = (self.destination / (stem + '.stdout')).read_text()
        self.assertNotIn(SECRET, exported)
        self.assertIn(MARKER, exported)
        manifest = json.loads((self.destination / 'manifest.json').read_text())
        self.assertEqual(manifest['source_sha256'][stem + '.stdout'], hashlib.sha256(path.read_bytes()).hexdigest())

    def test_oversized_capture_file_is_refused(self):
        stem = self.real_capture()
        path = self.root / 'evidence' / (stem + '.stdout')
        with open(path, 'ab') as handle:
            handle.truncate(16 * 1024 * 1024 + 1)
        self.assertEqual(self.run_export(stem).returncode, 1)
        self.assertEqual(list(self.destination.iterdir()), [])

    def load_exporter(self):
        spec = importlib.util.spec_from_file_location('amadis_export_' + secrets.token_hex(4), EXPORT)
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        return module

    def failing_writes(self, module, fail_at, before_fail=None):
        """Make the fail_at-th output open (1-based) raise; inputs ('rb') are untouched."""
        real = module.os.fdopen
        count = {'n': 0}
        def fdopen(fd, mode='r', *args, **kwargs):
            if mode == 'wb':
                count['n'] += 1
                if count['n'] == fail_at:
                    if before_fail:
                        before_fail()
                    os.close(fd)
                    raise OSError('injected write failure')
            return real(fd, mode, *args, **kwargs)
        return mock.patch.object(module.os, 'fdopen', fdopen)

    def test_write_failure_on_later_file_rolls_back_all_created_files(self):
        stem = self.real_capture()
        for fail_at in (2, 3, 4):
            with self.subTest(fail_at=fail_at):
                module = self.load_exporter()
                with self.failing_writes(module, fail_at), self.assertRaises(OSError):
                    module.export(str(self.root), stem, str(self.destination))
                self.assertEqual(list(self.destination.iterdir()), [])

    def test_rollback_preserves_entry_replaced_by_another_inode(self):
        stem = self.real_capture()
        module = self.load_exporter()
        first = stem + '.json'

        def replace_first():
            (self.destination / first).unlink()
            (self.destination / first).write_text('replacement')

        with self.failing_writes(module, 3, replace_first), self.assertRaises(OSError):
            module.export(str(self.root), stem, str(self.destination))
        self.assertEqual({p.name for p in self.destination.iterdir()}, {first})
        self.assertEqual((self.destination / first).read_text(), 'replacement')

    def test_cleanup_error_does_not_stop_remaining_cleanup_or_mask_original(self):
        stem = self.real_capture()
        module = self.load_exporter()
        real_unlink = module.os.unlink
        calls = []

        def unlink(name, *args, **kwargs):
            calls.append(name)
            if len(calls) == 1:
                raise PermissionError('injected cleanup failure')
            return real_unlink(name, *args, **kwargs)

        with self.failing_writes(module, 4), mock.patch.object(module.os, 'unlink', unlink):
            with self.assertRaises(OSError) as caught:
                module.export(str(self.root), stem, str(self.destination))
        self.assertEqual(str(caught.exception), 'injected write failure')
        self.assertIn('export rollback incomplete for: ' + calls[0], caught.exception.__notes__)
        self.assertEqual(len(calls), 4)
        self.assertEqual({p.name for p in self.destination.iterdir()}, {calls[0]})

    def test_capture_file_at_exact_size_cap_is_accepted(self):
        stem = self.real_capture()
        module = self.load_exporter()
        path = self.root / 'evidence' / (stem + '.stdout')
        with open(path, 'r+b') as handle:
            handle.truncate(module.MAX_BYTES)
        self.assertEqual(self.run_export(stem).returncode, 0)

    def test_repeated_export_into_same_destination_is_refused(self):
        stem = self.real_capture()
        self.assertEqual(self.run_export(stem).returncode, 0)
        self.assertEqual(self.run_export(stem).returncode, 1)


if __name__ == '__main__':
    unittest.main()
