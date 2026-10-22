import errno
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
from contextlib import nullcontext
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
                    with real(fd, mode, *args, **kwargs):
                        if before_fail:
                            before_fail()
                        raise OSError('injected write failure')
            return real(fd, mode, *args, **kwargs)
        return mock.patch.object(module.os, 'fdopen', fdopen)

    def track_output_fds(self, module):
        """Observe without duplicating/pinning FDs; reclaim leaks only after test assertions."""
        real_open, real_fstat, real_close = module.os.open, module.os.fstat, module.os.close
        outputs = {}

        def cleanup(fd, identity):
            try:
                info = real_fstat(fd)
            except OSError as error:
                if error.errno != errno.EBADF:
                    raise
            else:
                if (info.st_dev, info.st_ino) == identity:
                    real_close(fd)

        def opened(name, flags, *args, **kwargs):
            fd = real_open(name, flags, *args, **kwargs)
            if flags & os.O_CREAT:
                info = real_fstat(fd)
                identity = (info.st_dev, info.st_ino)
                outputs[name] = (fd, identity)
                self.addCleanup(cleanup, fd, identity)
            return fd

        return mock.patch.object(module.os, 'open', opened), outputs

    def assert_output_fds_closed(self, outputs):
        for fd, _ in outputs.values():
            with self.assertRaises(OSError) as caught:
                os.fstat(fd)
            self.assertEqual(caught.exception.errno, errno.EBADF)

    def test_output_fds_stay_pinned_until_commit_and_close_after_success(self):
        stem = self.real_capture()
        module = self.load_exporter()
        tracking, outputs = self.track_output_fds(module)
        real_listdir = module.os.listdir
        commits = []

        def listdir(fd):
            if outputs:
                self.assertEqual(len(outputs), 4)
                for output_fd, identity in outputs.values():
                    info = os.fstat(output_fd)
                    self.assertEqual((info.st_dev, info.st_ino), identity)
                    self.assertEqual(info.st_nlink, 1)
                    self.assertFalse(os.get_inheritable(output_fd))
                commits.append(True)
            return real_listdir(fd)

        with tracking, mock.patch.object(module.os, 'listdir', listdir):
            files = module.export(str(self.root), stem, str(self.destination))
            self.assert_output_fds_closed(outputs)
        self.assertEqual(commits, [True])
        self.assertEqual(files, sorted(outputs))

    def test_output_fds_close_on_open_fstat_fdopen_and_partial_write_failures(self):
        stem = self.real_capture()

        def exercise(operation, fail_at):
            module = self.load_exporter()
            tracking, outputs = self.track_output_fds(module)
            failure = OSError(errno.EIO, 'injected output ' + operation + ' failure')
            with tracking:
                real_open, real_fstat, real_fdopen = module.os.open, module.os.fstat, module.os.fdopen

                def opened(name, flags, *args, **kwargs):
                    if operation == 'open' and flags & os.O_CREAT and len(outputs) + 1 == fail_at:
                        raise failure
                    return real_open(name, flags, *args, **kwargs)

                def fstat(fd):
                    if operation == 'fstat' and len(outputs) == fail_at and fd == next(reversed(outputs.values()))[0]:
                        raise failure
                    return real_fstat(fd)

                def fdopen(fd, mode='r', *args, **kwargs):
                    if mode == 'wb' and len(outputs) == fail_at:
                        if operation == 'fdopen':
                            raise failure
                        if operation == 'write':
                            stream = real_fdopen(fd, mode, *args, **kwargs)
                            writer = mock.MagicMock()
                            writer.__enter__.return_value = writer
                            writer.__exit__.side_effect = stream.__exit__

                            def partial_write(content):
                                stream.write(content[:1])
                                raise failure

                            writer.write.side_effect = partial_write
                            return writer
                    return real_fdopen(fd, mode, *args, **kwargs)

                with mock.patch.object(module.os, 'open', opened), \
                     mock.patch.object(module.os, 'fstat', fstat), \
                     mock.patch.object(module.os, 'fdopen', fdopen), self.assertRaises(OSError) as caught:
                    module.export(str(self.root), stem, str(self.destination))
                self.assertIs(caught.exception, failure)
                self.assertEqual(len(outputs), fail_at - (operation == 'open'))
                self.assert_output_fds_closed(outputs)
            if operation == 'fstat':
                # Unknown identity must not authorize rollback deletion, even with a pinned fd.
                unverified = next(reversed(outputs))
                self.assertEqual({p.name for p in self.destination.iterdir()}, {unverified})
                (self.destination / unverified).unlink()
            else:
                self.assertEqual(list(self.destination.iterdir()), [])

        for operation in ('open', 'fstat', 'fdopen', 'write'):
            for fail_at in (1, 3, 4):
                with self.subTest(operation=operation, fail_at=fail_at):
                    exercise(operation, fail_at)

    def test_output_close_failure_preserves_original_and_closes_remaining_fds(self):
        stem = self.real_capture()

        def exercise(fail_write):
            module = self.load_exporter()
            tracking, outputs = self.track_output_fds(module)
            real_close = module.os.close
            close_error = OSError(errno.EIO, 'injected descriptor close failure')
            closed = []

            def close(fd):
                real_close(fd)
                if fd in {item[0] for item in outputs.values()}:
                    closed.append(fd)
                    if len(closed) == 1:
                        raise close_error

            with tracking, (self.failing_writes(module, 4) if fail_write else nullcontext()), \
                 mock.patch.object(module.os, 'close', close), self.assertRaises(OSError) as caught:
                module.export(str(self.root), stem, str(self.destination))
            self.assertEqual(len(outputs), 4)
            self.assertEqual(len(closed), 4)
            self.assertEqual(set(closed), {item[0] for item in outputs.values()})
            self.assert_output_fds_closed(outputs)
            if fail_write:
                self.assertEqual(str(caught.exception), 'injected write failure')
                self.assertIn('export output descriptor close failed', caught.exception.__notes__)
                self.assertEqual(list(self.destination.iterdir()), [])
            else:
                self.assertIs(caught.exception, close_error)
                self.assertEqual({p.name for p in self.destination.iterdir()}, set(outputs))

        for fail_write in (True, False):
            with self.subTest(fail_write=fail_write):
                exercise(fail_write)

    def test_output_inode_stays_pinned_after_unlink_recreate_through_rollback(self):
        stem = self.real_capture()
        module = self.load_exporter()
        first = stem + '.json'
        tracking, outputs = self.track_output_fds(module)
        observed = []
        real_stat = module.os.stat

        def observe():
            info = os.fstat(outputs[first][0])
            observed.append(((info.st_dev, info.st_ino), info.st_nlink))

        def replace_first():
            (self.destination / first).unlink()
            (self.destination / first).write_text('replacement')
            observe()

        def rollback_stat(name, *args, **kwargs):
            if name == first and kwargs.get('dir_fd') is not None:
                try:
                    observe()
                except OSError as error:
                    observed.append(error.errno)
            return real_stat(name, *args, **kwargs)

        with tracking, self.failing_writes(module, 2, replace_first), \
             mock.patch.object(module.os, 'stat', rollback_stat), self.assertRaises(OSError) as caught:
            module.export(str(self.root), stem, str(self.destination))
        self.assertEqual(str(caught.exception), 'injected write failure')
        self.assertEqual(observed, [(outputs[first][1], 0)] * 2)
        self.assert_output_fds_closed(outputs)
        self.assertEqual({p.name for p in self.destination.iterdir()}, {first})
        self.assertEqual((self.destination / first).read_text(), 'replacement')

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
