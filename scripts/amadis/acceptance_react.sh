#!/usr/bin/env bash
# Native React acceptance uses only a fresh guarded fixture and an owned server.
set -euo pipefail
cd "$(dirname "$0")/../.."
OUT=${1:?evidence dir required}
PORT=${2:-8043}
[ ! -e "$OUT" ] || { echo 'Refusing existing evidence directory.' >&2; exit 2; }
umask 077
mkdir -p "$OUT"
: "${AM_PLAYWRIGHT:?path to playwright package}" "${AM_CHROMIUM:?chromium executable}"
ROOT=$(scripts/amadis/launch.sh init)
export AMADIS_ROOT=$ROOT
printf '%s\n' "$ROOT" > "$OUT/root.txt"
printf 'step\tstart_utc\tend_utc\trc\n' > "$OUT/commands.tsv"
step() {
    local name=$1; shift
    local start end rc=0
    start=$(date -u +%FT%TZ)
    "$@" > "$OUT/$name.log" 2>&1 || rc=$?
    end=$(date -u +%FT%TZ)
    printf '%s\t%s\t%s\t%s\n' "$name" "$start" "$end" "$rc" >> "$OUT/commands.tsv"
    return "$rc"
}
step migrate scripts/amadis/launch.sh migrate
step seed scripts/amadis/launch.sh seed
step provision scripts/amadis/launch.sh provision
export AM_ACCEPTANCE_URL=http://127.0.0.1:$PORT
export AM_ACCEPTANCE_OUTPUT=$OUT/react-flow.json
step browser python3 scripts/amadis/acceptance_server.py "$PORT" "$OUT/serve.log" node scripts/amadis/acceptance_react.cjs
# Publish success only AFTER the supervisor has confirmed whole-group cleanup.
python3 - "$OUT" <<'PY'
import json
from pathlib import Path
import sys
root = Path(sys.argv[1])
result = json.loads((root / 'react-flow.json').read_text())
expected = ['catalog-native', 'catalog-spa-pagination-history', 'catalog-repeated-filters',
            'catalog-locale-head', 'catalog-dialog-cart', 'catalog-mobile', 'auth-native-post-errors',
            'auth-other-pages', 'catalog-delayed-chunk-draft', 'entry-failure-fallback',
            'no-js-and-explicit-rollback', 'admin-private-history',
            'home-native-mobile-keyboard-theme-reflow', 'home-native-search-category-locale',
            'pdp-native-inquiry-draft-cart-delta-undo', 'shop-guest-frozen-idempotent-replay',
            'shop-account-address-voucher-orders-favorites', 'dashboard-native-seven-regions-atomic-drafts',
            'dashboard-native-inline-consent-late-edit', 'dashboard-native-back-failure-recovery',
            'dashboard-native-order-json-once-refresh', 'dashboard-native-private-history',
            'account-native-update-export-logout', 'reset-native-flash-validation',
            'customer-totp-native-enrol-recovery', 'customer-totp-native-challenge',
            'admin-totp-native-enrol-recovery', 'admin-totp-native-challenge', 'console-clean']
optional = {'shop-account-completed-order-review', 'signed-unsubscribe-native-confirm-complete'}
assert set(result) == {'status', 'checks', 'consoleErrors', 'unavailable', 'expectedFaultCounts'}
assert result['status'] == 'PASS' and type(result['consoleErrors']) is int and result['consoleErrors'] == 0
checks, unavailable = result['checks'], result['unavailable']
assert isinstance(checks, list) and all(isinstance(name, str) for name in checks)
assert len(checks) == len(set(checks)) and [name for name in checks if name not in optional] == expected
assert isinstance(unavailable, list) and all(isinstance(name, str) for name in unavailable)
assert len(unavailable) == len(set(unavailable)) and set(unavailable) <= optional
assert all((name in checks) != (name in unavailable) for name in optional)
faults = result['expectedFaultCounts']
assert isinstance(faults, dict) and set(faults) == {'checkout-abort-requestfailed', 'checkout-abort-console', 'voucher-rejected-console', 'account-export-download'}
assert all(type(value) is int for value in faults.values())
assert faults['checkout-abort-requestfailed'] == 1
assert 0 <= faults['checkout-abort-console'] <= 1 and 0 <= faults['voucher-rejected-console'] <= 1
assert 0 <= faults['account-export-download'] <= 1
summary = {**result, 'supervisorExit': 0, 'cleanup': 'PASS'}
with (root / 'summary.json').open('x') as file:
    json.dump(summary, file, indent=2)
    file.write('\n')
print(json.dumps(summary))
PY
