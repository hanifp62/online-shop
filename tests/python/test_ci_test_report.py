"""NFR-CICD-004: synthetic PHPUnit JUnit gates, no application/DB/runtime tests."""

import json
import os
import subprocess
import sys
import tempfile
import unittest
import xml.etree.ElementTree as ET
from dataclasses import dataclass
from pathlib import Path
from typing import TypedDict

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / 'scripts/ci/check_test_report.py'
CLASS = 'Tests.Feature.SyntheticTest'
ALLOWED = f'{CLASS}::test_optional'
SENSITIVE = 'synthetic-private-value@example.invalid'
IDENTIFIER_TOKEN = 'github_pat_' + 'A' * 82  # Deliberately synthetic, valid identifier characters.


class GateSummary(TypedDict):
    status: str
    counts: dict[str, int] | None
    reasons: list[str]
    cases: list[dict[str, str]]


@dataclass(frozen=True)
class GateResult:
    returncode: int
    stdout: str
    stderr: str
    summary_text: str | None
    summary: GateSummary | None


def testcase(name='test_ok', outcome=None, classname=CLASS):
    case = ET.Element('testcase', classname=classname, name=name)
    if outcome:
        ET.SubElement(case, outcome, message=SENSITIVE).text = SENSITIVE
    return case


def suite(*children):
    element = ET.Element('testsuite', name='Synthetic')
    element.extend(children)
    cases = list(element.iter('testcase'))
    element.set('tests', str(len(cases)))
    for attribute, tag in [('failures', 'failure'), ('errors', 'error'), ('skipped', 'skipped')]:
        element.set(attribute, str(sum(len(case.findall(tag)) for case in cases)))
    return element


class CiTestReportTests(unittest.TestCase):
    def run_gate(self, report, *args, summary_target=None, stale_summary=False) -> GateResult:
        with tempfile.TemporaryDirectory(prefix='ci-junit-') as directory:
            path = Path(directory) / 'report.xml'
            if isinstance(report, ET.Element):
                path.write_bytes(ET.tostring(report, encoding='utf-8', xml_declaration=True))
            elif report is not None:
                path.write_bytes(report)
            original = path.read_bytes() if path.exists() else None
            summary_path = Path(directory) / summary_target if summary_target is not None else None
            if summary_path is not None:
                if stale_summary:
                    summary_path.write_text('{"status": "PASS"}', encoding='utf-8')
                args = (*args, '--summary-json', str(summary_path))
            completed = subprocess.run(
                [sys.executable, '-B', str(SCRIPT), str(path), *args],
                capture_output=True, text=True, timeout=10, check=False,
                env=dict(os.environ, PYTHONDONTWRITEBYTECODE='1'),
            )
            summary_text: str | None = None
            if summary_path is not None and summary_path != path and summary_path.is_file():
                summary_text = summary_path.read_text(encoding='utf-8')
            if original is not None:
                self.assertTrue(path.read_bytes() == original, 'Input report must not be overwritten')
            self.assertFalse(list(Path(directory).glob('.junit-summary-*')), 'Summary temporary file leaked')
        assert isinstance(completed.stdout, str)
        assert isinstance(completed.stderr, str)
        output = completed.stdout + completed.stderr + (summary_text or '')
        self.assertFalse(IDENTIFIER_TOKEN in output, 'Identifier-shaped synthetic token leaked')
        self.assertFalse(CLASS in output, 'Original test identity must never be printed')
        self.assertNotIn(SENSITIVE, output)
        self.assertNotIn(directory, output)
        self.assertNotIn('Traceback', output)
        return GateResult(
            returncode=completed.returncode,
            stdout=completed.stdout,
            stderr=completed.stderr,
            summary_text=summary_text,
            summary=json.loads(summary_text) if summary_text is not None else None,
        )

    def assert_rejected(self, report, *args) -> GateResult:
        result = self.run_gate(report, *args)
        self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
        self.assertIn('JUnit gate: FAIL', result.stdout)
        return result

    def test_passing_phpunit_report(self):
        root = ET.Element('testsuites')
        root.append(suite(suite(testcase(), testcase('test_other'))))
        result = self.run_gate(root)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn('tests=2 passed=2 failures=0 errors=0 skipped=0', result.stdout)

    def test_single_testsuite_root(self):
        result = self.run_gate(suite(testcase()))
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)

    def test_missing_file(self):
        self.assert_rejected(None)

    def test_empty_file_and_malformed_xml(self):
        for content in [b'', b'<testsuites>', b'<', b'\xff', b'<testsuite/><testsuite/>']:
            with self.subTest(content=content):
                self.assert_rejected(content)

    def test_wrong_format_and_namespaced_xml(self):
        for content in [b'OK (42 tests)', b'<report/>', b'<testsuite xmlns="urn:unknown"/>']:
            with self.subTest(content=content):
                self.assert_rejected(content)

    def test_zero_tests_even_with_claimed_summary(self):
        for element in [ET.Element('testsuites'), suite(), ET.Element('testsuite', tests='9')]:
            with self.subTest(attributes=element.attrib):
                self.assert_rejected(element)

    def test_failures_and_errors_never_allowlisted(self):
        for outcome in ['failure', 'error']:
            with self.subTest(outcome=outcome):
                result = self.assert_rejected(
                    suite(testcase('test_optional', outcome)), '--allow-skipped-test', ALLOWED)
                self.assertIn(f'{outcome} testcase-1', result.stdout)

    def test_unapproved_skip_is_not_passed(self):
        result = self.assert_rejected(suite(testcase(), testcase('test_optional', 'skipped')))
        self.assertIn('passed=1', result.stdout)
        self.assertIn('unexpected-skip testcase-2', result.stdout)

    def test_exact_allowlist_and_repeated_flags(self):
        second = f'{CLASS}::test_second'
        report = suite(testcase(), testcase('test_optional', 'skipped'), testcase('test_second', 'skipped'))
        result = self.run_gate(report, '--allow-skipped-test', ALLOWED, '--allow-skipped-test', second)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn('tests=3 passed=1 failures=0 errors=0 skipped=2', result.stdout)
        self.assertIn('allowed-skip testcase-2', result.stdout)
        self.assertIn('allowed-skip testcase-3', result.stdout)

    def test_allowlist_is_not_a_glob_substring_or_class_alias(self):
        for name in [CLASS + '::*', 'test_optional', ALLOWED[:-1], ALLOWED.lower(),
                     ALLOWED.replace('.', '\\'), ALLOWED + ' ']:
            with self.subTest(name=name):
                self.assert_rejected(suite(testcase(), testcase('test_optional', 'skipped')),
                                     '--allow-skipped-test', name)

    def test_data_sets_require_the_full_exact_name_and_are_redacted(self):
        name = 'test_optional with data set "' + SENSITIVE + '"'
        report = suite(testcase(), testcase(name, 'skipped'))
        self.assert_rejected(report, '--allow-skipped-test', ALLOWED)
        result = self.run_gate(report, '--allow-skipped-test', f'{CLASS}::{name}')
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn('allowed-skip testcase-2', result.stdout)

    def test_all_skipped_is_not_execution_even_if_allowlisted(self):
        self.assert_rejected(suite(testcase('test_optional', 'skipped')),
                             '--allow-skipped-test', ALLOWED)

    def test_unused_allowlist_does_not_require_a_skip(self):
        result = self.run_gate(suite(testcase('test_optional')), '--allow-skipped-test', ALLOWED)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)

    def test_summary_counters_cannot_hide_or_invent_outcomes(self):
        for key, value in [('tests', '0'), ('tests', '2'), ('failures', '1'),
                           ('errors', '1'), ('skipped', '1')]:
            with self.subTest(key=key, value=value):
                report = suite(testcase())
                report.set(key, value)
                self.assert_rejected(report)
        report = suite(testcase('test_bad', 'failure'))
        report.set('failures', '0')
        self.assert_rejected(report)

    def test_invalid_counters(self):
        for value in ['-1', 'nan', '1.0', '', ' 1', '+1', '1_0']:
            with self.subTest(value=value):
                report = suite(testcase())
                report.set('tests', value)
                self.assert_rejected(report)

    def test_parent_aggregate_is_checked_without_double_counting(self):
        root = ET.Element('testsuites', tests='2', failures='0', errors='0', skipped='0')
        root.append(suite(suite(testcase()), suite(testcase('test_other'))))
        result = self.run_gate(root)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn('tests=2 passed=2', result.stdout)
        root.set('errors', '1')
        self.assert_rejected(root)

    def test_aggregate_only_skip_cannot_bypass_exact_allowlist(self):
        root = suite(testcase(), ET.Element('testsuite', tests='1', skipped='1'))
        self.assert_rejected(root, '--allow-skipped-test', ALLOWED)

    def test_missing_test_identity(self):
        for attribute in ['name', 'classname']:
            with self.subTest(attribute=attribute):
                case = testcase()
                del case.attrib[attribute]
                self.assert_rejected(suite(case))

    def test_duplicate_test_identity(self):
        self.assert_rejected(suite(suite(testcase()), suite(testcase())))

    def test_conflicting_outcomes(self):
        case = testcase('test_optional', 'skipped')
        ET.SubElement(case, 'failure')
        self.assert_rejected(suite(case), '--allow-skipped-test', ALLOWED)

    def test_unknown_outcome_and_misplaced_elements(self):
        for tag in ['disabled', 'aborted', 'testsuite']:
            with self.subTest(tag=tag):
                self.assert_rejected(suite(testcase('test_unknown', tag)))
        report = suite(testcase())
        ET.SubElement(report, 'error').text = SENSITIVE
        self.assert_rejected(report)
        wrapped = ET.Element('wrapper')
        wrapped.append(testcase())
        self.assert_rejected(suite(wrapped))

    def test_disabled_status_is_not_silently_counted_as_passed(self):
        report = suite(testcase())
        report.set('disabled', '1')
        self.assert_rejected(report)
        case = testcase()
        case.set('status', 'notrun')
        self.assert_rejected(suite(case))

    def test_output_does_not_include_metadata_or_unsafe_names(self):
        case = testcase(SENSITIVE, 'error', classname=SENSITIVE)
        case.set('file', SENSITIVE)
        ET.SubElement(case, 'system-out').text = SENSITIVE
        report = suite(case)
        report.set('name', SENSITIVE)
        ET.SubElement(report, 'system-err').text = SENSITIVE
        properties = ET.SubElement(report, 'properties')
        ET.SubElement(properties, 'property', name='private', value=SENSITIVE)
        result = self.assert_rejected(report)
        self.assertIn('error testcase-1', result.stdout)

    def test_identifier_token_in_classname_is_opaque_for_error_and_allowed_skip(self):
        classname = 'Tests.Feature.' + IDENTIFIER_TOKEN
        for outcome in ['error', 'skipped']:
            identity = classname + '::test_optional'
            result = self.run_gate(suite(testcase(), testcase('test_optional', outcome, classname)),
                                   '--allow-skipped-test', identity)
            self.assertEqual(result.returncode, 1 if outcome == 'error' else 0)
            self.assertIn(('error' if outcome == 'error' else 'allowed-skip') + ' testcase-2', result.stdout)

    def test_identifier_token_in_method_is_opaque_for_error_and_allowed_skip(self):
        name = 'test_' + IDENTIFIER_TOKEN
        for outcome in ['error', 'skipped']:
            result = self.run_gate(suite(testcase(), testcase(name, outcome)),
                                   '--allow-skipped-test', CLASS + '::' + name)
            self.assertEqual(result.returncode, 1 if outcome == 'error' else 0)
            self.assertIn(('error' if outcome == 'error' else 'allowed-skip') + ' testcase-2', result.stdout)

    def test_summary_json_contains_only_counts_status_fixed_reasons_and_opaque_ids(self):
        for attribute in ['classname', 'name']:
            for outcome in ['error', 'skipped']:
                with self.subTest(attribute=attribute, outcome=outcome):
                    case = testcase('test_optional', outcome)
                    case.set(attribute, 'test_' + IDENTIFIER_TOKEN)
                    identity = case.attrib['classname'] + '::' + case.attrib['name']
                    result = self.run_gate(suite(testcase(), case), '--allow-skipped-test', identity,
                                           summary_target='summary.json')
                    rejected = outcome == 'error'
                    self.assertEqual(result.returncode, int(rejected))
                    self.assertEqual(result.summary, {
                        'status': 'FAIL' if rejected else 'PASS',
                        'counts': {'tests': 2, 'passed': 1, 'failures': 0,
                                   'errors': int(rejected), 'skipped': int(not rejected)},
                        'reasons': ['ERRORS'] if rejected else [],
                        'cases': [{'id': 'testcase-2', 'reason': 'ERROR' if rejected else 'ALLOWED_SKIP'}],
                    })

    def test_summary_json_success(self):
        report = suite(testcase())
        report.set('name', SENSITIVE)
        ET.SubElement(report, 'system-out').text = SENSITIVE
        result = self.run_gate(report, summary_target='summary.json')
        self.assertEqual(result.returncode, 0)
        self.assertEqual(result.summary, {
            'status': 'PASS',
            'counts': {'tests': 1, 'passed': 1, 'failures': 0, 'errors': 0, 'skipped': 0},
            'reasons': [], 'cases': [],
        })

    def test_summary_json_failure_and_unexpected_skip(self):
        result = self.run_gate(suite(testcase('test_failed', 'failure'), testcase('test_skip', 'skipped')),
                               summary_target='summary.json')
        self.assertEqual(result.returncode, 1)
        self.assertEqual(result.summary, {
            'status': 'FAIL',
            'counts': {'tests': 2, 'passed': 0, 'failures': 1, 'errors': 0, 'skipped': 1},
            'reasons': ['FAILURES', 'UNEXPECTED_SKIPS'],
            'cases': [{'id': 'testcase-1', 'reason': 'FAILURE'},
                      {'id': 'testcase-2', 'reason': 'UNEXPECTED_SKIP'}],
        })

    def test_missing_or_invalid_report_overwrites_stale_success_with_failure_summary(self):
        for report, reason in [(None, 'REPORT_NOT_READABLE'), (b'<broken', 'INVALID_XML'),
                               (suite(), 'NO_TESTS'),
                               (ET.Element('testsuite', tests='1'), 'COUNTER_MISMATCH')]:
            with self.subTest(reason=reason):
                result = self.run_gate(report, summary_target='summary.json', stale_summary=True)
                self.assertEqual(result.returncode, 1)
                self.assertEqual(result.summary, {
                    'status': 'FAIL', 'counts': None, 'reasons': [reason], 'cases': [],
                })

    def test_all_skipped_summary_still_fails(self):
        result = self.run_gate(suite(testcase('test_optional', 'skipped')),
                               '--allow-skipped-test', ALLOWED, summary_target='summary.json')
        self.assertEqual(result.returncode, 1)
        summary = result.summary
        assert summary is not None
        self.assertEqual(summary['status'], 'FAIL')
        self.assertEqual(summary['reasons'], ['NO_EXECUTED_TESTS'])
        counts = summary['counts']
        assert counts is not None
        self.assertEqual(counts['passed'], 0)

    def test_summary_write_errors_fail_closed_without_paths_or_partial_files(self):
        for report in [suite(testcase()), None, b'<broken']:
            for target in ['missing-parent/summary.json', '.']:
                with self.subTest(target=target):
                    result = self.run_gate(report, summary_target=target)
                    self.assertEqual(result.returncode, 1)
                    self.assertIn('JUnit gate: FAIL', result.stdout)
                    self.assertIn('SUMMARY_WRITE_FAILED', result.stdout)
                    self.assertNotIn('JUnit gate: PASS', result.stdout)
                    self.assertIsNone(result.summary)

    def test_summary_cannot_overwrite_input_report(self):
        result = self.run_gate(suite(testcase()), summary_target='report.xml')
        self.assertEqual(result.returncode, 1)
        self.assertIn('SUMMARY_WRITE_FAILED', result.stdout)

    def test_dtd_and_entities_are_rejected_including_utf16(self):
        xml = ('<!DOCTYPE testsuite [<!ENTITY private "' + SENSITIVE + '">]>'
               '<testsuite><testcase classname="Tests.Synthetic" name="test_ok">'
               '<system-out>&private;</system-out></testcase></testsuite>')
        for encoding in ['utf-8', 'utf-16']:
            with self.subTest(encoding=encoding):
                self.assert_rejected(xml.encode(encoding))

    def test_xml_entities_and_optional_metadata_are_supported(self):
        case = testcase('test_unicode with data set "é & 中文"')
        ET.SubElement(case, 'system-out').text = SENSITIVE
        report = suite(case)
        ET.SubElement(report, 'properties')
        result = self.run_gate(report)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)

    def test_cli_errors_do_not_echo_values(self):
        result = self.run_gate(suite(testcase()), '--unknown', SENSITIVE)
        self.assertEqual(result.returncode, 2)

    def test_fonttools_pin_matches_existing_provenance(self):
        record = json.loads((ROOT / 'resources/fonts/NotoSansSC-Regular.provenance.json').read_text())
        requirements = (ROOT / 'scripts/ci/requirements.txt').read_text().splitlines()
        packages = [line for line in requirements if line and not line.startswith('#')]
        self.assertEqual(packages, ['fonttools==' + record['tool']['fonttools_version']])


if __name__ == '__main__':
    unittest.main()
