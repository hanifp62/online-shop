"""NFR-CICD-004/006: synthetic filesystem + mocked processes; never Docker/PHP/DB."""
import contextlib
import importlib.util
import io
import json
import os

import subprocess
import tempfile
import unittest
import xml.etree.ElementTree as ET
from pathlib import Path
from unittest.mock import patch

SCRIPT = Path(__file__).resolve().parents[2] / 'scripts/ci/mysql_integration.py'
SPEC = importlib.util.spec_from_file_location('ci_mysql', SCRIPT)
assert SPEC is not None and SPEC.loader is not None
ci = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(ci)
SECRET = 'synthetic-secret-that-must-not-be-printed'
IMAGE_ID = 'sha256:' + 'a' * 64
CID = 'b' * 64


def result(data=b'', code=0):
    return subprocess.CompletedProcess([], code, data, None)


class MysqlIsolationTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.base = Path(self.temp.name).resolve()
        self.repo = self.base / 'checkout'
        self.repo.mkdir()
        self.output = self.base / 'output'
        self.output.mkdir(mode=0o700)
        # No unit test may accidentally invoke a real executable.
        self.process = self.enterContext(patch.object(ci.subprocess, 'run', side_effect=AssertionError('REAL_PROCESS_FORBIDDEN')))
        self.lane = ci.Lane(self.output, self.repo)
        self.lane.image = IMAGE_ID
        self.container = {'id': CID, 'name': 'zafira-ci-' + self.lane.token + '-source',
                          'role': 'source', 'password': SECRET, 'port': None}

    def metadata(self):
        return {'id': CID, 'name': '/' + self.container['name'],
                'labels': {ci.OWNER_LABEL: self.lane.token, ci.ROLE_LABEL: 'source'},
                'image': IMAGE_ID, 'bindings': {'3306/tcp': [{'HostIp': '127.0.0.1', 'HostPort': ''}]},
                'mounts': [{'Type': 'tmpfs', 'Destination': '/var/lib/mysql'}], 'running': True,
                'ports': {'3306/tcp': [{'HostIp': '127.0.0.1', 'HostPort': '49152'}], '33060/tcp': None}}

    def test_output_requires_existing_private_canonical_outside_directory(self):
        for mode in (0o755, 0o770):
            self.output.chmod(mode)
            with self.assertRaisesRegex(ci.LaneError, 'OUTPUT_NOT_PRIVATE'):
                ci.private_output(self.output, self.repo)
        self.output.chmod(0o700)
        link = self.base / 'link'
        link.symlink_to(self.output, target_is_directory=True)
        with self.assertRaisesRegex(ci.LaneError, 'OUTPUT_NOT_PRIVATE'):
            ci.private_output(link, self.repo)
        self.repo.chmod(0o700)
        with self.assertRaisesRegex(ci.LaneError, 'OUTPUT_INSIDE_CHECKOUT'):
            ci.private_output(self.repo, self.repo)
        with self.assertRaises(FileNotFoundError):
            ci.private_output(self.base / 'absent', self.repo)

    def test_dotenv_is_rejected_by_name_without_opening_it(self):
        for name in ('.env', '.env.testing', '.env.production', '.env.backup'):
            file = self.repo / name
            file.touch()
            with patch.object(Path, 'open', side_effect=AssertionError('FILE_READ_FORBIDDEN')):
                with self.assertRaisesRegex(ci.LaneError, 'DOTENV_PRESENT'):
                    ci.clean_checkout(self.repo, {})
            file.unlink()
        (self.repo / '.env').symlink_to(self.base / 'missing')
        with self.assertRaisesRegex(ci.LaneError, 'DOTENV_PRESENT'):
            ci.clean_checkout(self.repo, {})
        self.process.assert_not_called()

    def test_config_cache_even_a_broken_link_is_rejected_before_git(self):
        cache = self.repo / 'bootstrap/cache'
        cache.mkdir(parents=True)
        (cache / 'config.php').symlink_to(self.base / 'missing')
        with self.assertRaisesRegex(ci.LaneError, 'CONFIG_CACHE_PRESENT'):
            ci.clean_checkout(self.repo, {})
        self.process.assert_not_called()

    def test_example_allowed_without_read_and_dirty_checkout_rejected(self):
        (self.repo / '.env.example').touch()
        responses = [result(str(self.repo).encode()), result(b' M app/example.php\0')]
        with patch.object(ci, 'invoke', side_effect=responses), patch.object(Path, 'open', side_effect=AssertionError('FILE_READ_FORBIDDEN')):
            with self.assertRaisesRegex(ci.LaneError, 'DIRTY_CHECKOUT'):
                ci.clean_checkout(self.repo, {})

    def test_clean_checkout_returns_commit_and_rejects_changed_commit(self):
        responses = [result(str(self.repo).encode()), result(), result(b'c' * 40)]
        with patch.object(ci, 'invoke', side_effect=responses) as command:
            self.assertEqual(ci.clean_checkout(self.repo, {}), 'c' * 40)
            self.assertIn(':(exclude).env*', command.call_args_list[1].args[0])
        with patch.object(ci, 'invoke', side_effect=responses):
            with self.assertRaisesRegex(ci.LaneError, 'CHECKOUT_CHANGED'):
                ci.clean_checkout(self.repo, {}, 'd' * 40)

    def test_inherited_database_docker_and_php_variables_are_not_forwarded(self):
        with patch.dict(os.environ, {'DB_HOST': SECRET, 'DB_PASSWORD': SECRET,
                                    'DOCKER_HOST': 'tcp://untrusted.invalid', 'DOCKER_CONTEXT': SECRET,
                                    'PHPRC': SECRET, 'APP_CONFIG_CACHE': SECRET}):
            lane = ci.Lane(self.output, self.repo)
        self.assertEqual(set(lane.env), {'PATH', 'HOME', 'TMPDIR', 'LC_ALL'})
        self.assertIn('unix:///var/run/docker.sock', lane.docker)
        self.assertNotIn(SECRET, repr(lane.env))

    def test_no_exec_on_mismatched_id_name_label_image_or_mount(self):
        variants = []
        for key, value in [('id', 'd' * 64), ('name', '/other'), ('image', 'sha256:' + 'd' * 64),
                           ('labels', {}), ('mounts', [{'Type': 'bind', 'Destination': '/var/lib/mysql'}])]:
            variant = self.metadata()
            variant[key] = value
            variants.append(variant)
        for value in variants:
            with self.subTest(value=value), patch.object(self.lane, 'docker_run', return_value=result(json.dumps(value).encode())) as docker:
                with self.assertRaises(ci.LaneError):
                    self.lane.sql(self.container, 'SELECT 1;')
                self.assertEqual(docker.call_count, 1)
                self.assertEqual(docker.call_args.args[0][:2], ['container', 'inspect'])

    def test_port_must_be_owned_loopback_and_stable(self):
        self.container['port'] = 49152
        for ip, port in [('0.0.0.0', '49152'), ('127.0.0.1', '49153')]:
            value = self.metadata()
            value['ports']['3306/tcp'][0] = {'HostIp': ip, 'HostPort': port}
            with patch.object(self.lane, 'docker_run', return_value=result(json.dumps(value).encode())):
                with self.assertRaisesRegex(ci.LaneError, 'UNSAFE_CONTAINER_PORT'):
                    self.lane.inspect(self.container, running=True)

    def test_mysql_password_only_in_subprocess_environment_and_query_in_stdin(self):
        with patch.object(self.lane, 'docker_run', side_effect=[result(json.dumps(self.metadata()).encode()), result(b'1\n')]) as docker:
            self.assertEqual(self.lane.sql(self.container, 'SELECT 1;'), '1\n')
        command = docker.call_args
        self.assertNotIn(SECRET, repr(command.args))
        self.assertEqual(command.kwargs['env']['MYSQL_PWD'], SECRET)
        self.assertEqual(command.kwargs['data'], b'SELECT 1;')
        self.assertIn(CID, command.args[0])

    def test_missing_docker_and_timeout_never_echo_exception_output(self):
        for error, reason in [(FileNotFoundError(SECRET), 'TOOL_UNAVAILABLE'),
                              (subprocess.TimeoutExpired(SECRET, 1, output=SECRET), 'PROCESS_TIMEOUT')]:
            self.process.side_effect = error
            with self.assertRaisesRegex(ci.LaneError, reason) as caught:
                ci.invoke(['docker'], env={})
            self.assertNotIn(SECRET, str(caught.exception))

    def test_readiness_is_bounded_and_checks_engine_schema(self):
        with patch.object(self.lane, 'mysql', return_value=result(code=1)), \
             patch.object(ci.time, 'monotonic', side_effect=[0, 0, 121]), patch.object(ci.time, 'sleep'):
            with self.assertRaisesRegex(ci.LaneError, 'READINESS_TIMEOUT'):
                self.lane.ready(self.container)
        for output in [b'8.0.40\twrong\tInnoDB\t1', b'8.4.7\twrong\tInnoDB\t1',
                       f'8.4.7\t{ci.SCHEMA}\tMyISAM\t1'.encode()]:
            with patch.object(self.lane, 'mysql', return_value=result(output)):
                with self.assertRaisesRegex(ci.LaneError, 'ENGINE_SCHEMA_MISMATCH'):
                    self.lane.ready(self.container)

    def test_nonempty_schema_refused(self):
        with patch.object(self.lane, 'sql', return_value='1\n'):
            with self.assertRaisesRegex(ci.LaneError, 'SCHEMA_NOT_EMPTY'):
                self.lane.empty_schema(self.container)

    def test_cleanup_reverifies_each_full_id_and_attempts_both_on_failure(self):
        other = dict(self.container, id='c' * 64, role='restore')
        self.lane.containers = [self.container, other]
        with patch.object(self.lane, 'inspect', side_effect=[ci.LaneError('OWNERSHIP_MISMATCH'), CID]) as inspect, \
             patch.object(self.lane, 'docker_run', return_value=result()) as docker:
            self.assertFalse(self.lane.cleanup())
        self.assertEqual(inspect.call_count, 2)
        docker.assert_called_once_with(['rm', '--force', CID], timeout=30)
        self.assertEqual(self.lane.summary['cleanup_failures'], 1)

    def test_create_uses_pinned_image_random_name_env_password_and_inspect_before_start(self):
        events = []
        def docker(args, **kwargs):
            events.append(args[0])
            if args[0] == 'create':
                password = kwargs['env']['MYSQL_ROOT_PASSWORD']
                self.assertNotIn(password, repr(args))
                self.assertNotEqual(password, SECRET)
                self.assertIn('127.0.0.1::3306', args)
                self.assertEqual(args[-1], IMAGE_ID)
                return result(CID.encode())
            self.assertEqual(args, ['start', CID])
            return result()
        def inspect(container):
            events.append('inspect')
            self.assertEqual(container['id'], CID)
            self.assertTrue(container['name'].startswith('zafira-ci-' + self.lane.token))
            return CID
        with patch.object(self.lane, 'docker_run', side_effect=docker), patch.object(self.lane, 'inspect', side_effect=inspect):
            created = self.lane.create('source')
        self.assertEqual(events, ['create', 'inspect', 'start'])
        self.assertEqual(self.lane.containers, [created])

    def test_create_timeout_tracks_intent_and_recovers_only_matching_owned_identity(self):
        with patch.object(self.lane, 'docker_run', side_effect=ci.LaneError('PROCESS_TIMEOUT', 124)):
            with self.assertRaises(ci.LaneError):
                self.lane.create('source')
        self.assertEqual(len(self.lane.containers), 1)
        self.assertIsNone(self.lane.containers[0]['id'])
        metadata = self.metadata()
        metadata['name'] = '/' + self.lane.containers[0]['name']
        with patch.object(self.lane, 'docker_run', side_effect=[result(json.dumps(metadata).encode()), result()]) as docker:
            self.assertTrue(self.lane.cleanup())
        self.assertEqual(docker.call_args_list[0].args[0][-1], self.lane.containers[0]['name'])
        self.assertEqual(docker.call_args_list[1].args[0], ['rm', '--force', CID])

    def test_owned_image_labels_do_not_hide_mismatched_ownership(self):
        value = self.metadata()
        value['labels']['org.opencontainers.image.title'] = 'mysql'
        with patch.object(self.lane, 'docker_run', return_value=result(json.dumps(value).encode())):
            self.assertEqual(self.lane.inspect(self.container), CID)
        value['labels'][ci.OWNER_LABEL] = 'foreign'
        with patch.object(self.lane, 'docker_run', return_value=result(json.dumps(value).encode())):
            with self.assertRaisesRegex(ci.LaneError, 'OWNERSHIP_MISMATCH'):
                self.lane.inspect(self.container)

    def test_php_environment_is_explicit_testing_tcp_and_private(self):
        self.container['port'] = 49152
        env = self.lane.php_environment(self.container)
        self.assertEqual(env['DB_CONNECTION'], 'mysql')
        self.assertEqual(env['DB_HOST'], '127.0.0.1')
        self.assertEqual(env['DB_PORT'], '49152')
        self.assertEqual(env['DB_DATABASE'], ci.SCHEMA)
        self.assertEqual(env['DB_PASSWORD'], SECRET)
        self.assertEqual(env['DB_URL'], '')
        self.assertEqual(env['DB_SOCKET'], '')
        self.assertEqual(env['APP_ENV'], 'testing')
        self.assertEqual(env['ZAFIRA_SAFE_MYSQL_TESTS'], '1')
        self.assertTrue(env['APP_KEY'].startswith('base64:'))
        self.assertEqual(env['LOG_CHANNEL'], 'stderr')
        for name in ('APP_CONFIG_CACHE', 'APP_SERVICES_CACHE', 'APP_PACKAGES_CACHE',
                     'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE', 'VIEW_COMPILED_PATH', 'LARAVEL_STORAGE_PATH'):
            self.assertTrue(Path(env[name]).is_relative_to(self.lane.runtime))
        with patch.object(ci, 'clean_checkout'), patch.object(self.lane, 'inspect') as inspect, \
             patch.object(ci, 'invoke', return_value=result(code=7)) as invoke:
            self.assertEqual(self.lane.php(self.container, ['artisan', 'migrate', '--force'], env), 7)
        inspect.assert_called_once_with(self.container, running=True)
        self.assertEqual(invoke.call_args.kwargs['stdout'], subprocess.DEVNULL)
        self.assertNotIn(SECRET, repr(invoke.call_args.args))

    def test_php_refuses_runtime_config_cache(self):
        env = self.lane.php_environment(self.container)
        Path(env['APP_CONFIG_CACHE']).touch()
        with patch.object(ci, 'clean_checkout'), patch.object(self.lane, 'inspect'), patch.object(ci, 'invoke') as invoke:
            with self.assertRaisesRegex(ci.LaneError, 'CONFIG_CACHE_PRESENT'):
                self.lane.php(self.container, ['artisan', 'migrate'], env)
            invoke.assert_not_called()

    def report(self, outcome=None, wrong_identity=False):
        root = ET.Element('testsuite')
        for index, identity in enumerate(sorted(ci.TEST_IDS)):
            classname, name = identity.split('::')
            case = ET.SubElement(root, 'testcase', classname=classname, name='test_other' if wrong_identity and index == 0 else name)
            if index == 0 and outcome:
                ET.SubElement(case, outcome, message=SECRET).text = SECRET
        path = self.lane.runtime / 'phpunit.xml'
        path.write_bytes(ET.tostring(root))
        path.chmod(0o600)
        return path

    def test_canonical_report_gate_requires_exact_three_executed_zero_skipped(self):
        stdout = io.StringIO()
        with contextlib.redirect_stdout(stdout), contextlib.redirect_stderr(stdout):
            self.assertTrue(ci.check_report(self.report(), ci.REPO))
            for outcome in ('skipped', 'failure', 'error'):
                self.assertFalse(ci.check_report(self.report(outcome), ci.REPO))
            self.assertFalse(ci.check_report(self.report(wrong_identity=True), ci.REPO))
        self.assertEqual(stdout.getvalue(), '')

    def test_report_diagnostics_include_safe_counts_and_exact_test_line_only(self):
        path = self.report('failure')
        root = ET.fromstring(path.read_bytes())
        failure = root.find('testcase/failure')
        if failure is None:
            self.fail('Synthetic report must contain a failure element.')
        failure.text = '\n'.join([SECRET, str(ci.REPO / ci.TEST_FILE) + ':72',
                                  '/untrusted/' + ci.TEST_FILE + ':73',
                                  str(ci.REPO / ci.TEST_FILE) + ':74' + SECRET])
        path.write_bytes(ET.tostring(root))
        diagnostics = {}
        self.assertFalse(ci.check_report(path, ci.REPO, diagnostics))
        self.assertEqual(diagnostics['counts'], {'tests': 3, 'passed': 2, 'failures': 1, 'errors': 0, 'skipped': 0})
        self.assertEqual(diagnostics['cases'], [{'id': 'testcase-1', 'reason': 'FAILURE'}])
        self.assertEqual(diagnostics['locations'], [{'id': 'testcase-1', 'test_lines': [72]}])
        encoded = json.dumps(diagnostics)
        self.assertNotIn(SECRET, encoded)
        self.assertNotIn(str(ci.REPO), encoded)
        self.assertNotIn(ci.TEST_FILE, encoded)
        self.assertNotIn('test_deadlock', encoded)

    def test_report_diagnostics_missing_invalid_and_wrong_identity_stay_failed(self):
        diagnostics = {}
        self.assertFalse(ci.check_report(self.lane.runtime / 'absent.xml', ci.REPO, diagnostics))
        self.assertEqual(diagnostics['reasons'], ['REPORT_UNAVAILABLE'])
        path = self.report(wrong_identity=True)
        self.assertFalse(ci.check_report(path, ci.REPO, diagnostics))
        self.assertEqual(diagnostics['status'], 'FAIL')
        self.assertIn('EXPECTED_TEST_SET_MISMATCH', diagnostics['reasons'])
        path.write_text('<invalid')
        self.assertFalse(ci.check_report(path, ci.REPO, diagnostics))
        self.assertEqual(diagnostics['counts'], None)
        self.assertEqual(diagnostics['locations'], [])
        self.assertEqual(diagnostics['reasons'], ['INVALID_XML'])

    def test_report_diagnostics_use_one_snapshot_for_counts_identity_and_outcome(self):
        path = self.report()
        passed = path.read_bytes()
        original_open = Path.open
        for outcome in ('failure', 'error', 'skipped'):
            rejected = self.report(outcome).read_bytes()
            for first, second, expected in ((passed, rejected, True), (rejected, passed, False)):
                with self.subTest(outcome=outcome, accepted=expected):
                    reads = []
                    def changing_open(target, *args, **kwargs):
                        if target == path:
                            reads.append(target)
                            return io.BytesIO(first if len(reads) == 1 else second)
                        return original_open(target, *args, **kwargs)
                    diagnostics = {}
                    with patch.object(Path, 'open', changing_open):
                        self.assertEqual(ci.check_report(path, ci.REPO, diagnostics), expected)
                    self.assertEqual(len(reads), 1, 'Every decision must use the same parsed snapshot.')
                    self.assertEqual(diagnostics['status'], 'PASS' if expected else 'FAIL')
                    self.assertEqual(diagnostics['counts']['passed'], 3 if expected else 2)

    def test_report_diagnostics_success_requires_the_exact_three_passes(self):
        diagnostics = {}
        self.assertTrue(ci.check_report(self.report(), ci.REPO, diagnostics))
        self.assertEqual(diagnostics['status'], 'PASS')
        self.assertEqual(diagnostics['counts']['passed'], 3)
        self.assertEqual(diagnostics['cases'], [])
        self.assertEqual(diagnostics['locations'], [])

    def test_report_missing_public_or_symlink_is_not_accepted(self):
        self.assertFalse(ci.check_report(self.lane.runtime / 'absent.xml', ci.REPO))
        report = self.report()
        report.chmod(0o644)
        self.assertFalse(ci.check_report(report, ci.REPO))
        link = self.lane.runtime / 'link.xml'
        link.symlink_to(report)
        self.assertFalse(ci.check_report(link, ci.REPO))

    def orchestration(self, migration_exit=0, producer_exit=0, report=True, changed_restore=False):
        source = dict(self.container, port=49152)
        target = dict(self.container, id='d' * 64, port=49153, role='restore')
        self.lane.containers = [source, target]
        self.enterContext(patch.object(ci, 'clean_checkout', return_value='c' * 40))
        self.enterContext(patch.object(self.lane, 'docker_run', return_value=result(IMAGE_ID.encode())))
        self.enterContext(patch.object(self.lane, 'create', side_effect=[source, target]))
        self.enterContext(patch.object(self.lane, 'ready', return_value='8.4.7'))
        self.empty = self.enterContext(patch.object(self.lane, 'empty_schema'))
        self.enterContext(patch.object(self.lane, 'migrations', return_value={'migration_count': 1}))
        self.php = self.enterContext(patch.object(self.lane, 'php', side_effect=[migration_exit, producer_exit]))
        self.gate = self.enterContext(patch.object(ci, 'check_report', return_value=report))
        self.seed = self.enterContext(patch.object(self.lane, 'sql', return_value=''))
        before = {'row_counts': {'ci_restore_probe': 2}, 'schema_sha256': 'e' * 64}
        after = dict(before, schema_sha256='f' * 64) if changed_restore else before.copy()
        self.enterContext(patch.object(self.lane, 'snapshot', side_effect=[before, after]))
        self.restore = self.enterContext(patch.object(self.lane, 'restore'))

    def test_orchestration_runs_normal_migration_exact_file_junit_and_restore(self):
        self.orchestration()
        self.lane.run()
        self.assertEqual(self.php.call_args_list[0].args[1], ['artisan', 'migrate', '--force', '--no-interaction'])
        args = self.php.call_args_list[1].args[1]
        self.assertEqual(args[0], 'vendor/bin/phpunit')
        self.assertEqual(args[-1], ci.TEST_FILE)
        self.assertIn('--log-junit', args)
        self.assertIn('--do-not-cache-result', args)
        self.assertEqual(self.empty.call_count, 2)
        self.restore.assert_called_once()
        self.assertEqual(self.lane.summary['restore'], 'PASS')
        self.assertEqual(self.lane.summary['tests'], 3)
        self.assertEqual(self.lane.summary['skipped'], 0)

    def test_producer_failure_preserved_even_if_report_passes(self):
        self.orchestration(producer_exit=17)
        with self.assertRaisesRegex(ci.LaneError, 'PHPUNIT_FAILED') as error:
            self.lane.run()
        self.assertEqual(error.exception.code, 17)
        self.assertEqual(self.lane.summary['producer_exit'], 17)
        self.gate.assert_called_once()
        self.restore.assert_not_called()
        self.seed.assert_not_called()

    def test_migration_failure_does_not_run_tests_or_restore(self):
        self.orchestration(migration_exit=9)
        with self.assertRaisesRegex(ci.LaneError, 'MIGRATION_FAILED') as error:
            self.lane.run()
        self.assertEqual(error.exception.code, 9)
        self.assertEqual(self.php.call_count, 1)
        self.restore.assert_not_called()

    def test_rejected_report_blocks_restore_even_when_producer_returns_zero(self):
        self.orchestration(report=False)
        with self.assertRaisesRegex(ci.LaneError, 'JUNIT_REJECTED'):
            self.lane.run()
        self.restore.assert_not_called()

    def test_restore_digest_mismatch_is_not_success(self):
        self.orchestration(changed_restore=True)
        with self.assertRaisesRegex(ci.LaneError, 'RESTORE_COMPARISON_FAILED'):
            self.lane.run()
        self.assertNotIn('restore', self.lane.summary)
        self.assertEqual(self.lane.summary['restore_comparison'], {'row_counts': True, 'schema_sha256': False})

    def test_restore_mismatch_diagnostics_never_publish_ddl_or_weaken_comparison(self):
        self.orchestration(changed_restore=True)
        self.lane._schema_snapshots = {
            'source': ('utf8mb4\tutf8mb4_unicode_ci\n', SECRET + ' AUTO_INCREMENT=4\n'),
            'restore': ('utf8mb4\tutf8mb4_unicode_ci\n', SECRET + ' AUTO_INCREMENT=1\n'),
        }
        with self.assertRaisesRegex(ci.LaneError, 'RESTORE_COMPARISON_FAILED'):
            self.lane.run()
        self.assertEqual(self.lane.summary['schema_comparison'], {
            'table_lines_equal_without_separators': False, 'columns_equal_in_order': True,
            'defaults_equal': True, 'ddl_equal': False,
            'ddl_equal_without_auto_increment': True, 'ddl_lines_equal_unordered': False,
            'source_auto_increment': [4], 'restored_auto_increment': [1],
        })
        self.assertNotIn(SECRET, json.dumps(self.lane.summary))
        self.assertNotIn('restore', self.lane.summary)

    def test_restore_diagnostic_distinguishes_table_clause_order_from_column_order(self):
        self.orchestration(changed_restore=True)
        columns = '  `a` int,\n  `b` int,\n'
        source = 'probe\tCREATE TABLE `probe` (\n' + columns + '  KEY `x` (`a`),\n  KEY `y` (`b`)\n) ENGINE=InnoDB\n'
        restored = 'probe\tCREATE TABLE `probe` (\n' + columns + '  KEY `y` (`b`),\n  KEY `x` (`a`)\n) ENGINE=InnoDB\n'
        self.lane._schema_snapshots = {'source': ('same', source), 'restore': ('same', restored)}
        with self.assertRaisesRegex(ci.LaneError, 'RESTORE_COMPARISON_FAILED'):
            self.lane.run()
        comparison = self.lane.summary['schema_comparison']
        self.assertTrue(comparison['table_lines_equal_without_separators'])
        self.assertTrue(comparison['columns_equal_in_order'])
        self.assertFalse(comparison['ddl_lines_equal_unordered'])
        self.assertNotIn('restore', self.lane.summary)

    def test_schema_shapes_are_fixed_vocabulary_not_sql_or_literal_content(self):
        self.assertEqual(ci.ddl_shape("  `collate` varchar(100) DEFAULT 'not null' COLLATE utf8mb4_unicode_ci"),
                         ['opaque', 'varchar', 'opaque', 'default', 'opaque', 'collate', 'utf8mb4_unicode_ci'])
        self.assertNotIn(SECRET, json.dumps(ci.ddl_shape(SECRET)))
        for text in ("'" + SECRET + "'", '`' + SECRET + '`', '123456789',
                     "'not \\' null'", '"not null"', "'not '' null'"):
            self.assertEqual(ci.ddl_shape(text), ['opaque'])

    def test_schema_shapes_bound_input_tokens_and_total_tokenizer_calls(self):
        self.assertEqual(ci.ddl_shape("'" + 'int ' * 1024), ['TRUNCATED_INPUT'])
        with patch.object(ci.re, 'sub', side_effect=AssertionError('Quoted fragments require the linear scanner.')):
            for quote in ("'", '"', '`'):
                self.assertEqual(ci.ddl_shape(quote + '\\' * 40), ['opaque'])
                self.assertEqual(ci.ddl_shape(quote + 'not null'), ['opaque'])
        self.assertEqual(ci.ddl_shape('int ' * 64), ['int'] * 64)
        self.assertEqual(ci.ddl_shape('int ' * 65), ['int'] * 64 + ['TRUNCATED_TOKENS'])
        self.orchestration(changed_restore=True)
        self.lane._schema_snapshots = {'source': ('same', 'int\n' * 21),
                                       'restore': ('same', 'bigint\n' * 21)}
        with patch.object(ci, 'ddl_shape', wraps=ci.ddl_shape) as shape, \
             self.assertRaisesRegex(ci.LaneError, 'RESTORE_COMPARISON_FAILED'):
            self.lane.run()
        self.assertEqual(shape.call_count, 40)
        self.assertEqual(len(self.lane.summary['schema_change_shapes']), 20)
        self.assertTrue(self.lane.summary['schema_change_shapes_truncated'])
        self.assertNotIn('restore', self.lane.summary)

    def test_restore_streams_unlinked_dump_and_records_only_digest(self):
        payload = b'synthetic SQL bytes never published'
        target = dict(self.container, role='restore', id='d' * 64)
        def execute(container, args, **kwargs):
            if args[0] == 'mysqldump':
                self.assertIs(container, self.container)
                kwargs['stdout'].write(payload)
                kwargs['stdout'].flush()
            else:
                self.assertIs(container, target)
                self.assertIn('--local-infile=0', args)
                self.assertEqual(kwargs['stdin'].read(), payload)
                self.assertEqual(kwargs['stdout'], subprocess.DEVNULL)
            return result()
        with patch.object(self.lane, 'empty_schema') as empty, patch.object(self.lane, 'execute', side_effect=execute) as execute_mock:
            self.lane.restore(self.container, target)
        empty.assert_called_once_with(target)
        self.assertEqual(execute_mock.call_count, 2)
        self.assertEqual(self.lane.summary['dump_sha256'], ci.digest(payload.decode()))
        self.assertFalse(any(p.is_file() for p in self.lane.runtime.rglob('*')))

    def test_empty_failed_dump_and_failed_import_block_restore(self):
        for failure in ('empty', 'dump', 'import'):
            with self.subTest(failure=failure):
                def execute(container, args, failure=failure, **kwargs):
                    if args[0] == 'mysqldump':
                        if failure == 'dump':
                            return result(code=11)
                        if failure != 'empty':
                            kwargs['stdout'].write(b'SQL')
                            kwargs['stdout'].flush()
                        return result()
                    return result(code=12)
                with patch.object(self.lane, 'empty_schema'), patch.object(self.lane, 'execute', side_effect=execute):
                    with self.assertRaises(ci.LaneError) as error:
                        self.lane.restore(self.container, dict(self.container, role='restore'))
                    self.assertEqual(error.exception.code, {'empty': 1, 'dump': 11, 'import': 12}[failure])
                self.assertNotIn('dump_sha256', self.lane.summary)

    def column_rows(self):
        return [
            ['ci_restore_probe', 'id', 1, 'int', 'NO', None, None, None, 'auto_increment', '', ''],
            ['ci_restore_probe', 'marker', 2, 'varchar(64)', 'NO', None,
             'utf8mb4', 'utf8mb4_unicode_ci', '', '', SECRET],
            ['ci_restore_probe', 'parent_id', 3, 'int', 'YES', None, None, None, '', '', ''],
            ['migrations', 'id', 1, 'int', 'NO', None, None, None, '', '', ''],
            ['migrations', 'migration', 2, 'varchar(255)', 'NO', None,
             'utf8mb4', 'utf8mb4_unicode_ci', '', '', ''],
            ['migrations', 'batch', 3, 'int', 'NO', None, None, None, '', '', ''],
        ]

    def schema_snapshot(self, *, ddl=None, columns=None, role='source'):
        def query(_, sql):
            if ddl is not None and sql.startswith('SHOW CREATE TABLE'):
                return ddl
            if columns is not None and 'FROM information_schema.columns ' in sql:
                return columns
            return self.snapshot_sql(sql)
        with patch.object(self.lane, 'sql', side_effect=query), \
             patch.object(self.lane, 'migrations', return_value={'migration_count': 1}):
            return self.lane.snapshot(dict(self.container, role=role))

    def test_snapshot_redundant_charset_equivalence_keeps_all_other_ddl_strict(self):
        ddl = self.snapshot_sql('SHOW CREATE TABLE')
        explicit = ddl.replace('COLLATE utf8mb4_unicode_ci', 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
        before = self.schema_snapshot(ddl=ddl)
        self.assertEqual(before, self.schema_snapshot(ddl=explicit, role='restore'))
        changes = [
            explicit.replace('SET utf8mb4 COLLATE utf8mb4_unicode_ci', 'SET latin1 COLLATE latin1_swedish_ci', 1),
            explicit.replace('COLLATE utf8mb4_unicode_ci', 'COLLATE utf8mb4_bin', 1),
            explicit.replace('COLLATE utf8mb4_unicode_ci', 'COLLATE utf8mb4_unicode_ci_unknown', 1),
            explicit.replace('COLLATE utf8mb4_unicode_ci', 'COLLATE utf8mb4_unicode_ci$unknown', 1),
            explicit.replace('varchar(64)', 'varchar(65)', 1),
            explicit.replace('varchar(64)', 'char(64)', 1),
            explicit.replace('`marker` varchar', '`renamed` varchar', 1),
            explicit.replace('  `parent_id` int DEFAULT NULL,\n', ''),
            explicit.replace('  PRIMARY KEY', '  `added` int,\n  PRIMARY KEY', 1),
            explicit.replace('  `parent_id` int DEFAULT NULL,\n', '').replace(
                '  `id` int NOT NULL AUTO_INCREMENT,\n',
                '  `parent_id` int DEFAULT NULL,\n  `id` int NOT NULL AUTO_INCREMENT,\n', 1),
            explicit.replace('DEFAULT NULL', 'DEFAULT 0', 1),
            explicit.replace('REFERENCES `ci_restore_probe` (`id`)', 'REFERENCES `ci_restore_probe` (`parent_id`)'),
            explicit.replace('  PRIMARY KEY (`id`),\n  KEY `parent` (`parent_id`),',
                             '  KEY `parent` (`parent_id`),\n  PRIMARY KEY (`id`),'),
            explicit.replace('KEY `parent`', 'UNIQUE KEY `parent`', 1),
            explicit.replace('AUTO_INCREMENT=3', 'AUTO_INCREMENT=4', 1),
            explicit.replace('ENGINE=InnoDB', 'ENGINE=MyISAM', 1),
            explicit.replace('COLLATE=utf8mb4_0900_ai_ci', 'COLLATE=utf8mb4_unicode_ci', 1),
        ]
        for index, changed in enumerate(changes):
            with self.subTest(change=index):
                self.assertNotEqual(before['schema_sha256'], self.schema_snapshot(ddl=changed)['schema_sha256'])

    def test_column_charset_canonicalization_preserves_quoted_bytes_and_expression_tails(self):
        clause = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        for name in ('`' + clause + '`', '`a``' + clause + '`', '`a\\`', '`DEFAULT`'):
            head = 'probe\tCREATE TABLE `probe` (\n  ' + name + ' varchar(64) '
            self.assertEqual(ci.canonical_ddl(head + clause + '\n)\n'),
                             head + 'COLLATE utf8mb4_unicode_ci\n)\n')
        for datatype in ('varchar(64)', 'text', "enum('CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', 'a,b')"):
            for boundary in ('DEFAULT', 'GENERATED', 'COMMENT', 'AS', 'REFERENCES', 'CHECK'):
                head = 'probe\tCREATE TABLE `probe` (\n  `a``b` ' + datatype + ' '
                tail = ' ' + boundary + " ('" + clause + "') " + clause + '\n) ENGINE=InnoDB\n'
                self.assertEqual(ci.canonical_ddl(head + clause + tail),
                                 head + 'COLLATE utf8mb4_unicode_ci' + tail)
        for quoted in ('`a``' + clause + '`', "'a\\'" + clause + "'", "'a''" + clause + "'",
                       '"a""' + clause + '"', "'line\n  `fake` varchar(64) " + clause + "'"):
            ddl = 'probe\tCREATE TABLE `probe` (\n  `value` varchar(64) DEFAULT ' + quoted + '\n)\n'
            self.assertEqual(ci.canonical_ddl(ddl), ddl)
        for quote in ("'", '"', '`'):
            ddl = 'probe\tCREATE TABLE `probe` (\n  ' + quote + '\\' * 10000 + clause
            self.assertEqual(ci.canonical_ddl(ddl), ddl)

    def test_column_charset_canonicalization_leaves_comments_constraints_and_options_exact(self):
        clause = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        for fragment in ('/* ' + clause + ' */', '-- ' + clause + '\n', '# ' + clause + '\n',
                         'CHECK (`a` ' + clause + ')', 'KEY `key` (`a` ' + clause + ')'):
            ddl = 'probe\tCREATE TABLE `probe` (\n  ' + fragment + '\n) ' + clause + '\n'
            self.assertEqual(ci.canonical_ddl(ddl), ddl)
        ddl = 'probe\tCREATE TABLE `probe` (\n  `a` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci\n)\n'
        self.assertEqual(ci.canonical_ddl(ddl), ddl)

    def test_snapshot_column_metadata_is_private_ordered_and_independent(self):
        before = self.schema_snapshot()
        rows = self.column_rows()
        encoded = ''.join(json.dumps(row) + '\n' for row in rows)
        self.assertEqual(before['columns_sha256'], ci.digest(encoded))
        self.assertEqual(before['column_count'], 6)
        for index, value in enumerate(('migrations', 'renamed', 3, 'char(64)', 'YES', SECRET + '\n\t',
                                       'latin1', 'utf8mb4_bin', 'INVISIBLE', 'lower(marker)', SECRET + '-changed')):
            changed = self.column_rows()
            changed[1][index] = value
            with self.subTest(field=index):
                after = self.schema_snapshot(columns=''.join(json.dumps(row) + '\n' for row in changed))
                self.assertNotEqual(before['columns_sha256'], after['columns_sha256'])
                self.assertEqual(before['schema_sha256'], after['schema_sha256'])
                self.assertEqual(after['column_count'], 6)
                self.assertNotIn(SECRET, json.dumps(after))
        for changed in (rows[::-1], rows[:-1]):
            after = self.schema_snapshot(columns=''.join(json.dumps(row) + '\n' for row in changed))
            self.assertNotEqual(before['columns_sha256'], after['columns_sha256'])
            self.assertEqual(after['column_count'], len(changed))
        for invalid in ('', SECRET, '[]\n', json.dumps([None] * 11) + '\n'):
            with self.assertRaisesRegex(ci.LaneError, 'INVALID_COLUMN_METADATA'):
                self.schema_snapshot(columns=invalid)

    def test_column_metadata_unicode_line_separators_are_not_record_delimiters(self):
        for separator in ('\u0085', '\u2028', '\u2029'):
            for field in (5, 10):
                with self.subTest(codepoint=ord(separator), field=field):
                    rows = self.column_rows()
                    rows[1][field] = 'before' + separator + 'after'
                    encoded = ''.join(json.dumps(row, ensure_ascii=False) + '\n' for row in rows)
                    snapshot = self.schema_snapshot(columns=encoded)
                    self.assertEqual(snapshot['column_count'], 6)
                    self.assertEqual(snapshot['columns_sha256'], ci.digest(encoded))
                    self.assertEqual(snapshot, self.schema_snapshot(columns=encoded, role='restore'))
                    with self.assertRaisesRegex(ci.LaneError, 'INVALID_COLUMN_METADATA'):
                        self.schema_snapshot(columns=encoded + '\n')

    def test_restore_rejects_column_metadata_mismatch_even_with_equivalent_ddl(self):
        ddl = self.snapshot_sql('SHOW CREATE TABLE')
        before = self.schema_snapshot(ddl=ddl)
        rows = self.column_rows()
        rows[1][6] = 'latin1'
        after = self.schema_snapshot(
            ddl=ddl.replace('COLLATE utf8mb4_unicode_ci', 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'),
            columns=''.join(json.dumps(row) + '\n' for row in rows), role='restore')
        self.assertEqual(before['schema_sha256'], after['schema_sha256'])
        self.orchestration()
        self.lane.snapshot.side_effect = [before, after]
        with self.assertRaisesRegex(ci.LaneError, 'RESTORE_COMPARISON_FAILED'):
            self.lane.run()
        self.assertFalse(self.lane.summary['restore_comparison']['columns_sha256'])
        self.assertTrue(self.lane.summary['restore_comparison']['schema_sha256'])
        self.assertNotIn('restore', self.lane.summary)
        self.assertNotIn(SECRET, json.dumps(self.lane.summary))

    def snapshot_sql(self, query):
        if query.startswith('SELECT TABLE_NAME, ENGINE'):
            return 'ci_restore_probe\tInnoDB\tBASE TABLE\nmigrations\tInnoDB\tBASE TABLE\n'
        if query.startswith('SELECT COUNT(*) FROM information_schema.'):
            return '0\n'
        if query.startswith('SELECT DEFAULT_CHARACTER_SET_NAME'):
            return 'utf8mb4\tutf8mb4_0900_ai_ci\n'
        if query.startswith('SHOW CREATE TABLE'):
            return ('ci_restore_probe\tCREATE TABLE `ci_restore_probe` (\n'
                    '  `id` int NOT NULL AUTO_INCREMENT,\n'
                    '  `marker` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'' + SECRET + '\',\n'
                    '  `parent_id` int DEFAULT NULL,\n'
                    '  PRIMARY KEY (`id`),\n  KEY `parent` (`parent_id`),\n'
                    '  CONSTRAINT `parent` FOREIGN KEY (`parent_id`) REFERENCES `ci_restore_probe` (`id`)\n'
                    ') ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci\n'
                    'migrations\tCREATE TABLE `migrations` (\n  `id` int NOT NULL,\n'
                    '  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,\n'
                    '  `batch` int NOT NULL\n) ENGINE=InnoDB\n')
        if query.startswith('SELECT JSON_ARRAY(TABLE_NAME, COLUMN_NAME'):
            self.assertEqual(query, 'SELECT JSON_ARRAY(TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, '
                             'IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_SET_NAME, COLLATION_NAME, EXTRA, '
                             'GENERATION_EXPRESSION, COLUMN_COMMENT) FROM information_schema.columns '
                             'WHERE TABLE_SCHEMA=DATABASE() ORDER BY CAST(TABLE_NAME AS BINARY), ORDINAL_POSITION;')
            return ''.join(json.dumps(row) + '\n' for row in self.column_rows())
        if query.startswith("SELECT 'ci_restore_probe'"):
            return 'ci_restore_probe\t2\nmigrations\t1\n'
        if query.startswith('SELECT id, marker'):
            return f'1\t{ci.digest(self.lane.token)}\tNULL\n2\t{ci.digest(self.lane.token + "-child")}\t1\n'
        if query == 'SELECT @@foreign_key_checks;':
            return '1\n'
        if query.startswith('SELECT TABLE_NAME, CONSTRAINT_NAME'):
            return f'ci_restore_probe\tci_probe_parent\tparent_id\tci_restore_probe\tid\t{ci.SCHEMA}\t1\n'
        if query.startswith('SELECT COUNT(*) FROM `ci_restore_probe` c'):
            self.assertIn('IS NOT NULL AND NOT EXISTS', query)
            return '0\n'
        if query.startswith('SELECT id, migration'):
            return '1\t0001\t1\n'
        self.fail('Unexpected synthetic query')

    def test_snapshot_compares_schema_migrations_counts_marker_and_fk_data(self):
        migrations = self.repo / 'database/migrations'
        migrations.mkdir(parents=True)
        (migrations / '0001.php').touch()
        with patch.object(self.lane, 'sql', side_effect=lambda _, query: self.snapshot_sql(query)):
            snap = self.lane.snapshot(self.container)
        self.assertEqual(snap['row_counts'], {'ci_restore_probe': 2, 'migrations': 1})
        self.assertEqual(snap['foreign_keys'], 1)
        self.assertEqual(snap['foreign_key_violations'], 0)
        self.assertEqual(snap['migration_count'], 1)
        self.assertEqual(snap['column_count'], 6)
        self.assertEqual(len(snap['columns_sha256']), 64)
        self.assertNotIn(SECRET, json.dumps(snap))
        self.assertEqual(len(snap['marker_sha256']), 64)
        self.assertNotIn(self.lane.token, json.dumps(snap))

    def test_snapshot_rejects_missing_marker_bad_fk_or_unmigrated_schema(self):
        migrations = self.repo / 'database/migrations'
        migrations.mkdir(parents=True)
        (migrations / '0001.php').touch()
        for prefix, output, reason in [
            ('SELECT id, marker', '', 'RESTORED_MARKER_MISMATCH'),
            ('SELECT @@foreign_key_checks', '0\n', 'FK_CHECKS_DISABLED'),
            ('SELECT COUNT(*) FROM `ci_restore_probe` c', '1\n', 'FOREIGN_KEY_VIOLATION'),
            ('SELECT id, migration', '', 'MIGRATION_SET_MISMATCH'),
            ('SELECT TABLE_NAME, ENGINE', 'ci_restore_probe\tMyISAM\tBASE TABLE\n', 'SCHEMA_ENGINE_MISMATCH'),
        ]:
            with self.subTest(reason=reason):
                def query(_, sql, output=output, prefix=prefix):
                    return output if sql.startswith(prefix) else self.snapshot_sql(sql)
                with patch.object(self.lane, 'sql', side_effect=query):
                    with self.assertRaisesRegex(ci.LaneError, reason):
                        self.lane.snapshot(self.container)

    def test_complete_lane_with_fake_processes_verifies_every_mutation_and_cleans_both(self):
        migrations = self.repo / 'database/migrations'
        migrations.mkdir(parents=True)
        (migrations / '0001.php').touch()
        state = {}
        inspected = None
        removed = []
        report_gate = ci.check_report

        def process(command, **kwargs):
            nonlocal inspected
            self.assertEqual(kwargs['stderr'], subprocess.DEVNULL)
            self.assertGreater(kwargs['timeout'], 0)
            for container in state.values():
                self.assertNotIn(container['password'], repr(command))
            if command[0] == 'git':
                if '--show-toplevel' in command:
                    return result(str(self.repo).encode())
                return result() if 'status' in command else result(b'c' * 40)
            if command[0] == 'php':
                self.assertEqual(inspected, '1' * 64)
                inspected = None
                self.assertEqual(kwargs['stdout'], subprocess.DEVNULL)
                env = kwargs['env']
                self.assertEqual(env['DB_HOST'], '127.0.0.1')
                self.assertEqual(env['DB_PORT'], '49152')
                self.assertEqual(env['DB_PASSWORD'], state['1' * 64]['password'])
                if command[1] == 'vendor/bin/phpunit':
                    self.report()
                else:
                    self.assertEqual(command[1:3], ['artisan', 'migrate'])
                return result()
            self.assertEqual(command[:len(self.lane.docker)], self.lane.docker)
            args = command[len(self.lane.docker):]
            if args[:2] == ['image', 'inspect']:
                self.assertEqual(args[-1], 'mysql:8.4')
                return result(IMAGE_ID.encode())
            if args[0] == 'create':
                cid = str(len(state) + 1) * 64
                name = args[args.index('--name') + 1]
                role = name.rsplit('-', 1)[1]
                metadata = self.metadata()
                metadata.update(id=cid, name='/' + name, running=False)
                metadata['labels'][ci.ROLE_LABEL] = role
                metadata['ports']['3306/tcp'][0]['HostPort'] = str(49152 + len(state))
                state[cid] = {'metadata': metadata, 'password': kwargs['env']['MYSQL_ROOT_PASSWORD'], 'marker': False}
                return result(cid.encode())
            if args[:2] == ['container', 'inspect']:
                inspected = args[-1]
                return result(json.dumps(state[inspected]['metadata']).encode())
            if args[0] in ('start', 'rm'):
                self.assertEqual(args[-1], inspected)
                if args[0] == 'start':
                    state[inspected]['metadata']['running'] = True
                else:
                    removed.append(inspected)
                inspected = None
                return result()
            self.assertEqual(args[:4], ['exec', '--interactive', '--env', 'MYSQL_PWD'])
            cid, program = args[4:6]
            self.assertEqual(cid, inspected)
            inspected = None
            self.assertEqual(kwargs['env']['MYSQL_PWD'], state[cid]['password'])
            if program == 'mysqldump':
                self.assertTrue(state[cid]['marker'])
                kwargs['stdout'].write(b'synthetic-dump')
                kwargs['stdout'].flush()
                return result()
            self.assertEqual(program, 'mysql')
            if kwargs.get('stdin') is not None:
                self.assertEqual(cid, '2' * 64)
                self.assertEqual(kwargs['stdin'].read(), b'synthetic-dump')
                state[cid]['marker'] = True
                return result()
            query = kwargs['input'].decode()
            if query.startswith('SELECT VERSION()'):
                return result(f'8.4.7\t{ci.SCHEMA}\tInnoDB\t1\n'.encode())
            if query.startswith('CREATE TABLE ci_restore_probe'):
                state[cid]['marker'] = True
                return result()
            if not query.startswith(('SELECT COUNT(*) FROM information_schema.', 'SELECT id, migration')):
                self.assertTrue(state[cid]['marker'])
            return result(self.snapshot_sql(query).encode())

        self.process.side_effect = process
        output = io.StringIO()
        with patch.object(ci.sys, 'platform', 'linux'), patch.object(ci, 'Lane', return_value=self.lane), \
             patch.object(ci, 'check_report', side_effect=lambda report, _, diagnostics: report_gate(report, ci.REPO, diagnostics)), \
             contextlib.redirect_stdout(output), contextlib.redirect_stderr(output):
            self.assertEqual(ci.main(['--output', str(self.output)]), 0)
        summary = json.loads(output.getvalue())
        self.assertEqual(summary['status'], 'PASS')
        self.assertEqual(summary['cleanup'], 'PASS')
        self.assertEqual(summary['restore'], 'PASS')
        self.assertEqual(summary['tests'], 3)
        self.assertEqual(summary['skipped'], 0)
        self.assertEqual(summary['junit_report']['counts']['passed'], 3)
        self.assertEqual(summary['junit_report']['status'], 'PASS')
        self.assertEqual(summary['snapshot']['marker_rows'], 2)
        self.assertEqual(removed, ['2' * 64, '1' * 64])
        self.assertNotIn('synthetic-dump', output.getvalue())
        for container in state.values():
            self.assertNotIn(container['password'], output.getvalue())
        self.assertFalse(any(path.suffix in ('.sql', '.dump') for path in self.lane.directory.rglob('*')))

    def run_main(self, failure=None, cleanup=True):
        def clean():
            self.lane.summary['cleanup'] = 'PASS' if cleanup else 'FAIL'
            return cleanup
        output = io.StringIO()
        with patch.object(ci.sys, 'platform', 'linux'), patch.object(ci, 'Lane', return_value=self.lane), \
             patch.object(self.lane, 'run', side_effect=failure), patch.object(self.lane, 'cleanup', side_effect=clean) as cleaner, \
             contextlib.redirect_stdout(output), contextlib.redirect_stderr(output):
            code = ci.main(['--output', str(self.output)])
        cleaner.assert_called_once()
        self.assertNotIn(SECRET, output.getvalue())
        self.assertNotIn(str(self.repo), output.getvalue())
        self.assertNotIn('Traceback', output.getvalue())
        return code, json.loads(output.getvalue())

    def assert_summary_io_failure(self, stage, failure=None, cleanup=True):
        original_dump, original_fdopen, original_replace = ci.json.dump, ci.os.fdopen, ci.os.replace
        reached = []

        def fail_write(value, output, **kwargs):
            if stage == 'partial':
                output.write('{"status": "PASS"')
            else:
                original_dump(value, output, **kwargs)
            reached.append(stage)
            raise OSError(SECRET)

        @contextlib.contextmanager
        def failing_stream(fd, *args, **kwargs):
            with original_fdopen(fd, *args, **kwargs) as output:
                if stage == 'flush':
                    class FlushFailure:
                        write = output.write
                        fileno = output.fileno

                        def flush(self):
                            reached.append(stage)
                            raise OSError(SECRET)

                    yield FlushFailure()
                else:
                    yield output
            if stage == 'close':
                reached.append(stage)
                raise OSError(SECRET)

        def fail_fsync(fd):
            reached.append(stage)
            raise OSError(SECRET)

        def fail_replace(source, destination):
            if stage == 'replace-after-rename':
                original_replace(source, destination)
            reached.append(stage)
            raise OSError(SECRET)

        with contextlib.ExitStack() as stack:
            if stage in ('partial', 'full'):
                stack.enter_context(patch.object(ci.json, 'dump', side_effect=fail_write))
            elif stage in ('flush', 'close'):
                stack.enter_context(patch.object(ci.os, 'fdopen', side_effect=failing_stream))
            elif stage == 'fsync':
                stack.enter_context(patch.object(ci.os, 'fsync', side_effect=fail_fsync))
            else:
                stack.enter_context(patch.object(ci.os, 'replace', side_effect=fail_replace))
            code, summary = self.run_main(failure, cleanup=cleanup)
        self.assertEqual(reached, [stage])
        self.assertEqual(code, failure.code if failure else 1)
        expected_reason = str(failure) if failure else ('SUMMARY_WRITE_FAILED' if cleanup else 'CLEANUP_FAILED')
        self.assertEqual(summary['reason'], expected_reason)
        self.assertEqual(summary['status'], 'FAIL')
        self.assertEqual(summary['cleanup'], 'PASS' if cleanup else 'FAIL')
        self.assertFalse(os.path.lexists(self.lane.directory / 'summary.json'))
        self.assertEqual(list(self.lane.directory.glob('.summary-*.tmp')), [])
        self.process.assert_not_called()

    def test_main_summary_partial_write_failure_does_not_publish_pass(self):
        self.assert_summary_io_failure('partial')

    def test_main_summary_full_write_failure_does_not_publish_pass(self):
        self.assert_summary_io_failure('full')

    def test_main_summary_flush_failure_does_not_publish_pass(self):
        self.assert_summary_io_failure('flush')

    def test_main_summary_fsync_failure_does_not_publish_pass(self):
        self.assert_summary_io_failure('fsync')

    def test_main_summary_close_failure_does_not_publish_pass(self):
        self.assert_summary_io_failure('close')

    def test_main_summary_replace_failure_does_not_publish_pass(self):
        self.assert_summary_io_failure('replace')

    def test_main_summary_ambiguous_replace_failure_removes_published_pass(self):
        self.assert_summary_io_failure('replace-after-rename')

    def test_main_preserves_producer_code_when_summary_also_fails(self):
        for cleanup in (True, False):
            for stage in ('partial', 'full', 'flush', 'fsync', 'close', 'replace', 'replace-after-rename'):
                with self.subTest(stage=stage, cleanup=cleanup):
                    self.assert_summary_io_failure(stage, ci.LaneError('PHPUNIT_FAILED', 17), cleanup=cleanup)

    def test_main_preserves_cleanup_failure_when_summary_also_fails(self):
        self.assert_summary_io_failure('replace', cleanup=False)

    def test_main_summary_published_only_after_flush_fsync_and_close(self):
        original_fdopen, original_fsync, original_replace = ci.os.fdopen, ci.os.fsync, ci.os.replace
        opened, events = [], []
        destination = self.lane.directory / 'summary.json'

        def track_open(*args, **kwargs):
            output = original_fdopen(*args, **kwargs)
            opened.append(output)
            return output

        def synced(fd):
            # A complete JSON document must reach the FD before fsync, not stay buffered.
            self.assertEqual(json.loads(os.pread(fd, 4096, 0))['status'], 'PASS')
            self.assertFalse(destination.exists())
            events.append('fsync')
            original_fsync(fd)

        def replace(source, target):
            self.assertTrue(opened[0].closed)
            self.assertFalse(destination.exists())
            self.assertEqual(Path(source).parent, self.lane.directory)
            self.assertEqual(Path(source).stat().st_mode & 0o777, 0o600)
            events.append('replace')
            original_replace(source, target)

        with patch.object(ci.os, 'fdopen', side_effect=track_open), \
             patch.object(ci.os, 'fsync', side_effect=synced), \
             patch.object(ci.os, 'replace', side_effect=replace):
            code, summary = self.run_main()
        self.assertEqual(code, 0)
        self.assertEqual(events, ['fsync', 'replace'])
        self.assertEqual(json.loads(destination.read_text()), summary)
        self.assertEqual(destination.stat().st_mode & 0o777, 0o600)
        self.assertEqual(list(self.lane.directory.glob('.summary-*.tmp')), [])

    def test_main_cleanup_failure_blocks_pass(self):
        code, summary = self.run_main(cleanup=False)
        self.assertEqual(code, 1)
        self.assertEqual(summary['status'], 'FAIL')
        self.assertEqual(summary['reason'], 'CLEANUP_FAILED')
        self.assertEqual((self.lane.directory / 'summary.json').stat().st_mode & 0o777, 0o600)

    def test_main_preserves_producer_code_when_cleanup_also_fails(self):
        code, summary = self.run_main(ci.LaneError('PHPUNIT_FAILED', 17), cleanup=False)
        self.assertEqual(code, 17)
        self.assertEqual(summary['cleanup'], 'FAIL')

    def test_main_redacts_unexpected_error_and_still_cleans(self):
        code, summary = self.run_main(ValueError(SECRET))
        self.assertEqual(code, 1)
        self.assertEqual(summary['reason'], 'INTERNAL_ERROR')
        self.assertEqual(summary['cleanup'], 'PASS')

    def test_main_signal_failure_still_cleans(self):
        code, summary = self.run_main(ci.LaneError('INTERRUPTED', 143))
        self.assertEqual(code, 143)
        self.assertEqual(summary['cleanup'], 'PASS')

    def test_main_refuses_nonlinux_and_arbitrary_target_override(self):
        output = io.StringIO()
        with patch.object(ci.sys, 'platform', 'darwin'), contextlib.redirect_stdout(output):
            self.assertEqual(ci.main(['--output', str(self.output)]), 1)
        self.assertEqual(json.loads(output.getvalue())['reason'], 'LINUX_REQUIRED')
        with contextlib.redirect_stderr(output), self.assertRaises(SystemExit) as error:
            ci.main(['--output', str(self.output), '--host', SECRET])
        self.assertEqual(error.exception.code, 2)
        self.assertNotIn(SECRET, output.getvalue())
        self.process.assert_not_called()


if __name__ == '__main__':
    unittest.main()
