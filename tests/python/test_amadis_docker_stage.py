import importlib.util
import os
from pathlib import Path
import tempfile
import unittest
import json
from unittest.mock import patch
import subprocess
import sys


SPEC = importlib.util.spec_from_file_location('docker_stage', Path(__file__).resolve().parents[2] / 'scripts/amadis/docker_stage.py')
stage = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(stage)


class DockerStageTests(unittest.TestCase):
    def test_copy_uses_latest_bytes_and_excludes_operational_state(self):
        with tempfile.TemporaryDirectory() as directory:
            source = Path(directory) / 'source'
            target = Path(directory) / 'target'
            for name in ('app', 'bootstrap/cache', 'database/migrations', 'storage', 'vendor/composer'):
                (source / name).mkdir(parents=True)
            for name in ('composer.json', 'composer.lock', 'app/current.php', 'vendor/composer/installed.json',
                         '.env', 'database/database.sqlite', 'database/dump.sql', 'storage/session', 'bootstrap/cache/config.php'):
                (source / name).write_text('latest uncommitted bytes')
            stage.copy_source(source, target)
            for name in ('app/current.php', 'composer.lock', 'vendor/composer/installed.json'):
                self.assertEqual((target / name).read_text(), 'latest uncommitted bytes')
                self.assertNotEqual((target / name).stat().st_ino, (source / name).stat().st_ino)
                self.assertEqual((target / name).stat().st_nlink, 1)
            for name in ('.env', 'database/database.sqlite', 'database/dump.sql', 'storage', 'bootstrap/cache/config.php'):
                self.assertFalse((target / name).exists(), name)

    def test_full_source_and_executable_modes(self):
        with tempfile.TemporaryDirectory() as directory:
            source = Path(directory) / 'source'
            target = Path(directory) / 'target'
            for name in ('vendor', 'resources', 'public', 'tests', 'docs', 'scripts'):
                (source / name).mkdir(parents=True)
                (source / name / 'dirty.txt').write_text('latest')
            for name in ('composer.json', 'composer.lock', 'package.json', 'phpunit.xml'):
                (source / name).write_text('{}')
            executable = source / 'scripts/tool'
            executable.write_text('#!/bin/sh\n')
            executable.chmod(0o755)
            for name in ('scripts/auth.json', 'scripts/private.pem', 'scripts/test.sqlite-wal'):
                (source / name).write_text('secret')
            stage.copy_source(source, target)
            for name in ('resources', 'public', 'tests', 'docs'):
                self.assertTrue((target / name / 'dirty.txt').is_file())
            self.assertEqual((target / 'scripts/tool').stat().st_mode & 0o777, 0o700)
            self.assertTrue((target / 'package.json').is_file())
            for name in ('auth.json', 'private.pem', 'test.sqlite-wal'):
                self.assertFalse((target / 'scripts' / name).exists())

    def test_preflight_keeps_durable_evidence_for_every_outcome(self):
        for returncode, category in ((127, 'docker_missing'), (125, 'docker_unavailable'), (0, 'passed')):
            with self.subTest(category=category), tempfile.TemporaryDirectory() as directory:
                base = Path(directory)
                gate = {'returncode': returncode, 'category': category}
                with patch.object(stage.launch, 'BASE', base), patch.object(stage, 'copy_source'), \
                     patch.object(stage, 'stage_identity', return_value={}), \
                     patch.object(stage, 'run_gate', return_value=gate):
                    self.assertEqual(stage.main(['preflight']), returncode)
                root = next(p for p in base.iterdir() if not p.name.startswith('amadis-stage-'))
                record = json.loads((root / 'evidence/result.json').read_text())
                self.assertEqual((record['category'], record['returncode']), (category, returncode))

    def test_gate_categorizes_missing_docker_and_redacts_output(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            with patch.object(stage.subprocess, 'run', side_effect=FileNotFoundError):
                missing = stage.run_gate(root, 'docker', ['docker', 'info'])
            self.assertEqual((missing['category'], missing['returncode']), ('docker_missing', 127))
            leaky = subprocess.CompletedProcess([], 1, 'password=hunter2\n', 'Cannot connect to the Docker daemon')
            with patch.object(stage.subprocess, 'run', return_value=leaky):
                record = stage.run_gate(root, 'info', ['docker', 'info'])
            self.assertEqual(record['category'], 'docker_unavailable')
            self.assertNotIn('hunter2', record['stdout'])
            self.assertTrue(any(p.name.startswith('info-') for p in (root / 'evidence').iterdir()))

    def test_copy_rejects_symlinks_and_hardlinks(self):
        for kind in ('symlink', 'hardlink'):
            with self.subTest(kind=kind), tempfile.TemporaryDirectory() as directory:
                source = Path(directory) / 'source'
                (source / 'app').mkdir(parents=True)
                original = Path(directory) / 'outside'
                original.write_text('private')
                if kind == 'symlink':
                    (source / 'app/linked').symlink_to(original)
                else:
                    os.link(original, source / 'app/linked')
                with self.assertRaises(SystemExit):
                    stage.copy_source(source, Path(directory) / 'target')

    def test_command_mounts_only_independent_stage_and_is_offline(self):
        root = stage.launch.BASE / 'amadis-stage-test'
        command = stage.container_command(root, 'php-image', ['php', '-v'])
        for option, value in (('--network', 'none'), ('--cap-drop', 'ALL'),
                              ('--security-opt', 'no-new-privileges:true'), ('--memory', '2g'),
                              ('--cpus', '2'), ('--pids-limit', '128')):
            self.assertEqual(command[command.index(option) + 1], value)
        self.assertIn('--read-only', command)
        mounts = [command[i + 1] for i, item in enumerate(command) if item == '--mount']
        self.assertEqual(len(mounts), 1)
        self.assertIn('src=' + str(root), mounts[0])
        self.assertNotIn(str(stage.launch.REPO), ' '.join(mounts))
        self.assertNotIn('docker.sock', ' '.join(command))

    def test_proxy_allowlist_is_exact_and_port_bound(self):
        source = (Path(__file__).resolve().parents[2] / 'scripts/amadis/connect_proxy.php').read_text()
        self.assertIn("'repo.packagist.org'", source)
        self.assertIn("$port === '443'", source)
        self.assertIn("$parts[0] ?? '') === 'CONNECT'", source)
        self.assertIn("'403 Forbidden'", source)

    def test_preflight_uses_approved_composer_digest(self):
        self.assertEqual(stage.IMAGE, 'composer@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac')

    def test_install_gate_requires_every_recorded_predicate(self):
        required = {'allowed', 'sentinel_isolation'}
        self.assertFalse(stage.install_gate({}, required))
        self.assertFalse(stage.install_gate({'allowed': {'status': 'PASS'}}, required))
        self.assertFalse(stage.install_gate({'allowed': {'status': 'PASS'},
                                            'sentinel_isolation': {'status': 'FAIL'}}, required))
        self.assertFalse(stage.install_gate({'allowed': {'status': 'PASS'},
                                            'sentinel_isolation': {'status': 'PASS', 'returncode': 1}}, required))
        self.assertTrue(stage.install_gate({name: {'status': 'PASS', 'returncode': 0}
                                           for name in required}, required))

    def test_networked_command_stays_hardened_and_never_publishes_ports(self):
        root = stage.launch.BASE / 'amadis-stage-test'
        command = stage.container_command(root, stage.IMAGE, ['php', '-m'], network='amadis-stage-int-test')
        self.assertEqual(command[command.index('--network') + 1], 'amadis-stage-int-test')
        self.assertIn('--read-only', command)
        self.assertNotIn('-p', command)
        self.assertNotIn('--privileged', command)
        self.assertNotIn('host', command)

    def test_missing_or_crashed_probe_results_fail(self):
        for raw, rc in (('{}', 0), ('not json', 0), ('{"allowed":{"status":"PASS","returncode":0}}', 1)):
            records = stage.probe_results(raw, rc, {'allowed', 'direct'})
            self.assertEqual(set(records), {'allowed', 'direct'})
            self.assertFalse(stage.install_gate(records, {'allowed', 'direct'}))

    def test_stage_env_satisfies_guard_and_hook_requirements(self):
        root = stage.launch.BASE / 'amadis-abc'
        env = dict(stage.stage_env(root, 'base64:key'))
        self.assertEqual((env['AMADIS_ROOT'], env['AMADIS_DATABASE'], env['DB_DATABASE']),
                         (str(root), str(root / 'database.sqlite'), str(root / 'database.sqlite')))
        self.assertEqual((env['DB_CONNECTION'], env['DB_URL'], env['APP_KEY']), ('sqlite', '', 'base64:key'))
        for key, relative in (('LARAVEL_STORAGE_PATH', 'storage'), ('APP_CONFIG_CACHE', 'bootstrap/config.php'),
                              ('APP_SERVICES_CACHE', 'bootstrap/services.php'), ('APP_PACKAGES_CACHE', 'bootstrap/packages.php'),
                              ('APP_ROUTES_CACHE', 'bootstrap/routes.php'), ('APP_EVENTS_CACHE', 'bootstrap/events.php'),
                              ('VIEW_COMPILED_PATH', 'storage/framework/views')):
            self.assertEqual(env[key], str(root / relative))

    def test_topology_rejects_shared_or_missing_isolation(self):
        def item(*nets):
            return {'NetworkSettings': {'Ports': {}, 'Networks': {n: {} for n in nets}}, 'State': {'Running': True}}
        good = {'proxy': item('bridge', 'c', 's'), 'composer': item('c'), 'sentinel': item('s')}
        internal = {'c': {'Internal': True}, 's': {'Internal': True}}
        self.assertTrue(stage.topology_valid(good, internal, 'c', 's'))
        shared = dict(good, sentinel=item('c'))
        self.assertFalse(stage.topology_valid(shared, internal, 'c', 's'))
        self.assertFalse(stage.topology_valid(dict(good, composer=item('c', 's')), internal, 'c', 's'))
        self.assertFalse(stage.topology_valid(good, {'c': {'Internal': True}, 's': {'Internal': False}}, 'c', 's'))
        published = dict(good, composer={'NetworkSettings': {'Ports': {'80/tcp': [{}]}, 'Networks': {'c': {}}},
                                          'State': {'Running': True}})
        self.assertFalse(stage.topology_valid(published, internal, 'c', 's'))

    def test_unexpected_changes_flags_writes_outside_apply_set(self):
        before = {'composer.json': 'a', 'app/x.php': 'b', 'vendor/composer/installed.json': 'c'}
        after = {'composer.json': 'A', 'app/x.php': 'b', 'vendor/composer/installed.json': 'C',
                 'vendor/dompdf/dompdf/src/Dompdf.php': 'd'}
        self.assertEqual(stage.unexpected_changes(before, after), [])
        self.assertEqual(stage.unexpected_changes(before, dict(after, **{'app/x.php': 'evil'})), ['app/x.php'])
        self.assertEqual(stage.unexpected_changes(before, dict(after, **{'public/vendor/a.js': 'n'})),
                         ['public/vendor/a.js'])
        self.assertEqual(stage.unexpected_changes(dict(before, **{'routes/web.php': 'r'}), after), ['routes/web.php'])

    def test_lock_operations_are_parsed_exactly(self):
        text = 'Lock file operations: 5 installs, 0 updates, 0 removals\n'
        self.assertEqual(stage.lock_operations(text), (5, 0, 0))
        self.assertIsNone(stage.lock_operations('Nothing to modify in lock file'))

    def _apply_fixture(self, directory):
        repo, staged = Path(directory) / 'repo', Path(directory) / 'staged'
        for base, marker in ((repo, 'old'), (staged, 'new')):
            (base / 'vendor/composer').mkdir(parents=True)
            for name in ('composer.json', 'composer.lock', 'vendor/composer/installed.json'):
                (base / name).write_text(marker)
        (repo / 'vendor/laravel').mkdir()
        (repo / 'vendor/laravel/keep.php').write_text('keep')
        for package in ('dompdf/dompdf', 'masterminds/html5', 'sabberworm/php-css-parser'):
            (staged / 'vendor' / package).mkdir(parents=True)
            (staged / 'vendor' / package / 'f.php').write_text(package)
        return repo, staged

    def test_apply_copies_only_closure_and_rolls_back_on_failed_verify(self):
        with tempfile.TemporaryDirectory() as directory:
            repo, staged = self._apply_fixture(directory)
            expected = stage.tree_digest(repo, stage.APPLY_FILES + stage.APPLY_DIRS)
            snapshot = stage.tree_digest(repo, ['composer.json', 'composer.lock', 'vendor'])
            with self.assertRaises(RuntimeError):
                stage.apply_to_repo(repo, staged, Path(directory) / 'backup1', expected, lambda: False)
            self.assertEqual(stage.tree_digest(repo, ['composer.json', 'composer.lock', 'vendor']), snapshot)
            stage.apply_to_repo(repo, staged, Path(directory) / 'backup2', expected, lambda: True)
            self.assertEqual((repo / 'composer.lock').read_text(), 'new')
            self.assertEqual((repo / 'vendor/dompdf/dompdf/f.php').read_text(), 'dompdf/dompdf')
            self.assertEqual((repo / 'vendor/laravel/keep.php').read_text(), 'keep')

    def test_apply_includes_generated_autoload_maps(self):
        with tempfile.TemporaryDirectory() as directory:
            repo, staged = self._apply_fixture(directory)
            (repo / 'vendor/composer/autoload_psr4.php').write_text('old')
            (staged / 'vendor/composer/autoload_psr4.php').write_text('new')
            expected = stage.tree_digest(repo, stage.APPLY_FILES + stage.APPLY_DIRS)
            stage.apply_to_repo(repo, staged, Path(directory) / 'backup', expected, lambda: True)
            self.assertEqual((repo / 'vendor/composer/autoload_psr4.php').read_text(), 'new')

    def test_failed_verify_removes_newly_created_apply_files(self):
        with tempfile.TemporaryDirectory() as directory:
            repo, staged = self._apply_fixture(directory)
            (staged / 'vendor/composer/autoload_psr4.php').write_text('new')
            expected = stage.tree_digest(repo, stage.APPLY_FILES + stage.APPLY_DIRS)
            with self.assertRaises(RuntimeError):
                stage.apply_to_repo(repo, staged, Path(directory) / 'backup', expected, lambda: False)
            self.assertFalse((repo / 'vendor/composer/autoload_psr4.php').exists())
            self.assertEqual(stage.tree_digest(repo, stage.APPLY_FILES + stage.APPLY_DIRS), expected)

    def test_partial_copy_failure_rolls_back_to_initial_digest(self):
        with tempfile.TemporaryDirectory() as directory:
            repo, staged = self._apply_fixture(directory)
            (staged / 'vendor/composer/autoload_psr4.php').write_text('new')
            expected = stage.tree_digest(repo, stage.APPLY_FILES + stage.APPLY_DIRS)
            real_copyfile = stage.shutil.copyfile
            copies = []

            def failing_copy(src, dst):
                if Path(src).is_relative_to(staged):
                    copies.append(src)
                    real_copyfile(src, dst)
                    if len(copies) == 2:
                        self.assertEqual((repo / 'composer.json').read_text(), 'new')
                        raise OSError('synthetic disk full during apply')
                    return dst
                return real_copyfile(src, dst)

            with patch.object(stage.shutil, 'copyfile', failing_copy), self.assertRaises(OSError):
                stage.apply_to_repo(repo, staged, Path(directory) / 'backup', expected, lambda: True)
            self.assertEqual(len(copies), 2)
            self.assertEqual(stage.tree_digest(repo, stage.APPLY_FILES + stage.APPLY_DIRS), expected)
            self.assertFalse((repo / 'vendor/composer/autoload_psr4.php').exists())

    def _direct_egress(self, connect):
        import contextlib
        import io
        import socket
        namespace = {'__name__': 'probe'}
        out = io.StringIO()
        with patch.object(sys, 'argv', ['probe.py', 'proxy', 'sentinel', 'gateway']), \
             patch.object(socket, 'create_connection', connect), \
             patch.object(subprocess, 'run', side_effect=OSError('no subprocess')), \
             contextlib.redirect_stdout(out):
            exec(stage.PROBE_SCRIPT, namespace)
        return json.loads(out.getvalue())['direct_egress']

    def test_direct_egress_uses_literal_ips_and_passes_only_on_network_failure(self):
        import errno
        calls = []

        def unreachable(address, timeout=None):
            calls.append(address)
            raise OSError(errno.ENETUNREACH, 'Network is unreachable')
        record = self._direct_egress(unreachable)
        self.assertEqual(record['status'], 'PASS')
        # The shared socket patch also sees proxy/sentinel probes; only the direct targets matter here.
        self.assertEqual([c for c in calls if c[1] == 443], [('1.1.1.1', 443), ('8.8.8.8', 443), ('9.9.9.9', 443)])
        self.assertNotIn(('proxy', 443), calls)
        self.assertEqual({a['errno'] for a in record['detail']}, {errno.ENETUNREACH})

        def dns_like(address, timeout=None):
            raise OSError(-2, 'Name or service not known')
        self.assertEqual(self._direct_egress(dns_like)['status'], 'FAIL')

        def timed_out(address, timeout=None):
            raise TimeoutError('timed out')
        self.assertEqual(self._direct_egress(timed_out)['status'], 'FAIL')

        class Connected:
            def __enter__(self):
                return self

            def __exit__(self, *args):
                return False

            def getpeername(self):
                return ('1.1.1.1', 443)
        self.assertEqual(self._direct_egress(lambda address, timeout=None: Connected())['status'], 'FAIL')

    def test_apply_refuses_when_repo_drifted(self):
        with tempfile.TemporaryDirectory() as directory:
            repo, staged = self._apply_fixture(directory)
            expected = stage.tree_digest(repo, stage.APPLY_FILES + stage.APPLY_DIRS)
            (repo / 'composer.lock').write_text('drift')
            with self.assertRaises(RuntimeError):
                stage.apply_to_repo(repo, staged, Path(directory) / 'backup', expected, lambda: True)
            self.assertEqual((repo / 'composer.lock').read_text(), 'drift')
            self.assertFalse((repo / 'vendor/dompdf').exists())

    def test_install_stage_refuses_incomplete_preflight_without_running(self):
        calls = []
        with tempfile.TemporaryDirectory() as directory:
            base = Path(directory)
            boundary = {'allowed': {'status': 'PASS', 'returncode': 0}}
            with self.assertRaises(RuntimeError):
                stage.install_stage(base, base, base, lambda *a, **k: calls.append(a), 'net', boundary)
        self.assertEqual(calls, [])

    def test_hooks_require_verbose_script_lines_and_root_artifacts(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            text = ('> post-autoload-dump: App\\Support\\ComposerHooks::postAutoloadDump\n'
                    '> post-update-cmd: App\\Support\\ComposerHooks::postUpdate\n')
            self.assertFalse(stage.hooks_ran(text, root))
            (root / 'bootstrap').mkdir()
            (root / 'bootstrap/packages.php').write_text('<?php return [];')
            self.assertTrue(stage.hooks_ran(text, root))
            self.assertFalse(stage.hooks_ran('ComposerHooks::postUpdate', root))

    def test_stage_install_cli_runs_install_preflight(self):
        with patch.object(stage, 'boundary_preflight', return_value=0) as preflight:
            self.assertEqual(stage.main(['stage-install']), 0)
        preflight.assert_called_once_with(install=True)

    def test_stage_runtime_cli_runs_without_install(self):
        with patch.object(stage, 'boundary_preflight', return_value=0) as preflight:
            self.assertEqual(stage.main(['stage-runtime']), 0)
        preflight.assert_called_once_with(runtime_only=True)

    def test_install_crash_cannot_pass_report(self):
        with tempfile.TemporaryDirectory() as directory:
            base = Path(directory)
            with patch.object(stage.launch, 'BASE', base), patch.object(stage.launch, 'REPO', base), \
                 patch.object(stage, 'copy_source', side_effect=RuntimeError('copy crashed')):
                self.assertEqual(stage.boundary_preflight(install=True), 1)
            report = json.loads(next(base.glob('docs/**/report.json')).read_text())
            for name in ('install', 'pins', 'hooks', 'class_load', 'change_scope'):
                self.assertEqual(report['predicates'][name]['status'], 'FAIL')

    def test_cleanup_removes_same_named_containers_before_networks(self):
        created = [('network', 'amadis-stage-composer-x'), ('container', 'amadis-stage-proxy-x'),
                   ('container', 'amadis-stage-composer-x')]
        commands = stage.cleanup_commands(created)
        self.assertEqual([c[1] for c in commands], [['docker', 'rm', '--force', 'amadis-stage-composer-x'],
                                                     ['docker', 'rm', '--force', 'amadis-stage-proxy-x'],
                                                     ['docker', 'network', 'rm', 'amadis-stage-composer-x']])
        self.assertEqual(len({c[0] for c in commands}), 3)

    def test_install_remains_fail_closed(self):
        with self.assertRaises(SystemExit) as error:
            stage.main(['install'])
        self.assertIn('network', str(error.exception))


if __name__ == '__main__':
    unittest.main()
