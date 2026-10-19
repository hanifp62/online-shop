#!/usr/bin/env python3
"""Fail closed before native Artisan; never silently remove inherited variables."""
import json
import os
from pathlib import Path
import sys

BASE = Path('/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode')
PREFIXES = ('APP_', 'DB_', 'DATABASE_', 'LARAVEL_', 'SESSION_', 'CACHE_', 'QUEUE_', 'MAIL_',
            'REDIS_', 'MEMCACHED_', 'AWS_', 'FILESYSTEM_', 'BROADCAST_', 'LOG_', 'AMADIS_',
            'PHP_CLI_SERVER_', 'VIEW_', 'ADMIN_SEED_', 'MANUAL_CUSTOMER_')

def check_environment():
    rejected = sorted(key for key in os.environ if key.startswith(PREFIXES))
    if rejected:
        sys.exit('Rejected inherited variables (values withheld): ' + ', '.join(rejected)
                 + '. Explicitly unset these in your terminal and retry.')

def check_root(root):
    if root.parent != BASE or not root.name.startswith('zafira-manual-') or root.resolve() != root:
        sys.exit('Rejected root: canonical direct child of approved temp directory required.')
    return root

if __name__ == '__main__':
    check_environment()
    root = check_root(Path(os.environ.get('MANUAL_ROOT', '/invalid')))
    if len(sys.argv) > 1 and sys.argv[1] == 'serve':
        identity = json.loads((root / 'identity.json').read_text())
        database = root / 'database.sqlite'
        if identity != {'root': str(root), 'database': str(database)} or database.is_symlink() or not database.is_file():
            sys.exit('Rejected isolated database identity.')
        if (root / '.env').is_symlink() or (root / 'bootstrap/cache/config.php').exists():
            sys.exit('Rejected environment symlink or cached configuration.')
        expected = {
            'APP_ENV': 'local', 'APP_DEBUG': 'false', 'APP_URL': 'http://127.0.0.1:8017',
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
        values = dict(line.split('=', 1) for line in (root / '.env').read_text().splitlines() if '=' in line)
        if any(values.get(key) != value for key, value in expected.items()):
            sys.exit('Rejected altered environment sinks (values withheld).')
        for path in [root / 'storage', root / 'bootstrap/cache', root / 'vendor']:
            if path.is_symlink() or not path.resolve().is_relative_to(root):
                sys.exit('Rejected shared runtime or Composer autoload path.')
    print('PASS: inherited environment and isolated identity preflight.')
