"""NFR-CICD-004: gate hasil, hanya unittest/artefak sintetis; tanpa DB/browser."""
import contextlib
import copy
import importlib.util
import io
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch


ROOT = Path(__file__).resolve().parents[2]


def load(name):
    spec = importlib.util.spec_from_file_location(name, ROOT / 'scripts/ci' / (name + '.py'))
    if spec is None or spec.loader is None:
        raise ImportError('Test module unavailable')
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


runner = load('run_python_tests')
browser = load('check_browser_report')
PRIVATE = 'synthetic-private@example.test'
STEPS = ['migrate', 'seed', 'provision', 'am01', 'am06_escape_phpunit', 'browser', 'am10_db']
STAMP = '2026-10-22T01:00:00Z'


def synthetic_suite(kind):
    class Case(unittest.TestCase):
        def id(self):
            return PRIVATE

        def runTest(self):
            print(PRIVATE)
            print(PRIVATE, file=sys.stderr)
            if kind in ('fail', 'expected_failure'):
                self.fail(PRIVATE)
            if kind == 'error':
                raise RuntimeError(PRIVATE)
            if kind == 'skip':
                self.skipTest(PRIVATE)
            if kind == 'subtest':
                with self.subTest(private=PRIVATE):
                    self.fail(PRIVATE)
    if kind in ('expected_failure', 'unexpected_success'):
        Case.runTest = unittest.expectedFailure(Case.runTest)
    return unittest.TestSuite([Case()])


def report_fixture():
    return {
        'AM-02': {'status': 'PASS', 'counts': [8] * 9 + [4], 'uniqueProducts': 76},
        'AM-06-keyboard': {
            'status': 'PASS', 'enter': True, 'space': True, 'escape': True,
            'closeButton': True, 'focusRestored': True, 'tabSteps': 12,
            'focusToBrowserUi': 0, 'pageContentLeaks': [],
        },
        'AM-10': {'status': 'PASS_HTTP',
                  'product': {'id': 17, 'slug': 'amadis-demo-stock-37', 'stock': 37}, 'requests': [
            {'quantity': quantity, 'httpStatus': status, 'orderId': order,
             'actor': chr(97 + index) * 64,
             'preparedItems': [{'productId': 17, 'quantity': quantity}],
             'orderItems': [{'productId': 17, 'quantity': quantity}] if status == 201 else None,
             'start': STAMP, 'finish': STAMP}
            for index, (quantity, status, order) in enumerate([(20, 201, 101), (20, 422, None), (17, 201, 102)])
        ]},
    }


class ResultGateTests(unittest.TestCase):
    def setUp(self):
        directory = tempfile.TemporaryDirectory(prefix='ci-result-gates-')
        self.addCleanup(directory.cleanup)
        self.directory = Path(directory.name)
        self.invariants = {'AM-01_db_invariant': 1, 'AM-10_db_invariant': 1,
                           'browser_rc': 0, 'escape_rc': 0}
        self.report = report_fixture()
        self.commands = 'step\tstart_utc\tend_utc\trc\tcommand\n' + ''.join(
            name + '\t' + STAMP + '\t' + STAMP + '\t0\tsynthetic command\n' for name in STEPS)
        self.write_reports()

    def write_reports(self):
        (self.directory / 'invariants.json').write_text(json.dumps(self.invariants))
        (self.directory / 'browser-results.json').write_text(json.dumps(self.report))
        (self.directory / 'commands.tsv').write_text(self.commands)

    def call(self, module, args, **kwargs):
        output, errors = io.StringIO(), io.StringIO()
        with contextlib.redirect_stdout(output), contextlib.redirect_stderr(errors):
            status = module.main(args, **kwargs)
        self.assertEqual(errors.getvalue(), '')
        self.assertTrue(PRIVATE not in output.getvalue(), 'Gate output leaked a synthetic diagnostic.')
        self.assertEqual(len(output.getvalue().splitlines()), 1)
        return status, json.loads(output.getvalue())

    def test_module_loader_rejects_missing_spec_or_loader(self):
        for spec in (None, importlib.util.spec_from_loader('synthetic', loader=None)):
            with patch.object(importlib.util, 'spec_from_file_location', return_value=spec), \
                 self.assertRaisesRegex(ImportError, '^Test module unavailable$'):
                load('synthetic')

    def test_python_pass_failure_error_skip_subtest_and_empty(self):
        for kind in ('pass', 'fail', 'error', 'skip', 'subtest', 'empty', 'expected_failure', 'unexpected_success'):
            with self.subTest(kind=kind):
                suite = unittest.TestSuite() if kind == 'empty' else synthetic_suite(kind)
                status, summary = self.call(runner, [], suite=suite)
                self.assertEqual(status, 0 if kind == 'pass' else 1)
                self.assertEqual(summary['status'], 'PASS' if kind == 'pass' else 'FAIL')
                self.assertEqual(summary['counts']['tests'], 0 if kind == 'empty' else 1)
                self.assertEqual(set(summary), {'status', 'counts', 'reasons', 'cases'})
                for case in summary['cases']:
                    self.assertRegex(case['id'], r'^case-[0-9]+$')

    def test_python_skip_is_not_unittest_success(self):
        with contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
            ordinary = unittest.TextTestRunner(stream=io.StringIO()).run(synthetic_suite('skip'))
        self.assertTrue(ordinary.wasSuccessful(), 'Baseline unittest permits skipped tests.')
        status, summary = self.call(runner, [], suite=synthetic_suite('skip'))
        self.assertEqual(status, 1)
        self.assertEqual(summary['counts']['skipped'], 1)

    def test_python_discovery_import_error_is_safe_and_fails(self):
        folder = self.directory / 'tests/python'
        folder.mkdir(parents=True)
        (folder / 'test_result_gate_import_error.py').write_text(
            'print(' + repr(PRIVATE) + ')\nraise ImportError(' + repr(PRIVATE) + ')\n')
        with patch.object(runner, 'ROOT', self.directory), patch.object(sys, 'path', list(sys.path)):
            status, summary = self.call(runner, [])
        self.assertEqual(status, 1)
        self.assertGreater(summary['counts']['errors'], 0)
        self.assertGreater(summary['counts']['import_errors'], 0)

    def test_python_runner_exception_never_becomes_success_or_raw_output(self):
        with patch.object(runner.unittest.TestLoader, 'discover', side_effect=RuntimeError(PRIVATE)):
            status, summary = self.call(runner, [])
        self.assertEqual(status, 1)
        self.assertEqual(summary['reasons'], ['RUNNER_ERROR'])

    def test_six_skips_cannot_be_reported_as_six_passes(self):
        for kind, expected in [('skip', 1), ('pass', 0)]:
            suite = unittest.TestSuite(synthetic_suite(kind) for _ in range(6))
            status, summary = self.call(runner, [], suite=suite)
            self.assertEqual(status, expected)
            self.assertEqual(summary['counts']['tests'], 6)
            self.assertEqual(summary['counts']['skipped'], 6 if kind == 'skip' else 0)

    def test_python_class_setup_skip_and_error_fail(self):
        for skip in (True, False):
            class Case(unittest.TestCase):
                @classmethod
                def setUpClass(cls):
                    raise unittest.SkipTest(PRIVATE) if skip else RuntimeError(PRIVATE)

                def runTest(self):
                    pass
            status, summary = self.call(runner, [], suite=unittest.TestSuite([Case()]))
            self.assertEqual(status, 1)
            self.assertEqual(summary['counts']['skipped' if skip else 'errors'], 1)

    def test_python_early_stop_and_discovery_exit_are_failures(self):
        class Stopped(unittest.TestSuite):
            def run(self, result, debug=False):
                result.stop()
                return result
        status, summary = self.call(runner, [], suite=Stopped([unittest.FunctionTestCase(lambda: None)]))
        self.assertEqual(status, 1)
        self.assertIn('INCOMPLETE_SUITE', summary['reasons'])
        for failure in (SystemExit(0), KeyboardInterrupt()):
            with patch.object(runner.unittest.TestLoader, 'discover', side_effect=failure):
                status, summary = self.call(runner, [])
            self.assertEqual((status, summary['reasons']), (1, ['RUNNER_ERROR']))

    def test_real_python_cli_captures_fd_logging_child_and_discovery_output(self):
        project = self.directory / 'project'
        script = project / 'scripts/ci/run_python_tests.py'
        script.parent.mkdir(parents=True)
        script.write_bytes((ROOT / 'scripts/ci/run_python_tests.py').read_bytes())
        folder = project / 'tests/python'
        folder.mkdir(parents=True)
        fixture = folder / 'test_synthetic_cli.py'
        source = (
            'import logging, os, subprocess, sys, unittest\n'
            'private = ' + repr(PRIVATE) + '\n'
            'print(private)\n'
            'logger = logging.getLogger("synthetic")\n'
            'logger.addHandler(logging.StreamHandler(sys.stderr))\n'
            'class Loud(unittest.TestCase):\n'
            '    def id(self): return private\n'
            '    def test_output(self):\n'
            '        print(private)\n'
            '        print(private, file=sys.stderr)\n'
            '        os.write(1, private.encode())\n'
            '        os.write(2, private.encode())\n'
            '        logger.warning(private)\n'
            '        subprocess.run([sys.executable, "-c", "print(" + repr(private) + ")"], check=True, timeout=5)\n'
        )
        for kind, expected in [('pass', 0), ('skip', 1), ('fail', 1), ('import', 1), ('empty', 1)]:
            content = source
            if kind in ('skip', 'fail'):
                content += '        self.' + ('skipTest' if kind == 'skip' else 'fail') + '(private)\n'
            if kind == 'import':
                content += 'raise ImportError(private)\n'
            fixture.write_text(content if kind != 'empty' else '')
            process = subprocess.run([sys.executable, '-B', str(script)], cwd=self.directory,
                                     capture_output=True, text=True, timeout=10)
            self.assertEqual(process.returncode, expected, 'Synthetic CLI exit mismatch; output withheld.')
            self.assertTrue(PRIVATE not in process.stdout + process.stderr)
            self.assertEqual(process.stderr, '')
            self.assertEqual(len(process.stdout.splitlines()), 1)
            self.assertEqual(json.loads(process.stdout)['status'], 'PASS' if expected == 0 else 'FAIL')

    def test_browser_valid_report_and_multiline_commands(self):
        self.commands = self.commands.replace('am01\t' + STAMP + '\t' + STAMP + '\t0\tsynthetic command\n',
                                             'am01\t' + STAMP + '\t' + STAMP + '\t0\tsynthetic command\n'
                                             "select 'category', synthetic;\nselect 'stock37', synthetic;\n")
        self.commands += "select 'order', synthetic;\n"
        self.write_reports()
        status, summary = self.call(browser, [str(self.directory)])
        self.assertEqual(status, 0)
        self.assertEqual(set(summary), {'status', 'counts'})
        self.assertEqual(summary['counts']['commands'], 7)
        self.assertEqual(summary['counts']['created'], 2)
        self.assertEqual(summary['counts']['rejected'], 1)

    def test_browser_each_invariant_and_rc_is_required_and_strict(self):
        original = self.invariants.copy()
        for name, expected in original.items():
            for value in (None, True, str(expected), 9):
                with self.subTest(name=name, kind=type(value).__name__):
                    self.invariants = {**original, name: value}
                    self.write_reports()
                    self.assertEqual(self.call(browser, [str(self.directory)])[0], 1)

    def test_browser_keyboard_leak_blocks_even_with_pass_status_and_zero_rc(self):
        self.report['AM-06-keyboard']['pageContentLeaks'] = [{'tag': PRIVATE, 'step': 3}]
        self.write_reports()
        status, summary = self.call(browser, [str(self.directory)])
        self.assertEqual((status, summary['status']), (1, 'FAIL'))

    def test_browser_pagination_keyboard_and_checkout_contradictions_fail(self):
        mutations = [
            ('AM-02', 'counts', [8] * 10), ('AM-02', 'uniqueProducts', 75), ('AM-02', 'status', 'FAIL'),
            ('AM-06-keyboard', 'status', 'FAIL'), ('AM-06-keyboard', 'pageContentLeaks', None),
            ('AM-06-keyboard', 'focusRestored', False), ('AM-10', 'status', 'PENDING'),
            ('AM-10', 'requests', []),
        ]
        for section, key, value in mutations:
            with self.subTest(section=section, key=key):
                self.report = report_fixture()
                self.report[section][key] = value
                self.write_reports()
                self.assertEqual(self.call(browser, [str(self.directory)])[0], 1)
        for statuses in ([422, 422, 422], [500, 422, 201], [201, 201, 201]):
            self.report = report_fixture()
            for order_id, (request, status) in enumerate(zip(self.report['AM-10']['requests'], statuses), 101):
                request['httpStatus'] = status
                request['orderId'] = order_id if status == 201 else None
                request['orderItems'] = copy.deepcopy(request['preparedItems']) if status == 201 else None
            self.write_reports()
            self.assertEqual(self.call(browser, [str(self.directory)])[0], 1)

    def test_browser_legacy_quantity_labels_without_observations_fail(self):
        del self.report['AM-10']['product']
        for request in self.report['AM-10']['requests']:
            for key in ('actor', 'preparedItems', 'orderItems'):
                del request[key]
        self.write_reports()
        self.assertEqual(self.call(browser, [str(self.directory)])[0], 1)

    def test_browser_shared_or_malformed_identity_evidence_fails(self):
        for actor in ('a' * 64, PRIVATE, None, 101):
            self.report = report_fixture()
            self.report['AM-10']['requests'][1]['actor'] = actor
            self.write_reports()
            self.assertEqual(self.call(browser, [str(self.directory)])[0], 1)

    def test_browser_observed_prepared_and_order_items_must_match_quantity_and_product(self):
        values = [None, [], [{'productId': 17, 'quantity': 17}],
                  [{'productId': 99, 'quantity': 20}], [{'productId': 17.0, 'quantity': 20}],
                  [{'productId': 17, 'quantity': 20.0}],
                  [{'productId': 17, 'quantity': 20}, {'productId': 18, 'quantity': 1}],
                  [{'productId': 17, 'quantity': 20, 'private': PRIVATE}]]
        for field in ('preparedItems', 'orderItems'):
            for index, value in enumerate(values):
                with self.subTest(field=field, index=index):
                    self.report = report_fixture()
                    self.report['AM-10']['requests'][0][field] = value
                    self.write_reports()
                    self.assertEqual(self.call(browser, [str(self.directory)])[0], 1)

    def test_browser_stock_fixture_requires_exact_slug_and_initial_stock(self):
        for field, value in [('slug', 'other-product'), ('stock', 36), ('stock', 37.0),
                             ('id', 0), ('id', '17'), ('id', True), ('id', 999)]:
            self.report = report_fixture()
            self.report['AM-10']['product'][field] = value
            self.write_reports()
            self.assertEqual(self.call(browser, [str(self.directory)])[0], 1)

    def test_browser_rejected_orders_have_no_items_and_one_observed_success_is_valid(self):
        self.report['AM-10']['requests'][1]['orderItems'] = []
        self.write_reports()
        self.assertEqual(self.call(browser, [str(self.directory)])[0], 1)
        self.report = report_fixture()
        for request in self.report['AM-10']['requests'][1:]:
            request.update(httpStatus=422, orderId=None, orderItems=None)
        self.write_reports()
        status, summary = self.call(browser, [str(self.directory)])
        self.assertEqual((status, summary['counts']['created']), (0, 1))

    def test_browser_review031c2_duplicate_created_order_ids_fail(self):
        requests = self.report['AM-10']['requests']
        self.assertEqual(self.call(browser, [str(self.directory)])[0], 0)
        requests[2]['orderId'] = requests[0]['orderId']
        self.write_reports()
        status, summary = self.call(browser, [str(self.directory)])
        self.assertEqual((status, summary), (1, {'status': 'FAIL', 'counts': None}))

    def test_browser_review031c2_requires_common_http_interval_with_milliseconds(self):
        cases = [
            ('serial-seconds', [('00.000', '01.000'), ('02.000', '03.000'), ('04.000', '05.000')], 1),
            ('serial-milliseconds', [('00.100', '00.200'), ('00.201', '00.300'), ('00.301', '00.400')], 1),
            ('partial-overlap', [('00.100', '00.200'), ('00.150', '00.250'), ('00.201', '00.300')], 1),
            ('rejected-request-disjoint', [('00.100', '00.300'), ('00.401', '00.500'), ('00.200', '00.400')], 1),
            ('common-overlap', [('00.100', '00.300'), ('00.150', '00.250'), ('00.200', '00.400')], 0),
            ('touching-boundary', [('00.100', '00.200'), ('00.200', '00.300'), ('00.150', '00.250')], 0),
        ]
        for label, intervals, expected in cases:
            with self.subTest(case=label):
                self.report = report_fixture()
                for request, (start, finish) in zip(self.report['AM-10']['requests'], intervals):
                    request['start'] = '2026-10-22T01:00:' + start + 'Z'
                    request['finish'] = '2026-10-22T01:00:' + finish + 'Z'
                self.write_reports()
                status, summary = self.call(browser, [str(self.directory)])
                self.assertEqual(status, expected)
                self.assertEqual(summary['status'], 'FAIL' if expected else 'PASS')

    def test_browser_missing_steps_failed_extra_and_duplicate_steps_fail(self):
        original = self.commands
        for step in STEPS:
            self.commands = '\n'.join(line for line in original.splitlines() if not line.startswith(step + '\t')) + '\n'
            self.write_reports()
            self.assertEqual(self.call(browser, [str(self.directory)])[0], 1)
        for row in ('extra\t' + STAMP + '\t' + STAMP + '\t2\t' + PRIVATE + '\n',
                    original.splitlines()[1] + '\n', 'bad\tmalformed\n', 'not a command record\n'):
            self.commands = original + row
            self.write_reports()
            self.assertEqual(self.call(browser, [str(self.directory)])[0], 1)

    def test_browser_missing_malformed_duplicate_and_unreadable_reports_are_safe(self):
        for name in ('invariants.json', 'browser-results.json', 'commands.tsv'):
            path = self.directory / name
            path.unlink()
            self.assertEqual(self.call(browser, [str(self.directory)])[0], 1)
            self.write_reports()
        for content in ('{"browser_rc":0,"browser_rc":9}', '{' + PRIVATE, 'null', '[]', '{"value":NaN}'):
            (self.directory / 'invariants.json').write_text(content)
            self.assertEqual(self.call(browser, [str(self.directory)])[0], 1)
        with patch.object(browser.Path, 'open', side_effect=OSError(PRIVATE)):
            self.assertEqual(self.call(browser, [str(self.directory)])[0], 1)

    def test_browser_duplicate_keys_cannot_hide_failed_results(self):
        cases = [('invariants.json', '"browser_rc": 0', '"browser_rc": 9, "browser_rc": 0'),
                 ('browser-results.json', '"focusRestored": true',
                  '"focusRestored": false, "focusRestored": true')]
        for name, old, replacement in cases:
            self.write_reports()
            path = self.directory / name
            path.write_text(path.read_text().replace(old, replacement, 1))
            self.assertEqual(self.call(browser, [str(self.directory)])[0], 1)

    def test_browser_oversize_and_invalid_encoding_fail_without_echo(self):
        for data in (b'\xff', b'x' * (2 * 1024 * 1024 + 1)):
            (self.directory / 'invariants.json').write_bytes(data)
            self.assertEqual(self.call(browser, [str(self.directory)])[0], 1)

    def test_browser_strict_types_missing_fields_and_request_consistency(self):
        original = report_fixture()
        mutations = [('AM-02', 'counts', [8.0] * 9 + [4]),
                     ('AM-02', 'uniqueProducts', 76.0),
                     ('AM-06-keyboard', 'tabSteps', 12.0),
                     ('AM-06-keyboard', 'focusToBrowserUi', -1),
                     ('AM-06-keyboard', 'focusToBrowserUi', 13),
                     ('AM-06-keyboard', 'enter', 1),
                     ('AM-10', 'requests', {}),
                     ('AM-10', 'requests', [None] * 3),
                     ('AM-10', 'requests', [20] * 3),
                     ('AM-10', 'requests', original['AM-10']['requests'][:1])]
        for section, key, value in mutations:
            self.report = copy.deepcopy(original)
            self.report[section][key] = value
            self.write_reports()
            self.assertEqual(self.call(browser, [str(self.directory)])[0], 1)
        for section in original:
            for key in original[section]:
                self.report = copy.deepcopy(original)
                del self.report[section][key]
                self.write_reports()
                self.assertEqual(self.call(browser, [str(self.directory)])[0], 1)
        for key, value in [('quantity', 20.0), ('orderId', None), ('httpStatus', '201'),
                           ('httpStatus', 201.0), ('start', PRIVATE), ('finish', '2026-10-21T00:00:00Z')]:
            self.report = copy.deepcopy(original)
            self.report['AM-10']['requests'][0][key] = value
            self.write_reports()
            self.assertEqual(self.call(browser, [str(self.directory)])[0], 1)

    def test_browser_extra_zero_step_allowed_but_bad_tsv_rejected(self):
        original = self.commands
        self.commands += 'extra\t' + STAMP + '\t' + STAMP + '\t0\t' + PRIVATE + '\n'
        self.write_reports()
        status, summary = self.call(browser, [str(self.directory)])
        self.assertEqual((status, summary['counts']['commands']), (0, 8))
        for content in ('', original.replace('step\tstart_utc', 'wrong\tstart_utc', 1),
                        original.replace('\t0\t', '\t-1\t', 1), original.replace('\t0\t', '\t00\t', 1),
                        original.replace(STAMP, PRIVATE, 1), original.replace('synthetic command', '', 1)):
            self.commands = content
            self.write_reports()
            self.assertEqual(self.call(browser, [str(self.directory)])[0], 1)

    def test_real_browser_cli_safe_on_valid_and_malformed_artifacts(self):
        command = [sys.executable, '-B', str(ROOT / 'scripts/ci/check_browser_report.py'), str(self.directory)]
        for expected in (0, 1):
            if expected:
                (self.directory / 'browser-results.json').write_text(PRIVATE)
            process = subprocess.run(command, capture_output=True, text=True, timeout=10)
            self.assertEqual(process.returncode, expected)
            self.assertTrue(PRIVATE not in process.stdout + process.stderr)
            self.assertEqual(process.stderr, '')
            self.assertEqual(set(json.loads(process.stdout)), {'status', 'counts'})

    def test_cli_argument_errors_are_safe(self):
        for module, args in [(runner, [PRIVATE]), (browser, []), (browser, [PRIVATE, PRIVATE])]:
            status, summary = self.call(module, args)
            self.assertEqual((status, summary['status']), (2, 'FAIL'))


if __name__ == '__main__':
    unittest.main()
