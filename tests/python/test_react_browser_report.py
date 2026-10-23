"""VERIFY-CLOSE: exact native browser report and cleanup gates, offline only."""
import contextlib
import copy
import importlib.util
import io
import json
from pathlib import Path
import tempfile
import unittest

REPO = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location('react_report', REPO / 'scripts/ci/check_react_browser_report.py')
if SPEC is None or SPEC.loader is None:
    raise ImportError('Report module unavailable')
gate = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(gate)


def report(summary=True):
    result = {'status': 'PASS', 'checks': list(gate.MANDATORY), 'consoleErrors': 0, 'unavailable': [],
              'expectedFaultCounts': {key: limits[0] for key, limits in gate.FAULTS.items()}}
    if summary:
        result.update(supervisorExit=0, cleanup='PASS')
    return result


class ReactReportTests(unittest.TestCase):
    def test_accepts_only_exact_31_stage_schema(self):
        self.assertEqual(len(gate.MANDATORY), 31)
        self.assertEqual(len(set(gate.MANDATORY)), 31)
        self.assertEqual(gate.validate(report()), report())
        self.assertEqual(gate.parse_report(json.dumps(report(False)), summary=False), report(False))
        for index in range(31):
            for mutation in ('missing', 'duplicate', 'replaced', 'reordered'):
                value = report()
                if mutation == 'missing':
                    value['checks'].pop(index)
                elif mutation == 'duplicate':
                    value['checks'].insert(index, value['checks'][index])
                elif mutation == 'replaced':
                    value['checks'][index] = 'unknown'
                else:
                    other = (index + 1) % 31
                    value['checks'][index], value['checks'][other] = value['checks'][other], value['checks'][index]
                with self.subTest(index=index, mutation=mutation), self.assertRaises(ValueError):
                    gate.validate(value)

    def test_old_29_plus_optional_contract_is_not_a_pass(self):
        formerly_optional = ['shop-account-completed-order-review', 'signed-unsubscribe-native-confirm-complete']
        value = report()
        value['checks'] = [stage for stage in value['checks'] if stage not in formerly_optional]
        value['unavailable'] = formerly_optional
        with self.assertRaises(ValueError):
            gate.validate(value)
        for unavailable in (formerly_optional, [formerly_optional[0]], [formerly_optional[1]], None, {}, False):
            value = report()
            value['unavailable'] = unavailable
            with self.subTest(unavailable=unavailable), self.assertRaises(ValueError):
                gate.validate(value)

    def test_schema_status_type_and_cleanup_fail_closed(self):
        for key in report():
            value = report()
            del value[key]
            with self.subTest(missing=key), self.assertRaises(ValueError):
                gate.validate(value)
        for key, bad in (('status', 'FAIL'), ('consoleErrors', True), ('consoleErrors', 1), ('supervisorExit', False),
                         ('supervisorExit', 1), ('cleanup', 'PENDING'), ('extra', 'private-url')):
            with self.subTest(key=key, bad=bad), self.assertRaises(ValueError):
                gate.validate({**report(), key: bad})
        with self.assertRaises(ValueError):
            gate.validate(report(False))
        with self.assertRaises(ValueError):
            gate.validate(report(), summary=False)

    def test_fault_allowance_bounds_not_weakened(self):
        for key, bounds in gate.FAULTS.items():
            for bad in (-1, bounds[1] + 1, True, '1', None):
                value = report()
                value['expectedFaultCounts'][key] = bad
                with self.subTest(key=key, bad=bad), self.assertRaises(ValueError):
                    gate.validate(value)
        value = report()
        value['expectedFaultCounts']['checkout-abort-requestfailed'] = 0
        with self.assertRaises(ValueError):
            gate.validate(value)
        for key in ('new-noise', 'checkout-abort-console'):
            value = report()
            if key == 'new-noise':
                value['expectedFaultCounts'][key] = 0
            else:
                del value['expectedFaultCounts'][key]
            with self.assertRaises(ValueError):
                gate.validate(value)

    def test_duplicate_fields_nonfinite_and_oversized_input_rejected(self):
        raw = json.dumps(report())
        for value in (raw.replace('"PASS"', '"PASS", "status":"PASS"', 1),
                      raw.replace('"consoleErrors": 0', '"consoleErrors": NaN'), raw + ' ' * 32768):
            with self.assertRaises(ValueError):
                gate.parse_report(value)

    def files(self, directory):
        root = Path(directory)
        (root / 'react-flow.json').write_text(json.dumps(report(False)))
        stamp = '2026-10-23T06:00:00Z'
        commands = 'step\tstart_utc\tend_utc\trc\n' + ''.join(
            f'{step}\t{stamp}\t{stamp}\t0\n' for step in ('migrate', 'seed', 'provision', 'browser'))
        (root / 'commands.tsv').write_text(commands)
        return root, commands

    def test_publish_requires_all_steps_and_cleanup_success_and_is_exclusive(self):
        with tempfile.TemporaryDirectory() as directory:
            root, _ = self.files(directory)
            self.assertEqual(gate.publish(root), report())
            self.assertEqual(gate.parse_report((root / 'summary.json').read_bytes()), report())
            with self.assertRaises(FileExistsError):
                gate.publish(root)

    def test_failed_missing_reordered_or_overlapping_command_cannot_publish(self):
        with tempfile.TemporaryDirectory() as directory:
            root, commands = self.files(directory)
            lines = commands.splitlines(keepends=True)
            mutations = [commands.replace('\t0\n', '\t1\n', 1), ''.join(lines[:-1]),
                         ''.join([lines[0], lines[2], lines[1], *lines[3:]]), commands + lines[-1],
                         commands.replace('Z', ''), commands.replace('browser\t2026-10-23T06:00:00Z',
                                                                      'browser\t2026-10-23T05:00:00Z')]
            for value in mutations:
                (root / 'commands.tsv').write_text(value)
                with self.assertRaises(ValueError):
                    gate.publish(root)
                self.assertFalse((root / 'summary.json').exists())

    def test_cli_failure_withholds_input_and_never_emits_pass(self):
        with tempfile.TemporaryDirectory() as directory:
            root, _ = self.files(directory)
            value = copy.deepcopy(report(False))
            value['checks'][0] = 'https://example.test/private?signature=not-for-evidence'
            (root / 'react-flow.json').write_text(json.dumps(value))
            output = io.StringIO()
            with contextlib.redirect_stdout(output):
                self.assertEqual(gate.main(['publish', str(root)]), 1)
            self.assertEqual(output.getvalue(), '{"status":"FAIL"}\n')
            self.assertFalse((root / 'summary.json').exists())


if __name__ == '__main__':
    unittest.main()
