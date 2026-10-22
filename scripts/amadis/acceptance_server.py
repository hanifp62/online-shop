"""Acceptance server supervisor: only signal process groups created by this invocation."""
import os
from pathlib import Path
import signal
import shutil
import socket
import subprocess
import sys
import time
import urllib.request


def preflight(port):
    # A TCP bind detects even non-HTTP listeners. Never signal the existing listener.
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as probe:
        probe.bind(('127.0.0.1', port))


def owns_listener(port, group):
    tool = shutil.which('lsof') or '/usr/sbin/lsof'
    result = subprocess.run(
        [tool, '-nP', f'-iTCP:{port}', '-sTCP:LISTEN', '-t'],
        capture_output=True, text=True, timeout=2,
    )
    if result.returncode not in (0, 1):
        raise RuntimeError('cannot verify acceptance listener ownership')
    pids = result.stdout.split()
    if not pids:
        return False
    try:
        return all(os.getpgid(int(pid)) == group for pid in pids)
    except ProcessLookupError:
        return False


def exit_status(process):
    """Observe without reaping: the direct child's PID pins the group identity."""
    if not isinstance(process.pid, int) or process.pid <= 1:
        raise ValueError('invalid owned process group')
    if process.returncode is not None or signal.getsignal(signal.SIGCHLD) == signal.SIG_IGN:
        raise RuntimeError('owned leader identity is not pinned')
    def observe():
        try:
            result = os.waitid(os.P_PID, process.pid, os.WEXITED | os.WNOHANG | os.WNOWAIT)
        except ChildProcessError as error:
            # Never signal a numeric PGID after another reaper has released its PID.
            raise RuntimeError('owned leader was reaped; refusing stale PGID') from error
        if result is not None:
            if result.si_pid != process.pid:
                raise RuntimeError('waitid child identity does not match owned leader')
            if result.si_code not in (os.CLD_EXITED, os.CLD_KILLED, os.CLD_DUMPED):
                raise RuntimeError('waitid did not confirm owned child exit')
        return result

    result = observe()
    if result is None:
        try:
            group = os.getpgid(process.pid)
        except ProcessLookupError:
            # Darwin can hide the PGID before waitid exposes exit status.
            # Keep identity unknown (no signals or reap) until matching WNOWAIT
            # confirmation arrives; permanent uncertainty still fails closed.
            deadline = time.monotonic() + 0.100
            result = observe()
            while result is None:
                remaining = deadline - time.monotonic()
                if remaining <= 0:
                    raise RuntimeError('owned child identity unconfirmed after PGID lookup failed')
                time.sleep(min(0.001, remaining))
                if time.monotonic() >= deadline:
                    raise RuntimeError('owned child identity unconfirmed after PGID lookup failed')
                result = observe()
        else:
            if group != process.pid:
                raise RuntimeError('owned leader changed process group')
            return None
    # A confirmed unreaped exit pins the PID even when zombie getpgid is ESRCH.
    return result.si_status if result.si_code == os.CLD_EXITED else -result.si_status


def group_members(group):
    result = subprocess.run(['ps', '-axo', 'pid=,pgid='], capture_output=True, text=True, timeout=2)
    if result.returncode:
        raise RuntimeError('cannot verify whole-group cleanup')
    members = set()
    for line in result.stdout.splitlines():
        pid, pgid = map(int, line.split())
        if pgid == group:
            members.add(pid)
    if group not in members:
        raise RuntimeError('pinned group leader missing from process inventory')
    return members


def wait_group_drained(process, timeout):
    deadline = time.monotonic() + timeout
    while True:
        status = exit_status(process)
        # Include workers and their zombies, not just the direct child. Only the
        # deliberately unreaped leader may remain when its PID is finally released.
        if group_members(process.pid) == {process.pid} and status is not None:
            return True
        if time.monotonic() >= deadline:
            return False
        time.sleep(0.05)


def stop_owned(process):
    if process is None:
        return
    status = exit_status(process)  # keep our direct child's identity pinned
    if status is not None and group_members(process.pid) == {process.pid}:
        # Darwin can reject signals to a zombie-only group. Positive WNOWAIT
        # exit plus a singleton inventory proves no worker needs signalling.
        process.wait(timeout=1)  # release identity last; never signal after reap
        return
    os.killpg(process.pid, signal.SIGTERM)
    if not wait_group_drained(process, 5):
        exit_status(process)
        os.killpg(process.pid, signal.SIGKILL)
        if not wait_group_drained(process, 5):
            # Keep the leader unreaped: do not release its identity and then signal
            # a reused group. Failure is bounded and must block acceptance.
            raise TimeoutError('owned process group remains after SIGKILL')
    process.wait(timeout=1)  # last action; absolutely no group signals after this


def wait_command(process, timeout):
    deadline = time.monotonic() + timeout
    while True:
        status = exit_status(process)
        if status is not None:
            return status
        if time.monotonic() >= deadline:
            raise TimeoutError('acceptance command timed out')
        time.sleep(0.05)


def main(argv=None):
    args = sys.argv[1:] if argv is None else argv
    if len(args) < 3 or not args[0].isdecimal() or not 1024 <= int(args[0]) <= 65535:
        raise ValueError('usage: acceptance_server.py PORT SERVER_LOG COMMAND...')
    port, log_path, command = int(args[0]), Path(args[1]), args[2:]
    if not all(hasattr(os, name) for name in ('waitid', 'P_PID', 'WEXITED', 'WNOHANG', 'WNOWAIT')):
        raise RuntimeError('non-reaping child observation is required before launching acceptance')
    if signal.getsignal(signal.SIGCHLD) != signal.SIG_DFL:
        raise RuntimeError('acceptance requires exclusive ownership of child reaping')
    server = browser = None

    def interrupted(signum, _frame):
        raise InterruptedError(f'acceptance interrupted by signal {signum}')

    previous = {sig: signal.signal(sig, interrupted) for sig in (signal.SIGTERM, signal.SIGINT)}
    try:
        preflight(port)
        with log_path.open('x') as log:
            server = subprocess.Popen(
                ['scripts/amadis/launch.sh', 'serve', str(port), '4'],
                stdout=log, stderr=subprocess.STDOUT, start_new_session=True,
            )
            deadline = time.monotonic() + 20
            # Verify listener process-group identity before any readiness request.
            # A racing external listener cannot impersonate our spawned server.
            opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
            while True:
                if exit_status(server) is not None:
                    raise RuntimeError('owned acceptance server exited before readiness')
                if owns_listener(port, server.pid):
                    try:
                        with opener.open(f'http://127.0.0.1:{port}/koleksi', timeout=1) as response:
                            if response.status == 200 and exit_status(server) is None:
                                break
                    except OSError:
                        pass
                if time.monotonic() >= deadline:
                    raise TimeoutError('owned acceptance server not ready')
                time.sleep(0.1)
            browser = subprocess.Popen(command, start_new_session=True)
            return wait_command(browser, 300)
    finally:
        # Prevent a second termination signal from interrupting owned cleanup.
        for sig in previous:
            signal.signal(sig, signal.SIG_IGN)
        try:
            try:
                stop_owned(browser)
            finally:
                stop_owned(server)
        finally:
            for sig, handler in previous.items():
                signal.signal(sig, handler)


if __name__ == '__main__':
    raise SystemExit(main())
