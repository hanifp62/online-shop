#!/usr/bin/env python3
"""Bounded local HTTP smoke and customer/admin transaction workflow for isolated fixture."""
import http.cookiejar
import json
import os
import re
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

PORT = os.environ.get('MANUAL_PORT', '8017')
BASE = f'http://127.0.0.1:{PORT}'
ROOT = Path(os.environ['MANUAL_ROOT'])
from preflight import check_environment, check_root, check_checkout
check_environment()
check_checkout(check_root(ROOT))


class SameOriginRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        target = urllib.parse.urlsplit(newurl)
        if (target.scheme, target.netloc) != ('http', f'127.0.0.1:{PORT}'):
            raise AssertionError('Rejected cross-origin redirect before following it.')
        if req.full_url.endswith('/manual/identity.txt'):
            raise AssertionError('Identity endpoint must not redirect.')
        return super().redirect_request(req, fp, code, msg, headers, newurl)


def assert_identity(opener):
    nonce = (ROOT / 'public/manual/identity.txt').read_bytes().strip()
    status, _, body = request(opener, '/manual/identity.txt')
    assert status == 200 and body.strip() == nonce, 'Checkout HTTP identity mismatch; no credentials sent.'
    return status


def credentials():
    assert_identity(client())
    return json.loads((ROOT / 'private-runtime/credentials.json').read_text())

def client():
    jar = http.cookiejar.CookieJar()
    return urllib.request.build_opener(SameOriginRedirect(), urllib.request.HTTPCookieProcessor(jar))


def request(opener, path, method='GET', data=None, headers=None):
    body = urllib.parse.urlencode(data).encode() if data is not None else None
    req = urllib.request.Request(BASE + path, data=body, method=method, headers=headers or {})
    try:
        with opener.open(req, timeout=10) as response:
            return response.status, response.headers, response.read()
    except urllib.error.HTTPError as error:
        return error.code, error.headers, error.read()


def csrf(opener, path):
    _, _, body = request(opener, path)
    found = re.search(rb'name="_token" value="([^"]+)', body)
    if not found:
        raise AssertionError(f'CSRF token missing on {path}')
    return found.group(1).decode()

checks = {}
CREDS = credentials()

for path in ['/', '/koleksi', '/produk/manual-synthetic-sapphire']:
    status, _, body = request(client(), path)
    assert status == 200 and body, f'{path}: HTTP {status}'
    checks[path] = {'status': status, 'bytes': len(body)}

status, _, body = request(client(), '/produk/manual-synthetic-sapphire')
assert b'schema.org/InStock' in body, 'active stock PDP must claim InStock'
status, _, body = request(client(), '/produk/manual-synthetic-out')
assert status == 200 and b'schema.org/OutOfStock' in body and b'schema.org/InStock' not in body, f'sold-out PDP HTTP {status}'
checks['soldout-jsonld'] = 'OutOfStock'
status, _, _ = request(client(), '/produk/manual-synthetic-retired')
assert status == 404, f'inactive PDP HTTP {status}'
checks['inactive-pdp'] = status

customer = client()
token = csrf(customer, '/login')
status, headers, _ = request(customer, '/login', 'POST', CREDS['customer'] | {'_token': token})
status, _, _ = request(customer, '/akun')  # auth-only route proves the redirect-followed login created a session
assert status == 200, f'customer login/session HTTP {status}'
checks['customer-login'] = status
status, _, cart = request(customer, '/cart')
assert status == 200 and b'items' in cart
checks['cart'] = status
status, _, body = request(customer, '/koleksi')
meta = re.search(rb'name="csrf-token" content="([^"]+)', body)
product = re.search(rb'data-product-id="(\d+)"', body)
assert meta and product
headers = {'X-CSRF-TOKEN': meta.group(1).decode(), 'Accept': 'application/json'}
status, _, body = request(customer, '/cart/items', 'POST', {'product_id': product.group(1).decode(), 'quantity': '1'}, headers)
assert status == 200, f'cart add HTTP {status}'
status, _, body = request(customer, '/addresses', 'POST', {
    'label':'Synthetic', 'recipient_name':'Manual Synthetic', 'phone':'0800000000',
    'line1':'Synthetic test address', 'city':'Jakarta', 'province':'DKI Jakarta', 'postal_code':'12345',
}, headers)
assert status == 201, f'address create HTTP {status}: {body[:200]!r}'
address_id = json.loads(body)['address']['id']
status, _, body = request(customer, '/checkout', 'POST', {'address_id':address_id, 'idempotency_key':f'manual-synthetic-{int(time.time()*1000)}'}, headers)
assert status == 201, f'checkout HTTP {status}: {body[:300]!r}'
order_id = json.loads(body)['order']['id']
checks['checkout'] = {'status':status, 'order_id':order_id}

# Keranjang kosong setelah checkout berhasil; isi ulang sebelum tes replay
status, _, body = request(customer, '/cart/items', 'POST', {'product_id': product.group(1).decode(), 'quantity': '1'}, headers)
assert status == 200, f're-add to cart HTTP {status}'

# Verifikasi replay idempotensi dengan kunci yang sama via HTTP native
key_used = f'manual-synthetic-{int(time.time()*1000)}'
status, _, body_first = request(customer, '/checkout', 'POST', {'address_id':address_id, 'idempotency_key':key_used}, headers)
assert status == 201, f'first checkout HTTP {status}'
id_first = json.loads(body_first)['order']['id']
status, _, body_replay = request(customer, '/checkout', 'POST', {'address_id':address_id, 'idempotency_key':key_used}, headers)
assert status == 200 and json.loads(body_replay)['order']['id'] == id_first, f'replay failed HTTP {status}'
checks['http_idempotent_replay'] = 200

# Buat alamat kedua yang sah milik customer untuk menguji konflik muatan berubah -> 409
status, _, body = request(customer, '/addresses', 'POST', {
    'label':'Kantor', 'recipient_name':'Manual Synthetic 2', 'phone':'0811111111',
    'line1':'Jalan Kantor 2', 'city':'Jakarta', 'province':'DKI Jakarta', 'postal_code':'12345',
}, headers)
assert status == 201
addr2_id = json.loads(body)['address']['id']
status, _, _ = request(customer, '/checkout', 'POST', {'address_id':addr2_id, 'idempotency_key':key_used}, headers)
assert status == 409, f'expected 409 on changed payload, got {status}'
checks['http_idempotent_conflict_409'] = 409

status, _, body = request(customer, '/orders', headers=headers)
prior = [o for o in json.loads(body)['data'] if o['id'] not in (order_id, id_first)]
checks['persisted_prior_orders_visible'] = len(prior)

# Verifikasi double cancel tidak merestok ganda: cancel pertama 200, cancel kedua 200 (idempoten)
status, _, _ = request(customer, f'/orders/{order_id}/cancel', 'POST', headers=headers)
assert status == 200, f'cancel HTTP {status}'
status, _, _ = request(customer, f'/orders/{order_id}/cancel', 'POST', headers=headers)
assert status == 200, f'double cancel HTTP {status}'
checks['double_cancel_idempotent'] = 200

admin = client()
token = csrf(admin, '/admin/login')
status, _, _ = request(admin, '/admin/login', 'POST', CREDS['admin'] | {'_token': token})
status, _, _ = request(admin, '/admin')
assert status == 200, f'admin dashboard HTTP {status}'
status, _, _ = request(admin, '/admin/orders')
assert status == 200, f'admin orders HTTP {status}'
status, headers, body = request(admin, '/admin/reports/orders.pdf?period=30d')
assert status == 200 and headers.get('Content-Type','').startswith('application/pdf') and body.startswith(b'%PDF-'), f'PDF HTTP {status}'
checks['admin-pdf'] = {'status': status, 'content_type': headers.get('Content-Type'), 'bytes':len(body)}
print(json.dumps({'result':'PASS','checks':checks,'provenance':'Synthetic isolated data only'}, ensure_ascii=False, indent=2))
