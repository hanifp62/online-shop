#!/bin/bash
# Preflight precedes the native Artisan process that runs the fixture.
set -euo pipefail
umask 077
repo=$(cd "$(dirname "$0")/../.." && pwd -P)
root=${MANUAL_ROOT:?Set MANUAL_ROOT to isolated checkout}
[[ "$(pwd -P)" == "$root" ]] || { echo 'Run from the isolated checkout root.' >&2; exit 2; }
python3 "$repo/scripts/manual/preflight.py" fixture
output=$(mktemp "$root/private-runtime/fixture-output.XXXXXX")
trap 'rm -f "$output"' EXIT
# Tinker may print an exception yet exit 0, so success requires the exact final marker line.
status=0
php artisan tinker --execute='require base_path("scripts/manual/fixture.php");' >"$output" 2>&1 || status=$?
if [[ $status -ne 0 ]] || ! grep -Fqx 'Synthetic fixture ready; existing orders and inventory untouched.' "$output"; then
  # Only our own refusal messages are shown; they never contain credential values.
  grep -o 'Manual fixture refused: [^.]*\.' "$output" | head -1 >&2 || true
  echo "Fixture FAILED (artisan status $status); see refusal above." >&2
  exit 1
fi
cat "$output"
