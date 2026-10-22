#!/usr/bin/env python3
"""NFR-CICD-004: validasi tiga artefak acceptance_isolated.sh, tanpa browser/DB.

CLI DIRECTORY; stdout satu JSON {status, counts}. Exit 0 = valid, 1 = ditolak,
2 = argumen invalid. Tidak mencetak path, JSON mentah, command, atau exception.
AM-10 memeriksa irisan interval HTTP sisi klien yang dilaporkan, bukan
bukti konkurensi transaksi DB, integrasi provider, atau perilaku produksi.
"""
from datetime import datetime
import json
from pathlib import Path
import re
import sys

REQUIRED_STEPS = {'migrate', 'seed', 'provision', 'am01', 'am06_escape_phpunit', 'browser', 'am10_db'}
INVARIANTS = {'AM-01_db_invariant': 1, 'AM-10_db_invariant': 1, 'browser_rc': 0, 'escape_rc': 0}


def require(condition):
    if not condition:
        raise ValueError('INVALID_REPORT')


def unique_object(pairs):
    result = dict(pairs)
    require(len(result) == len(pairs))
    return result


def read(directory, name):
    path = directory / name
    require(path.is_file())
    with path.open('rb') as stream:
        data = stream.read(2 * 1024 * 1024 + 1)
    require(len(data) <= 2 * 1024 * 1024)
    return data.decode('utf-8')


def stamp(value):
    result = datetime.fromisoformat(value.replace('Z', '+00:00'))
    require(result.tzinfo is not None)
    return result


def commands(text):
    lines = text.splitlines()
    require(lines[0] == 'step\tstart_utc\tend_utc\trc\tcommand')
    seen, current = set(), None
    for line in lines[1:]:
        fields = line.split('\t', 4)
        if len(fields) == 1:
            # Producer memakai SQL multiline tanpa quoting TSV pada dua step ini.
            require(current in {'am01', 'am10_db'} and line.startswith('select ') and line.endswith(';'))
            continue
        require(len(fields) == 5)
        name, start, end, rc, command = fields
        require(re.fullmatch(r'[a-z][a-z0-9_]*', name) and name not in seen)
        require(rc == '0' and command.strip() and stamp(start) <= stamp(end))
        seen.add(name)
        current = name
    require(REQUIRED_STEPS <= seen)
    return len(seen)


def require_items(items, product_id, quantity):
    require(items == [{'productId': product_id, 'quantity': quantity}]
            and all(type(value) is int for value in items[0].values()))


def validate(directory):
    reports = [json.loads(read(directory, name), object_pairs_hook=unique_object,
                          parse_constant=lambda _: require(False))
               for name in ('invariants.json', 'browser-results.json')]
    invariant, report = reports
    require(invariant == INVARIANTS and all(type(value) is int for value in invariant.values()))
    require(set(report) == {'AM-02', 'AM-06-keyboard', 'AM-10'})
    pages, keyboard, checkout = report['AM-02'], report['AM-06-keyboard'], report['AM-10']
    require(set(pages) == {'status', 'counts', 'uniqueProducts'} and pages['status'] == 'PASS')
    require(type(pages['uniqueProducts']) is int and pages['uniqueProducts'] == 76)
    require(pages['counts'] == [8] * 9 + [4] and all(type(n) is int for n in pages['counts']))
    actions = {'enter', 'space', 'escape', 'closeButton', 'focusRestored'}
    require(set(keyboard) == actions | {'status', 'tabSteps', 'focusToBrowserUi', 'pageContentLeaks'})
    require(keyboard['status'] == 'PASS' and keyboard['pageContentLeaks'] == [])
    require(all(keyboard[action] is True for action in actions))
    require(type(keyboard['tabSteps']) is int and keyboard['tabSteps'] == 12)
    require(type(keyboard['focusToBrowserUi']) is int and 0 <= keyboard['focusToBrowserUi'] <= 12)
    require(set(checkout) == {'status', 'product', 'requests'} and checkout['status'] == 'PASS_HTTP')
    product = checkout['product']
    require(set(product) == {'id', 'slug', 'stock'} and product['slug'] == 'amadis-demo-stock-37')
    require(type(product['id']) is int and product['id'] > 0)
    require(type(product['stock']) is int and product['stock'] == 37)
    request_data = checkout['requests']
    if type(request_data) is not list or len(request_data) != 3:
        raise ValueError('INVALID_REPORT')
    requests: list[dict] = []
    for item in request_data:
        if not isinstance(item, dict):
            raise TypeError('INVALID_REPORT')
        requests.append(item)
    require([item['quantity'] for item in requests] == [20, 20, 17])
    for item in requests:
        require(set(item) == {'quantity', 'httpStatus', 'orderId', 'start', 'finish', 'actor', 'preparedItems', 'orderItems'})
        require(type(item['quantity']) is int and type(item['httpStatus']) is int)
        require(re.fullmatch(r'[a-f0-9]{64}', item['actor']))
        require_items(item['preparedItems'], product['id'], item['quantity'])
        require(item['httpStatus'] in (201, 422) and stamp(item['start']) <= stamp(item['finish']))
        require((type(item['orderId']) is int and item['orderId'] > 0) if item['httpStatus'] == 201
                else item['orderId'] is None)
        if item['httpStatus'] == 201:
            require_items(item['orderItems'], product['id'], item['quantity'])
        else:
            require(item['orderItems'] is None)
    require(len({item['actor'] for item in requests}) == 3)
    require(max(stamp(item['start']) for item in requests)
            <= min(stamp(item['finish']) for item in requests))
    created_ids = [item['orderId'] for item in requests if item['httpStatus'] == 201]
    created = len(created_ids)
    require(created == len(set(created_ids)))
    require(created > 0 and sum(item['orderItems'][0]['quantity'] for item in requests if item['httpStatus'] == 201) <= product['stock'])
    return {'commands': commands(read(directory, 'commands.tsv')), 'pages': 10, 'products': 76,
            'checkout_requests': len(requests), 'created': created, 'rejected': len(requests) - created,
            'page_content_leaks': 0}


def main(argv=None):
    args = sys.argv[1:] if argv is None else argv
    summary = {'status': 'FAIL', 'counts': None}
    if len(args) == 1:
        try:
            summary = {'status': 'PASS', 'counts': validate(Path(args[0]))}
        except Exception:
            pass
    print(json.dumps(summary, sort_keys=True))
    return 2 if len(args) != 1 else int(summary['status'] != 'PASS')


if __name__ == '__main__':
    sys.exit(main())
