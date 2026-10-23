"""Private React fixture channel and read-only SQLite oracle. Never bootstrap PHP.

Only `load` returns the private channel, for the Node parent via a captured pipe.
Every other mode emits a fixed verdict; exceptions/SQL/URLs are never printed.
"""
import hashlib
import json
import os
import re
import sqlite3
import stat
import sys
from contextlib import closing
from pathlib import Path

BASE = Path('/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode')
REPO = Path(__file__).resolve().parents[2]
PHASES = frozenset({'load', 'review-before', 'review-after', 'unsubscribe-before',
                    'unsubscribe-get', 'unsubscribe-after'})
MARKER = 'amadis-react-completed-order-v1'
REVIEW = 'Synthetic acceptance review'


def require(condition):
    if not condition:
        raise ValueError('REACT_FIXTURE_REJECTED')


def unique_object(pairs):
    result = dict(pairs)
    require(len(result) == len(pairs))
    return result


def directory(path):
    info = path.lstat()
    require(stat.S_ISDIR(info.st_mode) and info.st_uid == os.getuid()
            and not info.st_mode & 0o077 and path.resolve(strict=True) == path)


def private_file(path, limit=None):
    fd = os.open(path, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK)
    with os.fdopen(fd, 'rb') as stream:
        info = os.fstat(stream.fileno())
        require(stat.S_ISREG(info.st_mode) and info.st_nlink == 1
                and info.st_uid == os.getuid() and not info.st_mode & 0o077)
        if limit is None:
            return None
        data = stream.read(limit + 1)
        require(len(data) <= limit)
        return json.loads(data, object_pairs_hook=unique_object,
                          parse_constant=lambda _: require(False))


def load(root, origin):
    require(root.is_absolute() and root.parent == BASE
            and re.fullmatch(r'amadis-[a-f0-9]{24}', root.name))
    directory(root)
    directory(root / 'private-runtime')
    match = re.fullmatch(r'http://127\.0\.0\.1:([1-9][0-9]{3,4})', origin)
    if match is None:
        raise ValueError('REACT_FIXTURE_REJECTED')
    port = int(match[1])
    require(1024 <= port <= 65535 and port not in {8000, 8001})
    database = root / 'database.sqlite'
    identity = private_file(root / 'identity.json', 4 * 1024 * 1024)
    if not isinstance(identity, dict):
        raise ValueError('REACT_FIXTURE_REJECTED')
    require(identity['root'] == str(root) and identity['database'] == str(database)
            and identity['repo'] == str(REPO))
    private_file(database)
    for suffix in ('-wal', '-shm', '-journal'):
        sidecar = root / ('database.sqlite' + suffix)
        if os.path.lexists(sidecar):
            private_file(sidecar)
    fixture = private_file(root / 'private-runtime/react-fixture.json', 4096)
    if not isinstance(fixture, dict):
        raise ValueError('REACT_FIXTURE_REJECTED')
    require(set(fixture) == {'schema', 'origin', 'customer_id', 'product_id', 'order_id', 'unsubscribe_url'})
    require(type(fixture['schema']) is int and fixture['schema'] == 1 and fixture['origin'] == origin)
    require(all(type(fixture[key]) is int and 0 < fixture[key] < 2**53
                for key in ('customer_id', 'product_id', 'order_id')))
    target = origin + '/pengingat-keranjang/berhenti/' + str(fixture['customer_id'])
    require(isinstance(fixture['unsubscribe_url'], str)
            and re.fullmatch(re.escape(target) + r'\?signature=[a-f0-9]{64}', fixture['unsubscribe_url']))
    return fixture


def verify(root, origin, phase):
    require(phase in PHASES)
    fixture = load(root, origin)
    credentials = private_file(root / 'private-runtime/credentials.json', 4096)
    if not isinstance(credentials, dict):
        raise ValueError('REACT_FIXTURE_REJECTED')
    require(set(credentials) == {'admin', 'customer'})
    require(credentials['customer']['email'] == 'amadis-customer@example.test')
    with closing(sqlite3.connect((root / 'database.sqlite').as_uri() + '?mode=ro', uri=True, timeout=2)) as db:
        db.execute('PRAGMA query_only = ON')
        db.execute('BEGIN')
        customer = db.execute('SELECT id, cart_reminder_opt_in FROM users WHERE email = ?',
                              (credentials['customer']['email'],)).fetchall()
        require(len(customer) == 1 and customer[0][0] == fixture['customer_id'])
        require(customer[0][1] == (0 if phase == 'unsubscribe-after' else 1))
        product = db.execute('SELECT product_id, stock, is_active, product_price FROM products WHERE product_slug = ?',
                             ('amadis-demo-stock-37',)).fetchall()
        require(len(product) == 1 and product[0][0] == fixture['product_id']
                and product[0][2] == 1 and 0 <= product[0][1] <= 37 and product[0][3] > 0)
        price = product[0][3]
        orders = db.execute('SELECT id, user_id, status, subtotal, discount, tax, shipping_fee, total, payload_hash '
                            'FROM orders WHERE idempotency_key = ?', (MARKER,)).fetchall()
        require(orders == [(fixture['order_id'], fixture['customer_id'], 'completed', price, 0, 0, 0, price,
                            hashlib.sha256(MARKER.encode()).hexdigest())])
        items = db.execute('SELECT product_id, quantity, unit_price, subtotal FROM order_items WHERE order_id = ?',
                           (fixture['order_id'],)).fetchall()
        require(items == [(fixture['product_id'], 1, price, price)])
        reviews = db.execute('SELECT user_id, order_id, product_id, rating, body FROM reviews ORDER BY id').fetchall()
        if phase in {'load', 'review-before'}:
            require(reviews == [])
        else:
            require(reviews == [(fixture['customer_id'], fixture['order_id'], fixture['product_id'], 5, REVIEW)])
        if phase == 'load':
            require(product[0][1] == 37 and db.execute('SELECT count(*) FROM orders').fetchone()[0] == 1)
    return fixture


def main(argv=None):
    args = sys.argv[1:] if argv is None else argv
    try:
        require(len(args) == 1 and args[0] in PHASES)
        fixture = verify(Path(os.environ['AMADIS_ROOT']), os.environ['AM_ACCEPTANCE_URL'], args[0])
        result: dict[str, object] = {'status': 'PASS'}
        if args[0] == 'load':
            output_mode = os.fstat(sys.stdout.fileno()).st_mode
            require(stat.S_ISFIFO(output_mode) or stat.S_ISSOCK(output_mode))
            result['fixture'] = fixture
        print(json.dumps(result))
        return 0
    except Exception:
        print('{"status":"FAIL"}')
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
