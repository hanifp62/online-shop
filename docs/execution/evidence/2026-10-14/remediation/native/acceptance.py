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

BASE = 'http://127.0.0.1:8017'
ROOT = Path(os.environ['MANUAL_ROOT'])
CREDENTIALS = json.loads((ROOT / 'private-runtime/credentials.json').read_text())

def client():
    jar = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

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
for path in ['/', '/koleksi', '/produk/manual-synthetic-sapphire']:
    status, _, body = request(client(), path)
    assert status == 200 and body, f'{path}: HTTP {status}'
    checks[path] = {'status': status, 'bytes': len(body)}

customer = client()
token = csrf(customer, '/login')
status, headers, _ = request(customer, '/login', 'POST', CREDENTIALS['customer'] | {'_token': token})
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
status, _, body = request(customer, '/orders', headers=headers)
prior = [o for o in json.loads(body)['data'] if o['id'] != order_id]
checks['persisted_prior_orders_visible'] = len(prior)
status, _, _ = request(customer, f'/orders/{order_id}/cancel', 'POST', headers=headers)
assert status == 200, f'cancel HTTP {status}'
checks['cancel'] = status

admin = client()
token = csrf(admin, '/admin/login')
status, _, _ = request(admin, '/admin/login', 'POST', CREDENTIALS['admin'] | {'_token': token})
status, _, _ = request(admin, '/admin')
assert status == 200, f'admin dashboard HTTP {status}'
status, _, _ = request(admin, '/admin/orders')
assert status == 200, f'admin orders HTTP {status}'
status, headers, body = request(admin, '/admin/reports/orders.pdf?period=30d')
assert status == 200 and headers.get('Content-Type','').startswith('application/pdf') and body.startswith(b'%PDF-'), f'PDF HTTP {status}'
checks['admin-pdf'] = {'status': status, 'content_type': headers.get('Content-Type'), 'bytes':len(body)}
print(json.dumps({'result':'PASS','checks':checks,'provenance':'Synthetic isolated data only'}, ensure_ascii=False, indent=2))
