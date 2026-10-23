"""VERIFY-CLOSE / NFR-FE-003: exact native browser contract, no optional stages.

publish DIRECTORY is called only after the owned supervisor exits successfully.
verify SUMMARY verifies a published summary offline. Neither mode echoes bad input.
"""
import json
import sys
from datetime import datetime
from pathlib import Path

MANDATORY = [
    'catalog-native', 'catalog-spa-pagination-history', 'catalog-repeated-filters',
    'catalog-locale-head', 'catalog-dialog-cart', 'catalog-mobile', 'auth-native-post-errors',
    'auth-other-pages', 'catalog-delayed-chunk-draft', 'entry-failure-fallback',
    'no-js-and-explicit-rollback', 'admin-private-history',
    'home-native-mobile-keyboard-theme-reflow', 'home-native-search-category-locale',
    'pdp-native-inquiry-draft-cart-delta-undo', 'shop-guest-frozen-idempotent-replay',
    'shop-account-address-voucher-orders-favorites', 'shop-account-completed-order-review',
    'dashboard-native-seven-regions-atomic-drafts', 'dashboard-native-inline-consent-late-edit',
    'dashboard-native-back-failure-recovery', 'dashboard-native-order-json-once-refresh',
    'dashboard-native-private-history', 'signed-unsubscribe-native-confirm-complete',
    'account-native-update-export-logout', 'reset-native-flash-validation',
    'customer-totp-native-enrol-recovery', 'customer-totp-native-challenge',
    'admin-totp-native-enrol-recovery', 'admin-totp-native-challenge', 'console-clean',
]
FAULTS = {'checkout-abort-requestfailed': (1, 1), 'checkout-abort-console': (0, 1),
          'voucher-rejected-console': (0, 1), 'account-export-download': (0, 1)}


def require(condition):
    if not condition:
        raise ValueError('INVALID_REACT_REPORT')


def unique_object(pairs):
    result = dict(pairs)
    require(len(result) == len(pairs))
    return result


def validate(result, *, summary=True):
    fields = {'status', 'checks', 'consoleErrors', 'unavailable', 'expectedFaultCounts'}
    require(type(result) is dict and set(result) == fields | ({'supervisorExit', 'cleanup'} if summary else set()))
    require(result['status'] == 'PASS' and type(result['consoleErrors']) is int and result['consoleErrors'] == 0)
    require(type(result['checks']) is list and result['checks'] == MANDATORY)
    require(type(result['unavailable']) is list and result['unavailable'] == [])
    faults = result['expectedFaultCounts']
    require(type(faults) is dict and set(faults) == set(FAULTS))
    if not isinstance(faults, dict):
        raise ValueError('INVALID_REACT_REPORT')
    for name, (minimum, maximum) in FAULTS.items():
        value = faults[name]
        if type(value) is not int:
            raise ValueError('INVALID_REACT_REPORT')
        require(minimum <= value <= maximum)
    if summary:
        require(type(result['supervisorExit']) is int and result['supervisorExit'] == 0 and result['cleanup'] == 'PASS')
    return result


def parse_report(data, *, summary=True):
    require(len(data) <= 32768)
    return validate(json.loads(data, object_pairs_hook=unique_object,
                               parse_constant=lambda _: require(False)), summary=summary)


def read(path):
    require(not path.is_symlink() and path.is_file() and path.stat().st_nlink == 1)
    with path.open('rb') as stream:
        data = stream.read(32769)
    require(len(data) <= 32768)
    return data


def publish(directory):
    result = parse_report(read(directory / 'react-flow.json'), summary=False)
    lines = read(directory / 'commands.tsv').decode().splitlines()
    require(len(lines) == 5 and lines[0] == 'step\tstart_utc\tend_utc\trc')
    previous = None
    for name, line in zip(('migrate', 'seed', 'provision', 'browser'), lines[1:]):
        fields = line.split('\t')
        require(len(fields) == 4 and fields[0] == name and fields[3] == '0')
        start, end = (datetime.fromisoformat(value.replace('Z', '+00:00')) for value in fields[1:3])
        require(start.tzinfo is not None and end.tzinfo is not None and start <= end)
        require(previous is None or previous <= start)
        previous = end
    summary = validate({**result, 'supervisorExit': 0, 'cleanup': 'PASS'})
    with (directory / 'summary.json').open('x') as stream:
        json.dump(summary, stream, indent=2)
        stream.write('\n')
    return summary


def main(argv=None):
    args = sys.argv[1:] if argv is None else argv
    try:
        require(len(args) == 2 and args[0] in {'publish', 'verify'})
        result = publish(Path(args[1])) if args[0] == 'publish' else parse_report(read(Path(args[1])))
        print(json.dumps(result))
        return 0
    except Exception:
        print('{"status":"FAIL"}')
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
