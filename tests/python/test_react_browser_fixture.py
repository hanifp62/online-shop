"""VERIFY-CLOSE: synthetic SQLite only; no PHP, server, browser or operational DB."""
import contextlib
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import sqlite3
import tempfile
import unittest
from unittest.mock import patch

REPO = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location('react_fixture', REPO / 'scripts/amadis/acceptance_react_fixture.py')
if SPEC is None or SPEC.loader is None:
    raise ImportError('Fixture module unavailable')
fixture = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(fixture)
LAUNCH_SPEC = importlib.util.spec_from_file_location('react_launch', REPO / 'scripts/amadis/launch.py')
if LAUNCH_SPEC is None or LAUNCH_SPEC.loader is None:
    raise ImportError('Launcher module unavailable')
launch = importlib.util.module_from_spec(LAUNCH_SPEC)
LAUNCH_SPEC.loader.exec_module(launch)
ORIGIN = 'http://127.0.0.1:8043'


class ReactFixtureTests(unittest.TestCase):
    def setUp(self):
        temporary = tempfile.TemporaryDirectory()
        self.addCleanup(temporary.cleanup)
        self.base = Path(temporary.name).resolve()
        self.root = self.base / ('amadis-' + 'a' * 24)
        self.root.mkdir(mode=0o700)
        self.private = self.root / 'private-runtime'
        self.private.mkdir(mode=0o700)
        base = patch.object(fixture, 'BASE', self.base)
        base.start()
        self.addCleanup(base.stop)
        self.identity = {'root': str(self.root), 'database': str(self.root / 'database.sqlite'), 'repo': str(REPO)}
        self.value = {'schema': 1, 'origin': ORIGIN, 'customer_id': 7, 'product_id': 17, 'order_id': 31,
                      'unsubscribe_url': ORIGIN + '/pengingat-keranjang/berhenti/7?signature=' + 'b' * 64}
        self.write(self.root / 'identity.json', self.identity)
        self.write(self.private / 'credentials.json', {'admin': {'email': 'amadis-admin@example.test'},
                                                      'customer': {'email': 'amadis-customer@example.test'}})
        self.write(self.private / 'react-fixture.json', self.value)
        with contextlib.closing(sqlite3.connect(self.root / 'database.sqlite')) as db, db:
            db.executescript('''
                CREATE TABLE users (id INTEGER, email TEXT, cart_reminder_opt_in INTEGER);
                CREATE TABLE products (product_id INTEGER, product_slug TEXT, stock INTEGER, is_active INTEGER, product_price INTEGER);
                CREATE TABLE orders (id INTEGER, user_id INTEGER, status TEXT, subtotal INTEGER, discount INTEGER,
                    tax INTEGER, shipping_fee INTEGER, total INTEGER, payload_hash TEXT, idempotency_key TEXT);
                CREATE TABLE order_items (order_id INTEGER, product_id INTEGER, quantity INTEGER, unit_price INTEGER, subtotal INTEGER);
                CREATE TABLE reviews (id INTEGER, user_id INTEGER, order_id INTEGER, product_id INTEGER, rating INTEGER, body TEXT);
                INSERT INTO users VALUES (7, 'amadis-customer@example.test', 1);
                INSERT INTO products VALUES (17, 'amadis-demo-stock-37', 37, 1, 1250000);
                INSERT INTO order_items VALUES (31, 17, 1, 1250000, 1250000);
            ''')
            db.execute('INSERT INTO orders VALUES (31, 7, ?, 1250000, 0, 0, 0, 1250000, ?, ?)',
                       ('completed', hashlib.sha256(fixture.MARKER.encode()).hexdigest(), fixture.MARKER))
        (self.root / 'database.sqlite').chmod(0o600)

    def write(self, path, value):
        path.write_text(json.dumps(value))
        path.chmod(0o600)

    def sql(self, statement, parameters=()):
        with contextlib.closing(sqlite3.connect(self.root / 'database.sqlite')) as db, db:
            db.execute(statement, parameters)

    def review(self):
        self.sql('INSERT INTO reviews VALUES (1, 7, 31, 17, 5, ?)', (fixture.REVIEW,))

    def verify(self, phase='load'):
        return fixture.verify(self.root, ORIGIN, phase)

    def test_private_load_and_all_state_transitions_are_read_only(self):
        self.assertEqual(self.verify(), self.value)
        self.verify('review-before')
        self.review()
        self.verify('review-after')
        self.verify('unsubscribe-before')
        self.verify('unsubscribe-get')
        self.sql('UPDATE users SET cart_reminder_opt_in = 0')
        database = self.root / 'database.sqlite'
        before = database.read_bytes(), database.stat().st_mtime_ns
        self.verify('unsubscribe-after')
        self.verify('unsubscribe-after')
        self.assertEqual((database.read_bytes(), database.stat().st_mtime_ns), before)

    def test_wrong_root_origin_and_identity_rejected(self):
        for origin in ('http://localhost:8043', 'https://example.test', ORIGIN + '/', 'http://127.0.0.1:8000',
                       'http://127.0.0.1:8001', 'http://127.0.0.1:65536', 'http://127.0.0.1:08043'):
            with self.subTest(origin=origin), self.assertRaises(ValueError):
                fixture.verify(self.root, origin, 'load')
        with self.assertRaises(ValueError):
            fixture.verify(self.base, ORIGIN, 'load')
        for key in ('root', 'database', 'repo'):
            self.write(self.root / 'identity.json', {**self.identity, key: '/wrong'})
            with self.subTest(key=key), self.assertRaises(ValueError):
                self.verify()
        self.write(self.root / 'identity.json', self.identity)

    def test_wrong_channel_schema_ids_and_signed_target_rejected(self):
        mutations = [{**self.value, key: value} for key, value in (
            ('schema', True), ('customer_id', True), ('product_id', -1), ('order_id', 999),
            ('customer_id', 8), ('product_id', 18), ('origin', 'http://127.0.0.1:8044'),
            ('extra', 'private-value'), ('unsubscribe_url', ORIGIN + '/akun'),
            ('unsubscribe_url', self.value['unsubscribe_url'].replace('/7?', '/8?')),
            ('unsubscribe_url', self.value['unsubscribe_url'] + '&unknown=1'),
            ('unsubscribe_url', self.value['unsubscribe_url'].replace(ORIGIN, 'https://example.test')),
            ('unsubscribe_url', self.value['unsubscribe_url'].replace('signature=', 'signature=BAD')))]
        for value in mutations:
            self.write(self.private / 'react-fixture.json', value)
            with self.subTest(value=list(value)), self.assertRaises(ValueError):
                self.verify()

    def test_missing_malformed_duplicate_or_large_channel_fails(self):
        path = self.private / 'react-fixture.json'
        for text in ('{', '{"schema":1,"schema":1}', 'x' * 4097, 'NaN'):
            path.write_text(text)
            with self.subTest(size=len(text)), self.assertRaises((ValueError, TypeError)):
                self.verify()
        path.unlink()
        with self.assertRaises(FileNotFoundError):
            self.verify()

    def test_links_public_permissions_and_missing_database_rejected(self):
        for path in (self.root / 'database.sqlite', self.root / 'identity.json', self.private / 'react-fixture.json'):
            with self.subTest(name=path.name):
                saved = path.with_name(path.name + '.saved')
                path.rename(saved)
                path.symlink_to(saved)
                with self.assertRaises((ValueError, OSError)):
                    self.verify()
                path.unlink()
                os.link(saved, path)
                with self.assertRaises(ValueError):
                    self.verify()
                path.unlink()
                saved.rename(path)
                path.chmod(0o644)
                with self.assertRaises(ValueError):
                    self.verify()
                path.chmod(0o600)
        self.private.chmod(0o755)
        with self.assertRaises(ValueError):
            self.verify()
        self.private.chmod(0o700)
        (self.root / 'database.sqlite').unlink()
        with self.assertRaises(FileNotFoundError):
            self.verify()
        self.assertFalse((self.root / 'database.sqlite').exists())

    def test_guard_rejects_linked_private_directory_and_database_sidecar(self):
        saved = self.root / 'saved-private'
        self.private.rename(saved)
        self.private.symlink_to(saved, target_is_directory=True)
        with self.assertRaises(ValueError):
            self.verify()
        self.private.unlink()
        saved.rename(self.private)
        (self.root / 'database.sqlite-wal').symlink_to(self.private / 'react-fixture.json')
        with self.assertRaises(OSError):
            self.verify()

    def test_wrong_completed_order_or_item_never_qualifies(self):
        for statement, restore in (
            ("UPDATE orders SET status='pending'", "UPDATE orders SET status='completed'"),
            ('UPDATE orders SET user_id=9', 'UPDATE orders SET user_id=7'),
            ('UPDATE orders SET total=1', 'UPDATE orders SET total=1250000'),
            ('UPDATE order_items SET product_id=9', 'UPDATE order_items SET product_id=17'),
            ('UPDATE order_items SET quantity=2', 'UPDATE order_items SET quantity=1'),
            ('UPDATE products SET is_active=0', 'UPDATE products SET is_active=1'),
            ('UPDATE products SET stock=36', 'UPDATE products SET stock=37'),
        ):
            self.sql(statement)
            with self.subTest(statement=statement), self.assertRaises(ValueError):
                self.verify()
            self.sql(restore)

    def test_state_oracle_rejects_missing_wrong_or_duplicate_review(self):
        with self.assertRaises(ValueError):
            self.verify('review-after')
        self.review()
        with self.assertRaises(ValueError):
            self.verify('review-before')
        for column, wrong, correct in (('user_id', 9, 7), ('order_id', 9, 31), ('product_id', 9, 17),
                                       ('rating', 4, 5), ('body', 'wrong', fixture.REVIEW)):
            self.sql('UPDATE reviews SET ' + column + ' = ?', (wrong,))
            with self.subTest(column=column), self.assertRaises(ValueError):
                self.verify('review-after')
            self.sql('UPDATE reviews SET ' + column + ' = ?', (correct,))
        self.review()
        with self.assertRaises(ValueError):
            self.verify('review-after')

    def test_get_mutation_and_noop_post_fail_closed(self):
        self.review()
        with self.assertRaises(ValueError):
            self.verify('unsubscribe-after')
        self.sql('UPDATE users SET cart_reminder_opt_in = 0')
        with self.assertRaises(ValueError):
            self.verify('unsubscribe-get')

    def test_cli_diagnostics_never_echo_private_material(self):
        output = io.StringIO()
        with patch.dict(os.environ, {'AMADIS_ROOT': str(self.root), 'AM_ACCEPTANCE_URL': ORIGIN}), \
             contextlib.redirect_stdout(output):
            self.assertEqual(fixture.main(['review-before']), 0)
            with patch.object(fixture, 'verify', side_effect=RuntimeError(self.value['unsubscribe_url'])):
                self.assertEqual(fixture.main(['load']), 1)
            self.assertEqual(fixture.main(['unknown']), 1)
        self.assertEqual(output.getvalue(), '{"status": "PASS"}\n{"status":"FAIL"}\n{"status":"FAIL"}\n')

    def test_load_refuses_terminal_or_regular_file_output(self):
        for mode in (0o100600, 0o020600):
            output = io.StringIO()
            with patch.dict(os.environ, {'AMADIS_ROOT': str(self.root), 'AM_ACCEPTANCE_URL': ORIGIN}), \
                 patch.object(fixture, 'verify', return_value=self.value), \
                 patch.object(fixture.os, 'fstat', return_value=type('Stat', (), {'st_mode': mode})()), \
                 contextlib.redirect_stdout(output):
                with patch.object(output, 'fileno', return_value=42):
                    self.assertEqual(fixture.main(['load']), 1)
            self.assertEqual(output.getvalue(), '{"status":"FAIL"}\n')

    def test_server_log_sanitizer_removes_whole_signed_capabilities(self):
        target = self.value['unsubscribe_url']
        for raw in (target, 'GET ' + target.removeprefix(ORIGIN) + ' HTTP/1.1', json.dumps({'url': target})):
            clean = launch.sanitized(raw)
            self.assertNotIn('b' * 64, clean)
            self.assertNotIn('/pengingat-keranjang/berhenti/', clean)
            self.assertIn('[SIGNED_URL_REDACTED]', clean)

    def test_unsigned_paths_and_standalone_signature_redaction(self):
        unsigned = '/' + 'segment/' * 10000
        self.assertEqual(launch.sanitized(unsigned), unsigned)
        self.assertNotIn('b' * 64, launch.sanitized(json.dumps({'signature': 'b' * 64})))
        self.assertNotIn('b' * 64, launch.sanitized(json.dumps({'unsubscribe_url': self.value['unsubscribe_url']})))

    def test_php_provisioner_stays_guarded_private_and_explicitly_opt_in(self):
        source = (REPO / 'scripts/amadis/artisan.php').read_text()
        self.assertLess(source.index('$root = amadisGuard();'), source.index('$kernel->bootstrap();'))
        block = source.split("if (in_array($argv[1] ?? '', ['amadis:provision', 'amadis:provision-react'], true))", 1)[1]
        block = block.split("if (($argv[1] ?? '') === 'amadis:lifecycle-snapshot')", 1)[0]
        for required in ("$db->getDriverName() !== 'sqlite'", "$db->getDatabaseName() !== $root.'/database.sqlite'",
                         "$db->table('orders')->exists()", "if ($react)", '(new CartReminder($customer, []))->unsubscribeUrl()',
                         "$origin !== getenv('APP_URL')", "fopen($private.'/react-fixture.json', 'x')", 'umask(0077)'):
            self.assertIn(required, block)
        self.assertNotIn('echo json_encode($fixture', block)
        self.assertNotIn('decrement(', block)


if __name__ == '__main__':
    unittest.main()
