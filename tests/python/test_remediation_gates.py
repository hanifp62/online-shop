"""REM-20261021 / FR-REM-006: mocks only, no listeners or child processes."""
import importlib.util
from pathlib import Path
import signal
import subprocess
from types import SimpleNamespace
import unittest
from unittest.mock import MagicMock, patch

SPEC = importlib.util.spec_from_file_location(
    'acceptance_server', Path(__file__).resolve().parents[2] / 'scripts/amadis/acceptance_server.py')
if SPEC is None or SPEC.loader is None:
    raise ImportError('Acceptance supervisor test module cannot be loaded')
supervisor = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(supervisor)


def child():
    return MagicMock(pid=123, returncode=None)


class RemediationGatesTests(unittest.TestCase):
    def test_occupied_port_never_starts_or_signals_a_process(self):
        with patch.object(supervisor, 'preflight', side_effect=OSError('occupied')), \
             patch.object(supervisor.subprocess, 'Popen') as spawn, \
             patch.object(supervisor.os, 'killpg') as kill:
            with self.assertRaises(OSError):
                supervisor.main(['8042', 'unused.log', 'node', 'unused.cjs'])
            spawn.assert_not_called()
            kill.assert_not_called()

    def test_preflight_checks_bind_without_connecting_or_reusing_port(self):
        with patch.object(supervisor.socket, 'socket') as socket:
            supervisor.preflight(8042)
            probe = socket.return_value.__enter__.return_value
            probe.bind.assert_called_once_with(('127.0.0.1', 8042))
            probe.connect.assert_not_called()
            probe.setsockopt.assert_not_called()

    def test_every_listener_must_belong_to_owned_group(self):
        output = subprocess.CompletedProcess([], 0, '789\n790\n', '')
        for groups, expected in [([123, 123], True), ([123, 999], False)]:
            with self.subTest(groups=groups), \
                 patch.object(supervisor.subprocess, 'run', return_value=output), \
                 patch.object(supervisor.os, 'getpgid', side_effect=groups), \
                 patch.object(supervisor.os, 'killpg') as kill:
                self.assertEqual(supervisor.owns_listener(8042, 123), expected)
                kill.assert_not_called()

    def test_ownership_diagnostic_failure_is_not_readiness(self):
        output = subprocess.CompletedProcess([], 2, '', 'synthetic failure')
        with patch.object(supervisor.subprocess, 'run', return_value=output):
            with self.assertRaises(RuntimeError):
                supervisor.owns_listener(8042, 123)

    def test_observe_exited_leader_without_reaping_or_releasing_group_identity(self):
        process = child()
        exited = SimpleNamespace(si_pid=123, si_code=supervisor.os.CLD_EXITED, si_status=7)
        with patch.object(supervisor.os, 'waitid', return_value=exited) as waitid, \
             patch.object(supervisor.os, 'getpgid', return_value=123):
            self.assertEqual(supervisor.exit_status(process), 7)
        flags = waitid.call_args.args[2]
        self.assertTrue(flags & supervisor.os.WNOWAIT)
        self.assertTrue(flags & supervisor.os.WNOHANG)
        process.poll.assert_not_called()
        process.wait.assert_not_called()

    def test_darwin_confirmed_exited_child_does_not_require_zombie_getpgid(self):
        process = child()
        exited = SimpleNamespace(si_pid=123, si_code=supervisor.os.CLD_EXITED, si_status=0)
        with patch.object(supervisor.os, 'waitid', return_value=exited) as waitid, \
             patch.object(supervisor.os, 'getpgid', side_effect=ProcessLookupError()) as getpgid:
            self.assertEqual(supervisor.exit_status(process), 0)
            self.assertEqual(supervisor.exit_status(process), 0)
        self.assertEqual(waitid.call_count, 2)
        getpgid.assert_not_called()
        process.wait.assert_not_called()
        process.poll.assert_not_called()

    def test_alive_child_changed_group_is_rejected_without_signalling(self):
        with patch.object(supervisor.os, 'waitid', return_value=None), \
             patch.object(supervisor.os, 'getpgid', return_value=999), \
             patch.object(supervisor.os, 'killpg') as kill:
            with self.assertRaisesRegex(RuntimeError, 'changed process group'):
                supervisor.stop_owned(child())
            kill.assert_not_called()

    def test_waitid_unknown_child_pid_is_rejected_without_signalling(self):
        for pid in (0, 999):
            exited = SimpleNamespace(si_pid=pid, si_code=supervisor.os.CLD_EXITED, si_status=0)
            with self.subTest(pid=pid), \
                 patch.object(supervisor.os, 'waitid', return_value=exited), \
                 patch.object(supervisor.os, 'getpgid', return_value=123), \
                 patch.object(supervisor, 'wait_group_drained', return_value=True), \
                 patch.object(supervisor.os, 'killpg') as kill:
                with self.assertRaisesRegex(RuntimeError, 'child identity'):
                    supervisor.stop_owned(child())
                kill.assert_not_called()

    def test_racing_exit_after_no_waitid_result_is_reinspected_without_reaping(self):
        process = child()
        exited = SimpleNamespace(si_pid=123, si_code=supervisor.os.CLD_EXITED, si_status=7)
        with patch.object(supervisor.os, 'waitid', side_effect=[None, exited]) as waitid, \
             patch.object(supervisor.os, 'getpgid', side_effect=ProcessLookupError()):
            self.assertEqual(supervisor.exit_status(process), 7)
        self.assertEqual(waitid.call_count, 2)
        for call in waitid.call_args_list:
            self.assertTrue(call.args[2] & supervisor.os.WNOWAIT)
        process.wait.assert_not_called()
        process.poll.assert_not_called()

    def test_racing_exit_requires_positive_matching_child_observation(self):
        wrong_child = SimpleNamespace(si_pid=999, si_code=supervisor.os.CLD_EXITED, si_status=0)
        for second in (None, wrong_child, ChildProcessError()):
            with self.subTest(second=second), \
                 patch.object(supervisor.os, 'waitid', side_effect=[None, second]), \
                 patch.object(supervisor.os, 'getpgid', side_effect=ProcessLookupError()), \
                 patch.object(supervisor.os, 'killpg') as kill:
                with self.assertRaises(RuntimeError):
                    supervisor.stop_owned(child())
                kill.assert_not_called()

    def test_cicd004_exited_singleton_reaps_without_any_group_signal(self):
        process = child()
        exited = SimpleNamespace(si_pid=123, si_code=supervisor.os.CLD_EXITED, si_status=0)
        events = []
        def inventory(group):
            self.assertEqual(group, 123)
            self.assertIsNone(process.returncode)
            process.wait.assert_not_called()
            events.append('inventory-pinned')
            return {123}
        def reap(**_):
            self.assertIsNone(process.returncode)
            events.append('reap')
            process.returncode = 0
            return 0
        process.wait.side_effect = reap
        with patch.object(supervisor.os, 'waitid', return_value=exited), \
             patch.object(supervisor, 'group_members', side_effect=inventory), \
             patch.object(supervisor.os, 'killpg', side_effect=PermissionError()) as kill:
            supervisor.stop_owned(process)
            kill.assert_not_called()
        self.assertEqual(events, ['inventory-pinned', 'reap'])
        process.wait.assert_called_once_with(timeout=1)
        process.poll.assert_not_called()

    def test_cicd004_unknown_inventory_never_reaps_or_signals_exited_leader(self):
        for failure in (RuntimeError('unknown inventory'), PermissionError()):
            process = child()
            with self.subTest(failure=type(failure).__name__), \
                 patch.object(supervisor, 'exit_status', return_value=0), \
                 patch.object(supervisor, 'group_members', side_effect=failure), \
                 patch.object(supervisor.os, 'killpg') as kill:
                with self.assertRaises(type(failure)):
                    supervisor.stop_owned(process)
                process.wait.assert_not_called()
                kill.assert_not_called()

    def test_cicd004_alive_singleton_does_not_bypass_signal_permission_error(self):
        process = child()
        with patch.object(supervisor, 'exit_status', return_value=None), \
             patch.object(supervisor, 'group_members', return_value={123}) as members, \
             patch.object(supervisor.os, 'killpg', side_effect=PermissionError()) as kill:
            with self.assertRaises(PermissionError):
                supervisor.stop_owned(process)
            kill.assert_called_once_with(123, signal.SIGTERM)
            members.assert_not_called()
            process.wait.assert_not_called()

    def test_confirmed_exited_child_still_fails_closed_on_signal_permission_error(self):
        process = child()
        exited = SimpleNamespace(si_pid=123, si_code=supervisor.os.CLD_EXITED, si_status=0)
        with patch.object(supervisor.os, 'waitid', return_value=exited), \
             patch.object(supervisor.os, 'getpgid', side_effect=ProcessLookupError()), \
             patch.object(supervisor, 'group_members', return_value={123, 789}), \
             patch.object(supervisor.os, 'killpg', side_effect=PermissionError()) as kill, \
             patch.object(supervisor, 'wait_group_drained') as drain:
            with self.assertRaises(PermissionError):
                supervisor.stop_owned(process)
            kill.assert_called_once_with(123, signal.SIGTERM)
            drain.assert_not_called()
            process.wait.assert_not_called()

    def test_reaped_leader_with_reused_numeric_group_is_never_signalled(self):
        process = child()
        with patch.object(supervisor.os, 'waitid', side_effect=ChildProcessError()), \
             patch.object(supervisor.os, 'getpgid', return_value=123), \
             patch.object(supervisor.os, 'killpg') as kill:
            with self.assertRaisesRegex(RuntimeError, 'reaped'):
                supervisor.stop_owned(process)
            kill.assert_not_called()
            process.wait.assert_not_called()

    def test_identity_loss_before_escalation_never_signals_reused_group(self):
        process = child()
        with patch.object(supervisor, 'exit_status', side_effect=[0, RuntimeError('reaped')]), \
             patch.object(supervisor, 'group_members', return_value={123, 789}), \
             patch.object(supervisor, 'wait_group_drained', return_value=False), \
             patch.object(supervisor.os, 'killpg') as kill:
            with self.assertRaisesRegex(RuntimeError, 'reaped'):
                supervisor.stop_owned(process)
        kill.assert_called_once_with(123, signal.SIGTERM)
        process.wait.assert_not_called()

    def test_command_completion_does_not_reap_before_cleanup(self):
        process = child()
        with patch.object(supervisor, 'exit_status', return_value=17):
            self.assertEqual(supervisor.wait_command(process, 300), 17)
        process.wait.assert_not_called()
        process.poll.assert_not_called()
        if supervisor.__file__ is None:
            self.fail('Acceptance supervisor source path is unavailable')
        source = Path(supervisor.__file__).read_text()
        self.assertNotIn('.poll(', source)
        self.assertNotIn('browser.wait(', source)
        self.assertNotIn('server.wait(', source)

    def test_already_waited_leader_is_never_signalled(self):
        process = child()
        process.returncode = 0
        with patch.object(supervisor.os, 'killpg') as kill:
            with self.assertRaisesRegex(RuntimeError, 'not pinned'):
                supervisor.stop_owned(process)
            kill.assert_not_called()

    def test_exited_leader_with_live_worker_is_not_a_drained_group(self):
        process = child()
        with patch.object(supervisor, 'exit_status', return_value=0), \
             patch.object(supervisor, 'group_members', side_effect=[{123, 789}, {123}]) as members, \
             patch.object(supervisor.time, 'sleep'):
            self.assertTrue(supervisor.wait_group_drained(process, 5))
        self.assertEqual(members.call_count, 2)
        process.wait.assert_not_called()
        process.poll.assert_not_called()

    def test_cleanup_waits_whole_group_after_kill_then_reaps_leader_last(self):
        process = child()
        events = []
        process.wait.side_effect = lambda **_: events.append('reap')
        def drained(*_):
            events.append('drain')
            return events.count('drain') == 2
        with patch.object(supervisor, 'exit_status', return_value=0), \
             patch.object(supervisor, 'group_members', return_value={123, 789}), \
             patch.object(supervisor, 'wait_group_drained', side_effect=drained), \
             patch.object(supervisor.os, 'killpg', side_effect=lambda pid, sig: events.append((pid, sig))):
            supervisor.stop_owned(process)
        self.assertEqual(events, [(123, signal.SIGTERM), 'drain', (123, signal.SIGKILL), 'drain', 'reap'])
        process.poll.assert_not_called()

    def test_group_remaining_after_kill_is_bounded_failure_not_leader_only_success(self):
        process = child()
        with patch.object(supervisor, 'exit_status', return_value=0), \
             patch.object(supervisor, 'group_members', return_value={123, 789}), \
             patch.object(supervisor, 'wait_group_drained', return_value=False) as drain, \
             patch.object(supervisor.os, 'killpg') as kill:
            with self.assertRaisesRegex(TimeoutError, 'remains after SIGKILL'):
                supervisor.stop_owned(process)
        self.assertEqual(drain.call_count, 2)
        self.assertEqual(kill.call_args_list[-1].args, (123, signal.SIGKILL))
        process.wait.assert_not_called()

    def test_worker_remaining_makes_drain_timeout_even_when_leader_exited(self):
        with patch.object(supervisor, 'exit_status', return_value=0), \
             patch.object(supervisor, 'group_members', return_value={123, 789}), \
             patch.object(supervisor.time, 'monotonic', side_effect=[0, 6]):
            self.assertFalse(supervisor.wait_group_drained(child(), 5))

    def test_group_inventory_includes_workers_and_zombies(self):
        output = subprocess.CompletedProcess([], 0, '123 123\n789 123\n999 999\n', '')
        with patch.object(supervisor.subprocess, 'run', return_value=output):
            self.assertEqual(supervisor.group_members(123), {123, 789})

    def test_inventory_failure_or_missing_leader_blocks_cleanup(self):
        for rc, output in [(1, ''), (0, '789 123\n')]:
            result = subprocess.CompletedProcess([], rc, output, '')
            with self.subTest(rc=rc), patch.object(supervisor.subprocess, 'run', return_value=result):
                with self.assertRaises(RuntimeError):
                    supervisor.group_members(123)

    def test_cleanup_permission_error_is_not_silently_reported_as_success(self):
        with patch.object(supervisor, 'exit_status', return_value=None), \
             patch.object(supervisor.os, 'killpg', side_effect=PermissionError()):
            with self.assertRaises(PermissionError):
                supervisor.stop_owned(child())


if __name__ == '__main__':
    unittest.main()
