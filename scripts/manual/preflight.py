#!/usr/bin/env python3
"""Fail closed before every native Artisan invocation; never unset inherited values."""
import base64
import hashlib
import json
import os
from pathlib import Path
import sys

BASE = Path('/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode')
PREFIXES = ('APP_', 'DB_', 'DATABASE_', 'LARAVEL_', 'SESSION_', 'CACHE_', 'QUEUE_', 'MAIL_',
            'REDIS_', 'MEMCACHED_', 'AWS_', 'FILESYSTEM_', 'BROADCAST_', 'LOG_', 'AMADIS_',
            'PHP_', 'VIEW_', 'ADMIN_SEED_', 'MANUAL_CUSTOMER_')


def manual_port():
    port = os.environ.get('MANUAL_PORT', '8017')
    if port not in ('8017', '8018'):
        raise ValueError('Only isolated ports 8017 and 8018 are allowed.')
    return port


def check_environment():
    manual_port()
    rejected = sorted(key for key in os.environ if key.startswith(PREFIXES)
                      or key in ('PHPRC', 'XDEBUG_CONFIG', 'XDEBUG_MODE'))
    if rejected:
        raise ValueError('Rejected inherited variables (values withheld): ' + ', '.join(rejected))


def check_root(root):
    if root.parent != BASE or not root.name.startswith('zafira-manual-') or root.resolve() != root:
        raise ValueError('Rejected root: canonical direct child of approved temp directory required.')
    return root


def check_checkout(root):
    # Recursive rejection includes broken links, nested storage links and executable autoload paths.
    for directory in (root,):
        for current, dirs, files in os.walk(directory, followlinks=False):
            for name in dirs + files:
                if (Path(current) / name).is_symlink():
                    raise ValueError('Rejected nested symlink in isolated checkout.')
    database = root / 'database.sqlite'
    if json.loads((root / 'identity.json').read_text()) != {'root': str(root), 'database': str(database)} or not database.is_file():
        raise ValueError('Rejected isolated database identity.')
    expected = {
        'APP_NAME': 'ZAFIRA-MANUAL', 'APP_ENV': 'local', 'APP_DEBUG': 'false',
        'LOG_CHANNEL': 'single', 'LOG_LEVEL': 'warning', 'APP_URL': 'http://127.0.0.1:' + manual_port(),
        'DB_CONNECTION': 'sqlite', 'DB_DATABASE': str(database), 'DB_URL': '',
        'SESSION_DRIVER': 'file', 'CACHE_STORE': 'file', 'QUEUE_CONNECTION': 'sync',
        'MAIL_MAILER': 'log', 'FILESYSTEM_DISK': 'local', 'LARAVEL_STORAGE_PATH': str(root / 'storage'),
        'APP_CONFIG_CACHE': str(root / 'bootstrap/cache/config.php'),
        'APP_SERVICES_CACHE': str(root / 'bootstrap/cache/services.php'),
        'APP_PACKAGES_CACHE': str(root / 'bootstrap/cache/packages.php'),
        'APP_ROUTES_CACHE': str(root / 'bootstrap/cache/routes.php'),
        'APP_EVENTS_CACHE': str(root / 'bootstrap/cache/events.php'),
        'VIEW_COMPILED_PATH': str(root / 'storage/framework/views'),
    }
    lines = (root / '.env').read_text().splitlines()
    values = {}
    for line in lines:
        key, value = line.split('=', 1)
        if key in values:
            raise ValueError('Rejected duplicate environment key.')
        values[key] = value
    key = values.pop('APP_KEY', '')
    if values != expected or not key.startswith('base64:') or len(base64.b64decode(key[7:], validate=True)) != 32:
        raise ValueError('Rejected environment keys or sinks (values withheld).')
    if (root / '.env').stat().st_mode & 0o077:
        raise ValueError('Rejected public environment permissions.')
    for path in (root / 'storage', root / 'bootstrap/cache', root / 'vendor', root / 'private-runtime', root / 'public/manual'):
        if not path.is_dir() or not path.resolve().is_relative_to(root):
            raise ValueError('Rejected missing or external runtime sink.')
    cache = root / 'bootstrap/cache'
    manifest = root / 'private-runtime/cache-hashes.json'
    hashes = json.loads(manifest.read_text()) if manifest.exists() else {}
    actual = {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in cache.iterdir() if p.is_file()}
    if actual != hashes or any(name not in ('services.php', 'packages.php') for name in actual):
        raise ValueError('Rejected executable cache: unapproved name or changed bytes.')
    nonce = (root / 'public/manual/identity.txt').read_text().strip()
    if len(nonce) != 64 or any(c not in '0123456789abcdef' for c in nonce):
        raise ValueError('Rejected checkout HTTP identity.')


if __name__ == '__main__':
    try:
        check_environment()
        root = check_root(Path(os.environ.get('MANUAL_ROOT', '/invalid')))
        if len(sys.argv) > 1 and sys.argv[1] != 'setup':
            check_checkout(root)
        print('PASS: inherited environment and isolated identity preflight.')
    except (ValueError, OSError, KeyError) as error:
        sys.exit(str(error))
