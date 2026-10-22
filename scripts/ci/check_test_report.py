#!/usr/bin/env python3
"""NFR-CICD-004: fail-closed PHPUnit JUnit gate (Python stdlib only).

Usage: python3 scripts/ci/check_test_report.py FILE [--summary-json PATH]
       [--allow-skipped-test EXACT ...]
Repeat the flag for each exception. EXACT is the XML classname + '::' + name,
including a data-set suffix. PHPUnit 12 uses dotted classname values (not class).
No globs, aliases, default exceptions, console-log parsing or unittest XML dialect.

Accept unnamespaced <testsuites> with nested <testsuite> nodes, or a <testsuite> root.
Count testcase outcomes, cross-check every supplied aggregate, require at least
one executed case, and reject failures/errors or unapproved skips. PHPUnit emits
incomplete tests as skipped, so they are subject to the same exact allowlist.
Exit 0 = gate passed; 1 = bad/missing report or rejected results; 2 = CLI misuse.
The producer's exit status is a separate mandatory gate: never mask it with this
checker (JUnit does not encode every runner-level warning or nonzero exit).

Only counts, status, fixed reasons and opaque testcase-N ordinals are emitted.
Original identities are used only for exact matching, never for output, even if
syntactically valid identifiers. Paths, XML metadata, data-set labels, diagnostic
bodies, stdout/stderr and parser exception text are not logged or serialized.

--summary-json atomically replaces PATH with {status, counts, reasons, cases}.
Counts are null if the report cannot be validated; cases contain only opaque id
and fixed reason for non-passing cases. The parent directory must already exist.
Missing/invalid reports also produce a FAIL summary. A write error forces exit 1
and SUMMARY_WRITE_FAILED (no success output or partial JSON); do not upload an old
summary left by an unsuccessful write as evidence of this run. The input report
cannot be used as the summary destination.
"""
import argparse
from collections import Counter
import json
import os
from pathlib import Path
import re
import sys
import tempfile
import xml.etree.ElementTree as ET

MAX_REPORT_BYTES = 32 * 1024 * 1024
COUNTERS = ('tests', 'failures', 'errors', 'skipped')
OUTCOMES = {'failure': 'failures', 'error': 'errors', 'skipped': 'skipped'}
METADATA = {'properties', 'system-out', 'system-err'}
REPORT_REASONS = {
    'DTD_NOT_ALLOWED', 'REPORT_NOT_READABLE', 'REPORT_TOO_LARGE', 'INVALID_XML',
    'INVALID_COUNTER', 'INVALID_METADATA', 'MISSING_TEST_ID', 'DUPLICATE_TEST_ID',
    'UNSUPPORTED_TEST_STATUS', 'UNSUPPORTED_TEST_ELEMENT', 'CONFLICTING_TEST_OUTCOMES',
    'REPORT_TOO_DEEP', 'UNSUPPORTED_SUITE_ELEMENT', 'COUNTER_MISMATCH', 'DISABLED_TESTS',
    'UNSUPPORTED_ROOT', 'NO_TESTS',
}


class ReportError(Exception):
    """Only fixed, non-sensitive reason codes may be used as messages."""


class SafeTreeBuilder(ET.TreeBuilder):
    def doctype(self, name, pubid, system):
        # Reject DTDs before entity expansion, including UTF-16 encoded inputs.
        raise ReportError('DTD_NOT_ALLOWED')


class SafeArgumentParser(argparse.ArgumentParser):
    def error(self, message):
        self.print_usage(sys.stderr)
        self.exit(2, 'JUnit gate: invalid arguments; use --help.\n')


def read_xml(path):
    try:
        if not path.is_file():
            raise ReportError('REPORT_NOT_READABLE')
        with path.open('rb') as stream:
            content = stream.read(MAX_REPORT_BYTES + 1)
    except OSError:
        raise ReportError('REPORT_NOT_READABLE') from None
    if len(content) > MAX_REPORT_BYTES:
        raise ReportError('REPORT_TOO_LARGE')
    try:
        return ET.fromstring(content, parser=ET.XMLParser(target=SafeTreeBuilder()))
    except (ET.ParseError, ValueError, LookupError):
        raise ReportError('INVALID_XML') from None


def count_attribute(element, name):
    raw = element.get(name)
    if raw is None:
        return None
    if not re.fullmatch(r'[0-9]{1,10}', raw):
        raise ReportError('INVALID_COUNTER')
    return int(raw)


def validate_metadata(element):
    if element.tag == 'properties':
        if any(child.tag != 'property' or len(child) for child in element):
            raise ReportError('INVALID_METADATA')
    elif len(element):
        raise ReportError('INVALID_METADATA')


def read_case(element, cases, seen):
    classname, name = element.get('classname', ''), element.get('name', '')
    if not classname.strip() or not name.strip():
        raise ReportError('MISSING_TEST_ID')
    identity = classname + '::' + name
    if identity in seen:
        raise ReportError('DUPLICATE_TEST_ID')
    seen.add(identity)
    if element.get('status') not in (None, 'run') or count_attribute(element, 'disabled') not in (None, 0):
        raise ReportError('UNSUPPORTED_TEST_STATUS')
    outcomes = []
    for child in element:
        if child.tag in OUTCOMES and not len(child):
            outcomes.append(child.tag)
        elif child.tag in METADATA:
            validate_metadata(child)
        else:
            raise ReportError('UNSUPPORTED_TEST_ELEMENT')
    if len(outcomes) > 1:
        raise ReportError('CONFLICTING_TEST_OUTCOMES')
    outcome = outcomes[0] if outcomes else 'passed'
    cases.append((identity, outcome))
    counts = Counter(tests=1)
    if outcome in OUTCOMES:
        counts[OUTCOMES[outcome]] = 1
    return counts


def read_suite(element, cases, seen, depth=0):
    if depth > 64:
        raise ReportError('REPORT_TOO_DEEP')
    counts = Counter({name: 0 for name in COUNTERS})
    for child in element:
        if child.tag == 'testsuite':
            counts.update(read_suite(child, cases, seen, depth + 1))
        elif child.tag == 'testcase' and element.tag == 'testsuite':
            counts.update(read_case(child, cases, seen))
        elif child.tag in METADATA:
            validate_metadata(child)
        else:
            raise ReportError('UNSUPPORTED_SUITE_ELEMENT')
    for name in COUNTERS:
        declared = count_attribute(element, name)
        if declared is not None and declared != counts[name]:
            raise ReportError('COUNTER_MISMATCH')
    if count_attribute(element, 'disabled') not in (None, 0):
        raise ReportError('DISABLED_TESTS')
    return counts


def report_summary(path, allowed):
    try:
        root = read_xml(path)
        if root.tag not in ('testsuites', 'testsuite'):
            raise ReportError('UNSUPPORTED_ROOT')
        cases = []
        counts = read_suite(root, cases, set())
        if counts['tests'] == 0:
            raise ReportError('NO_TESTS')
    except ReportError as error:
        reason = str(error) if str(error) in REPORT_REASONS else 'INVALID_REPORT'
        return {'status': 'FAIL', 'counts': None, 'reasons': [reason], 'cases': []}

    unexpected_skips = any(outcome == 'skipped' and name not in allowed for name, outcome in cases)
    executed = counts['tests'] - counts['skipped']
    counts['passed'] = executed - counts['failures'] - counts['errors']
    reasons = []
    for rejected, reason in [(counts['failures'], 'FAILURES'), (counts['errors'], 'ERRORS'),
                             (unexpected_skips, 'UNEXPECTED_SKIPS'), (executed == 0, 'NO_EXECUTED_TESTS')]:
        if rejected:
            reasons.append(reason)
    safe_cases = []
    for index, (identity, outcome) in enumerate(cases, 1):
        if outcome == 'passed':
            continue
        if outcome == 'skipped':
            reason = 'ALLOWED_SKIP' if identity in allowed else 'UNEXPECTED_SKIP'
        else:
            reason = 'FAILURE' if outcome == 'failure' else 'ERROR'
        # Even class/method identifiers can be secrets. Do not derive output from them.
        safe_cases.append({'id': f'testcase-{index}', 'reason': reason})
    return {'status': 'FAIL' if reasons else 'PASS', 'counts': dict(counts),
            'reasons': reasons, 'cases': safe_cases}


def write_summary(path, summary, report_path):
    if (path.resolve() == report_path.resolve()
            or (path.exists() and report_path.exists() and path.samefile(report_path))):
        raise OSError('Summary destination conflicts with input.')
    temporary = None
    try:
        # Same-directory replacement avoids publishing truncated JSON on a write failure.
        with tempfile.NamedTemporaryFile(mode='w', encoding='utf-8', dir=path.parent,
                                         prefix='.junit-summary-', delete=False) as stream:
            temporary = Path(stream.name)
            json.dump(summary, stream, indent=2, sort_keys=True)
            stream.write('\n')
        os.replace(temporary, path)
    finally:
        if temporary is not None:
            temporary.unlink(missing_ok=True)


def print_summary(summary):
    heading = f'JUnit gate: {summary["status"]}'
    if summary['counts'] is not None:
        counts = summary['counts']
        heading += (f' tests={counts["tests"]} passed={counts["passed"]} failures={counts["failures"]}'
                    f' errors={counts["errors"]} skipped={counts["skipped"]}')
    print(heading)
    for reason in summary['reasons']:
        print(reason)
    for case in summary['cases']:
        print(f'{case["reason"].lower().replace("_", "-")} {case["id"]}')


def main(argv=None):
    parser = SafeArgumentParser(
        prog='check_test_report.py', description='Check PHPUnit JUnit results; no console-log parsing.')
    parser.add_argument('file', metavar='FILE', type=Path)
    parser.add_argument('--allow-skipped-test', metavar='EXACT', action='append', default=[],
                        help='Repeat for exact XML classname::name IDs (PHPUnit uses dotted classname).')
    parser.add_argument('--summary-json', metavar='PATH', type=Path,
                        help='Write a safe JSON summary atomically; parent directory must exist.')
    args = parser.parse_args(argv)
    summary = report_summary(args.file, set(args.allow_skipped_test))
    if args.summary_json is not None:
        try:
            write_summary(args.summary_json, summary, args.file)
        except (OSError, ValueError, RuntimeError):
            summary['status'] = 'FAIL'
            summary['reasons'].append('SUMMARY_WRITE_FAILED')
    print_summary(summary)
    return 0 if summary['status'] == 'PASS' else 1


if __name__ == '__main__':
    sys.exit(main())
