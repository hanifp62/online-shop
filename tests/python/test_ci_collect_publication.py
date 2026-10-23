"""Offline NFR-CICD-002/005 publication collector contracts; no GitHub traffic."""
from datetime import datetime, timezone
import hashlib
import gzip
import http.client
import socket
import threading
import time
import tarfile
import zlib
import urllib.error
import urllib.request
import urllib.response
from email.message import Message
import io
import json
from pathlib import Path
from urllib.parse import parse_qs, urlsplit
import sys
import tempfile
import unittest
import warnings
from unittest.mock import patch
import zipfile

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT))
sys.path.insert(0, str(ROOT / 'scripts/ci'))
from scripts.ci import collect_publication as publication

react_report = publication.check_react_browser_report
SHA = 'a' * 40
REPO = 'hanifp62/online-shop'
STAMP = '2026-10-22T17:20:00Z'
END = '2026-10-22T17:30:00Z'


class StubResponse(urllib.response.addinfourl):
    msg: str

    def __init__(self, data, headers, url, code, message):
        stream = data if isinstance(data, io.BytesIO) else io.BytesIO(data)
        super().__init__(stream, headers, url, code)
        self.msg = message


def zip_bytes(files):
    output = io.BytesIO()
    with zipfile.ZipFile(output, 'w') as archive:
        for name, data in files.items():
            archive.writestr(name, data)
    return output.getvalue()


class Clock:
    def __init__(self):
        self.value = 0

    def now(self):
        return self.value

    def sleep(self, seconds):
        self.value += seconds


class FakeClient:
    def __init__(self, batches, artifacts=None, payloads=None):
        self.batches = batches
        self.artifacts = artifacts or []
        self.payloads = payloads or {}
        self.job_reads = 0
        self.requests = []

    def json(self, endpoint, deadline=None):
        if endpoint.endswith('/actions/runs/42'):
            return {'id': 42, 'head_sha': SHA, 'run_attempt': 2, 'event': 'push',
                    'status': 'completed', 'conclusion': 'success',
                    'repository': {'full_name': REPO}}
        raise AssertionError('Unexpected endpoint')

    def pages(self, endpoint, key, deadline=None):
        self.requests.append(endpoint)
        if key == 'jobs':
            index = min(self.job_reads, len(self.batches) - 1)
            self.job_reads += 1
            return self.batches[index]
        return self.artifacts

    def download(self, endpoint, target, limit, deadline):
        self.requests.append(endpoint)
        value = self.payloads[endpoint]
        if isinstance(value, Exception):
            target.write_bytes(b'partial')
            raise value
        if len(value) > limit:
            raise publication.Rejected('Too large.')
        target.write_bytes(value)
        return len(value), hashlib.sha256(value).hexdigest()


def jobs():
    return [{'name': name, 'status': 'completed', 'conclusion': 'success',
             'run_id': 42, 'head_sha': SHA, 'run_attempt': 2,
             'started_at': STAMP, 'completed_at': END}
            for name in ('quality', 'mysql', 'browser', 'release', 'php')]


def native_summary():
    return {'status': 'PASS', 'checks': list(react_report.MANDATORY), 'consoleErrors': 0,
            'unavailable': [], 'expectedFaultCounts': {key: bounds[0] for key, bounds in react_report.FAULTS.items()},
            'supervisorExit': 0, 'cleanup': 'PASS'}


def fixtures(native_report=None):
    payloads = {}
    artifacts = []
    expected = {'quality': ('phpunit-summary.json', 'python-summary.json'),
                'mysql': ('mysql-integration-' + 'a' * 32 + '/summary.json',),
                'browser': ('browser-summary.json', 'react-browser-evidence/summary.json'),
                'release': ('release.tar.gz', 'release-manifest.json')}
    for index, (kind, names) in enumerate(expected.items(), start=1):
        contents = {name: b'fixture' if kind == 'release' else b'{"status":"PASS"}' for name in names}
        if kind == 'browser':
            contents['react-browser-evidence/summary.json'] = (json.dumps(native_summary()).encode()
                                                              if native_report is None else native_report)
        data = zip_bytes(contents)
        payloads[f'repos/{REPO}/actions/artifacts/{index}/zip'] = data
        artifacts.append({'name': f'{"release-candidate" if kind == "release" else kind + "-summary"}-{SHA}',
                          'id': index, 'workflow_run': {'id': 42, 'head_sha': SHA},
                          'created_at': '2026-10-22T17:25:00Z',
                          'expires_at': '2026-11-05T17:25:00Z', 'expired': False,
                          'size_in_bytes': len(data),
                          'digest': 'sha256:' + hashlib.sha256(data).hexdigest()})
    return artifacts, payloads


class PublicationTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.network_guard = {'enabled': True}
        def block_network(event, args):
            if not cls.network_guard['enabled']:
                return
            if event in ('socket.getaddrinfo', 'socket.gethostbyname', 'socket.gethostbyname_ex'):
                raise AssertionError('DNS is forbidden in collector tests.')
            if event == 'socket.connect' and args[0].family in (socket.AF_INET, socket.AF_INET6):
                raise AssertionError('External connection is forbidden in collector tests.')
        sys.addaudithook(block_network)

    @classmethod
    def tearDownClass(cls):
        cls.network_guard['enabled'] = False

    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.output = Path(self.temp.name) / 'evidence'
        self.clock = Clock()
        self.utc = datetime(2026, 10, 23, tzinfo=timezone.utc)

    def collect(self, client, **kwargs):
        return publication.collect(client, REPO, 42, SHA, 2, 'push', self.output,
                                   clock=self.clock.now, now=lambda: self.utc, sleep=self.clock.sleep,
                                   wait=10, download_wait=10, **kwargs)

    def test_stale_to_complete_and_targeted_artifacts(self):
        artifacts, payloads = fixtures()
        client = FakeClient([jobs()[:-1], jobs()], artifacts, payloads)
        manifest = {'source': {'commit': SHA}, 'release_id': 'fixture-release',
                    'archive': {'sha256': 'b' * 64}}
        with patch.object(publication.release_artifact, 'verify', return_value=manifest) as verify:
            summary = self.collect(client)
        self.assertEqual(self.clock.value, 5)
        self.assertEqual(client.job_reads, 2)
        self.assertEqual(summary['attempt'], 2)
        self.assertEqual(summary['release_id'], 'fixture-release')
        self.assertEqual(len([request for request in client.requests if request.endswith('/zip')]), 4)
        self.assertTrue(all('/attempts/2/jobs' in request for request in client.requests if '/jobs' in request))
        self.assertTrue((self.output / 'publication-summary.json').is_file())
        self.assertFalse((self.output / 'mysql').exists())
        self.assertEqual(set(self.output.iterdir()),
                         {self.output / 'publication-summary.json', self.output / 'release'})
        self.assertEqual(len(list(self.output.glob('*.zip'))), 0)
        verify.assert_called_once()

    def test_stale_wait_is_bounded_and_does_not_publish(self):
        client = FakeClient([jobs()[:-1]])
        with self.assertRaisesRegex(publication.Rejected, 'consistency wait'):
            self.collect(client)
        self.assertEqual(self.clock.value, 10)
        self.assertFalse(self.output.exists())

    def test_run_identity_and_jobs_fail_closed(self):
        run = FakeClient([jobs()]).json('repos/x/actions/runs/42')
        for key, value in [('head_sha', 'b' * 40), ('run_attempt', 1), ('event', 'pull_request'),
                           ('status', 'in_progress'), ('conclusion', 'failure')]:
            with self.subTest(key=key), self.assertRaises(publication.Rejected):
                publication.check_run(dict(run, **{key: value}), REPO, 42, SHA, 2, 'push')
        variants = [jobs()[:-1], jobs() + [jobs()[0]],
                    [dict(job, conclusion='failure') if job['name'] == 'php' else job for job in jobs()],
                    [dict(job, conclusion='skipped') if job['name'] == 'php' else job for job in jobs()],
                    [dict(job, run_attempt=1) if job['name'] == 'php' else job for job in jobs()]]
        self.assertFalse(publication.check_jobs(variants[0], 42, SHA, 2))
        with self.assertRaisesRegex(publication.Rejected, 'Duplicate'):
            publication.check_jobs(variants[1], 42, SHA, 2)
        self.assertFalse(publication.check_jobs(variants[2], 42, SHA, 2))
        self.assertFalse(publication.check_jobs(variants[3], 42, SHA, 2))
        with self.assertRaisesRegex(publication.Rejected, 'identity'):
            publication.check_jobs(variants[4], 42, SHA, 2)
        for field, value in (('run_id', 43), ('head_sha', 'b' * 40),
                             ('run_attempt', None)):
            variant = [dict(job, **{field: value}) if job['name'] == 'php' else job
                       for job in jobs()]
            with self.subTest(field=field), self.assertRaisesRegex(publication.Rejected, 'identity'):
                publication.check_jobs(variant, 42, SHA, 2)

    def test_late_success_after_deadline_is_not_accepted(self):
        class Delayed(FakeClient):
            def pages(inner, endpoint, key, deadline=None):
                self.clock.value = 53
                return super().pages(endpoint, key, deadline=deadline)
        with self.assertRaisesRegex(publication.Rejected, 'consistency wait'):
            publication.wait_jobs(Delayed([jobs()]), REPO, 42, SHA, 2, 'push',
                                  clock=self.clock.now, sleep=self.clock.sleep, wait=30)
        self.assertFalse(self.output.exists())

    def test_metadata_page_read_obeys_deadline(self):
        def opener(request, timeout):
            self.assertEqual(timeout, 7)
            self.clock.value = 8
            return io.BytesIO(b'{"jobs":[]}')
        client = publication.Github('synthetic', opener=opener, clock=self.clock.now)
        with self.assertRaisesRegex(publication.Rejected, 'deadline'):
            client.pages('repos/a/b/actions/runs/42/attempts/2/jobs', 'jobs', deadline=7)

    def test_artifact_provenance_and_uniqueness(self):
        artifacts, _ = fixtures()
        for variant in (artifacts[:-1], artifacts + [artifacts[0]],
                        [dict(artifacts[0], created_at='2026-10-22T17:10:00Z')] + artifacts[1:],
                        [dict(artifacts[0], workflow_run={'id': 43, 'head_sha': SHA})] + artifacts[1:],
                        [dict(artifacts[0], expires_at='2026-10-22T17:25:00Z')] + artifacts[1:],
                        [dict(artifacts[0], expires_at='not-a-date')] + artifacts[1:]):
            with self.subTest(variant=variant), self.assertRaises(publication.Rejected):
                publication.check_artifacts(variant, SHA, 42, jobs(), lambda: self.utc)

    def test_expiration_during_download_never_publishes(self):
        artifacts, payloads = fixtures()
        client = FakeClient([jobs()], artifacts, payloads)
        def advance(*args, **kwargs):
            self.utc = datetime(2026, 11, 6, tzinfo=timezone.utc)
            return {'source': {'commit': SHA}, 'release_id': 'fixture',
                    'archive': {'sha256': 'b' * 64}}
        with patch.object(publication.release_artifact, 'verify', side_effect=advance):
            with self.assertRaisesRegex(publication.Rejected, 'expired during'):
                self.collect(client)
        self.assertFalse(self.output.exists())

    def test_browser_artifact_nested_contract_and_unknown_path(self):
        payload = zip_bytes({'browser-summary.json': b'{"status":"PASS"}',
                             'react-browser-evidence/summary.json': json.dumps(native_summary()).encode()})
        with tempfile.TemporaryDirectory() as directory:
            folder = Path(directory)

            archive = folder / 'browser.zip'
            archive.write_bytes(payload)
            output = folder / 'extracted'
            output.mkdir()
            publication.extract_expected(archive, output,
                frozenset({'browser-summary.json', 'react-browser-evidence/summary.json'}), len(payload))
            self.assertTrue((output / 'react-browser-evidence/summary.json').is_file())
            with self.assertRaisesRegex(publication.Rejected, 'entries mismatch'):
                publication.extract_expected(archive, output,
                    frozenset({'browser-summary.json', 'summary.json'}), len(payload))

    def test_native_browser_summary_uses_strict_parser_on_raw_bytes(self):
        report = native_summary()
        self.assertEqual(len(report['checks']), 31)
        self.assertIn('shop-account-completed-order-review', report['checks'])
        self.assertIn('signed-unsubscribe-native-confirm-complete', report['checks'])
        raw = json.dumps(report).encode()
        artifacts, payloads = fixtures(raw)
        manifest = {'source': {'commit': SHA}, 'release_id': 'fixture-release',
                    'archive': {'sha256': 'b' * 64}}
        with patch.object(react_report, 'parse_report', wraps=react_report.parse_report) as parse, \
             patch.object(publication.release_artifact, 'verify', return_value=manifest):
            summary = self.collect(FakeClient([jobs()], artifacts, payloads))
        parse.assert_called_once_with(raw)
        self.assertEqual(summary['status'], 'ok')
        self.assertFalse((self.output / 'browser').exists())

    def assert_native_report_rejected(self, raw):
        artifacts, payloads = fixtures(raw)
        manifest = {'source': {'commit': SHA}, 'release_id': 'fixture-release',
                    'archive': {'sha256': 'b' * 64}}
        with patch.object(publication.release_artifact, 'verify', return_value=manifest) as verify, \
             self.assertRaisesRegex(publication.Rejected, '^Invalid native browser summary\\.$'):
            self.collect(FakeClient([jobs()], artifacts, payloads))
        verify.assert_not_called()
        self.assertFalse(self.output.exists())
        self.assertEqual(list(Path(self.temp.name).iterdir()), [])

    def test_native_browser_summary_rejects_29_and_missing_flows(self):
        formerly_optional = ['shop-account-completed-order-review', 'signed-unsubscribe-native-confirm-complete']
        for missing in (formerly_optional, [formerly_optional[0]], [formerly_optional[1]]):
            for unavailable in ([], missing):
                report = native_summary()
                report['checks'] = [name for name in report['checks'] if name not in missing]
                report['unavailable'] = unavailable
                with self.subTest(missing=missing, unavailable=unavailable):
                    self.assert_native_report_rejected(json.dumps(report).encode())
        for mutation in ('duplicate', 'reordered', 'unknown'):
            report = native_summary()
            if mutation == 'duplicate':
                report['checks'][1] = report['checks'][0]
            elif mutation == 'reordered':
                report['checks'][0], report['checks'][1] = report['checks'][1], report['checks'][0]
            else:
                report['checks'][0] = 'unknown'
            with self.subTest(mutation=mutation):
                self.assert_native_report_rejected(json.dumps(report).encode())

    def test_native_browser_summary_rejects_fake_pass_and_fault_schema(self):
        self.assert_native_report_rejected(b'{"status":"PASS"}')
        for key in native_summary():
            report = native_summary()
            del report[key]
            with self.subTest(missing=key):
                self.assert_native_report_rejected(json.dumps(report).encode())
        for key, value in (('status', 'FAIL'), ('consoleErrors', True), ('consoleErrors', 1),
                           ('supervisorExit', False), ('supervisorExit', 1), ('cleanup', 'PENDING'),
                           ('unavailable', None), ('unavailable', ['missing-flow']),
                           ('expectedFaultCounts', None), ('extra', 'private-value')):
            with self.subTest(key=key, value=value):
                self.assert_native_report_rejected(json.dumps({**native_summary(), key: value}).encode())
        for key, bounds in react_report.FAULTS.items():
            for value in (bounds[0] - 1, bounds[1] + 1, True, '1', None):
                report = native_summary()
                report['expectedFaultCounts'][key] = value
                with self.subTest(fault=key, value=value):
                    self.assert_native_report_rejected(json.dumps(report).encode())
        for key in (*react_report.FAULTS, 'unknown-fault'):
            report = native_summary()
            if key in report['expectedFaultCounts']:
                del report['expectedFaultCounts'][key]
            else:
                report['expectedFaultCounts'][key] = 0
            with self.subTest(fault_key=key):
                self.assert_native_report_rejected(json.dumps(report).encode())

    def test_native_browser_summary_rejects_duplicate_keys_and_malformed_json(self):
        raw = json.dumps(native_summary()).encode()
        variants = (
            raw.replace(b'"status": "PASS"', b'"status": "FAIL", "status": "PASS"', 1),
            raw.replace(b'"checks": [', b'"checks": [], "checks": [', 1),
            raw.replace(b'"checkout-abort-requestfailed": 1',
                        b'"checkout-abort-requestfailed": 0, "checkout-abort-requestfailed": 1', 1),
            raw.replace(b'"consoleErrors": 0', b'"consoleErrors": NaN'),
            raw + b' ' * 32768,
            b'{"status":"PASS",}', b'{"status":"PASS","extra":"\xff"}', b'null',
            b'{"status":"PASS","extra":' + b'[' * 2000 + b'0' + b']' * 2000 + b'}',
        )
        for index, value in enumerate(variants):
            with self.subTest(case=index):
                self.assert_native_report_rejected(value)

    def test_native_browser_cli_rejection_is_sanitized(self):
        raw = json.dumps({**native_summary(), 'private': 'https://example.test/?signature=synthetic-secret'}).encode()
        artifacts, payloads = fixtures(raw)
        client = FakeClient([jobs()], artifacts, payloads)
        manifest = {'source': {'commit': SHA}, 'release_id': 'fixture-release',
                    'archive': {'sha256': 'b' * 64}}
        with patch.object(publication, 'Github', return_value=client), \
             patch.object(publication.release_artifact, 'verify', return_value=manifest) as verify, \
             patch.dict(publication.os.environ, {'GH_TOKEN': 'synthetic'}), \
             patch('sys.stderr', new_callable=io.StringIO) as errors, \
             patch('sys.stdout', new_callable=io.StringIO) as output:
            status = publication.main(['--repo', REPO, '--run-id', '42', '--sha', SHA,
                                       '--attempt', '2', '--event', 'push', '--output', str(self.output)])
        self.assertEqual(status, 1)
        self.assertEqual(output.getvalue(), '')
        self.assertEqual(errors.getvalue(),
                         'Publication collection rejected; inspect run identity, jobs and artifacts privately.\n')
        verify.assert_not_called()
        self.assertFalse(self.output.exists())

    def test_mysql_producer_glob_and_zip_mapping(self):
        workflow = (ROOT / '.github/workflows/ci.yml').read_text()
        producer = (ROOT / 'scripts/ci/mysql_integration.py').read_text()
        self.assertIn('mysql-evidence/mysql-integration-*/summary.json', workflow)
        self.assertIn('self.token = secrets.token_hex(16)', producer)
        name = 'mysql-integration-' + 'a' * 32 + '/summary.json'
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            archive = root / 'mysql.zip'
            archive.write_bytes(zip_bytes({name: b'{"status":"PASS"}'}))
            output = root / 'output'
            output.mkdir()
            publication.extract_expected(archive, output, frozenset({'summary.json'}),
                                         500, kind='mysql')
            self.assertEqual((output / 'summary.json').read_bytes(), b'{"status":"PASS"}')
            self.assertEqual(list(output.iterdir()), [output / 'summary.json'])

    def test_mysql_zip_rejects_unexpected_entries_and_links(self):
        valid = 'mysql-integration-' + 'a' * 32 + '/summary.json'
        invalid = [
            {'summary.json': b'{}'},
            {'mysql-integration-' + 'A' * 32 + '/summary.json': b'{}'},
            {'mysql-integration-' + 'a' * 31 + '/summary.json': b'{}'},
            {'other-' + 'a' * 32 + '/summary.json': b'{}'},
            {'../' + valid: b'{}'},
            {valid: b'{}', 'mysql-integration-' + 'b' * 32 + '/summary.json': b'{}'},
            {valid: b'{}', 'unexpected.log': b'private'},
        ]
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            archive = root / 'mysql.zip'
            for files in invalid:
                with self.subTest(files=tuple(files)):
                    archive.write_bytes(zip_bytes(files))
                    output = root / 'output'
                    output.mkdir()
                    with self.assertRaisesRegex(publication.Rejected, 'entries mismatch'):
                        publication.extract_expected(archive, output, frozenset({'summary.json'}),
                                                     500, kind='mysql')
                    self.assertEqual(list(output.iterdir()), [])
                    output.rmdir()
            with io.BytesIO() as duplicate:
                with zipfile.ZipFile(duplicate, 'w') as zipped, warnings.catch_warnings():
                    warnings.simplefilter('ignore', UserWarning)
                    zipped.writestr(valid, b'one')
                    zipped.writestr(valid, b'two')
                archive.write_bytes(duplicate.getvalue())
            output = root / 'output'
            output.mkdir()
            with self.assertRaisesRegex(publication.Rejected, 'entries mismatch'):
                publication.extract_expected(archive, output, frozenset({'summary.json'}),
                                             500, kind='mysql')
            self.assertEqual(list(output.iterdir()), [])
            output.rmdir()
            with io.BytesIO() as raw:
                with zipfile.ZipFile(raw, 'w') as zipped:
                    link = zipfile.ZipInfo(valid)
                    link.create_system = 3
                    link.external_attr = 0o120777 << 16
                    zipped.writestr(link, b'private')
                archive.write_bytes(raw.getvalue())
            output = root / 'output'
            output.mkdir()
            with self.assertRaisesRegex(publication.Rejected, 'Unsafe ZIP entry'):
                publication.extract_expected(archive, output, frozenset({'summary.json'}),
                                             500, kind='mysql')
            self.assertEqual(list(output.iterdir()), [])

    def test_socket_timeout_taxonomy_before_and_at_deadline(self):
        class TimedSocket:
            def __init__(self, clock, expire=False):
                self.clock = clock
                self.expire = expire
                self.timeout = None
            def settimeout(self, value):
                self.timeout = value
            def recv_into(self, *args):
                if self.expire:
                    self.clock.value = 10
                raise TimeoutError('private socket detail')
            def recv(self, *args):
                if self.expire:
                    self.clock.value = 10
                raise TimeoutError('private socket detail')
        for method in ('recv_into', 'recv'):
            for expire in (False, True):
                with self.subTest(method=method, expire=expire):
                    self.clock.value = 0
                    transport = TimedSocket(self.clock, expire)
                    sock = publication.DeadlineSocket(transport, 10, self.clock.now)
                    with self.assertRaises(publication.Rejected if expire else publication.TransportTimeout):
                        getattr(sock, method)(bytearray(1))
                    self.assertEqual(transport.timeout, 10)

    def test_body_timeout_retries_same_deadline_and_removes_partial_file(self):
        artifacts, payloads = fixtures()
        class Recover(FakeClient):
            def __init__(inner, fail_twice):
                super().__init__([jobs()], artifacts, dict(payloads))
                inner.fail_twice = fail_twice
                inner.attempts = []
            def download(inner, endpoint, target, limit, deadline):
                if endpoint.endswith('/artifacts/2/zip'):
                    inner.attempts.append(deadline)
                    if len(inner.attempts) == 1 or inner.fail_twice:
                        target.write_bytes(b'partial')
                        self.clock.value = 2
                        raise publication.TransportTimeout('safe transport timeout')
                    self.assertFalse(target.exists(), 'Partial ZIP must be removed before retry.')
                return super().download(endpoint, target, limit, deadline)
        success = Recover(False)
        manifest = {'source': {'commit': SHA}, 'release_id': 'fixture-release',
                    'archive': {'sha256': 'b' * 64}}
        with patch.object(publication.release_artifact, 'verify', return_value=manifest):
            self.collect(success)
        self.assertEqual(success.attempts, [10, 10])
        self.assertTrue((self.output / 'publication-summary.json').is_file())
        self.output.rename(Path(self.temp.name) / 'previous-success')
        self.clock.value = 0
        failed = Recover(True)
        with self.assertRaisesRegex(publication.Rejected, '^Artifact download timed out\\.$'):
            self.collect(failed)
        self.assertEqual(failed.attempts, [10, 10])
        self.assertFalse(self.output.exists())

    def test_exhausted_budget_and_non_timeout_io_are_not_retried(self):
        artifacts, payloads = fixtures()
        class Stop(FakeClient):
            def __init__(inner, error):
                super().__init__([jobs()], artifacts, dict(payloads))
                inner.error = error
                inner.attempts = 0
            def download(inner, endpoint, target, limit, deadline):
                if endpoint.endswith('/artifacts/2/zip'):
                    inner.attempts += 1
                    target.write_bytes(b'partial')
                    if isinstance(inner.error, publication.TransportTimeout):
                        self.clock.value = deadline
                    raise inner.error
                return super().download(endpoint, target, limit, deadline)
        for error, expected in ((publication.TransportTimeout('private'), publication.Rejected),
                                (OSError('private filesystem error'), OSError)):
            self.clock.value = 0
            client = Stop(error)
            with self.subTest(error=type(error)), self.assertRaises(expected):
                self.collect(client)
            self.assertEqual(client.attempts, 1)
            self.assertFalse(self.output.exists())

    def test_timeout_or_partial_download_never_publishes(self):
        artifacts, payloads = fixtures()
        for error, expected in ((TimeoutError('secret URL'), publication.Rejected),
                                (publication.Rejected('partial'), publication.Rejected)):
            client = FakeClient([jobs()], artifacts, dict(payloads))
            client.payloads[f'repos/{REPO}/actions/artifacts/2/zip'] = error
            with self.subTest(error=error), self.assertRaises(expected):
                self.collect(client)
            self.assertFalse(self.output.exists())
        client = FakeClient([jobs()], artifacts, dict(payloads))
        client.payloads[f'repos/{REPO}/actions/artifacts/2/zip'] = b'partial'
        with self.assertRaisesRegex(publication.Rejected, 'size or digest'):
            self.collect(client)
        self.assertFalse(self.output.exists())

    def test_malformed_summary_zip_fails_without_publication(self):
        artifacts, payloads = fixtures()
        bad = zip_bytes({'mysql-integration-' + 'a' * 32 + '/summary.json': b'not json'})
        payloads[f'repos/{REPO}/actions/artifacts/2/zip'] = bad
        artifacts[1]['size_in_bytes'] = len(bad)
        artifacts[1]['digest'] = 'sha256:' + hashlib.sha256(bad).hexdigest()
        with self.assertRaisesRegex(publication.Rejected, 'summary JSON'):
            self.collect(FakeClient([jobs()], artifacts, payloads))
        self.assertFalse(self.output.exists())

    def test_pagination_and_byte_cap_with_mock_http(self):
        class Response(io.BytesIO):
            pass

        seen = []
        def opener(request, timeout):
            seen.append((request.full_url, timeout))
            page = int(parse_qs(urlsplit(request.full_url).query)['page'][0])
            content = {'jobs': [{'name': 'placeholder'}] * (100 if page == 1 else 1)}
            return Response(json.dumps(content).encode())
        client = publication.Github('synthetic-token', opener=opener, clock=self.clock.now)
        self.assertEqual(len(client.pages('repos/a/b/actions/runs/42/attempts/2/jobs', 'jobs')), 101)
        self.assertEqual(len(seen), 2)
        self.assertEqual([parse_qs(urlsplit(url).query)['page'][0] for url, _ in seen], ['1', '2'])
        with patch.object(client, 'open', return_value=Response(b'x' * 9)):
            with self.assertRaisesRegex(publication.Rejected, 'size limit'):
                client.download('test', Path(self.temp.name) / 'partial', 8, 10)

    def test_redirect_handler_drops_credentials_and_blocks_http_with_bounded_hops(self):
        requests = []
        class StubTransport(urllib.request.BaseHandler):
            handler_order = 100
            def https_open(inner, request):
                requests.append(request)
                headers = Message()
                if len(requests) < 2:
                    headers['Location'] = 'https://objects.example.test/asset.zip'
                    code = 302
                else:
                    code = 200
                return StubResponse(b'ok', headers, request.full_url, code,
                                    'Found' if code == 302 else 'OK')
        opener = urllib.request.build_opener(urllib.request.ProxyHandler({}),
                                             publication.SafeRedirect(), StubTransport()).open
        client = publication.Github('synthetic-token', opener=opener)
        with client.open('repos/a/b/actions/artifacts/1/zip', artifact_download=True) as response:
            self.assertEqual(response.read(), b'ok')
        self.assertEqual(len(requests), 2)
        self.assertEqual(requests[0].get_header('Authorization'), 'Bearer synthetic-token')
        self.assertIsNone(requests[1].get_header('Authorization'))
        self.assertEqual(urlsplit(requests[1].full_url).scheme, 'https')
        for target, download in (('http://objects.example.test/file', True),
                                 ('https://objects.example.test/file', False)):
            request = publication.PublicationRequest('https://api.github.com/initial',
                headers={'Authorization': 'Bearer synthetic-token'}, artifact_download=download)
            location = http.client.HTTPMessage()
            location['Location'] = target
            with self.subTest(target=target), self.assertRaises(publication.Rejected):
                publication.SafeRedirect().http_error_302(request, io.BytesIO(), 302, 'Found',
                                                           location)
        class Loop(urllib.request.BaseHandler):
            handler_order = 100
            def https_open(inner, request):
                headers = Message()
                headers['Location'] = 'https://api.github.com/loop'
                return StubResponse(b'', headers, request.full_url, 302, 'Found')
        looping = urllib.request.build_opener(urllib.request.ProxyHandler({}),
                                               publication.SafeRedirect(), Loop()).open
        request = publication.PublicationRequest('https://api.github.com/loop',
            request_deadline=publication.time.monotonic() + 2)
        with self.assertRaisesRegex(publication.Rejected, 'Redirect limit'):
            looping(request, timeout=1)

    def test_redirect_derives_new_host_and_preserves_signed_query(self):
        signed = 'https://objects.example.test/container/candidate.zip?sig=abc%2Bdef&sp=r'
        class Capture(urllib.request.OpenerDirector):
            def __init__(self):
                super().__init__()
                self.request: urllib.request.Request | None = None
            def open(self, fullurl, data=None, timeout=None):
                assert isinstance(fullurl, urllib.request.Request)
                self.request = fullurl
                return io.BytesIO(b'ok')
        for same_origin in (False, True):
            with self.subTest(same_origin=same_origin):
                request = publication.PublicationRequest('https://api.github.com/initial',
                    headers={'Authorization': 'Bearer synthetic',
                             'X-GitHub-Api-Version': '2022-11-28', 'Cookie': 'session=synthetic',
                             'Connection': 'keep-alive', 'User-Agent': 'collector'},
                    artifact_download=True, request_deadline=time.monotonic() + 2)
                request.add_unredirected_header('Host', 'api.github.com')
                request.add_unredirected_header('Proxy-Authorization', 'synthetic')
                handler = publication.SafeRedirect()
                parent = Capture()
                handler.parent = parent
                target = 'https://api.github.com/next' if same_origin else signed
                location = http.client.HTTPMessage()
                location['Location'] = target
                with handler.http_error_302(request, io.BytesIO(b'unread'), 302, 'Found',
                                            location) as result:
                    self.assertEqual(result.read(), b'ok')
                forwarded = parent.request
                assert forwarded is not None
                self.assertEqual(forwarded.full_url, target)
                self.assertIsNone(forwarded.get_header('Host'))
                self.assertIsNone(forwarded.get_header('Connection'))
                self.assertIsNone(forwarded.get_header('Proxy-authorization'))
                self.assertEqual(forwarded.get_header('Authorization'),
                                 'Bearer synthetic' if same_origin else None)
                self.assertEqual(forwarded.get_header('Cookie'),
                                 'session=synthetic' if same_origin else None)
                self.assertEqual(forwarded.get_header('X-github-api-version'),
                                 '2022-11-28' if same_origin else None)
                self.assertEqual(forwarded.get_header('User-agent'), 'collector')
                if not same_origin:
                    self.assertEqual(urlsplit(forwarded.full_url).query, 'sig=abc%2Bdef&sp=r')
                    self.assertEqual(urlsplit(forwarded.full_url).hostname,
                                     'objects.example.test')

    def test_http_error_does_not_retry_or_disclose_response(self):
        artifacts, payloads = fixtures()
        error = urllib.error.HTTPError('https://objects.example.test/private?sig=synthetic',
                                       400, 'synthetic-secret', Message(), io.BytesIO(b'private'))
        client = FakeClient([jobs()], artifacts, dict(payloads))
        client.payloads[f'repos/{REPO}/actions/artifacts/2/zip'] = error
        with self.assertRaisesRegex(publication.Rejected, '^Artifact HTTP response rejected\\.$'):
            self.collect(client)
        self.assertEqual(sum(item.endswith('/artifacts/2/zip') for item in client.requests), 1)
        self.assertFalse(self.output.exists())
        error.close()

    def test_truncated_gzip_consistent_manifest_is_sanitized(self):
        artifacts, payloads = fixtures()
        truncated = gzip.compress(b'not-a-tar')[:-6]
        manifest = {'archive': {'size': len(truncated),
                                'sha256': hashlib.sha256(truncated).hexdigest()}}
        broken = zip_bytes({'release.tar.gz': truncated,
                            'release-manifest.json': json.dumps(manifest).encode()})
        payloads[f'repos/{REPO}/actions/artifacts/4/zip'] = broken
        artifacts[3]['size_in_bytes'] = len(broken)
        artifacts[3]['digest'] = 'sha256:' + hashlib.sha256(broken).hexdigest()
        client = FakeClient([jobs()], artifacts, payloads)
        actual_verify = publication.release_artifact.verify
        observed = []
        def verify_truncated(*args):
            try:
                return actual_verify(*args)
            except (EOFError, tarfile.TarError, zlib.error) as error:
                observed.append(type(error))
                raise
        with patch.object(publication.release_artifact, 'manifest_entries', return_value={}), \
             patch.object(publication.release_artifact, 'verify', side_effect=verify_truncated) as verifier, \
             patch.object(publication, 'Github', return_value=client), \
             patch.dict(publication.os.environ, {'GH_TOKEN': 'synthetic'}), \
             patch('sys.stderr', new_callable=io.StringIO) as errors, \
             patch('sys.stdout', new_callable=io.StringIO) as output:
            rc = publication.main(['--repo', REPO, '--run-id', '42', '--sha', SHA,
                                   '--attempt', '2', '--event', 'push', '--output', str(self.output)])
        self.assertEqual(rc, 1)
        verifier.assert_called_once()
        self.assertTrue(observed, 'Verifier must reach the truncated gzip stream.')
        self.assertEqual(output.getvalue(), '')
        self.assertIn('Publication collection rejected', errors.getvalue())
        self.assertNotIn('Traceback', errors.getvalue())
        self.assertFalse(self.output.exists())

    def test_redirect_body_is_closed_without_any_read(self):
        class NoRead(io.BytesIO):
            def read(self, *args):
                raise AssertionError('Redirect body was read.')
        class Transport(urllib.request.BaseHandler):
            handler_order = 100
            def https_open(inner, request):
                if request.full_url.endswith('/zip'):
                    headers = Message()
                    headers['Location'] = 'https://objects.example.test/artifact'
                    headers['Content-Length'] = '2097153'
                    return StubResponse(NoRead(b'x' * 16), headers, request.full_url,
                                        302, 'Found')
                return StubResponse(b'ok', Message(), request.full_url, 200, 'OK')
        opener = urllib.request.build_opener(urllib.request.ProxyHandler({}),
                                             publication.SafeRedirect(), Transport()).open
        client = publication.Github('synthetic', opener=opener)
        with client.open('repos/a/b/actions/artifacts/1/zip', artifact_download=True) as response:
            self.assertEqual(response.read(), b'ok')

    def test_af_unix_complete_http_response_metadata_and_download(self):
        samples = [(False, b'{"status":"PASS"}'),
                   (True, b'z' * 65536), (True, b'z' * (65536 * 2))]
        for is_download, body in samples:
            with self.subTest(download=is_download, bytes=len(body)):
                client_sock, server_sock = socket.socketpair()
                def send_body():
                    try:
                        server_sock.sendall(b'HTTP/1.1 200 OK\r\nContent-Length: '
                                            + str(len(body)).encode() + b'\r\n\r\n' + body)
                        server_sock.shutdown(socket.SHUT_WR)
                    except OSError:
                        pass
                writer = threading.Thread(target=send_body)
                writer.start()
                try:
                    response = http.client.HTTPResponse(client_sock)
                    response.begin()
                    client = publication.Github('synthetic', opener=lambda *a, **k: response,
                                                clock=time.monotonic)
                    if is_download:
                        size, digest = client.download('synthetic', self.output,
                                                       len(body) + 1, time.monotonic() + 2)
                        self.assertEqual(size, len(body))
                        self.assertEqual(digest, hashlib.sha256(body).hexdigest())
                        self.assertEqual(self.output.read_bytes(), body)
                        self.output.unlink()
                    else:
                        self.assertEqual(client.json('synthetic', deadline=time.monotonic() + 2),
                                         {'status': 'PASS'})
                finally:
                    client_sock.close()
                    server_sock.close()
                    writer.join(timeout=1)
                    self.assertFalse(writer.is_alive())

    def test_af_unix_truncated_or_unknown_http_response_rejected(self):
        for body, advertised, download in ((b'{"status":"PASS"}', 40, False),
                                            (b'abcdef', 12, True)):
            with self.subTest(advertised=advertised, download=download):
                client_sock, server_sock = socket.socketpair()
                try:
                    length = (b'Content-Length: ' + str(advertised).encode() + b'\r\n') \
                        if advertised is not None else b''
                    server_sock.sendall(b'HTTP/1.1 200 OK\r\n' + length + b'\r\n' + body)
                    server_sock.shutdown(socket.SHUT_WR)
                    response = http.client.HTTPResponse(client_sock)
                    response.begin()
                    client = publication.Github('synthetic', opener=lambda *a, **k: response,
                                                clock=time.monotonic)
                    with self.assertRaisesRegex(publication.Rejected, 'Truncated|Unknown'):
                        if download:
                            client.download('synthetic', self.output, 100,
                                            time.monotonic() + 2)
                        else:
                            client.json('synthetic', deadline=time.monotonic() + 2)
                finally:
                    client_sock.close()
                    server_sock.close()
                    self.output.unlink(missing_ok=True)
        class Unrecognized:
            def read(self, size):
                return b''
            def close(self):
                pass
            def __enter__(self):
                return self
            def __exit__(self, *args):
                self.close()
        unknown = publication.Github('synthetic', opener=lambda *a, **k: Unrecognized())
        with self.assertRaisesRegex(publication.Rejected, 'Unknown response transport'):
            unknown.json('synthetic', deadline=time.monotonic() + 2)

    def test_af_unix_chunked_and_close_delimited_success(self):
        for framing, download in (('chunked', False), ('chunked', True),
                                  ('close', False), ('close', True)):
            with self.subTest(framing=framing, download=download):
                payload = b'{"status":"PASS"}' if not download else b'artifact-data'
                if framing == 'chunked':
                    headers = b'Transfer-Encoding: chunked\r\n'
                    body = format(len(payload), 'x').encode() + b'\r\n' + payload + b'\r\n0\r\n\r\n'
                else:
                    headers = b'Connection: close\r\n'
                    body = payload
                client_sock, server_sock = socket.socketpair()
                try:
                    server_sock.sendall(b'HTTP/1.1 200 OK\r\n' + headers + b'\r\n' + body)
                    server_sock.shutdown(socket.SHUT_WR)
                    response = http.client.HTTPResponse(client_sock)
                    response.begin()
                    client = publication.Github('synthetic', opener=lambda *a, **k: response,
                                                clock=time.monotonic)
                    if download:
                        size, digest = client.download('synthetic', self.output, 100,
                                                       time.monotonic() + 2)
                        self.assertEqual((size, digest),
                                         (len(payload), hashlib.sha256(payload).hexdigest()))
                        self.assertEqual(self.output.read_bytes(), payload)
                    else:
                        self.assertEqual(client.json('synthetic', deadline=time.monotonic() + 2),
                                         {'status': 'PASS'})
                finally:
                    client_sock.close()
                    server_sock.close()
                    self.output.unlink(missing_ok=True)

    def test_af_unix_truncated_chunk_and_slow_header_fail_closed(self):
        for slow in (False, True):
            with self.subTest(slow=slow):
                client_sock, server_sock = socket.socketpair()
                stop = threading.Event()
                def sender():
                    try:
                        server_sock.sendall(b'HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n')
                        if slow:
                            while not stop.is_set():
                                server_sock.sendall(b'1')
                                stop.wait(0.01)
                        else:
                            server_sock.sendall(b'10\r\nabc')
                            server_sock.shutdown(socket.SHUT_WR)
                    except OSError:
                        pass
                    finally:
                        server_sock.close()
                writer = threading.Thread(target=sender)
                writer.start()
                response = http.client.HTTPResponse(client_sock)
                response.begin()
                client = publication.Github('synthetic', opener=lambda *a, **k: response,
                                            clock=time.monotonic)
                started = time.monotonic()
                try:
                    with self.assertRaises((publication.Rejected, http.client.HTTPException)):
                        client.download('synthetic', self.output, 100, started + 0.07)
                    self.assertLess(time.monotonic() - started, 0.25)
                finally:
                    stop.set()
                    response.close()
                    client_sock.close()
                    writer.join(timeout=1)
                    self.assertFalse(writer.is_alive())
                    self.output.unlink(missing_ok=True)

    def test_audit_hook_blocks_tcp_before_connect(self):
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as connection:
            with self.assertRaisesRegex(AssertionError, 'External connection'):
                connection.connect(('127.0.0.1', 9))

    def test_af_unix_slow_drip_metadata_and_download_stop_near_deadline(self):
        # No DNS/TCP/remote host. Real HTTPResponse body is fed by AF_UNIX sockets.
        for is_download in (False, True):
            with self.subTest(download=is_download):
                client_sock, server_sock = socket.socketpair()
                stop = threading.Event()
                def drip():
                    try:
                        server_sock.sendall(b'HTTP/1.1 200 OK\r\nContent-Length: 100\r\n\r\n')
                        while not stop.is_set():
                            server_sock.sendall(b'x')
                            stop.wait(0.02)
                    except (BrokenPipeError, OSError):
                        pass
                    finally:
                        server_sock.close()
                producer = threading.Thread(target=drip)
                producer.start()
                response = http.client.HTTPResponse(client_sock)
                response.begin()
                client = publication.Github('synthetic', opener=lambda *a, **k: response,
                                            clock=time.monotonic)
                started = time.monotonic()
                try:
                    with self.assertRaisesRegex(publication.Rejected, 'deadline'):
                        if is_download:
                            client.download('synthetic', self.output, 1024, started + 0.07)
                        else:
                            client.json('synthetic', deadline=started + 0.07)
                    self.assertLess(time.monotonic() - started, 0.25)
                finally:
                    stop.set()
                    response.close()
                    client_sock.close()
                    producer.join(timeout=1)
                    self.assertFalse(producer.is_alive())
                    self.output.unlink(missing_ok=True)

    def test_cli_download_budget_default_and_valid_overrides(self):
        for value in (None, '1', '90', '91', '899', '900'):
            args = ['--repo', REPO, '--run-id', '42', '--sha', SHA,
                    '--attempt', '2', '--event', 'push', '--output', str(self.output)]
            if value is not None:
                args += ['--download-seconds', value]
            with self.subTest(value=value), \
                 patch.dict(publication.os.environ, {'GH_TOKEN': 'synthetic'}), \
                 patch.object(publication, 'Github') as github, \
                 patch.object(publication, 'collect', return_value={'status': 'ok'}) as collect, \
                 patch('sys.stdout', new_callable=io.StringIO) as output:
                self.assertEqual(publication.main(args), 0)
                github.assert_called_once_with('synthetic')
                collect.assert_called_once_with(github.return_value, REPO, 42, SHA, 2, 'push',
                                                self.output, download_wait=90 if value is None else int(value))
                self.assertEqual(json.loads(output.getvalue()), {'status': 'ok'})

    def test_cli_invalid_download_budget_never_reads_token_or_uses_network(self):
        for value in ('0', '-1', '901', '100000', '1.5', '1e2', 'nan', 'inf', 'abc', '', None):
            args = ['--repo', REPO, '--run-id', '42', '--sha', SHA,
                    '--attempt', '2', '--event', 'push', '--output', str(self.output),
                    '--download-seconds']
            if value is not None:
                args.append(value)
            with self.subTest(value=value), \
                 patch.object(publication.os.environ, 'get', return_value=None) as environment, \
                 patch.object(publication, 'Github') as github, \
                 patch.object(publication.urllib.request, 'build_opener') as opener, \
                 patch.object(publication, 'collect') as collect, \
                 patch('sys.stderr', new_callable=io.StringIO) as errors, \
                 patch('sys.stdout', new_callable=io.StringIO) as output:
                with self.assertRaises(SystemExit) as raised:
                    publication.main(args)
                self.assertEqual(raised.exception.code, 2)
                self.assertNotIn('GH_TOKEN', [call.args[0] for call in environment.call_args_list])
                github.assert_not_called()
                opener.assert_not_called()
                collect.assert_not_called()
                self.assertIn('--download-seconds', errors.getvalue())
                self.assertIn('1..900', errors.getvalue())
                self.assertEqual(output.getvalue(), '')
                self.assertFalse(self.output.exists())

    def test_api_default_and_custom_short_download_budgets_remain_available(self):
        artifacts, payloads = fixtures()
        manifest = {'source': {'commit': SHA}, 'release_id': 'fixture-release',
                    'archive': {'sha256': 'b' * 64}}
        class Recorded(FakeClient):
            def __init__(self):
                super().__init__([jobs()], artifacts, payloads)
                self.deadlines = []
            def download(self, endpoint, target, limit, deadline):
                self.deadlines.append(deadline)
                return super().download(endpoint, target, limit, deadline)
        for name, options, expected in (('default', {}, 90), ('short', {'download_wait': 0.25}, 0.25)):
            client = Recorded()
            with self.subTest(name=name), \
                 patch.object(publication.release_artifact, 'verify', return_value=manifest):
                publication.collect(client, REPO, 42, SHA, 2, 'push', self.output.with_name(name),
                                    clock=self.clock.now, now=lambda: self.utc,
                                    sleep=self.clock.sleep, wait=10, **options)
            self.assertEqual(client.deadlines, [expected] * 4)
        self.assertEqual(publication.REQUEST_SECONDS, 15)

    def test_null_list_string_repository_fail_sanitized_via_cli(self):
        for bad in (None, [], 'not-an-object'):
            class Invalid(FakeClient):
                def json(inner, endpoint, deadline=None):
                    return dict(super().json(endpoint, deadline=deadline), repository=bad)
            with self.subTest(bad=bad), patch.object(publication, 'Github', return_value=Invalid([jobs()])), \
                 patch.dict(publication.os.environ, {'GH_TOKEN': 'synthetic'}), \
                 patch('sys.stderr', new_callable=io.StringIO) as errors:
                rc = publication.main(['--repo', REPO, '--run-id', '42', '--sha', SHA,
                                       '--attempt', '2', '--event', 'push', '--output', str(self.output)])
                self.assertEqual(rc, 1)
                self.assertIn('Publication collection rejected', errors.getvalue())
                self.assertNotIn('Traceback', errors.getvalue())
                self.assertFalse(self.output.exists())

    def test_cli_rejects_error_without_printing_exception(self):
        with patch.dict(publication.os.environ, {'GH_TOKEN': ''}), \
             patch('sys.stderr', new_callable=io.StringIO) as output:
            status = publication.main(['--repo', REPO, '--run-id', '42', '--sha', SHA,
                                       '--attempt', '2', '--event', 'push', '--output', str(self.output)])
        self.assertEqual(status, 1)
        self.assertNotIn('token', output.getvalue().lower())
        self.assertFalse(self.output.exists())


if __name__ == '__main__':
    unittest.main()
