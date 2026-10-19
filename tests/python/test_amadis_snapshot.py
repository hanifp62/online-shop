"""Read-only lifecycle snapshot capability of scripts/amadis/launch.py, checked without PHP."""
import importlib.util
import json
from pathlib import Path
import secrets
import shutil
import sys
import unittest

REPO = Path(__file__).resolve().parents[2]
LAUNCH = REPO / 'scripts/amadis/launch.py'
BASE = Path('/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode')


class SnapshotTests(unittest.TestCase):
    def setUp(self):
        self.root = BASE / ('amadis-snapshottest-' + secrets.token_hex(8))
        self.root.mkdir(mode=0o700)
        self.addCleanup(shutil.rmtree, self.root, True)
        (self.root / 'identity.json').write_text(json.dumps({'root': str(self.root.resolve()),
            'database': str(self.root.resolve() / 'database.sqlite')}))

    def run_main(self, *args):
        spec = importlib.util.spec_from_file_location('amadis_launch_snapshot', LAUNCH)
        launch = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(launch)
        calls = []
        launch.capture = lambda root, label, argv, env: calls.append((label, argv)) or 0
        old = sys.argv, launch.os.environ.get('AMADIS_ROOT')
        sys.argv = ['launch.py', *args]
        launch.os.environ['AMADIS_ROOT'] = str(self.root)
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

    def test_snapshot_runs_only_fixed_entry_point(self):
        status, calls = self.run_main('snapshot')
        self.assertEqual(status, 0)
        self.assertEqual(calls[-1], ('snapshot', ['php', 'scripts/amadis/artisan.php', 'amadis:lifecycle-snapshot']))

    def test_snapshot_refuses_arguments(self):
        with self.assertRaises(SystemExit):
            self.run_main('snapshot', '--table=users')

    def test_php_snapshot_is_query_only_and_column_allowlisted(self):
        source = (REPO / 'scripts/amadis/artisan.php').read_text()
        block = source.split("'amadis:lifecycle-snapshot'", 1)[1].split('$status = $kernel->handle', 1)[0]
        self.assertIn('PRAGMA query_only = ON', block)
        self.assertNotIn("'users'", block)
        self.assertNotIn("'admins'", block)
        for forbidden in ('password', 'email', 'shipping_address', 'idempotency_key', 'payload_hash', 'remember_token'):
            self.assertNotIn(forbidden, block)


if __name__ == '__main__':
    unittest.main(verbosity=2)
