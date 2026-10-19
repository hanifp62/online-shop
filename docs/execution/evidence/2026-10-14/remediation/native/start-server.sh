#!/bin/bash
set -euo pipefail
repo=$(cd "$(dirname "$0")/../.." && pwd -P)
python3 "$repo/scripts/manual/preflight.py" serve
root=${MANUAL_ROOT:?Set MANUAL_ROOT to isolated checkout}
[[ "$(pwd -P)" == "$root" ]] || { echo 'Start from the isolated checkout root.' >&2; exit 2; }
# This is the native Laravel command. Values remain visible and explicit in shell history/process command line.
php artisan serve --host=127.0.0.1 --port=8017
