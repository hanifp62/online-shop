import http.client
import json
import os
import socket
from pathlib import Path
import tempfile
import subprocess
import time
import unittest

PROXY_SCRIPT = Path(__file__).resolve().parents[2] / 'scripts/amadis/connect_proxy.php'


class ConnectProxyTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.log = tempfile.TemporaryFile(mode='w+')
        cls.process = subprocess.Popen(
            ['php', str(PROXY_SCRIPT)],
            env={'PATH': os.environ['PATH'], 'PROXY_LISTEN': '127.0.0.1:8992'},
            stdin=subprocess.DEVNULL, stderr=cls.log, text=True
        )
        time.sleep(0.3)

    @classmethod
    def tearDownClass(cls):
        cls.process.terminate()
        try:
            cls.process.wait(timeout=2)
        except subprocess.TimeoutExpired:
            cls.process.kill()
        cls.log.close()

    def test_every_connect_is_logged(self):
        conn = http.client.HTTPConnection('127.0.0.1', 8992, timeout=5)
        conn.request('CONNECT', 'logged.invalid:443')
        conn.getresponse()
        conn.close()
        time.sleep(0.1)
        self.log.seek(0)
        records = [json.loads(line) for line in self.log if line.strip()]
        self.assertIn({'host': 'logged.invalid', 'port': '443', 'allowed': False}, records)

    def test_suffix_and_malformed_targets_are_refused(self):
        for target in ('repo.packagist.org.example.com:443', 'repo.packagist.org:443:80',
                       'repo.packagist.org.:443', 'user@repo.packagist.org:443', '[::1]:443'):
            with self.subTest(target=target):
                conn = http.client.HTTPConnection('127.0.0.1', 8992, timeout=5)
                conn.request('CONNECT', target)
                self.assertEqual(conn.getresponse().status, 403)
                conn.close()

    def test_non_connect_method_is_refused(self):
        conn = http.client.HTTPConnection('127.0.0.1', 8992, timeout=5)
        conn.request('GET', 'repo.packagist.org:443')
        self.assertEqual(conn.getresponse().status, 403)
        conn.close()

    def test_disallowed_host_returns_403(self):
        conn = http.client.HTTPConnection('127.0.0.1', 8992, timeout=5)
        conn.request('CONNECT', 'example.com:443')
        resp = conn.getresponse()
        self.assertEqual(resp.status, 403)
        conn.close()

    def test_disallowed_port_returns_403(self):
        conn = http.client.HTTPConnection('127.0.0.1', 8992, timeout=5)
        conn.request('CONNECT', 'repo.packagist.org:80')
        resp = conn.getresponse()
        self.assertEqual(resp.status, 403)
        conn.close()

    def test_private_host_returns_403(self):
        for host in ('127.0.0.1:443', '10.0.0.1:443', '192.168.1.1:443', 'host.docker.internal:443'):
            with self.subTest(host=host):
                conn = http.client.HTTPConnection('127.0.0.1', 8992, timeout=5)
                conn.request('CONNECT', host)
                resp = conn.getresponse()
                self.assertEqual(resp.status, 403)
                conn.close()

    def test_incomplete_and_oversized_headers_are_rejected(self):
        for request in (b'CONNECT example.com:443 HTTP/1.1\r\n' + b'X: a\r\n' * 65,
                        b'CONNECT example.com:443 HTTP/1.1\r\nBad: ' + b'x' * 4096 + b'\r\n\r\n'):
            with self.subTest(length=len(request)), socket.create_connection(('127.0.0.1', 8992), timeout=5) as conn:
                conn.sendall(request)
                self.assertIn(b'400 Bad Request', conn.recv(256))

    def test_stalled_client_does_not_block_other_clients(self):
        # Composer holds tunnels open and fetches in parallel: one slow client must not starve the rest.
        with socket.create_connection(('127.0.0.1', 8992), timeout=5) as slow:
            slow.sendall(b'CONNECT repo.packagist.org:443 HTTP/1.1\r\n')
            started = time.monotonic()
            with socket.create_connection(('127.0.0.1', 8992), timeout=5) as fast:
                fast.sendall(b'CONNECT example.com:443 HTTP/1.1\r\n\r\n')
                self.assertIn(b'403 Forbidden', fast.recv(256))
            self.assertLess(time.monotonic() - started, 1.5)

    def test_many_concurrent_refusals_all_complete(self):
        sockets = [socket.create_connection(('127.0.0.1', 8992), timeout=5) for _ in range(8)]
        try:
            for conn in sockets:
                conn.sendall(b'CONNECT example.com:443 HTTP/1.1\r\n\r\n')
            for conn in sockets:
                self.assertIn(b'403 Forbidden', conn.recv(256))
        finally:
            for conn in sockets:
                conn.close()

    def test_non_loopback_bind_requires_opt_in(self):
        with subprocess.Popen(['php', str(PROXY_SCRIPT)], env={'PATH': os.environ['PATH'],
                              'PROXY_LISTEN': '0.0.0.0:0'}, stdout=subprocess.DEVNULL,
                              stderr=subprocess.PIPE) as process:
            self.assertNotEqual(process.wait(timeout=2), 0)

    def test_public_address_filter_rejects_local_and_reserved_ranges(self):
        self.assertIn('FILTER_FLAG_NO_PRIV_RANGE', PROXY_SCRIPT.read_text())
        self.assertIn('FILTER_FLAG_NO_RES_RANGE', PROXY_SCRIPT.read_text())


if __name__ == '__main__':
    unittest.main()
