#!/usr/bin/env python3
"""Export one capture triplet, never a directory tree.

Only pattern-based sanitization is provided; arbitrary secrets and encoded secrets
are not discoverable. Review artifacts before publishing. Destination must already
exist, be private and empty, and be a direct child of launch.BASE.
"""
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import sys
from contextlib import ExitStack

import launch

MAX_BYTES = 16 * 1024 * 1024  # per capture file; bounds memory before sanitizing


def refuse(condition):
    if not condition:
        raise ValueError('export boundary rejected')


def private(fd, directory=False):
    info = os.fstat(fd)
    refuse(info.st_uid == os.getuid() and not info.st_mode & 0o077)
    refuse(stat.S_ISDIR(info.st_mode) if directory else stat.S_ISREG(info.st_mode) and info.st_nlink == 1)


def export(root_arg, stem, destination_arg):
    refuse(Path.cwd().resolve() == launch.REPO)
    refuse(re.fullmatch(r'\d{8}T\d{6}Z-[a-z][a-z0-9-]*-[0-9a-f]{6}', stem) is not None)
    for value in (root_arg, destination_arg):
        refuse(value == str(Path(value)) and Path(value).is_absolute() and '..' not in Path(value).parts)
        refuse(Path(value).parent == launch.BASE)
    root, destination = Path(root_arg), Path(destination_arg)
    refuse(root != destination and re.fullmatch(r'amadis-[0-9a-f]{24}', root.name) is not None)
    with ExitStack() as stack:
        def opened(name, flags, parent=None):
            fd = os.open(name, flags | os.O_NOFOLLOW | os.O_NONBLOCK, dir_fd=parent)
            stack.callback(os.close, fd)
            return fd

        # Open every component without resolving away a symlink.
        base = opened('/', os.O_RDONLY | os.O_DIRECTORY)
        for part in launch.BASE.parts[1:]:
            base = opened(part, os.O_RDONLY | os.O_DIRECTORY, base)
        rootfd = opened(root.name, os.O_RDONLY | os.O_DIRECTORY, base)
        destfd = opened(destination.name, os.O_RDONLY | os.O_DIRECTORY, base)
        private(rootfd, True)
        private(destfd, True)
        fcntl.flock(destfd, fcntl.LOCK_EX | fcntl.LOCK_NB)
        refuse(not os.listdir(destfd))

        def read(parent, name):
            fd = opened(name, os.O_RDONLY, parent)
            private(fd)
            refuse(os.fstat(fd).st_size <= MAX_BYTES)
            with os.fdopen(os.dup(fd), 'rb') as handle:
                content = handle.read(MAX_BYTES + 1)
            refuse(len(content) <= MAX_BYTES)
            return content

        identity = json.loads(read(rootfd, 'identity.json'))
        refuse(isinstance(identity, dict))
        refuse(all(identity.get(k) == v for k, v in {
            'repo': str(launch.REPO), 'root': str(root), 'database': str(root / 'database.sqlite')}.items()))
        evidencefd = opened('evidence', os.O_RDONLY | os.O_DIRECTORY, rootfd)
        private(evidencefd, True)
        data = {stem + suffix: read(evidencefd, stem + suffix) for suffix in ('.json', '.stdout', '.stderr')}
        meta = json.loads(data[stem + '.json'])
        refuse(isinstance(meta, dict) and {'start', 'finish', 'command', 'returncode'} <= meta.keys())

        def clean(value):
            if isinstance(value, dict):
                return {k: '[REDACTED]' if launch.sensitive(k) else clean(v) for k, v in value.items()}
            if isinstance(value, list):
                return [clean(v) for v in value]
            return launch.sanitized(value) if isinstance(value, str) else value

        source_hashes = {n: hashlib.sha256(b).hexdigest() for n, b in data.items()}
        for name in data:
            text = (json.dumps(clean(meta), indent=2) + '\n' if name.endswith('.json')
                    else launch.sanitized(data[name].decode('utf-8')))
            data[name] = text.encode('utf-8')
        manifest = {'stem': stem, 'files': {n: hashlib.sha256(b).hexdigest() for n, b in data.items()},
                    'source_sha256': source_hashes, 'sanitization': 'pattern-based; not arbitrary secret detection'}
        data['manifest.json'] = (json.dumps(manifest, indent=2) + '\n').encode()
        written = {}

        def close_output(fd, error):
            try:
                os.close(fd)
            except OSError:
                if error is None:
                    raise
                error.add_note('export output descriptor close failed')

        try:
            for name, content in data.items():
                fd = os.open(name, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600, dir_fd=destfd)
                # Pin all four output inodes until commit/rollback to prevent unlink/recreate ABA.
                stack.push(lambda _type, error, _traceback, fd=fd: close_output(fd, error))
                info = os.fstat(fd)
                written[name] = (info.st_dev, info.st_ino)
                with os.fdopen(fd, 'wb', closefd=False) as handle:
                    handle.write(content)
            refuse(set(os.listdir(destfd)) == set(data))
        except BaseException as export_error:
            # Unlink only entries still our own inode. shortcut: a same-UID process can still
            # swap between stat and unlink; keep the destination exclusively controlled.
            cleanup_errors = []
            for name, identity in written.items():
                try:
                    now = os.stat(name, dir_fd=destfd, follow_symlinks=False)
                    if (now.st_dev, now.st_ino) == identity:
                        os.unlink(name, dir_fd=destfd)
                except FileNotFoundError:
                    continue
                except OSError as cleanup_error:
                    cleanup_errors.append((name, cleanup_error))
            if cleanup_errors:
                names = ', '.join(name for name, _ in cleanup_errors)
                export_error.add_note(f'export rollback incomplete for: {names}')
            raise
    return sorted(data)


if __name__ == '__main__':
    try:
        refuse(len(sys.argv) == 4)
        print(json.dumps({'files': export(*sys.argv[1:])}))
    except (OSError, ValueError, TypeError) as error:
        for note in getattr(error, '__notes__', ()):
            print(note, file=sys.stderr)
        sys.exit('export_evidence: refused (invalid boundary, missing file, or invalid capture)')
