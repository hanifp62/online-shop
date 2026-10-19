"""Negative guard tests; never bootstrap Laravel or access a real database.

The Python audit hook records and blocks descendant execution before it happens.
PHP process functions are disabled; command cases must report an explicit denial.
"""
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch
from urllib.parse import quote, quote_plus


REPO = Path(__file__).resolve().parents[1]
LAUNCH = REPO / 'scripts/amadis/launch.py'
ARTISAN = REPO / 'scripts/amadis/artisan.php'
BASE = Path('/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode')
PHP = shutil.which('php')
CHILD_MARKER = 'NEGATIVE_TEST_CHILD_ATTEMPT'
AUDITED_RUNNER = r'''
import os, pathlib, runpy, sys
script, *args = sys.argv[1:]
def audit(event, args):
    if event in {'subprocess.Popen', 'os.system', 'os.exec', 'os.posix_spawn', 'os.fork', 'os.forkpty'}:
        print('NEGATIVE_TEST_CHILD_ATTEMPT', file=sys.stderr)
        raise RuntimeError('Child execution blocked by negative test')
    if event == 'open' and isinstance(args[0], (str, bytes)):
        path = pathlib.Path(os.fsdecode(args[0]))
        if any(part.startswith('.env') for part in path.parts):
            raise RuntimeError('Environment file access blocked by negative test')
sys.addaudithook(audit)
sys.argv = [script, *args]
runpy.run_path(script, run_name='__main__')
'''
PHP_DISABLED = ','.join((
    'exec', 'system', 'passthru', 'shell_exec', 'proc_open', 'popen',
    'pcntl_exec', 'pcntl_fork',
))
COMMANDS = {
    'db': ('db',), 'd': ('d',), 'db_s': ('db:s',),
    'db_seed': ('db:seed',), 'db_wipe': ('db:wipe',),
    'tinker': ('tinker',), 'migrate_fresh': ('migrate:fresh',),
    'migrate_f': ('migrate:f',), 'm_f': ('m:f',),
    'command_option': ('--command=db',), 'separator_db': ('--', 'db'),
    'list_env': ('list', '--env=x'), 'serve': ('serve',),
}


class AmadisGuardNegativeTest(unittest.TestCase):
    def setUp(self):
        self.root = Path(tempfile.mkdtemp(prefix='amadis-negative-', dir=BASE))
        self.addCleanup(shutil.rmtree, self.root)
        self.env = {
            'PATH': '/usr/bin:/bin', 'HOME': str(self.root),
            'TMPDIR': str(self.root), 'AMADIS_ROOT': str(self.root),
            'DB_CONNECTION': 'sqlite', 'DB_DATABASE': ':memory:',
            'DB_URL': '', 'PYTHONDONTWRITEBYTECODE': '1',
        }

    def invoke(self, runner, args, root=None):
        env = dict(self.env, AMADIS_ROOT=root if root is not None else str(self.root))
        if runner == 'launch':
            argv = [sys.executable, '-B', '-c', AUDITED_RUNNER, str(LAUNCH), *args]
        else:
            if PHP is None:
                self.skipTest('PHP executable unavailable')
            # No php.ini, auto-prepend, framework, database driver, or process APIs.
            argv = [PHP, '-n', '-d', 'disable_functions=' + PHP_DISABLED, str(ARTISAN), *args]
        sentinel = self.root / 'unchanged-sentinel'
        sentinel.write_bytes(b'negative guard test: preserve existing files\n')
        before = {str(p.relative_to(self.root)): p.read_bytes()
                  for p in self.root.rglob('*') if p.is_file() and not p.is_symlink()}
        before_paths = sorted(str(p.relative_to(self.root)) for p in self.root.rglob('*'))
        result = subprocess.run(argv, cwd=REPO, env=env, stdin=subprocess.DEVNULL,
                                capture_output=True, text=True, timeout=20)
        output = result.stdout + result.stderr
        with self.subTest(check='nonzero exit'):
            self.assertNotEqual(result.returncode, 0, output)
        after_paths = sorted(str(p.relative_to(self.root)) for p in self.root.rglob('*'))
        after = {str(p.relative_to(self.root)): p.read_bytes()
                 for p in self.root.rglob('*') if p.is_file() and not p.is_symlink()}
        with self.subTest(check='no created files'):
            self.assertEqual(before_paths, after_paths, output)
        with self.subTest(check='no modified files'):
            self.assertEqual(before, after, output)
        with self.subTest(check='no descendant execution'):
            self.assertNotIn(CHILD_MARKER, output)
            self.assertNotIn('Call to undefined function', output)
        with self.subTest(check='no sqlite prompt'):
            self.assertNotIn('sqlite3', output.lower())
            self.assertNotIn('sqlite>', output.lower())
        return output

    def rejected_command(self, runner, args):
        output = self.invoke(runner, ('artisan', *args) if runner == 'launch' else args)
        self.assertIn('not allowed', output.lower(),
                      'Must reject command, not merely fail root/bootstrap validation: ' + output)

    def test_launch_seed_r44_extra_argument(self):
        output = self.invoke('launch', ('seed-r44', 'extra-arg'))
        self.assertIn('seed-r44 accepts no arguments', output)

    def test_launch_seed_other_class(self):
        output = self.invoke('launch', ('seed', '--class=Other'))
        self.assertIn('only AmadisDemoSeeder is allowed', output)

    def test_launch_seed_rejects_extra_arguments_before_root_mutation(self):
        output = self.invoke('launch', ('seed', 'Database\\\\Seeders\\\\AmadisDemoSeeder', '--force'))
        self.assertIn('seed accepts only AmadisDemoSeeder', output)

    def test_launch_rejects_arbitrary_run_mode(self):
        output = self.invoke('launch', ('run', 'echo', 'unsafe'))
        self.assertIn('usage:', output)

    def invalid_root(self, runner, kind):
        roots = {
            'repository': str(REPO), 'relative': './amadis-relative',
            'nonabsolute': 'amadis-relative',
            'dotdot': str(self.root) + '/../' + self.root.name,
        }
        if kind == 'symlink':
            link = self.root / 'linked-root'
            link.symlink_to(self.root, target_is_directory=True)
            root = str(link)
        else:
            root = roots[kind]
        args = ('init',) if runner == 'launch' else ('amadis:cache-config',)
        output = self.invoke(runner, args, root=root)
        self.assertRegex(output, r'AMADIS_ROOT|canonical Amadis root', output)

    def fixture_module(self):
        spec = importlib.util.spec_from_file_location('amadis_fixture_launch', LAUNCH)
        module = importlib.util.module_from_spec(spec)
        with patch.object(sys, 'dont_write_bytecode', True):
            spec.loader.exec_module(module)
        module.BASE = self.root
        fixture = self.root / 'r44-final/fixture/seed-r44.php'
        fixture.parent.mkdir(parents=True)
        data = b'<?php echo "synthetic fixture";'
        fixture.write_bytes(data)
        fixture.chmod(0o600)
        module.R44_FIXTURE_SHA256 = module.hashlib.sha256(data).hexdigest()
        return module, fixture, data

    def test_tampered_fixture_hash(self):
        module, fixture, _ = self.fixture_module()
        fixture.write_bytes(b'<?php echo "tampered";')
        with self.assertRaisesRegex(SystemExit, 'sha256 mismatch'):
            module.verify_r44_fixture()

    def test_verified_fixture_bytes_survive_source_replacement(self):
        module, fixture, data = self.fixture_module()
        verified = module.verify_r44_fixture()
        fixture.unlink()
        fixture.write_bytes(b'<?php echo "replacement";')
        self.assertEqual(verified, data)
        with tempfile.TemporaryFile() as lock:
            module.lock = lock
            result = subprocess.CompletedProcess(['php'], 0, b'', b'')
            with patch.object(module.subprocess, 'run', return_value=result) as run, \
                    patch.object(module, 'write_new'), \
                    patch('builtins.print'):
                self.assertEqual(module.capture(self.root, 'seed-r44', ['php'], {}, input_data=verified), 0)
            self.assertEqual(run.call_args.kwargs['input'], data)
            self.assertFalse(run.call_args.kwargs['text'])
            self.assertEqual(run.call_args.args[0], ['php'])

    def test_literal_secret_redaction(self):
        spec = importlib.util.spec_from_file_location('amadis_negative_launch', LAUNCH)
        module = importlib.util.module_from_spec(spec)
        # Import does not call main; avoid writing __pycache__ in the scripts directory.
        with patch.object(sys, 'dont_write_bytecode', True):
            spec.loader.exec_module(module)
        redact = getattr(module, 'redact_literal_secrets', None)
        if redact is None:
            self.skipTest('launch.py does not expose a literal-secret redaction function')
        secret = 'synthetic-quote" slash\\ space +/end'
        env = {'R44_ADMIN_PASSWORD': secret, 'R44_TOTP_SECRET': 'SYNTHETICTOTP2345'}
        variants = (secret, json.dumps(secret)[1:-1], quote(secret, safe=''), quote_plus(secret))
        with patch.dict(os.environ, {}, clear=True):
            for value in (*variants, env['R44_TOTP_SECRET']):
                with self.subTest(value=value):
                    result = redact('before ' + value + ' after', env)
                    self.assertNotIn(value, result)
                    self.assertEqual(result, 'before [REDACTED] after')
            self.assertEqual(redact('ordinary public text', env), 'ordinary public text')


def command_test(runner, args):
    def test(self):
        self.rejected_command(runner, args)
    return test


def root_test(runner, kind):
    def test(self):
        self.invalid_root(runner, kind)
    return test


for _runner in ('launch', 'php'):
    for _name, _args in COMMANDS.items():
        setattr(AmadisGuardNegativeTest, f'test_{_runner}_reject_{_name}', command_test(_runner, _args))
    for _kind in ('repository', 'relative', 'nonabsolute', 'dotdot', 'symlink'):
        setattr(AmadisGuardNegativeTest, f'test_{_runner}_root_{_kind}', root_test(_runner, _kind))


if __name__ == '__main__':
    unittest.main()
