"""Collect CI publication evidence for one explicit GitHub run attempt; never deploy.

Requires a GitHub token with Actions read access via GH_TOKEN. Output is a small
allowlisted JSON summary; downloaded ZIPs and raw metadata are never published.
Socket reads, redirect hops, and metadata polling are bounded. urllib DNS/TLS setup
may exceed a socket timeout on some platforms; this is not a hard real-time deadline.
"""
import argparse
import hashlib
import http.client
import io
import json
import os
import re
import shutil
import sys
import tarfile
import tempfile
import time
import urllib.error
import urllib.request
import zipfile
import zlib
from datetime import UTC, datetime
from pathlib import Path
from urllib.parse import urljoin, urlsplit

import check_react_browser_report
import release_artifact

JOBS = frozenset({'quality', 'mysql', 'browser', 'release', 'php'})
ARTIFACTS = {
    'quality': frozenset({'phpunit-summary.json', 'python-summary.json'}),
    'mysql': frozenset({'summary.json'}),
    'browser': frozenset({'browser-summary.json', 'react-browser-evidence/summary.json'}),
    'release': frozenset({'release.tar.gz', 'release-manifest.json'}),
}
MAX_PAGES = 20
PAGE_SIZE = 100
POLL_SECONDS = 5
WAIT_SECONDS = 60
REQUEST_SECONDS = 15
DOWNLOAD_SECONDS = 90
MAX_DOWNLOAD_SECONDS = 900
SUMMARY_LIMIT = 2 * 1024 * 1024
RELEASE_LIMIT = 512 * 1024 * 1024


class Rejected(Exception):
    pass


class TransportTimeout(TimeoutError):
    """A socket timeout before the overall artifact deadline; safe to retry GET."""


class PublicationRequest(urllib.request.Request):
    def __init__(self, url, *, headers=None, artifact_download=False,
                 request_deadline=0.0, redirect_hops=0):
        super().__init__(url, headers=headers or {})
        self.artifact_download = artifact_download
        self.request_deadline = request_deadline
        self.redirect_hops = redirect_hops


def require(condition, reason):
    if not condition:
        raise Rejected(reason)


def stamp(value):
    require(isinstance(value, str), 'Invalid timestamp.')
    try:
        parsed = datetime.fromisoformat(value)
    except ValueError:
        raise Rejected('Invalid timestamp.') from None
    require(parsed.tzinfo is not None, 'Invalid timestamp.')
    return parsed.astimezone(UTC)


class SafeRedirect(urllib.request.HTTPRedirectHandler):
    def __init__(self, clock=time.monotonic):
        self.clock = clock

    def http_error_302(self, req, fp, code, msg, headers):
        # urllib's inherited handler drains fp.read() without a bound. Close, never drain.
        try:
            if not isinstance(req, PublicationRequest):
                raise Rejected('Unrecognized redirect request.')
            location = headers.get('Location') or headers.get('URI')
            require(isinstance(location, str) and code in (301, 302, 303, 307, 308),
                    'Invalid redirect.')
            target_url = urljoin(req.full_url, location)
            target = urlsplit(target_url)
            require(target.scheme == 'https' and target.hostname and not target.username
                    and not target.password and target.port in (None, 443), 'Unsafe redirect.')
            same_origin = target.hostname == 'api.github.com'
            require(same_origin or req.artifact_download,
                    'Metadata redirect left API origin.')
            require(req.redirect_hops < 3, 'Redirect limit exceeded.')
            remaining = req.request_deadline - self.clock()
            require(remaining > 0, 'Request deadline exceeded.')
            # urllib may add Host as an unredirected header after the first request.
            # Reusing it would send api.github.com as the signed blob host.
            hop_headers = {'host', 'connection', 'proxy-connection', 'keep-alive',
                           'transfer-encoding', 'te', 'trailer', 'upgrade',
                           'content-length', 'proxy-authorization'}
            request_headers = {key: value for key, value in req.header_items()
                               if key.lower() not in hop_headers and
                               (same_origin or key.lower() == 'user-agent')}
            redirected = PublicationRequest(target_url, headers=request_headers,
                                            artifact_download=req.artifact_download,
                                            request_deadline=req.request_deadline,
                                            redirect_hops=req.redirect_hops + 1)
        finally:
            fp.close()
        result = self.parent.open(redirected, timeout=min(REQUEST_SECONDS, remaining))
        if self.clock() >= req.request_deadline:
            result.close()
            raise Rejected('Request deadline exceeded.')
        return result

    http_error_301 = http_error_303 = http_error_307 = http_error_308 = http_error_302


def response_size(response, limit):
    current = response
    for _ in range(4):
        if isinstance(current, http.client.HTTPResponse):
            value = response.info().get('Content-Length')
            require(not current.chunked or value is None, 'Conflicting HTTP framing.')
            if value is None:
                return None
            require(isinstance(value, str) and re.fullmatch(r'[0-9]+', value),
                    'Invalid HTTP response length.')
            size = int(value)
            require(0 < size <= limit, 'Invalid HTTP response length.')
            return size
        current = getattr(current, 'fp', None)
        if current is None:
            break
    require(isinstance(response, io.BytesIO), 'Unknown response transport.')
    return None


class DeadlineSocket:
    def __init__(self, sock, deadline, clock):
        self.sock = sock
        self.deadline = deadline
        self.clock = clock

    def __getattr__(self, name):
        return getattr(self.sock, name)

    def recv_into(self, *args):
        remaining = self.deadline - self.clock()
        require(remaining > 0, 'Response deadline exceeded.')
        self.sock.settimeout(min(REQUEST_SECONDS, remaining))
        try:
            data = self.sock.recv_into(*args)
        except TimeoutError:
            if self.clock() >= self.deadline:
                raise Rejected('Response deadline exceeded.') from None
            raise TransportTimeout('Response transport timed out.') from None
        require(self.clock() < self.deadline, 'Response deadline exceeded.')
        return data

    def recv(self, *args):
        remaining = self.deadline - self.clock()
        require(remaining > 0, 'Response deadline exceeded.')
        self.sock.settimeout(min(REQUEST_SECONDS, remaining))
        try:
            data = self.sock.recv(*args)
        except TimeoutError:
            if self.clock() >= self.deadline:
                raise Rejected('Response deadline exceeded.') from None
            raise TransportTimeout('Response transport timed out.') from None
        require(self.clock() < self.deadline, 'Response deadline exceeded.')
        return data


def bounded_read(response, amount, deadline, clock):
    remaining = deadline - clock()
    require(remaining > 0, 'Response deadline exceeded.')
    # Wrap the socket held by HTTPResponse's buffered reader so its chunk header,
    # trailer and body parser cannot extend the deadline with successive recv calls.
    current = response
    sock = None
    complete_eof = False
    for _ in range(4):
        if isinstance(current, http.client.HTTPResponse) and current.isclosed():
            complete_eof = (current.length == 0 or
                            (current.chunked and current.chunk_left is None) or
                            (not current.chunked and current.length is None))
            break
        raw = getattr(current, 'raw', None)
        sock = getattr(raw, '_sock', None)
        if sock is not None and raw is not None:
            if not isinstance(sock, DeadlineSocket):
                raw._sock = DeadlineSocket(sock, deadline, clock)
            else:
                sock.deadline = min(sock.deadline, deadline)
            break
        current = getattr(current, 'fp', None)
        if current is None:
            break
    if sock is None:
        if complete_eof:
            return b''
        require(isinstance(response, io.BytesIO), 'Unbounded response transport.')
    reader = getattr(response, 'read1', None)
    try:
        data = reader(amount) if reader is not None else response.read(1)
    except TransportTimeout:
        raise
    except TimeoutError:
        if clock() >= deadline:
            raise Rejected('Response deadline exceeded.') from None
        raise TransportTimeout('Response transport timed out.') from None
    require(clock() < deadline, 'Response deadline exceeded.')
    return data


class Github:
    def __init__(self, token, opener=None, clock=time.monotonic):
        require(bool(token), 'Actions read token required.')
        self.token = token
        self.opener = opener or urllib.request.build_opener(SafeRedirect(clock)).open
        self.clock = clock

    def open(self, endpoint, timeout=REQUEST_SECONDS, artifact_download=False):
        request = PublicationRequest('https://api.github.com/' + endpoint,
                                     headers={'Authorization': 'Bearer ' + self.token,
                                              'Accept': 'application/vnd.github+json',
                                              'X-GitHub-Api-Version': '2022-11-28',
                                              'User-Agent': 'zafira-publication-collector'},
                                     artifact_download=artifact_download,
                                     request_deadline=self.clock() + timeout)
        response = self.opener(request, timeout=timeout)
        if self.clock() >= request.request_deadline:
            response.close()
            raise Rejected('Request deadline exceeded.')
        return response

    def remaining(self, deadline):
        remaining = deadline - self.clock()
        require(remaining > 0, 'Metadata deadline exceeded.')
        return min(REQUEST_SECONDS, remaining)

    def json(self, endpoint, deadline=None):
        deadline = deadline if deadline is not None else self.clock() + REQUEST_SECONDS
        timeout = self.remaining(deadline)
        raw = bytearray()
        with self.open(endpoint, timeout=timeout) as response:
            expected = response_size(response, SUMMARY_LIMIT)
            while expected is None or len(raw) < expected:
                amount = min(65536, SUMMARY_LIMIT - len(raw) + 1)
                if expected is not None:
                    amount = min(amount, expected - len(raw))
                data = bounded_read(response, amount, deadline, self.clock)
                if not data:
                    require(expected is None, 'Truncated HTTP response.')
                    break
                raw.extend(data)
                require(len(raw) <= SUMMARY_LIMIT, 'Metadata exceeds limit.')
        self.remaining(deadline)
        try:
            return json.loads(raw)
        except (ValueError, UnicodeDecodeError):
            raise Rejected('Malformed metadata.') from None

    def pages(self, endpoint, key, deadline=None):
        found = []
        for page in range(1, MAX_PAGES + 1):
            if deadline is not None:
                self.remaining(deadline)
            data = self.json(f'{endpoint}?per_page={PAGE_SIZE}&page={page}', deadline=deadline)
            require(isinstance(data, dict) and isinstance(data.get(key), list), 'Invalid page.')
            items = data[key]
            require(len(items) <= PAGE_SIZE, 'Invalid page size.')
            found.extend(items)
            if len(items) < PAGE_SIZE:
                return found
        raise Rejected('Pagination limit exceeded.')

    def download(self, endpoint, target, limit, deadline):
        remaining = deadline - self.clock()
        require(remaining > 0, 'Artifact download deadline exceeded.')
        digest = hashlib.sha256()
        size = 0
        with self.open(endpoint, timeout=min(REQUEST_SECONDS, remaining), artifact_download=True) as response, target.open('xb') as output:
            expected = response_size(response, limit)
            while expected is None or size < expected:
                require(self.clock() < deadline, 'Artifact download deadline exceeded.')
                amount = min(65536, limit - size + 1)
                if expected is not None:
                    amount = min(amount, expected - size)
                data = bounded_read(response, amount, deadline, self.clock)
                if not data:
                    require(expected is None, 'Truncated HTTP response.')
                    break
                size += len(data)
                require(size <= limit, 'Artifact exceeds size limit.')
                digest.update(data)
                output.write(data)
        require(self.clock() < deadline, 'Artifact download deadline exceeded.')
        require(size > 0, 'Empty artifact.')
        return size, digest.hexdigest()


def check_run(run, repo, run_id, sha, attempt, event):
    require(isinstance(run, dict) and run.get('id') == run_id
            and run.get('head_sha') == sha and run.get('run_attempt') == attempt
            and run.get('event') == event, 'Run identity mismatch.')
    require(run.get('status') == 'completed' and run.get('conclusion') == 'success',
            'Run is not successful and complete.')
    repository = run.get('repository')
    require(isinstance(repository, dict) and repository.get('full_name') == repo,
            'Repository mismatch.')


def check_jobs(jobs, run_id, sha, attempt):
    require(isinstance(jobs, list), 'Invalid jobs.')
    seen = set()
    for job in jobs:
        require(isinstance(job, dict) and isinstance(job.get('name'), str), 'Invalid job.')
        name = job['name']
        require(name not in seen, 'Duplicate job name.')
        seen.add(name)
        require(job.get('run_id') == run_id and job.get('head_sha') == sha
                and job.get('run_attempt') == attempt, 'Job identity mismatch.')
    if seen != JOBS:
        return False
    return all(job['status'] == 'completed' and job['conclusion'] == 'success'
               and stamp(job['started_at']) <= stamp(job['completed_at']) for job in jobs)


def wait_jobs(client, repo, run_id, sha, attempt, event, *, clock=time.monotonic,
              sleep=time.sleep, wait=WAIT_SECONDS):
    base = f'repos/{repo}/actions/runs/{run_id}'
    deadline = clock() + wait
    while True:
        require(clock() < deadline, 'Jobs incomplete or unsuccessful after consistency wait.')
        run = client.json(base, deadline=deadline)
        check_run(run, repo, run_id, sha, attempt, event)
        jobs = client.pages(base + f'/attempts/{attempt}/jobs', 'jobs', deadline=deadline)
        require(clock() < deadline, 'Jobs incomplete or unsuccessful after consistency wait.')
        if check_jobs(jobs, run_id, sha, attempt):
            return jobs
        remaining = deadline - clock()
        require(remaining > 0, 'Jobs incomplete or unsuccessful after consistency wait.')
        sleep(min(POLL_SECONDS, remaining))


def check_artifacts(artifacts, sha, run_id, jobs, now):
    require(isinstance(artifacts, list), 'Invalid artifacts.')
    start = min(stamp(job['started_at']) for job in jobs)
    end = max(stamp(job['completed_at']) for job in jobs)
    found = {}
    for item in artifacts:
        require(isinstance(item, dict), 'Invalid artifact.')
        for kind in ARTIFACTS:
            if item.get('name') == f'{kind}-summary-{sha}' or (kind == 'release' and item.get('name') == f'release-candidate-{sha}'):
                require(kind not in found, 'Duplicate artifact.')
                identity = item.get('workflow_run')
                require(isinstance(identity, dict) and identity.get('id') == run_id
                        and identity.get('head_sha') == sha, 'Artifact run mismatch.')
                require(start <= stamp(item.get('created_at')) <= end,
                        'Artifact outside attempt job window.')
                require(stamp(item.get('expires_at')) > now(), 'Artifact has expired.')
                limit = RELEASE_LIMIT if kind == 'release' else SUMMARY_LIMIT
                require(item.get('expired') is False and type(item.get('id')) is int
                        and item['id'] > 0 and type(item.get('size_in_bytes')) is int
                        and 0 < item['size_in_bytes'] <= limit, 'Artifact unavailable or oversized.')
                require(isinstance(item.get('digest'), str)
                        and re.fullmatch(r'sha256:[0-9a-f]{64}', item['digest']),
                        'Artifact ZIP digest unavailable.')
                found[kind] = item
    require(set(found) == set(ARTIFACTS), 'Missing artifact.')
    return found


def extract_expected(zip_path, output, names, limit, kind=None):
    try:
        with zipfile.ZipFile(zip_path) as archive:
            entries = archive.infolist()
            if kind == 'mysql':
                # The workflow uploads mysql-evidence/mysql-integration-*/summary.json;
                # the producer's token_hex(16) fixes the one permitted ZIP entry shape.
                require(names == frozenset({'summary.json'}) and len(entries) == 1 and
                        re.fullmatch(r'mysql-integration-[a-f0-9]{32}/summary\.json',
                                     entries[0].filename) is not None,
                        'Artifact ZIP entries mismatch.')
            else:
                require({entry.filename for entry in entries} == names and len(entries) == len(names),
                        'Artifact ZIP entries mismatch.')
            require(sum(entry.file_size for entry in entries) <= limit,
                    'Artifact uncompressed size exceeds limit.')
            for entry in entries:
                destination_name = 'summary.json' if kind == 'mysql' else entry.filename
                parts = destination_name.split('/')
                require(len(parts) == 1 or (len(parts) == 2 and parts[0] == 'react-browser-evidence'),
                        'Unsafe ZIP entry path.')
                if len(parts) == 2:
                    (output / parts[0]).mkdir(exist_ok=True)
                mode = (entry.external_attr >> 16) & 0o170000
                require(not entry.is_dir() and mode != 0o120000
                        and 0 < entry.file_size <= limit, 'Unsafe ZIP entry.')
                with archive.open(entry) as source, (output / destination_name).open('xb') as destination:
                    remaining = entry.file_size
                    while remaining:
                        data = source.read(min(65536, remaining))
                        require(bool(data), 'Truncated ZIP entry.')
                        remaining -= len(data)
                        destination.write(data)
                    require(not source.read(1), 'ZIP entry exceeds declared size.')
    except (zipfile.BadZipFile, RuntimeError):
        raise Rejected('Invalid artifact ZIP.') from None


def collect(client, repo, run_id, sha, attempt, event, output, *, clock=time.monotonic,
            now=lambda: datetime.now(UTC), sleep=time.sleep, wait=WAIT_SECONDS,
            download_wait: float = DOWNLOAD_SECONDS):
    require(re.fullmatch(r'[A-Za-z0-9_-]+/[A-Za-z0-9_.-]+', repo) is not None
            and re.fullmatch(r'[0-9a-f]{40}', sha) is not None
            and run_id > 0 and attempt > 0 and event in ('push', 'pull_request'),
            'Invalid explicit run identity.')
    output = Path(output)
    require(output.parent.is_dir() and not output.parent.is_symlink() and not output.exists(),
            'Output must be new in an existing directory.')
    jobs = wait_jobs(client, repo, run_id, sha, attempt, event, clock=clock, sleep=sleep, wait=wait)
    artifacts = check_artifacts(client.pages(f'repos/{repo}/actions/runs/{run_id}/artifacts', 'artifacts'),
                                sha, run_id, jobs, now)
    with tempfile.TemporaryDirectory(prefix='publication-', dir=output.parent) as temporary:
        root = Path(temporary)
        for kind, item in artifacts.items():
            folder = root / kind
            folder.mkdir()
            zip_path = root / (kind + '.zip')
            limit = RELEASE_LIMIT if kind == 'release' else SUMMARY_LIMIT
            deadline = clock() + download_wait
            downloaded = None
            for retry in range(2):
                try:
                    downloaded = client.download(f'repos/{repo}/actions/artifacts/{item["id"]}/zip',
                                                 zip_path, limit, deadline)
                    break
                except urllib.error.HTTPError as error:
                    error.close()
                    raise Rejected('Artifact HTTP response rejected.') from None
                except (TimeoutError, urllib.error.URLError):
                    zip_path.unlink(missing_ok=True)
                    if retry or clock() >= deadline:
                        raise Rejected('Artifact download timed out.') from None
            if downloaded is None:
                raise Rejected('Artifact download incomplete.')
            size, digest = downloaded
            require(size == item['size_in_bytes'] and digest == item['digest'][7:],
                    'Artifact ZIP size or digest mismatch.')
            extract_expected(zip_path, folder, ARTIFACTS[kind], limit, kind=kind)
            if kind != 'release':
                for name in ARTIFACTS[kind]:
                    raw = (folder / name).read_bytes()
                    if kind == 'browser' and name == 'react-browser-evidence/summary.json':
                        try:
                            check_react_browser_report.parse_report(raw)
                        except (ValueError, RecursionError):
                            raise Rejected('Invalid native browser summary.') from None
                        continue
                    try:
                        report = json.loads(raw)
                    except (ValueError, UnicodeDecodeError):
                        raise Rejected('Invalid artifact summary JSON.') from None
                    require(isinstance(report, dict) and report.get('status') == 'PASS',
                            'Artifact summary did not pass.')
                # Do not retain untrusted report fields, even if the CI producer is expected
                # to publish only sanitized summaries. The output JSON is allowlisted below.
                shutil.rmtree(folder)
            zip_path.unlink()
        manifest = release_artifact.verify(root / 'release/release.tar.gz',
                                           root / 'release/release-manifest.json')
        require(manifest['source']['commit'] == sha, 'Release source SHA mismatch.')
        require(all(stamp(item['expires_at']) > now() for item in artifacts.values()),
                'Artifact expired during collection.')
        summary = {'status': 'ok', 'repo': repo, 'run_id': run_id, 'sha': sha,
                   'attempt': attempt, 'event': event, 'jobs': sorted(JOBS),
                   'artifacts': sorted(artifacts), 'release_id': manifest['release_id'],
                   'archive_sha256': manifest['archive']['sha256']}
        (root / 'publication-summary.json').write_text(json.dumps(summary, sort_keys=True) + '\n')
        # Keep only the verified candidate and a collector-owned allowlisted summary.
        root.rename(output)
    return summary


def download_seconds(value):
    message = f'must be an integer in 1..{MAX_DOWNLOAD_SECONDS}'
    try:
        seconds = int(value)
    except ValueError:
        raise argparse.ArgumentTypeError(message) from None
    if not 1 <= seconds <= MAX_DOWNLOAD_SECONDS:
        raise argparse.ArgumentTypeError(message)
    return seconds


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--repo', required=True)
    parser.add_argument('--run-id', required=True, type=int)
    parser.add_argument('--sha', required=True)
    parser.add_argument('--attempt', required=True, type=int)
    parser.add_argument('--event', required=True, choices=('push', 'pull_request'))
    parser.add_argument('--output', required=True, type=Path)
    parser.add_argument('--download-seconds', type=download_seconds, default=DOWNLOAD_SECONDS,
                        metavar=f'1..{MAX_DOWNLOAD_SECONDS}',
                        help='Per-artifact download budget; retries share it (default: %(default)s seconds).')
    args = parser.parse_args(argv)
    try:
        client = Github(os.environ.get('GH_TOKEN'))
        summary = collect(client, args.repo, args.run_id, args.sha, args.attempt,
                          args.event, args.output, download_wait=args.download_seconds)
        print(json.dumps(summary, sort_keys=True))
        return 0
    except (Rejected, OSError, ValueError, KeyError, TypeError, EOFError, http.client.HTTPException,
            tarfile.TarError, zlib.error, urllib.error.URLError, zipfile.BadZipFile,
            release_artifact.ArtifactError):
        # No raw exception, URL, response, token, path or untrusted metadata in output.
        print('Publication collection rejected; inspect run identity, jobs and artifacts privately.',
              file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
