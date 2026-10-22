#!/usr/bin/env python3
"""NFR-CICD-004: discover tests/python; hanya hasil all-pass boleh exit 0.

CLI tanpa argumen. Stdout satu JSON {status, counts, reasons, cases}; exit 1
untuk hasil ditolak/error, 2 untuk argumen invalid. Semua skip (termasuk
FontTools), expected failure, unexpected success, dan suite kosong ditolak.
Identitas/diagnostik asli tidak keluar: kasus memakai ordinal, log dibuang.
Kasus menambah test_index: posisi daun discovery (1-based), null bila tidak unik/terpetakan.
"""
from contextlib import contextmanager, redirect_stderr, redirect_stdout
import gc
import json
import os
from pathlib import Path
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]


@contextmanager
def quiet_output():
    """Tangkap output Python, handler logging lama, fd mentah, dan child process."""
    original, saved = (sys.stdout, sys.stderr), []
    with tempfile.TemporaryFile(mode='w+', encoding='utf-8') as log:
        try:
            for number in (1, 2):
                saved.append((number, os.dup(number)))
                os.dup2(log.fileno(), number)
            with redirect_stdout(log), redirect_stderr(log):
                yield log
        finally:
            try:
                for stream in (*original, log):
                    stream.flush()
            finally:
                for number, descriptor in saved:
                    os.dup2(descriptor, number)
                    os.close(descriptor)


def rejected(reason):
    return {'status': 'FAIL', 'counts': None, 'reasons': [reason], 'cases': []}


def iter_tests(suite):
    """Urutan daun discovery depth-first, sebelum TestSuite membersihkan isinya."""
    if isinstance(suite, unittest.BaseTestSuite):
        for test in suite:
            yield from iter_tests(test)
    else:
        yield suite


def _run_tests(suite, log):
    """Frame terpisah: referensi suite/result lepas sebelum output dipulihkan."""
    try:
        loader = unittest.TestLoader()
        if suite is None:
            suite = loader.discover(str(ROOT / 'tests/python'))
        planned = suite.countTestCases()
        # Simpan referensi agar cleanup suite tidak memungkinkan id objek dipakai ulang.
        discovered = list(iter_tests(suite))
        test_indices = {}
        for index, test in enumerate(discovered, 1):
            identity = id(test)
            test_indices[identity] = None if identity in test_indices else index
        result = unittest.TextTestRunner(stream=log, verbosity=0).run(suite)
        outcomes = {
            'failures': result.failures, 'errors': result.errors, 'skipped': result.skipped,
            'expected_failures': result.expectedFailures,
            'unexpected_successes': result.unexpectedSuccesses,
        }
        counts = {'tests': result.testsRun, 'import_errors': len(loader.errors)}
        counts.update({name: len(entries) for name, entries in outcomes.items()})
        reasons = [name.upper() for name, value in counts.items() if name != 'tests' and value]
        if not result.testsRun:
            reasons.append('NO_TESTS')
        if result.testsRun != planned or result.shouldStop:
            reasons.append('INCOMPLETE_SUITE')
        cases = []
        for name, entries in outcomes.items():
            for entry in entries:
                test = entry if name == 'unexpected_successes' else entry[0]
                if isinstance(test, unittest.case._SubTest):
                    test = test.test_case
                cases.append({'id': 'case-' + str(len(cases) + 1), 'reason': name.upper(),
                              'test_index': test_indices.get(id(test))})
        return {'status': 'FAIL' if reasons else 'PASS', 'counts': counts,
                'reasons': reasons, 'cases': cases}
    except BaseException:
        # SystemExit(0), interrupt, discovery, dan formatting error juga bukan sukses.
        return rejected('RUNNER_ERROR')


def run_tests(suite=None):
    """Suite injection hanya untuk regression sintetis; default selalu discovery."""
    try:
        with quiet_output() as log:
            summary = _run_tests(suite, log)
            # Siklus exception/traceback dapat menahan frame helper setelah ia kembali.
            gc.collect()
            return summary
    except BaseException:
        return rejected('RUNNER_ERROR')


def main(argv=None, *, suite=None):
    args = sys.argv[1:] if argv is None else argv
    summary = rejected('INVALID_ARGUMENTS') if args else run_tests(suite)
    print(json.dumps(summary, sort_keys=True))
    return 2 if args else int(summary['status'] != 'PASS')


if __name__ == '__main__':
    sys.dont_write_bytecode = True
    sys.exit(main())
