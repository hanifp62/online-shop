#!/usr/bin/env bash
# UI-LOCAL-20261025. Run SERIAL after the integrator's build; never invokes a build.
# Same environment as acceptance_react.sh: AM_PLAYWRIGHT, AM_CHROMIUM.
# Usage: bash scripts/amadis/acceptance_ui_local.sh NEW_EVIDENCE_DIR [PORT=8044]
set -euo pipefail
cd "$(dirname "$0")/../.."
OUT=${1:?new evidence directory required}
PORT=${2:-8044}
[[ $# -le 2 && "$PORT" =~ ^[1-9][0-9]{3,4}$ ]] && (( PORT >= 1024 && PORT <= 65535 && PORT != 8000 && PORT != 8001 )) || { echo 'UI_LOCAL_ARGUMENTS_REJECTED' >&2; exit 2; }
[ ! -e "$OUT" ] && [ ! -L "$OUT" ] || { echo 'Refusing existing evidence directory.' >&2; exit 2; }
: "${AM_PLAYWRIGHT:?path to playwright package}" "${AM_CHROMIUM:?chromium executable}"
# Do not let Playwright API debugging log fill arguments, even to the step log.
unset DEBUG PWDEBUG NODE_DEBUG
umask 077
mkdir -p -- "$(dirname "$OUT")"
mkdir -- "$OUT"
OUT=$(cd "$OUT" && pwd -P)
export AM_ACCEPTANCE_URL="http://127.0.0.1:$PORT"
export AM_ACCEPTANCE_OUTPUT="$OUT/ui-local-browser.json"
printf 'step\tstart_utc\tend_utc\trc\n' > "$OUT/commands.tsv"
STAGE=init
finish() {
    local rc=$? published=0
    trap - EXIT
    # A zero browser exit alone is not acceptance: publish only after the supervisor
    # has returned and proved cleanup. Failed setup/cleanup still produces a FAIL report.
    node scripts/amadis/acceptance_ui_local.cjs --finalize "$OUT" "$STAGE" "$rc" || published=$?
    if [ "$rc" -ne 0 ]; then exit "$rc"; fi
    exit "$published"
}
trap finish EXIT
step() {
    local name=$1; shift
    local start end rc=0
    STAGE=$name
    start=$(date -u +%FT%TZ)
    "$@" > "$OUT/$name.log" 2>&1 || rc=$?
    end=$(date -u +%FT%TZ)
    printf '%s\t%s\t%s\t%s\n' "$name" "$start" "$end" "$rc" >> "$OUT/commands.tsv"
    return "$rc"
}
bounded_setup() {
    # Reuse the existing pinned-PID/group cleanup implementation, including setup
    # descendants. No bare timeout/killall can leave an orphaned migration process.
    python3 -B - "$@" <<'PY'
import signal
import subprocess
import sys
from scripts.amadis.acceptance_server import wait_command, stop_owned

process = None
code = 1

def interrupted(_signal, _frame):
    raise InterruptedError()

for sig in (signal.SIGINT, signal.SIGTERM):
    signal.signal(sig, interrupted)
try:
    process = subprocess.Popen(sys.argv[1:], stdin=subprocess.DEVNULL, start_new_session=True)
    result = wait_command(process, 120)
    code = result if result >= 0 else 1
except Exception:
    print('UI_LOCAL_SETUP_FAILED', file=sys.stderr)
finally:
    for sig in (signal.SIGINT, signal.SIGTERM):
        signal.signal(sig, signal.SIG_IGN)
    try:
        stop_owned(process)
    except Exception:
        print('UI_LOCAL_SETUP_CLEANUP_FAILED', file=sys.stderr)
        code = 1
sys.exit(code)
PY
}
# Always a fresh fixture. Inherited AMADIS_ROOT is deliberately not reused.
unset AMADIS_ROOT
step init bounded_setup scripts/amadis/launch.sh init
IFS= read -r AMADIS_ROOT < "$OUT/init.log"
export AMADIS_ROOT
printf '%s\n' "$AMADIS_ROOT" > "$OUT/root.txt"
step migrate bounded_setup scripts/amadis/launch.sh migrate
step seed bounded_setup scripts/amadis/launch.sh seed
step provision bounded_setup scripts/amadis/launch.sh provision-react "$PORT"
export AMADIS_UI_OPS_SUMMARY=1
step browser python3 -B scripts/amadis/acceptance_server.py "$PORT" "$OUT/serve.log" node scripts/amadis/acceptance_ui_local.cjs
STAGE=complete
