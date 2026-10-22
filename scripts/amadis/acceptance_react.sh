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
            'no-js-and-explicit-rollback', 'admin-private-history', 'account-native-update-export-logout',
                        'reset-native-flash-validation', 'customer-totp-native-challenge', 'admin-totp-native-challenge', 'console-clean']
assert result == {'status': 'PASS', 'checks': expected, 'consoleErrors': 0}, 'Native acceptance is incomplete'
summary = {**result, 'supervisorExit': 0, 'cleanup': 'PASS'}
with (root / 'summary.json').open('x') as file:
    json.dump(summary, file, indent=2)
    file.write('\n')
print(json.dumps(summary))
PY
