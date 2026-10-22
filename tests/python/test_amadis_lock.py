"""Real per-root lock tests for scripts/amadis/launch.py.

Holders acquire the lock through launch.main() and spawn children through launch.capture(),
so the flock and pass_fds inheritance under test are the launcher's own code, not a replica.
Synchronisation uses pipes, FIFOs and process exit only; signal.alarm is a hang watchdog.
No PHP, no git: each root gets a pre-written identity.json so `init` skips bootstrap.
"""
import json
import os
from pathlib import Path
import secrets
import signal
import subprocess
import sys
import tempfile
import unittest

LAUNCH = Path(__file__).resolve().parents[2] / 'scripts/amadis/launch.py'
BASE = Path('/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode')

# Holder: take the lock via the real main('init'); optionally keep it in a child via real capture().
HOLDER = r'''
import importlib.util, json, sys
sys.dont_write_bytecode = True
spec = importlib.util.spec_from_file_location('amadis_launch', sys.argv[1])
launch = importlib.util.module_from_spec(spec)
spec.loader.exec_module(launch)
sys.argv = ['launch.py', 'init']
assert launch.main() == 0 and launch.lock is not None
print('locked', flush=True)
child_argv = json.loads(sys.stdin.readline())
if child_argv:
    launch.capture(launch.Path(child_argv[0]), 'lock-holder', child_argv[1:], {'PATH': '/usr/bin:/bin'})
else:
    sys.stdin.read()  # hold the lock until the test closes stdin
'''

# Child: report the lock fds it inherited, wait for release, close them, report, exit.
CHILD = r'''
import os, signal, sys
signal.alarm(45)  # watchdog also bounds an orphan if the test fails before releasing it
lock_path, ready, release = sys.argv[1:4]
inode = os.stat(lock_path).st_ino
fds = []
for fd in range(3, 256):
    try:
        if os.fstat(fd).st_ino == inode:
            fds.append(fd)
    except OSError:
        pass
out = os.open(ready, os.O_WRONLY)
os.write(out, f'ready {os.getpid()} {len(fds)}\n'.encode())
with open(release) as handle:
    handle.read()
for fd in fds:
    os.close(fd)
os.write(out, b'released\n')
'''


class AmadisLockTests(unittest.TestCase):
    def setUp(self):
        signal.signal(signal.SIGALRM, lambda *_: (_ for _ in ()).throw(TimeoutError('lock test hung')))
        signal.alarm(60)
        self.addCleanup(signal.alarm, 0)
        # Fresh unique root per test: never reuses or overwrites earlier evidence.
        self.root = BASE / ('amadis-' + secrets.token_hex(12))
        self.root.mkdir(mode=0o700)
        database = self.root / 'database.sqlite'
        (self.root / 'identity.json').write_text(json.dumps({'root': str(self.root.resolve()),
            'database': str(self.root.resolve() / 'database.sqlite')}))
        self.scratch = Path(tempfile.mkdtemp(prefix='amadis-lockfifo-', dir=BASE))
        self.env = {'PATH': '/usr/bin:/bin', 'AMADIS_ROOT': str(self.root), 'PYTHONDONTWRITEBYTECODE': '1'}
        self.procs = []
        self.addCleanup(self.kill_all)

    def kill_all(self):
        for proc in self.procs:
            if proc.poll() is None:
                proc.kill()
                proc.wait()
            for stream in (proc.stdin, proc.stdout, proc.stderr):
                if stream and not stream.closed:
                    stream.close()

    def contender(self):
        return subprocess.run([sys.executable, str(LAUNCH), 'init'], env=self.env, capture_output=True, text=True)

    def assert_busy(self):
        b = self.contender()
        self.assertNotEqual(b.returncode, 0)
        self.assertIn('root is busy', b.stderr)

    def assert_reusable(self):
        b = self.contender()
        self.assertEqual(b.returncode, 0, b.stderr)
        self.assertEqual(b.stdout.strip(), str(self.root.resolve()))

    def start_holder(self):
        holder = subprocess.Popen([sys.executable, '-c', HOLDER, str(LAUNCH)], env=self.env, text=True,
                                  stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        self.procs.append(holder)
        lines = [holder.stdout.readline() for _ in range(2)]  # main() prints root, then holder prints locked
        self.assertEqual(lines[1].strip(), 'locked', lines + [holder.stderr.read() if holder.poll() is not None else ''])
        return holder

    def test_contender_rejected_while_holder_runs_then_root_reusable(self):
        holder = self.start_holder()
        holder.stdin.write('[]\n')
        holder.stdin.flush()
        self.assert_busy()
        self.assert_busy()  # rejection is stable, not a one-off
        holder.stdin.close()
        self.assertEqual(holder.wait(), 0)
        self.assert_reusable()

    def test_lock_survives_killed_parent_via_captured_child(self):
        ready, release = self.scratch / 'ready', self.scratch / 'release'
        os.mkfifo(ready, 0o600)
        os.mkfifo(release, 0o600)
        holder = self.start_holder()
        child = [str(self.root), sys.executable, '-c', CHILD, str(self.root / '.lock'), str(ready), str(release)]
        holder.stdin.write(json.dumps(child) + '\n')
        holder.stdin.flush()
        with open(ready) as signals:  # blocks until the captured child opens its end
            word, pid, inherited = signals.readline().split()
            self.assertEqual(word, 'ready')
            self.assertEqual(inherited, '1', 'child must inherit exactly the launcher lock fd')
            pid = int(pid)
            self.assert_busy()
            holder.kill()
            self.assertEqual(holder.wait(), -signal.SIGKILL)
            os.kill(pid, 0)  # child still alive after parent death (raises if gone)
            self.assert_busy()
            with open(release, 'w'):
                pass
            self.assertEqual(signals.readline().strip(), 'released')
            self.assertEqual(signals.readline(), '')  # EOF: child exited
        self.assert_reusable()


if __name__ == '__main__':
    unittest.main(verbosity=2)
