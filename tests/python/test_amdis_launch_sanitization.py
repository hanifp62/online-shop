import contextlib
import importlib.util
import io
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import unittest

MODULE_PATH = Path(__file__).resolve().parents[2] / 'scripts/amadis/launch.py'
spec = importlib.util.spec_from_file_location('amadis_launch', MODULE_PATH)
launch = importlib.util.module_from_spec(spec)
spec.loader.exec_module(launch)


class SanitizationTests(unittest.TestCase):
    def assert_redacted(self, text, *secrets):
        result = launch.sanitized(text)
        for secret in secrets:
            self.assertNotIn(secret, result)
        self.assertIn('[REDACTED]', result)

    def test_sentinel_json_and_nested_json_values(self):
        self.assert_redacted(
            '{"sentinel":{"APP_KEY":"key-secret"},"access_token":"tok-secret",'
            '"safe":"visible"}', 'key-secret', 'tok-secret')

    def test_bearer_and_multiline_cookie_headers(self):
        self.assert_redacted(
            'Authorization: Bearer bearer-secret\r\nCookie: sid=cookie-secret; theme=dark\r\n'
            'Set-Cookie: session=session-secret; HttpOnly\r\n\tfolded-secret',
            'bearer-secret', 'cookie-secret', 'session-secret', 'folded-secret')

    def test_separate_environment_and_cli_assignments(self):
        self.assert_redacted(
            'APP_KEY=env-secret --password cli-secret API_TOKEN: other-secret',
            'env-secret', 'cli-secret', 'other-secret')

    def test_synthetic_leak_shapes_are_redacted(self):
        cases = {
            'AWS_ACCESS_KEY_ID=aws-sentinel': 'aws-sentinel',
            'SIGNING_KEY=key-sentinel': 'key-sentinel',
            '> Authorization: Basic prefixed-sentinel': 'prefixed-sentinel',
            'DB_URL=mysql://user-sentinel:pw-sentinel@127.0.0.1/db': 'pw-sentinel',
            'REDIS_URL=redis://:redis-sentinel@127.0.0.1:6379/0': 'redis-sentinel',
            'API_TOKEN=Bearer bearer-sentinel': 'bearer-sentinel',
            'token=Basic basic-sentinel': 'basic-sentinel',
            'Authorization: Basic header-sentinel': 'header-sentinel',
            "{'access_token':'single-sentinel'}": 'single-sentinel',
            "'APP_KEY'='assign-sentinel'": 'assign-sentinel',
            '{"APP_KEY": malformed-sentinel}': 'malformed-sentinel',
            "['password' => php-sentinel]": 'php-sentinel',
            'password=>bare-php-sentinel': 'bare-php-sentinel',
            'db.password=dotted-sentinel': 'dotted-sentinel',
        }
        for text, secret in cases.items():
            with self.subTest(text=text):
                self.assert_redacted(text, secret)

    def test_prefixed_headers_redact_every_cookie_pair(self):
        cases = {
            '> Cookie: s1=first-sentinel; s2=second-sentinel': ('first-sentinel', 'second-sentinel'),
            'curl --header=Cookie: a=1; sid=inline-sentinel': ('inline-sentinel',),
            '* < Set-Cookie: x=verbose-sentinel; y=verbose2-sentinel': ('verbose-sentinel', 'verbose2-sentinel'),
            '> Authorization: Basic prefix-sentinel': ('prefix-sentinel',),
        }
        for text, leaked in cases.items():
            with self.subTest(text=text):
                self.assert_redacted(text, *leaked)

    def test_prefixed_folded_continuation_is_redacted(self):
        self.assert_redacted('< Set-Cookie: a=fold-sentinel;\n< part2=fold2-sentinel',
                             'fold-sentinel', 'fold2-sentinel')

    def test_header_redaction_stops_at_next_header(self):
        result = launch.sanitized('> Cookie: a=stop-sentinel\n> Host: example.test\n< HTTP/1.1 200 OK')
        self.assertNotIn('stop-sentinel', result)
        self.assertIn('> Host: example.test', result)
        self.assertIn('< HTTP/1.1 200 OK', result)

    def test_quoted_auth_scheme_values_are_redacted(self):
        cases = {
            '> Authorization: Bearer "quoted-sentinel"': 'quoted-sentinel',
            "x Basic 'single-quoted-sentinel'": 'single-quoted-sentinel',
            'h: Bearer \\"escaped-sentinel\\"': 'escaped-sentinel',
        }
        for text, secret in cases.items():
            with self.subTest(text=text):
                self.assert_redacted(text, secret)

    def test_php_dump_bracket_keys_are_redacted(self):
        self.assert_redacted('["password"]=>\n  string(10) "dump-sentinel"', 'dump-sentinel')
        self.assert_redacted('[password] => printr-sentinel', 'printr-sentinel')

    def test_additional_secret_names_are_redacted(self):
        self.assert_redacted(
            '{"auth":"a-sentinel","credentials":"c-sentinel","pass":"p-sentinel",'
            '"csrf":"csrf-sentinel","xsrf_token":"xsrf-sentinel","privatekey":"pk-sentinel"}',
            'a-sentinel', 'c-sentinel', 'p-sentinel', 'csrf-sentinel', 'xsrf-sentinel', 'pk-sentinel')
        self.assert_redacted(
            'auth=ka-sentinel credentials=kc-sentinel pass=kp-sentinel '
            '_csrf=kcsrf-sentinel XSRF-TOKEN=kxsrf-sentinel privateKey=kpk-sentinel',
            'ka-sentinel', 'kc-sentinel', 'kp-sentinel', 'kcsrf-sentinel', 'kxsrf-sentinel', 'kpk-sentinel')

    def test_non_secret_words_with_secret_prefix_remain(self):
        self.assertEqual(launch.sanitized('author=Bob status=ok'), 'author=Bob status=ok')

    def test_multiline_pem_body_is_redacted(self):
        self.assert_redacted(
            'private_key=-----BEGIN PRIVATE KEY-----\nMIIpem-sentinel\npem2-sentinel==\n'
            '-----END PRIVATE KEY-----\nnext', 'pem-sentinel', 'pem2-sentinel')
        self.assert_redacted(
            'private_key=-----BEGIN PRIVATE KEY-----\\nMIIescaped-pem-sentinel\\n-----END PRIVATE KEY-----',
            'escaped-pem-sentinel')

    def test_command_metadata_sanitizes_credential_options(self):
        cases = [
            (['curl', '-u', 'user:u-sentinel'], ('u-sentinel',)),
            (['curl', '-uuser:attached-sentinel'], ('attached-sentinel',)),
            (['curl', '--user=me:eq-sentinel'], ('eq-sentinel',)),
            (['x', '--pass', 'pass-sentinel', '--key', 'key-sentinel', '--auth=auth-sentinel'],
             ('pass-sentinel', 'key-sentinel', 'auth-sentinel')),
            (['curl', '--header=Cookie: a=1; sid=hdr-sentinel'], ('hdr-sentinel',)),
            (['curl', '-H', 'Cookie: a=1; sid=short-hdr-sentinel'], ('short-hdr-sentinel',)),
        ]
        for argv, leaked in cases:
            with self.subTest(argv=argv):
                result = launch.sanitized_command(argv)
                for secret in leaked:
                    self.assertNotIn(secret, result)
                self.assertIn('[REDACTED]', result)

    def test_diagnostic_shape_and_unrelated_values_remain(self):
        source = 'status=failed APP_KEY=secret detail=kept'
        result = launch.sanitized(source)
        self.assertEqual(result, 'status=failed APP_KEY=[REDACTED] detail=kept')

    def test_manifest_excludes_database_dumps_and_links(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory).resolve()
            subprocess.run(['git', 'init', '-q'], cwd=root, check=True)
            (root / 'database/seeders').mkdir(parents=True)
            (root / 'database/seeders/Demo.php').write_text('<?php')
            (root / 'database/users.sql').write_text('private fixture')
            (root / 'config').mkdir()
            (root / 'config/app.php').write_text('<?php')
            outside = Path(directory).parent / ('outside-' + root.name)
            outside.write_text('outside secret')
            self.addCleanup(outside.unlink)
            (root / 'config/linked.php').symlink_to(outside)
            original = launch.REPO
            launch.REPO = root
            try:
                records = launch.manifest(root)
            finally:
                launch.REPO = original
        paths = {record['path'] for record in records}
        self.assertIn('database/seeders/Demo.php', paths)
        self.assertIn('config/app.php', paths)
        self.assertNotIn('database/users.sql', paths)
        self.assertNotIn('config/linked.php', paths)

    def test_prepare_rejects_symlinked_writable_directories(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / 'amadis-test'
            root.mkdir(mode=0o700)
            outside = Path(directory) / 'outside'
            outside.mkdir()
            (root / 'evidence').symlink_to(outside, target_is_directory=True)
            with self.assertRaises(SystemExit):
                launch.prepare(root)

    def test_open_file_rejects_symlinked_config_cache(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / 'amadis-test'
            root.mkdir(mode=0o700)
            launch.prepare(root)
            outside = Path(directory) / 'config-cache'
            outside.write_text('outside')
            (root / 'bootstrap/config.php').symlink_to(outside)
            with self.assertRaises(SystemExit):
                launch.open_file(root, 'bootstrap/config.php', os.O_WRONLY)
            self.assertEqual(outside.read_text(), 'outside')

    def test_prepare_rejects_nested_storage_symlink_and_hardlink(self):
        for kind in ('symlink', 'hardlink'):
            with self.subTest(kind=kind), tempfile.TemporaryDirectory() as directory:
                root = Path(directory) / 'amadis-test'
                root.mkdir(mode=0o700)
                launch.prepare(root)
                outside = Path(directory) / 'outside.log'
                outside.write_text('outside')
                target = root / 'storage/logs/laravel.log'
                target.symlink_to(outside) if kind == 'symlink' else target.hardlink_to(outside)
                with self.assertRaises(SystemExit):
                    launch.prepare(root)

    def test_evidence_write_does_not_follow_planted_symlink(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / 'amadis-test'
            root.mkdir(mode=0o700)
            launch.prepare(root)
            outside = Path(directory) / 'outside.json'
            outside.write_text('outside')
            (root / 'evidence/run.json').symlink_to(outside)
            with self.assertRaises(SystemExit):
                launch.write_new(root, 'evidence/run.json', 'APP_KEY=secret')
            self.assertEqual(outside.read_text(), 'outside')

    def test_capture_writes_sanitized_metadata_inside_root(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / 'amadis-test'
            root.mkdir(mode=0o700)
            launch.prepare(root)
            original = launch.lock
            with open(root / '.lock', 'a') as lock:
                launch.lock = lock
                try:
                    with contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
                        status = launch.capture(root, 'unit', [sys.executable, '-c', 'print("TOKEN=out-secret")',
                                                               '--password', 'cli-secret'], {})
                finally:
                    launch.lock = original
            self.assertEqual(status, 0)
            evidence = ''.join(path.read_text() for path in (root / 'evidence').iterdir())
            self.assertNotIn('out-secret', evidence)
            self.assertNotIn('cli-secret', evidence)
            self.assertIn('[REDACTED]', evidence)

    def test_prepare_rejects_hardlinked_database(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / 'amadis-test'
            root.mkdir(mode=0o700)
            outside = Path(directory) / 'database.sqlite'
            outside.write_text('outside')
            (root / 'database.sqlite').hardlink_to(outside)
            with self.assertRaises(SystemExit):
                launch.prepare(root)

    def test_command_metadata_sanitizes_inline_and_separate_arguments(self):
        result = launch.sanitized_command([
            'tool', '--token', 'arg-secret', '--api-key=inline-secret', 'PASSWORD=env-secret'])
        for secret in ('arg-secret', 'inline-secret', 'env-secret'):
            self.assertNotIn(secret, result)
        self.assertEqual(result, "tool --token '[REDACTED]' '--api-key=[REDACTED]' 'PASSWORD=[REDACTED]'")

    def test_command_metadata_sanitizes_dotted_keys_and_short_password(self):
        result = launch.sanitized_command(['tool', 'db.password=dotted-secret', '-p', 'short-secret',
                                           '-p=inline-secret', 'AWS_ACCESS_KEY_ID=aws-secret'])
        for secret in ('dotted-secret', 'short-secret', 'inline-secret', 'aws-secret'):
            self.assertNotIn(secret, result)

    def test_mocked_capture_private_write_and_allowlisted_export(self):
        secrets_seen = ('out-sentinel', 'err-sentinel', 'arg-sentinel', 'url-sentinel')
        stdout = 'AWS_ACCESS_KEY_ID=out-sentinel\nDB_URL=mysql://u:url-sentinel@db/app\nvisible'
        stderr = "API_TOKEN=Bearer err-sentinel 'password' => php-err-sentinel"
        fake = subprocess.CompletedProcess([], 0, stdout, stderr)
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / 'amadis-test'
            root.mkdir(mode=0o700)
            launch.prepare(root)
            original_lock, original_run = launch.lock, launch.subprocess.run
            with open(root / '.lock', 'a') as lock:
                launch.lock = lock
                launch.subprocess.run = lambda *a, **k: fake
                try:
                    out, err = io.StringIO(), io.StringIO()
                    with contextlib.redirect_stdout(out), contextlib.redirect_stderr(err):
                        launch.capture(root, 'mock', ['tool', '-p', 'arg-sentinel'], {})
                finally:
                    launch.lock, launch.subprocess.run = original_lock, original_run
            files = sorted((root / 'evidence').iterdir())
            # Pin exactly what capture writes: one stem, three suffixes, nothing else.
            names = [p.name for p in files]
            self.assertEqual(len(names), 3)
            stem = re.fullmatch(r'(\d{8}T\d{6}Z-mock-[0-9a-f]{6})\.(?:json|stdout|stderr)', names[0])
            self.assertIsNotNone(stem)
            expected = {stem[1] + suffix for suffix in ('.json', '.stdout', '.stderr')}
            self.assertEqual(set(names), expected)
            for path in files:  # private write: owner-only files, nothing outside evidence/
                self.assertEqual(path.stat().st_mode & 0o777, 0o600)
            # Plant an unexpected file after capture; the exact-name allowlist must reject it.
            (root / 'evidence/x.log').write_text('planted-log-sentinel')
            exported = {p.name: launch.read_file(root, 'evidence/' + p.name)
                        for p in sorted((root / 'evidence').iterdir()) if p.name in expected}
            self.assertEqual(set(exported), expected)
            self.assertNotIn('x.log', exported)
            combined = ''.join(exported.values()) + out.getvalue() + err.getvalue()
            self.assertNotIn('planted-log-sentinel', combined)
            for secret in secrets_seen + ('php-err-sentinel',):
                self.assertNotIn(secret, combined)
            self.assertIn('visible', combined)
            self.assertIn('[REDACTED]', combined)


if __name__ == '__main__':
    unittest.main()
