"""NFR-CICD-003 / CICD-03, CICD-08: regression sintetis tanpa DB/server."""
import contextlib
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch


SCRIPT = Path(__file__).resolve().parents[2] / 'scripts/ci/security_scan.py'
SPEC = importlib.util.spec_from_file_location('ci_security_scan', SCRIPT)
if SPEC is None or SPEC.loader is None:
    raise ImportError('Security scanner test module cannot be loaded')
scanner = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(scanner)


SYNTHETIC_KEY = b'base' + b'64:' + b'Q' * 43 + b'='
PEM = b'-----BEGIN ' + b'PRIVATE KEY'
PEM_FIXTURE_PATH = 'tests/python/test_amdis_launch_sanitization.py'
PEM_FIXTURE = (
    b'    def test_multiline_pem_body_is_redacted(self):\n'
    b'        self.assert_redacted(\n'
    b"            'private_key=" + PEM + b"-----\\nMIIpem-sentinel\\npem2-sentinel==\\n'\n"
    b"            '-----END PRIVATE KEY-----\\nnext', 'pem-sentinel', 'pem2-sentinel')\n"
    b'        self.assert_redacted(\n'
    b"            'private_key=" + PEM + b"-----\\\\nMIIescaped-pem-sentinel\\\\n-----END PRIVATE KEY-----',\n"
    b"            'escaped-pem-sentinel')\n"
)


def phpunit_fixture(key=SYNTHETIC_KEY, environment=b'testing', extra=b''):
    return (b'<phpunit>\n<php>\n<env name="APP_ENV" value="' + environment + b'"/>\n'
            b'<env name="APP_KEY" value="' + key + b'"/>\n' + extra + b'\n</php>\n</phpunit>')


class SecurityScanTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory(prefix='ci-security-synthetic-')
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name)

    def write(self, path, content):
        target = self.root / path
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(content)

    def invoke(self, mode, paths):
        output, errors = io.StringIO(), io.StringIO()
        with patch.object(scanner, 'repository_root', return_value=self.root), \
             patch.object(scanner, 'git_paths', return_value=paths), \
             contextlib.redirect_stdout(output), contextlib.redirect_stderr(errors):
            status = scanner.main(['--mode', mode])
        return status, output.getvalue(), errors.getvalue()

    def test_pii_no_match_is_clean(self):
        self.write('app/Clean.php', b"Log::info('event', ['order_id' => 7]);")
        status, output, errors = self.invoke('pii', ['app/Clean.php'])
        self.assertEqual((status, output, errors), (0, '', ''))

    def test_pii_match_reports_only_path(self):
        self.write('app/Leak.php', b"Log::info('email', ['email' => 'synthetic@example.test']);")
        status, output, errors = self.invoke('pii', ['app/Leak.php'])
        self.assertEqual(status, 1)
        self.assertEqual(output, '"app/Leak.php"\n')
        self.assertEqual(errors, '')

    def test_pii_read_error_is_not_clean_and_never_echoes_exception(self):
        with patch.object(scanner, 'read_file', side_effect=OSError('synthetic-private-detail')):
            status, output, errors = self.invoke('pii', ['app/Unreadable.php'])
        self.assertEqual(status, 2)
        self.assertEqual(output, '')
        self.assertTrue('synthetic-private-detail' not in errors)
        self.assertTrue('scan failed' in errors)

    def test_pii_preserves_workflow_and_phpunit_patterns(self):
        samples = [b"Log::" + level + b"('" + field + b"');"
                   for level in (b'info', b'warning', b'error', b'debug')
                   for field in (b'email', b'password', b'phone', b'address', b'token')]
        samples += [b"LOG::WARNING (\n'event', [\n" + bytes([36]) + field + b']);'
                    for field in (b'password', b'token', b'pin', b'cvv', b'secret')]
        for index, sample in enumerate(samples):
            with self.subTest(index=index):
                self.write('app/Leak.php', sample)
                status, output, errors = self.invoke('pii', ['app/Leak.php'])
                self.assertEqual((status, output, errors), (1, '"app/Leak.php"\n', ''))

    def test_every_secret_family_is_detected_in_docs_tests_and_agent_paths(self):
        samples = [SYNTHETIC_KEY, PEM + b'-----', b'AK' + b'IA' + b'Z' * 16,
                   b'gh' + b'p_' + b'a' * 36, b'ng' + b'pk_' + b'b' * 12,
                   b'SB-Mid' + b'-server-' + b'c' * 12, b'Mid' + b'-server-' + b'd' * 12,
                   b'sk' + b'_live']
        paths = ['docs/report.md', 'tests/python/fixture.py', 'tests/Feature/Leak.php',
                 '.agents/skills/demo/SKILL.md', '.claude/notes.md', '.codex/config.toml',
                 'app/Config.php', 'vendor/tracked.php']
        for path, sample in zip(paths, samples):
            self.write(path, b'context\n' + sample + b'\n')
        status, output, errors = self.invoke('secrets', paths + paths)
        self.assertEqual(status, 1)
        self.assertEqual([json.loads(line) for line in output.splitlines()], sorted(paths))
        self.assertEqual(errors, '')

    def test_previously_allowlisted_files_do_not_hide_new_secrets(self):
        for path in ('.github/workflows/ci.yml', 'tests/Feature/SecurityScanIntegrityTest.php',
                     PEM_FIXTURE_PATH, 'phpunit.xml'):
            with self.subTest(path=path):
                self.write(path, b'<phpunit><php><env name="OTHER" value="'
                           + b'gh' + b'p_' + b'z' * 36 + b'"/></php></phpunit>')
                status, output, errors = self.invoke('secrets', [path])
                self.assertEqual((status, output, errors), (1, json.dumps(path) + '\n', ''))

    def test_phpunit_exemption_requires_pinned_key_and_exact_testing_context(self):
        self.write('phpunit.xml', phpunit_fixture())
        self.assertEqual(self.invoke('secrets', ['phpunit.xml'])[0], 1)
        fingerprint = hashlib.sha256(SYNTHETIC_KEY).hexdigest()
        with patch.object(scanner, 'PHPUNIT_KEY_SHA256', fingerprint):
            self.assertEqual(self.invoke('secrets', ['phpunit.xml']), (0, '', ''))
            variants = [phpunit_fixture(environment=b'production'),
                        phpunit_fixture(key=b'base' + b'64:' + b'R' * 43 + b'='),
                        phpunit_fixture(extra=b'<env name="OTHER" value="' + SYNTHETIC_KEY + b'"/>'),
                        phpunit_fixture(extra=b'<env name="APP_KEY" value="' + SYNTHETIC_KEY + b'"/>'),
                        phpunit_fixture(extra=b'<env name="APP_ENV" value="production"/>'),
                        phpunit_fixture().replace(b'<php>', b'<other>').replace(b'</php>', b'</other>'),
                        phpunit_fixture().replace(b'<env name="APP_KEY"', b'<!--\n<env name="APP_KEY"')
                        .replace(b'\n</php>', b'\n-->\n</php>'),
                        phpunit_fixture().replace(b'/>\n\n</php>', b'/> ' + SYNTHETIC_KEY + b'\n</php>')]
            for index, content in enumerate(variants):
                with self.subTest(index=index):
                    self.write('phpunit.xml', content)
                    self.assertEqual(self.invoke('secrets', ['phpunit.xml'])[0], 1)
            self.write('docs/copied.xml', phpunit_fixture())
            self.assertEqual(self.invoke('secrets', ['docs/copied.xml'])[0], 1)

    def test_phpunit_parse_error_fails_closed_without_echoing_content(self):
        self.write('phpunit.xml', b'<broken secret="' + SYNTHETIC_KEY + b'"')
        status, output, errors = self.invoke('secrets', ['phpunit.xml'])
        self.assertEqual(status, 2)
        self.assertEqual(output, '')
        self.assertTrue('broken' not in errors and SYNTHETIC_KEY.decode() not in errors)

    def test_pem_sentinel_exemption_is_per_match_in_exact_context(self):
        self.write(PEM_FIXTURE_PATH, PEM_FIXTURE)
        self.assertEqual(self.invoke('secrets', [PEM_FIXTURE_PATH]), (0, '', ''))
        variants = [PEM_FIXTURE + SYNTHETIC_KEY,
                    PEM_FIXTURE.replace(b"'escaped-pem-sentinel')", b"'escaped-pem-sentinel') " + SYNTHETIC_KEY),
                    PEM_FIXTURE.replace(b'MIIpem-sentinel', b'different-body'),
                    PEM_FIXTURE.replace(b'test_multiline_pem_body_is_redacted', b'another_test'),
                    PEM_FIXTURE + PEM_FIXTURE]
        for index, content in enumerate(variants):
            with self.subTest(index=index):
                self.write(PEM_FIXTURE_PATH, content)
                self.assertEqual(self.invoke('secrets', [PEM_FIXTURE_PATH])[0], 1)
        self.write('tests/copied.py', PEM_FIXTURE)
        self.assertEqual(self.invoke('secrets', ['tests/copied.py'])[0], 1)

    def test_env_paths_are_never_opened_and_pii_scope_is_app_only(self):
        paths = ['.env', '.env.example', 'app/.env.testing', 'nested/.env.local/key',
                 'app/Clean.php', 'docs/not-app.md']
        with patch.object(scanner, 'read_file', return_value=b'clean') as read:
            self.assertEqual(self.invoke('secrets', paths), (0, '', ''))
            self.assertEqual([call.args[1] for call in read.call_args_list],
                             ['app/Clean.php', 'docs/not-app.md'])
            read.reset_mock()
            self.assertEqual(self.invoke('pii', paths), (0, '', ''))
            read.assert_called_once_with(self.root, 'app/Clean.php', link_payload=False)

    def test_empty_scan_scope_fails_closed(self):
        for mode, paths in [('secrets', []), ('secrets', ['.env']), ('pii', ['docs/only.md'])]:
            with self.subTest(mode=mode):
                self.assertEqual(self.invoke(mode, paths)[0], 2)

    def test_invalid_git_paths_fail_closed_before_read(self):
        for path in ('../outside', '/absolute', 'app/../outside', '', 'app//bad', 'app/./bad', 'bad\0path'):
            with patch.object(scanner, 'read_file') as read:
                self.assertEqual(self.invoke('secrets', [path])[0], 2)
                read.assert_not_called()

    def test_binary_policy_and_non_utf8_text_match_git_grep_behavior(self):
        self.write('docs/binary.bin', b'\0' + SYNTHETIC_KEY)
        self.assertEqual(self.invoke('secrets', ['docs/binary.bin']), (0, '', ''))
        self.write('docs/encoded.txt', b'\xff' + SYNTHETIC_KEY)
        self.assertEqual(self.invoke('secrets', ['docs/encoded.txt'])[0], 1)

    def test_missing_file_pii_symlink_symlink_parent_and_fifo_fail_closed(self):
        self.write('source.txt', SYNTHETIC_KEY)
        (self.root / 'app').mkdir()
        (self.root / 'app/Link.php').symlink_to(self.root / 'source.txt')
        (self.root / 'linked').symlink_to(self.root / 'app', target_is_directory=True)
        os.mkfifo(self.root / 'app/pipe')
        for path in ('app/Missing.php', 'app/Link.php', 'linked/Link.php', 'app/pipe', 'app'):
            with self.subTest(path=path):
                mode = 'pii' if path == 'app/Link.php' else 'secrets'
                status, output, errors = self.invoke(mode, [path])
                self.assertEqual((status, output), (2, ''))
                self.assertTrue(SYNTHETIC_KEY.decode() not in errors)

    def test_secrets_scan_symlink_payload_without_following_target(self):
        self.write('source.txt', SYNTHETIC_KEY)
        (self.root / 'public').mkdir()
        link = self.root / 'public/link'
        link.symlink_to('../source.txt')
        self.assertEqual(self.invoke('secrets', ['public/link']), (0, '', ''))
        self.assertEqual(self.invoke('secrets', ['public/link', 'source.txt']),
                         (1, '"source.txt"\n', ''))
        link.unlink()
        link.symlink_to('../.env')  # Tidak ada file .env yang dibuat/dibaca.
        self.assertEqual(self.invoke('secrets', ['public/link']), (0, '', ''))
        link.unlink()
        link.symlink_to(SYNTHETIC_KEY.decode())
        self.assertEqual(self.invoke('secrets', ['public/link']), (1, '"public/link"\n', ''))

    def test_regex_compile_and_execution_errors_are_never_clean(self):
        self.write('app/Source.php', b'clean')
        for mode, name in [('secrets', 'SECRET_PATTERN'), ('pii', 'PII_PATTERN')]:
            with self.subTest(mode=mode), patch.object(scanner, name, b'('):
                self.assertEqual(self.invoke(mode, ['app/Source.php'])[0], 2)
        with patch.object(scanner.re, 'compile') as compile_pattern:
            compile_pattern.return_value.finditer.side_effect = re.error('private-diagnostic')
            status, output, errors = self.invoke('pii', ['app/Source.php'])
        self.assertEqual((status, output), (2, ''))
        self.assertTrue('private-diagnostic' not in errors)

    def test_error_after_a_match_discards_partial_findings(self):
        self.write('app/A.php', b"Log::info('email');")
        status, output, errors = self.invoke('pii', ['app/A.php', 'app/Z-missing.php'])
        self.assertEqual((status, output), (2, ''))
        self.assertTrue('scan failed' in errors)

    def test_unsafe_or_sensitive_filenames_use_safe_identity(self):
        paths = ['app/new\n::error::injection.php', 'app/\x1b[31mfile.php',
                 'app/synthetic@example.test', 'app/' + SYNTHETIC_KEY.decode()]
        for path in paths:
            with patch.object(scanner, 'read_file', return_value=SYNTHETIC_KEY):
                status, output, errors = self.invoke('secrets', [path])
            self.assertEqual(status, 1)
            self.assertEqual(json.loads(output), 'path-sha256:' + hashlib.sha256(os.fsencode(path)).hexdigest())
            self.assertEqual(errors, '')

    def call_main(self, args):
        output, errors = io.StringIO(), io.StringIO()
        with patch.object(scanner, 'repository_root', return_value=self.root), \
             contextlib.redirect_stdout(output), contextlib.redirect_stderr(errors):
            status = scanner.main(args)
        return status, output.getvalue(), errors.getvalue()

    def test_argument_errors_do_not_echo_argv(self):
        for args in ([], ['--mode', 'synthetic-private-value'],
                     ['--mode', 'pii', '--unexpected', 'synthetic-private-value']):
            status, output, errors = self.call_main(args)
            self.assertEqual((status, output), (2, ''))
            self.assertTrue('synthetic-private-value' not in errors)

    def test_git_listing_failure_timeout_and_malformed_output_fail_closed(self):
        top = subprocess.CompletedProcess([], 0, os.fsencode(self.root) + b'\n', b'')
        failures = [OSError('private-error'),
                    subprocess.CalledProcessError(2, ['git'], output=b'private-output', stderr=b'private-error'),
                    subprocess.TimeoutExpired(['git'], 30, output=b'private-output'),
                    subprocess.CompletedProcess([], 0, b'app/file-without-nul', b''),
                    subprocess.CompletedProcess([], 0, b'', b''),
                    subprocess.CompletedProcess([], 0, b'app/a\0\0', b'')]
        for index, failure in enumerate(failures):
            with self.subTest(index=index), patch.object(scanner.subprocess, 'run', side_effect=[top, failure]):
                status, output, errors = self.call_main(['--mode', 'pii'])
                self.assertEqual((status, output), (2, ''))
                self.assertTrue('private-' not in errors)
        wrong_root = subprocess.CompletedProcess([], 0, b'/different-root\n', b'')
        with patch.object(scanner.subprocess, 'run', return_value=wrong_root):
            self.assertEqual(self.call_main(['--mode', 'secrets'])[0], 2)

    def test_git_success_with_warning_is_not_a_trustworthy_listing(self):
        top = subprocess.CompletedProcess([], 0, os.fsencode(self.root) + b'\n', b'')
        listing = subprocess.CompletedProcess([], 0, b'app/Clean.php\0', b'private-unreadable-directory')
        with patch.object(scanner.subprocess, 'run', side_effect=[top, listing]), \
             patch.object(scanner, 'read_file', return_value=b'clean'):
            status, output, errors = self.call_main(['--mode', 'pii'])
        self.assertEqual((status, output), (2, ''))
        self.assertTrue('private-' not in errors)

    def test_internal_error_never_echoes_content_or_traceback(self):
        with patch.object(scanner, 'read_file', side_effect=RuntimeError(SYNTHETIC_KEY.decode())):
            status, output, errors = self.invoke('secrets', ['app/Source.php'])
        self.assertEqual((status, output), (2, ''))
        self.assertTrue(SYNTHETIC_KEY.decode() not in errors and 'Traceback' not in errors)

    def git(self, *args):
        result = subprocess.run(['git', '--no-pager', '--no-optional-locks', '-C', str(self.root), *args],
                                capture_output=True, timeout=10)
        self.assertEqual(result.returncode, 0, 'Synthetic Git operation failed; output withheld.')

    def test_git_listing_tracks_changes_and_excludes_ignored_untracked_files(self):
        self.git('init', '-q')
        self.write('.gitignore', b'ignored.txt\n')
        self.write('tracked.txt', b'clean')
        self.git('add', '--', '.gitignore', 'tracked.txt')
        self.write('tracked.txt', SYNTHETIC_KEY)
        self.write('docs/untracked.md', SYNTHETIC_KEY)
        self.write('ignored.txt', SYNTHETIC_KEY)
        status, output, errors = self.call_main(['--mode', 'secrets'])
        self.assertEqual((status, errors), (1, ''))
        self.assertEqual([json.loads(line) for line in output.splitlines()], ['docs/untracked.md', 'tracked.txt'])
        self.git('add', '-f', '--', 'ignored.txt')
        self.assertIn('ignored.txt', scanner.git_paths(self.root))

    def test_real_cli_infers_script_root_and_preserves_exit_contract(self):
        self.git('init', '-q')
        self.write('scripts/ci/security_scan.py', SCRIPT.read_bytes())
        self.write('app/Source.php', b'clean')
        for mode in ('pii', 'secrets'):
            for expected in (0, 1, 2):
                with self.subTest(mode=mode, expected=expected):
                    self.write('app/Source.php', b'clean' if expected == 0 else
                               (b"Log::info('email');" if mode == 'pii' else SYNTHETIC_KEY))
                    self.git('add', '--', 'app/Source.php')
                    if expected == 2:
                        (self.root / 'app/Source.php').unlink()
                    result = subprocess.run([sys.executable, '-B', str(self.root / 'scripts/ci/security_scan.py'),
                                             '--mode', mode], cwd=self.root / 'app', capture_output=True, timeout=10)
                    self.assertEqual(result.returncode, expected, 'CLI status differs; output withheld.')
                    self.assertTrue(SYNTHETIC_KEY not in result.stdout + result.stderr)
                    self.assertTrue(b'Log::' not in result.stdout + result.stderr)


if __name__ == '__main__':
    unittest.main()
