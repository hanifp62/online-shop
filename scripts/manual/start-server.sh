#!/bin/bash
set -euo pipefail
repo=$(cd "$(dirname "$0")/../.." && pwd -P)
root=${MANUAL_ROOT:?Set MANUAL_ROOT to isolated checkout}
[[ "$(pwd -P)" == "$root" ]] || { echo 'Start from the isolated checkout root.' >&2; exit 2; }
port=${MANUAL_PORT:-8017}
python3 "$repo/scripts/manual/preflight.py" serve
# Native Laravel command, flags visible on the process command line.
php artisan serve --host=127.0.0.1 --port="$port"
