
"""NFR-CICD-004/006: MySQL 8.4 disposable integration, never a host DB target.

Usage: python3 scripts/ci/mysql_integration.py --output EXISTING_PRIVATE_DIR
Linux + Python 3.11+; local /var/run/docker.sock, preloaded mysql:8.4, PHP + pdo_mysql and
Composer dev dependencies required. No pulls, installs, dotenv reads or target
options. Run from a clean, trusted checkout. Output must be canonical, owned,
0700 and outside the checkout. Each run gets a new private subdirectory.

Only summary.json is publishable. phpunit.xml and runtime files remain PRIVATE.
Dump bytes live in an unlinked temporary file, never in evidence. SIGINT/TERM
attempt bounded cleanup; SIGKILL/daemon loss require runner-level reclamation.
The local daemon, installed tools/dependencies and exclusive checkout are trusted.
"""
import argparse
import base64

import hashlib
import importlib.util
import io
import json
import os

import re
import secrets
import signal
import stat
import subprocess
import sys
import tempfile
import time
from contextlib import redirect_stderr, redirect_stdout
from pathlib import Path

# Importing the canonical checker must not write into the checkout.
sys.dont_write_bytecode = True
REPO = Path(__file__).resolve().parents[2]
IMAGE = 'mysql:8.4'
SCHEMA = 'zafira_upgrade13_phpunit_20261015_0420'
OWNER_LABEL = 'io.zafira.ci.mysql.owner'
ROLE_LABEL = 'io.zafira.ci.mysql.role'
TEST_FILE = 'tests/Feature/MysqlCheckoutRetryTest.php'
TEST_IDS = {
    'Tests.Feature.MysqlCheckoutRetryTest::' + name for name in (
        'test_deadlock_retry_succeeds_replays_and_cancel_restores_stock_only_once',
        'test_deadlock_exhaustion_returns_503_without_partial_writes_and_key_is_reusable',
        'test_lock_wait_timeout_is_not_classified_as_deadlock',
    )
}
INSPECT = ('{"id":{{json .Id}},"name":{{json .Name}},'
           '"labels":{{json .Config.Labels}},"image":{{json .Image}},'
           '"bindings":{{json .HostConfig.PortBindings}},'
           '"mounts":{{json .Mounts}},"running":{{json .State.Running}},'
           '"ports":{{json .NetworkSettings.Ports}}}')


class LaneError(Exception):
    """Messages are fixed reason codes, never subprocess output or paths."""
    def __init__(self, reason, code=1):
        super().__init__(reason)
        self.code = code if 1 <= code <= 255 else 1


def require(condition, reason):
    if not condition:
        raise LaneError(reason)


def exit_code(code):
    return 128 - code if code < 0 else code


def invoke(argv, *, env, cwd=REPO, timeout=30, data=None, stdin=None,
           stdout=subprocess.PIPE):
    try:
        return subprocess.run(argv, cwd=cwd, env=env, timeout=timeout, check=False,
                              input=data, stdin=stdin, stdout=stdout, stderr=subprocess.DEVNULL)
    except subprocess.TimeoutExpired:
        raise LaneError('PROCESS_TIMEOUT', 124) from None
    except OSError:
        raise LaneError('TOOL_UNAVAILABLE') from None


def successful(result, reason):
    if result.returncode:
        raise LaneError(reason, exit_code(result.returncode))
    return result.stdout


def clean_checkout(repo, env, expected=None):
    # Names/metadata only, before Git or PHP can inspect any operational dotenv.
    for entry in repo.iterdir():
        if entry.name.startswith('.env'):
            require(entry.name == '.env.example' and not entry.is_symlink(), 'DOTENV_PRESENT')
    require(not os.path.lexists(repo / 'bootstrap/cache/config.php'), 'CONFIG_CACHE_PRESENT')
    git = ['git', '--no-pager', '--no-optional-locks', '--no-replace-objects',
           '-c', 'core.fsmonitor=false']
    top = successful(invoke(git + ['rev-parse', '--show-toplevel'], cwd=repo, env=env), 'GIT_FAILED')
    require(Path(top.decode().strip()).resolve() == repo, 'CHECKOUT_ROOT_MISMATCH')
    # The example is not source input to this lane and must not be opened.
    dirty = successful(invoke(git + ['status', '--porcelain=v1', '-z', '--untracked-files=all',
                                    '--ignore-submodules=none', '--', '.', ':(exclude).env*'],
                              cwd=repo, env=env), 'GIT_FAILED')
    require(not dirty, 'DIRTY_CHECKOUT')
    commit = successful(invoke(git + ['rev-parse', '--verify', 'HEAD^{commit}'],
                               cwd=repo, env=env), 'GIT_FAILED').decode().strip()
    require(re.fullmatch(r'[a-f0-9]{40}|[a-f0-9]{64}', commit), 'INVALID_COMMIT')
    require(expected is None or expected == commit, 'CHECKOUT_CHANGED')
    return commit


def private_output(path, repo):
    path = Path(os.path.abspath(path))
    info = path.lstat()
    require(stat.S_ISDIR(info.st_mode) and path.resolve(strict=True) == path
            and info.st_uid == os.getuid() and stat.S_IMODE(info.st_mode) == 0o700,
            'OUTPUT_NOT_PRIVATE')
    require(path != repo and repo not in path.parents, 'OUTPUT_INSIDE_CHECKOUT')
    return path


class Lane:
    def __init__(self, output, repo=REPO):
        self.repo = repo
        self.output = private_output(output, repo)
        self.token = secrets.token_hex(16)
        self.directory = self.output / ('mysql-integration-' + self.token)
        self.directory.mkdir(mode=0o700)
        self.runtime = self.directory / 'private'
        for relative in ('', 'docker', 'bootstrap', 'storage/framework/cache/data',
                         'storage/framework/sessions', 'storage/framework/views', 'storage/logs'):
            (self.runtime / relative).mkdir(mode=0o700, parents=True, exist_ok=True)
        self.env = {'PATH': os.environ.get('PATH', '/usr/bin:/bin'),
                    'HOME': str(self.runtime), 'TMPDIR': str(self.runtime), 'LC_ALL': 'C'}
        self.docker = ['docker', '--host', 'unix:///var/run/docker.sock',
                       '--config', str(self.runtime / 'docker')]
        self.containers = []
        self.image = None
        self.commit = None
        self.summary: dict[str, object] = {'status': 'FAIL', 'cleanup': 'NOT_STARTED'}

    def docker_run(self, args, **kwargs):
        return invoke(self.docker + args, cwd=self.repo, env=kwargs.pop('env', self.env), **kwargs)

    def inspect(self, container, running=False):
        result = self.docker_run(['container', 'inspect', '--format', INSPECT,
                                  container['id'] or container['name']], timeout=10)
        raw = successful(result, 'OWNERSHIP_UNCONFIRMED')
        try:
            value = json.loads(raw)
            cid = value['id']
            require(isinstance(cid, str) and re.fullmatch(r'[a-f0-9]{64}', cid), 'OWNERSHIP_MISMATCH')
            require(container['id'] in (None, cid)
                    and value['name'] == '/' + container['name']
                    and value['labels'].get(OWNER_LABEL) == self.token
                    and value['labels'].get(ROLE_LABEL) == container['role']
                    and value['image'] == self.image, 'OWNERSHIP_MISMATCH')
            # Even cleanup refuses foreign bind mounts/volumes and non-loopback publication.
            bindings = value['bindings']
            require(set(bindings) == {'3306/tcp'} and len(bindings['3306/tcp']) == 1
                    and bindings['3306/tcp'][0]['HostIp'] == '127.0.0.1', 'UNSAFE_CONTAINER')
            require(all(m['Type'] == 'tmpfs' and m['Destination'] == '/var/lib/mysql'
                        for m in value['mounts']), 'UNSAFE_CONTAINER')
            if running:
                require(value['running'] is True, 'CONTAINER_NOT_RUNNING')
                ports = {key: ports for key, ports in value['ports'].items() if ports}
                require(set(ports) == {'3306/tcp'} and len(ports['3306/tcp']) == 1,
                        'UNSAFE_CONTAINER_PORT')
                binding = ports['3306/tcp'][0]
                port = int(binding['HostPort'])
                require(binding['HostIp'] == '127.0.0.1' and 1024 <= port <= 65535
                        and container['port'] in (None, port), 'UNSAFE_CONTAINER_PORT')
                container['port'] = port
            container['id'] = cid
        except (ValueError, KeyError, TypeError):
            raise LaneError('INVALID_INSPECT') from None
        return container['id']

    def create(self, role):
        container = {'role': role, 'name': 'zafira-ci-' + self.token + '-' + role,
                     'id': None, 'port': None, 'password': secrets.token_hex(32)}
        # Register intent before create: a timeout may leave a container behind.
        self.containers.append(container)
        env = dict(self.env, MYSQL_ROOT_PASSWORD=container['password'],
                   MYSQL_ROOT_HOST='%', MYSQL_DATABASE=SCHEMA)
        result = self.docker_run([
            'create', '--pull=never', '--name', container['name'],
            '--label', OWNER_LABEL + '=' + self.token, '--label', ROLE_LABEL + '=' + role,
            '--publish', '127.0.0.1::3306', '--network', 'bridge',
            '--tmpfs', '/var/lib/mysql:rw,nosuid,size=1073741824',
            '--env', 'MYSQL_ROOT_PASSWORD', '--env', 'MYSQL_ROOT_HOST', '--env', 'MYSQL_DATABASE',
            self.image,
        ], env=env, timeout=30)
        cid = successful(result, 'CREATE_FAILED').decode().strip()
        require(re.fullmatch(r'[a-f0-9]{64}', cid), 'INVALID_CONTAINER_ID')
        container['id'] = cid
        self.inspect(container)
        successful(self.docker_run(['start', cid], timeout=30), 'START_FAILED')
        return container

    def execute(self, container, args, **kwargs):
        cid = self.inspect(container, running=True)
        return self.docker_run(['exec', '--interactive', '--env', 'MYSQL_PWD', cid] + args,
                               env=dict(self.env, MYSQL_PWD=container['password']), **kwargs)

    def mysql(self, container, query):
        return self.execute(container, [
            'mysql', '--no-defaults', '--protocol=TCP', '--host=127.0.0.1', '--port=3306',
            '--user=root', '--connect-timeout=5', '--batch', '--skip-column-names', '--raw', SCHEMA,
        ], data=query.encode(), timeout=15)

    def sql(self, container, query):
        return successful(self.mysql(container, query), 'SQL_FAILED').decode('utf-8')

    def ready(self, container):
        deadline = time.monotonic() + 120
        while time.monotonic() < deadline:
            result = self.mysql(container, 'SELECT VERSION(), DATABASE(), @@default_storage_engine, @@foreign_key_checks;')
            if result.returncode == 0:
                values = result.stdout.decode().strip().split('\t')
                require(len(values) == 4 and re.fullmatch(r'8\.4\.\d+(?:-[A-Za-z0-9_.]+)?', values[0])
                        and values[1:] == [SCHEMA, 'InnoDB', '1'], 'ENGINE_SCHEMA_MISMATCH')
                return values[0]
            time.sleep(1)
        raise LaneError('READINESS_TIMEOUT', 124)

    def empty_schema(self, container):
        for kind in ('tables', 'routines', 'triggers', 'events'):
            column = {'tables': 'table_schema', 'routines': 'routine_schema',
                      'triggers': 'trigger_schema', 'events': 'event_schema'}[kind]
            require(self.sql(container, f'SELECT COUNT(*) FROM information_schema.{kind} WHERE {column}=DATABASE();').strip() == '0',
                    'SCHEMA_NOT_EMPTY')

    def php_environment(self, source):
        storage = self.runtime / 'storage'
        env = dict(self.env, APP_ENV='testing', APP_DEBUG='false', APP_URL='http://localhost',
                   APP_KEY='base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
                   APP_PREVIOUS_KEYS='', DB_CONNECTION='mysql', DB_HOST='127.0.0.1',
                   DB_PORT=str(source['port']), DB_DATABASE=SCHEMA, DB_USERNAME='root',
                   DB_PASSWORD=source['password'], DB_URL='', DB_SOCKET='',
                   DB_CHARSET='utf8mb4', DB_COLLATION='utf8mb4_unicode_ci',
                   ZAFIRA_SAFE_MYSQL_TESTS='1', CACHE_STORE='array', SESSION_DRIVER='array',
                   MAIL_MAILER='array', QUEUE_CONNECTION='sync', QUEUE_FAILED_DRIVER='null',
                   LOG_CHANNEL='stderr', LOG_DEPRECATIONS_CHANNEL='stderr',
                   LARAVEL_STORAGE_PATH=str(storage), VIEW_COMPILED_PATH=str(storage / 'framework/views'))
        for key, filename in {'APP_CONFIG_CACHE': 'config.php', 'APP_SERVICES_CACHE': 'services.php',
                              'APP_PACKAGES_CACHE': 'packages.php', 'APP_ROUTES_CACHE': 'routes.php',
                              'APP_EVENTS_CACHE': 'events.php'}.items():
            env[key] = str(self.runtime / 'bootstrap' / filename)
        return env

    def php(self, source, arguments, env):
        clean_checkout(self.repo, self.env, self.commit)
        self.inspect(source, running=True)
        require(not os.path.lexists(env['APP_CONFIG_CACHE']), 'CONFIG_CACHE_PRESENT')
        return invoke(['php'] + arguments, env=env, cwd=self.repo, timeout=300,
                      stdout=subprocess.DEVNULL).returncode

    def migrations(self, source):
        expected = sorted(p.stem for p in (self.repo / 'database/migrations').glob('*.php'))
        rows = self.sql(source, 'SELECT id, migration, batch FROM migrations ORDER BY migration;')
        parsed = [row.split('\t') for row in rows.splitlines()]
        require(expected and all(len(row) == 3 and row[0].isdigit() and row[2].isdigit() for row in parsed)
                and [row[1] for row in parsed] == expected, 'MIGRATION_SET_MISMATCH')
        return {'migration_count': len(parsed), 'migrations_sha256': digest(rows)}

    def snapshot(self, source):
        tables = self.sql(source, 'SELECT TABLE_NAME, ENGINE, TABLE_TYPE FROM information_schema.tables '
                          'WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME;')
        parsed = [row.split('\t') for row in tables.splitlines()]
        require(0 < len(parsed) <= 256 and all(len(row) == 3 and row[1:] == ['InnoDB', 'BASE TABLE']
                                             for row in parsed), 'SCHEMA_ENGINE_MISMATCH')
        names = [row[0] for row in parsed]
        require('ci_restore_probe' in names and len(set(names)) == len(names), 'MARKER_TABLE_MISSING')
        for kind, column in [('routines', 'routine_schema'), ('triggers', 'trigger_schema'), ('events', 'event_schema')]:
            require(self.sql(source, f'SELECT COUNT(*) FROM information_schema.{kind} WHERE {column}=DATABASE();').strip() == '0',
                    'UNSUPPORTED_SCHEMA_OBJECTS')
        defaults = self.sql(source, 'SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME '
                            'FROM information_schema.schemata WHERE SCHEMA_NAME=DATABASE();')
        require(len(defaults.splitlines()) == 1 and len(defaults.strip().split('\t')) == 2, 'SCHEMA_MISSING')
        ddl = self.sql(source, '\n'.join('SHOW CREATE TABLE ' + identifier(name) + ';' for name in names))
        require(bool(ddl.strip()), 'SCHEMA_MISSING')
        counts = self.sql(source, ' UNION ALL '.join(
            f"SELECT '{name}', COUNT(*) FROM {identifier(name)}" for name in names) + ';')
        rows = [row.split('\t') for row in counts.splitlines()]
        require([row[0] for row in rows] == names and all(len(row) == 2 and row[1].isdigit() for row in rows),
                'INVALID_ROW_COUNTS')
        marker = self.sql(source, 'SELECT id, marker, parent_id FROM ci_restore_probe ORDER BY id;')
        expected = f'1\t{digest(self.token)}\tNULL\n2\t{digest(self.token + "-child")}\t1\n'
        require(marker == expected, 'RESTORED_MARKER_MISMATCH')
        require(self.sql(source, 'SELECT @@foreign_key_checks;').strip() == '1', 'FK_CHECKS_DISABLED')
        fk_rows = self.sql(source, 'SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, '
                           'REFERENCED_COLUMN_NAME, REFERENCED_TABLE_SCHEMA, ORDINAL_POSITION '
                           'FROM information_schema.key_column_usage WHERE TABLE_SCHEMA=DATABASE() '
                           'AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION;')
        groups = {}
        for row in fk_rows.splitlines():
            parts = row.split('\t')
            require(len(parts) == 7 and parts[5] == SCHEMA and parts[6].isdigit(), 'INVALID_FOREIGN_KEY')
            child, constraint, column, parent, referenced = parts[:5]
            for value in parts[:5]:
                identifier(value)
            groups.setdefault((child, constraint, parent), []).append((column, referenced))
        require(bool(groups), 'FOREIGN_KEYS_MISSING')
        checks = []
        for (child, _, parent), columns in groups.items():
            nonnull = ' AND '.join('c.' + identifier(col) + ' IS NOT NULL' for col, _ in columns)
            matches = ' AND '.join('p.' + identifier(ref) + '=c.' + identifier(col) for col, ref in columns)
            checks.append(f'SELECT COUNT(*) FROM {identifier(child)} c WHERE {nonnull} '
                          f'AND NOT EXISTS (SELECT 1 FROM {identifier(parent)} p WHERE {matches})')
        violations = self.sql(source, ' UNION ALL '.join(checks) + ';').splitlines()
        require(violations == ['0'] * len(checks), 'FOREIGN_KEY_VIOLATION')
        return dict(self.migrations(source), schema_sha256=digest(defaults + ddl),
                    row_counts={row[0]: int(row[1]) for row in rows}, foreign_keys=len(groups),
                    foreign_key_violations=0, marker_rows=2, marker_sha256=digest(marker))

    def restore(self, source, target):
        self.empty_schema(target)
        # TemporaryFile is unlinked on Linux; raw SQL is not an output artifact.
        with tempfile.TemporaryFile(dir=self.runtime) as dump:
            successful(self.execute(source, [
                'mysqldump', '--no-defaults', '--protocol=TCP', '--host=127.0.0.1', '--port=3306',
                '--user=root', '--single-transaction', '--no-tablespaces', '--set-gtid-purged=OFF', SCHEMA,
            ], timeout=120, stdout=dump), 'DUMP_FAILED')
            require(0 < dump.tell() <= 64 * 1024 * 1024, 'INVALID_DUMP_SIZE')
            dump.seek(0)
            checksum = hashlib.file_digest(dump, 'sha256').hexdigest()
            dump.seek(0)
            successful(self.execute(target, [
                'mysql', '--no-defaults', '--protocol=TCP', '--host=127.0.0.1', '--port=3306',
                '--user=root', '--connect-timeout=5', '--binary-mode', '--local-infile=0', SCHEMA,
            ], timeout=120, stdin=dump, stdout=subprocess.DEVNULL), 'RESTORE_FAILED')
        self.summary['dump_sha256'] = checksum

    def run(self):
        self.commit = clean_checkout(self.repo, self.env)
        self.summary['commit'] = self.commit
        self.image = successful(self.docker_run(['image', 'inspect', '--format', '{{.Id}}', IMAGE]),
                                'MYSQL_IMAGE_UNAVAILABLE').decode().strip()
        require(re.fullmatch(r'sha256:[a-f0-9]{64}', self.image), 'INVALID_IMAGE_ID')
        self.summary['image_id'] = self.image
        source, target = self.create('source'), self.create('restore')
        versions = [self.ready(source), self.ready(target)]
        require(versions[0] == versions[1], 'VERSION_MISMATCH')
        self.summary['mysql_version'] = versions[0]
        self.empty_schema(source)
        self.empty_schema(target)
        env = self.php_environment(source)
        rc = self.php(source, ['artisan', 'migrate', '--force', '--no-interaction'], env)
        self.summary['migration_exit'] = exit_code(rc)
        if rc:
            raise LaneError('MIGRATION_FAILED', exit_code(rc))
        self.migrations(source)
        report = self.runtime / 'phpunit.xml'
        rc = self.php(source, ['vendor/bin/phpunit', '--configuration', 'phpunit.xml',
                              '--do-not-cache-result', '--log-junit', str(report), TEST_FILE], env)
        self.summary['producer_exit'] = exit_code(rc)
        self.summary['junit_gate'] = check_report(report, self.repo)
        if rc:
            raise LaneError('PHPUNIT_FAILED', exit_code(rc))
        require(self.summary['junit_gate'], 'JUNIT_REJECTED')
        self.summary['tests'] = 3
        self.summary['skipped'] = 0
        self.sql(source, 'CREATE TABLE ci_restore_probe (id INT PRIMARY KEY, marker CHAR(64) NOT NULL, '
                 'parent_id INT NULL, CONSTRAINT ci_probe_parent FOREIGN KEY (parent_id) REFERENCES ci_restore_probe(id)) ENGINE=InnoDB;'
                 f"INSERT INTO ci_restore_probe VALUES (1, '{digest(self.token)}', NULL);"
                 f"INSERT INTO ci_restore_probe VALUES (2, '{digest(self.token + '-child')}', 1);")
        before = self.snapshot(source)
        self.restore(source, target)
        after = self.snapshot(target)
        require(before == after, 'RESTORE_COMPARISON_FAILED')
        clean_checkout(self.repo, self.env, self.commit)
        self.summary['snapshot'] = after
        self.summary['restore'] = 'PASS'

    def cleanup(self):
        failures = 0
        for container in reversed(self.containers):
            try:
                cid = self.inspect(container)
                successful(self.docker_run(['rm', '--force', cid], timeout=30), 'REMOVE_FAILED')
            except Exception:
                # No name-wide cleanup, daemon prune, or fallback kill after identity loss.
                failures += 1
        self.summary['cleanup'] = 'FAIL' if failures else 'PASS'
        self.summary['cleanup_failures'] = failures
        self.summary['container_ids'] = [c['id'] for c in self.containers if c['id']]
        return failures == 0


def identifier(value):
    require(re.fullmatch(r'[A-Za-z_][A-Za-z0-9_]{0,63}', value), 'INVALID_SQL_IDENTIFIER')
    return '`' + value + '`'


def digest(text):
    return hashlib.sha256(text.encode()).hexdigest()


def check_report(path, repo):
    try:
        info = path.lstat()
        require(stat.S_ISREG(info.st_mode) and info.st_nlink == 1 and info.st_uid == os.getuid()
                and stat.S_IMODE(info.st_mode) == 0o600, 'REPORT_NOT_PRIVATE')
        spec = importlib.util.spec_from_file_location('ci_mysql_junit', repo / 'scripts/ci/check_test_report.py')
        if spec is None or spec.loader is None:
            return False
        gate = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(gate)
        with redirect_stdout(io.StringIO()), redirect_stderr(io.StringIO()):
            if gate.main([str(path)]) != 0:  # no skip allowlist
                return False
        cases = []
        counts = gate.read_suite(gate.read_xml(path), cases, set())
        return (counts['tests'] == 3 and counts['skipped'] == 0
                and {name for name, outcome in cases if outcome == 'passed'} == TEST_IDS)
    except Exception:
        return False


def publish_summary(directory, summary):
    # Only this invocation owns directory. Never expose PASS before write/close succeed.
    destination = directory / 'summary.json'
    staging = None
    try:
        fd, name = tempfile.mkstemp(prefix='.summary-', suffix='.tmp', dir=directory)
        staging = Path(name)
        with os.fdopen(fd, 'w', encoding='utf-8') as output:
            json.dump(summary, output, sort_keys=True, indent=2)
            output.write('\n')
            output.flush()
            os.fsync(output.fileno())
        os.replace(staging, destination)
    except Exception:
        # Also invalidate an ambiguous rename outcome; unlink never follows symlinks.
        try:
            destination.unlink(missing_ok=True)
        finally:
            if staging is not None:
                staging.unlink(missing_ok=True)
        raise


class SafeParser(argparse.ArgumentParser):
    def error(self, message):
        self.print_usage(sys.stderr)
        self.exit(2, 'MySQL integration: invalid arguments; use --help.\n')


def main(argv=None):
    parser = SafeParser(description=__doc__)
    parser.add_argument('--output', required=True, type=Path)
    args = parser.parse_args(argv)
    lane = None
    code, reason = 1, 'INTERNAL_ERROR'
    old_umask = os.umask(0o077)
    previous = {}

    def interrupted(signum, _frame):
        raise LaneError('INTERRUPTED', 128 + signum)

    try:
        require(sys.platform == 'linux', 'LINUX_REQUIRED')
        for sig in (signal.SIGTERM, signal.SIGINT):
            previous[sig] = signal.signal(sig, interrupted)
        lane = Lane(args.output)
        lane.run()
        code, reason = 0, 'COMPLETED'
    except LaneError as error:
        code, reason = error.code, str(error)
    except (Exception, KeyboardInterrupt):
        # Never serialize PHP/MySQL errors, argv, exception messages or local paths.
        code, reason = 1, 'INTERNAL_ERROR'
    finally:
        for sig in previous:
            signal.signal(sig, signal.SIG_IGN)
        summary = lane.summary if lane else {}
        if lane and not lane.cleanup() and code == 0:
            code, reason = 1, 'CLEANUP_FAILED'
        summary.update(status='PASS' if code == 0 else 'FAIL', reason=reason, exit=code)
        if lane:
            try:
                publish_summary(lane.directory, summary)
            except Exception:
                if code == 0:
                    code, reason = 1, 'SUMMARY_WRITE_FAILED'
                summary.update(status='FAIL', reason=reason, exit=code)
        for sig, handler in previous.items():
            signal.signal(sig, handler)
        os.umask(old_umask)
        print(json.dumps(summary, sort_keys=True))
    return code


if __name__ == '__main__':
    raise SystemExit(main())
