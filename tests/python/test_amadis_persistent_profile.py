"""Persistent demo profile of scripts/amadis/launch.py, checked without PHP.

capture() is replaced by a recorder so main() reveals the env it would hand to artisan/serve.
"""
import importlib.util
import json
from pathlib import Path
import secrets
import shutil
import sys
import unittest

LAUNCH = Path(__file__).resolve().parents[2] / 'scripts/amadis/launch.py'
BASE = Path('/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode')


def load():
    spec = importlib.util.spec_from_file_location('amadis_launch_profile', LAUNCH)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


class PersistentProfileTests(unittest.TestCase):
    def setUp(self):
        self.root = BASE / ('amadis-' + secrets.token_hex(12))
        self.root.mkdir(mode=0o700)
        self.addCleanup(shutil.rmtree, self.root, True)
        (self.root / 'identity.json').write_text(json.dumps({'root': str(self.root.resolve()),
            'database': str(self.root.resolve() / 'database.sqlite')}))

    def run_main(self, *args):
        launch = load()
        calls = []
        launch.capture = lambda root, label, argv, env: calls.append((label, argv, dict(env))) or 0
        old = sys.argv, launch.os.environ.get('AMADIS_ROOT')
        sys.argv = ['launch.py', *args]
        launch.os.environ['AMADIS_ROOT'] = str(self.root)
        try:
            status = launch.main()
        finally:
            sys.argv = old[0]
            if old[1] is None:
                launch.os.environ.pop('AMADIS_ROOT')
            else:
                launch.os.environ['AMADIS_ROOT'] = old[1]
            if launch.lock:
                launch.lock.close()
        return status, calls

    def test_serve_profile_is_persistent_local_and_loopback(self):
        status, calls = self.run_main('serve', '8123', '2')
        self.assertEqual(status, 0)
        label, argv, env = calls[-1]
        root = str(self.root.resolve())
        self.assertEqual(label, 'serve')
        self.assertIn('127.0.0.1:8123', argv)
        self.assertEqual(env['APP_URL'], 'http://127.0.0.1:8123')
        self.assertEqual(env['APP_ENV'], 'local')
        self.assertEqual(env['DB_DATABASE'], root + '/database.sqlite')
        self.assertEqual((env['SESSION_DRIVER'], env['CACHE_STORE'], env['MAIL_MAILER']), ('file', 'file', 'log'))
        self.assertTrue(env['LARAVEL_STORAGE_PATH'].startswith(root + '/'))
        self.assertEqual(env['PHP_CLI_SERVER_WORKERS'], '2')

    def test_demo_key_is_stable_across_restarts(self):
        first = self.run_main('serve', '8123')[1][-1][2]['APP_KEY']
        second = self.run_main('artisan', 'about')[1][-1][2]['APP_KEY']
        self.assertTrue(first.startswith('base64:'))
        self.assertEqual(first, second)
        self.assertEqual((self.root / 'demo-key').read_text(), first)

    def test_test_profile_stays_in_memory(self):
        env = self.run_main('test')[1][-1][2]
        self.assertEqual((env['APP_ENV'], env['DB_DATABASE'], env['SESSION_DRIVER'], env['MAIL_MAILER']),
                         ('testing', ':memory:', 'array', 'array'))

    def test_seed_defaults_to_demo_seeder(self):
        argv = self.run_main('seed')[1][-1][1]
        self.assertIn('--class=Database\\Seeders\\AmadisDemoSeeder', argv)

    def test_reserved_ports_and_bad_workers_refused(self):
        for args in (('serve', '8000'), ('serve', '8001'), ('serve', '08000'), ('serve', 'abc'),
                     ('serve', '8123', '0'), ('serve', '8123', '9')):
            with self.subTest(args=args), self.assertRaises(SystemExit):
                self.run_main(*args)


if __name__ == '__main__':
    unittest.main(verbosity=2)


class PhpArraySecretTests(unittest.TestCase):
    def test_php_array_password_redacted_in_command_metadata(self):
        import importlib.util
        from pathlib import Path
        spec = importlib.util.spec_from_file_location('l', Path(__file__).resolve().parents[2] / 'scripts/amadis/launch.py')
        m = importlib.util.module_from_spec(spec); spec.loader.exec_module(m)
        out = m.sanitized_command(['php', 'a.php', 'tinker', '--execute=X::create(["email"=>"a@b.test","password"=>"SENTINEL-PW-1!"]);'])
        self.assertNotIn('SENTINEL-PW-1', out)
        self.assertIn('a@b.test', out)
