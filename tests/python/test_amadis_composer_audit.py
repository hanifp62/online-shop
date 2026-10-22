"""Guarded composer-audit capability of scripts/amadis/launch.py, checked without Composer."""
import importlib.util
import json
from pathlib import Path
import secrets
import shutil
import sys
import unittest
from unittest.mock import patch

REPO = Path(__file__).resolve().parents[2]
LAUNCH = REPO / 'scripts/amadis/launch.py'
BASE = Path('/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode')
AUDIT = ['composer', 'audit', '--locked', '--format=json', '--no-interaction', '--no-ansi']


class ComposerAuditTests(unittest.TestCase):
    def setUp(self):
        self.root = BASE / ('amadis-' + secrets.token_hex(12))
        self.root.mkdir(mode=0o700)
        self.addCleanup(shutil.rmtree, self.root, True)
        (self.root / 'identity.json').write_text(json.dumps({'root': str(self.root.resolve()),
            'database': str(self.root.resolve() / 'database.sqlite'), 'repo': str(REPO)}))

    def run_main(self, *args, root=None, mutate=None, status=0):
        spec = importlib.util.spec_from_file_location('amadis_launch_audit', LAUNCH)
        launch = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(launch)
        calls = []

        def capture(root, label, argv, env):
            calls.append((label, argv, env))
            if label == 'composer-audit' and mutate:
                mutate(launch)
            return status if label == 'composer-audit' else 0
        launch.capture = capture
        old = sys.argv, launch.os.environ.get('AMADIS_ROOT')
        sys.argv = ['launch.py', *args]
        launch.os.environ['AMADIS_ROOT'] = str(root or self.root)
        try:
            return launch.main(), calls
        finally:
            sys.argv = old[0]
            if old[1] is None:
                launch.os.environ.pop('AMADIS_ROOT')
            else:
                launch.os.environ['AMADIS_ROOT'] = old[1]
            if launch.lock:
                launch.lock.close()

    def test_runs_only_fixed_argv_in_isolated_env(self):
        status, calls = self.run_main('composer-audit', '--locked', '--format=json')
        self.assertEqual(status, 0)
        label, argv, env = calls[-1]
        self.assertEqual((label, argv), ('composer-audit', AUDIT))
        self.assertEqual(env['AMADIS_ROOT'], str(self.root.resolve()))
        self.assertEqual(env['HOME'], str(self.root.resolve()))
        self.assertEqual(env['DB_DATABASE'], str(self.root.resolve() / 'database.sqlite'))

    def test_raw_audit_returncode_is_propagated(self):
        status, _ = self.run_main('composer-audit', '--locked', '--format=json', status=1)
        self.assertEqual(status, 1)

    def test_refuses_any_other_arguments_before_bootstrap(self):
        for args in (['composer-audit'], ['composer-audit', '--locked'],
                     ['composer-audit', '--locked', '--format=json', '--no-plugins'],
                     ['composer-audit', '--format=json', '--locked'],
                     ['composer-audit', '--locked', '--format=table']):
            with self.subTest(args=args), self.assertRaises(SystemExit) as cm:
                self.run_main(*args)
            self.assertIn('usage: launch.sh composer-audit --locked --format=json', str(cm.exception))

    def test_refuses_root_outside_approved_base(self):
        with self.assertRaises(SystemExit) as cm:
            self.run_main('composer-audit', '--locked', '--format=json', root=REPO)
        self.assertIn('AMADIS_ROOT must be a real direct child of approved root', str(cm.exception))

    def test_refuses_identity_mismatch(self):
        (self.root / 'identity.json').write_text(json.dumps({'root': '/elsewhere', 'database': '/elsewhere/db'}))
        with self.assertRaises(SystemExit) as cm:
            self.run_main('composer-audit', '--locked', '--format=json')
        self.assertIn('root/database identity mismatch', str(cm.exception))

    def test_refuses_repo_identity_mismatch(self):
        (self.root / 'identity.json').write_text(json.dumps({'root': str(self.root),
            'database': str(self.root / 'database.sqlite'), 'repo': '/elsewhere'}))
        with self.assertRaises(SystemExit) as cm:
            self.run_main('composer-audit', '--locked', '--format=json')
        self.assertIn('repository identity and cwd', str(cm.exception))

    def test_refuses_wrong_cwd(self):
        with patch.object(Path, 'cwd', return_value=BASE), self.assertRaises(SystemExit) as cm:
            self.run_main('composer-audit', '--locked', '--format=json')
        self.assertIn('repository identity and cwd', str(cm.exception))

    def test_refuses_when_manifests_change(self):
        def mutate(launch):
            fingerprint = launch._pdf_fingerprint
            launch._pdf_fingerprint = lambda: dict(fingerprint(), **{'composer.lock': 'changed'})
        with self.assertRaises(SystemExit) as cm:
            self.run_main('composer-audit', '--locked', '--format=json', mutate=mutate)
        self.assertIn('composer audit changed', str(cm.exception))


class RawCommandTests(unittest.TestCase):
    def test_raw_run_still_blocks_composer(self):
        spec = importlib.util.spec_from_file_location('amadis_launch_raw_denied', LAUNCH)
        launch = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(launch)
        with patch.object(sys, 'argv', ['launch.py', 'run', 'composer', 'audit']), \
             patch.object(launch, 'capture') as capture, \
             patch.object(launch, 'prepare') as prepare:
            with self.assertRaises(SystemExit):
                launch.main()
            capture.assert_not_called()
            prepare.assert_not_called()


if __name__ == '__main__':
    unittest.main(verbosity=2)
