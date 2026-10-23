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
step provision scripts/amadis/launch.sh provision-react "$PORT"
export AM_ACCEPTANCE_URL=http://127.0.0.1:$PORT
export AM_ACCEPTANCE_OUTPUT=$OUT/react-flow.json
step browser python3 scripts/amadis/acceptance_server.py "$PORT" "$OUT/serve.log" node scripts/amadis/acceptance_react.cjs
# Publish success only AFTER the supervisor has confirmed whole-group cleanup.
python3 scripts/ci/check_react_browser_report.py publish "$OUT"
