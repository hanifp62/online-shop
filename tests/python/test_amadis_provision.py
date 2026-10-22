"""provision: private credentials, no leakage, refuses used roots. No PHP needed."""
import importlib.util
import json
from pathlib import Path
import secrets
import shutil
import sqlite3
import stat
import subprocess
import sys
import unittest

LAUNCH = Path(__file__).resolve().parents[2] / 'scripts/amadis/launch.py'
BASE = Path('/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode')


class ProvisionTests(unittest.TestCase):
    def setUp(self):
        self.root = BASE / ('amadis-' + secrets.token_hex(12))
        self.root.mkdir(mode=0o700)
        self.addCleanup(shutil.rmtree, self.root, True)
        (self.root / 'identity.json').write_text(json.dumps({'root': str(self.root.resolve()),
            'database': str(self.root.resolve() / 'database.sqlite')}))

    def db(self, orders):
        with sqlite3.connect(self.root / 'database.sqlite') as db:
            if orders is not None:
                db.execute('create table orders (id integer)')
                for i in range(orders):
                    db.execute('insert into orders values (?)', (i,))

    def run_main(self, *args, child):
        spec = importlib.util.spec_from_file_location('amadis_launch_prov', LAUNCH)
        launch = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(launch)
        calls = []
        def fake(argv, **kwargs):
            calls.append((argv, kwargs))
            return child
        launch.subprocess = type('S', (), {'run': staticmethod(fake), 'DEVNULL': subprocess.DEVNULL,
                                           'check_output': staticmethod(subprocess.check_output)})
        launch.capture = lambda *a: 0
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
            launch.lock.close()

    def child(self, stdout, code=0):
        return subprocess.CompletedProcess([], code, stdout, 'SECRET-LEAK-IN-STDERR')

    def test_writes_private_credentials_and_prints_only_ids(self):
        self.db(0)
        out = self.child('{"status":"provisioned","admin_id":1,"customer_id":2}\n')
        import io, contextlib
        buffer = io.StringIO()
        with contextlib.redirect_stdout(buffer):
            status, calls = self.run_main('provision', child=out)
        self.assertEqual(status, 0)
        path = self.root / 'private-runtime/credentials.json'
        self.assertEqual(stat.S_IMODE(path.stat().st_mode), 0o600)
        credentials = json.loads(path.read_text())
        for role in ('admin', 'customer'):
            self.assertGreaterEqual(len(credentials[role]['password']), 24)
        everything = buffer.getvalue() + ''.join(p.read_text() for p in (self.root / 'evidence').glob('provision-*.json'))
        everything += json.dumps([c[0] for c in calls])
        for role in ('admin', 'customer'):
            self.assertNotIn(credentials[role]['password'], everything)
        self.assertNotIn('SECRET-LEAK', everything)
        self.assertEqual(json.loads(buffer.getvalue()), {'status': 'provisioned', 'admin_id': 1, 'customer_id': 2})

    def test_malformed_child_output_is_discarded(self):
        self.db(0)
        import io, contextlib
        buffer = io.StringIO()
        with contextlib.redirect_stdout(buffer):
            status, _ = self.run_main('provision', child=self.child('boom password=xyz'))
        self.assertEqual(status, 1)
        self.assertEqual(json.loads(buffer.getvalue()), {'status': 'failed'})

    def test_refuses_root_with_orders_or_no_schema_and_writes_nothing(self):
        import io, contextlib
        buffer = io.StringIO()
        with contextlib.redirect_stdout(buffer):
            for orders in (1, None):
                with self.subTest(orders=orders):
                    if (self.root / 'database.sqlite').exists():
                        (self.root / 'database.sqlite').unlink()
                    self.db(orders)
                    status, calls = self.run_main('provision', child=self.child(''))
                    self.assertEqual(status, 1)
                    self.assertEqual(calls, [])
                    self.assertFalse((self.root / 'private-runtime').exists())

    def test_provision_refuses_arguments(self):
        with self.assertRaises(SystemExit):
            self.run_main('provision', 'x', child=self.child(''))


if __name__ == '__main__':
    unittest.main(verbosity=2)
