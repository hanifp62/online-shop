#!/usr/bin/env bash
# Ukur indikator baseline ZAFIRA agar dapat dibandingkan sebelum/sesudah perbaikan.
# Pemakaian: bash scripts/benchmarks/capture_baseline.sh
# Hasil: snapshot bertanggal di docs/05-quality/baselines/ + salinan terkini di scripts/benchmarks/baseline_report.txt
set -uo pipefail

SNAPSHOT_DIR="docs/05-quality/baselines"
mkdir -p "$SNAPSHOT_DIR"
OUT="${BASELINE_OUT:-$SNAPSHOT_DIR/baseline_$(date -u +%Y-%m-%dT%H%MZ).txt}"
LATEST="scripts/benchmarks/baseline_report.txt"
NOW="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
GIT="$(git rev-parse --short HEAD 2>/dev/null || echo none)"
DIRTY="$(git status --porcelain 2>/dev/null | wc -l | tr -d ' ')"

routes=$(php artisan route:list --json 2>/dev/null | python3 -c "import json,sys;print(len(json.load(sys.stdin)))" 2>/dev/null)
migrations=$(ls database/migrations 2>/dev/null | wc -l | tr -d ' ')
php_files=$(find app -name '*.php' | wc -l | tr -d ' ')
views=$(find resources/views -name '*.blade.php' | wc -l | tr -d ' ')
test_files=$(find tests -name '*Test.php' | wc -l | tr -d ' ')
build_bytes=$(find public/build -type f -exec stat -f%z {} + 2>/dev/null | awk '{s+=$1} END {print s+0}')

rm -f /tmp/zafira-junit.xml
php -d memory_limit=1G vendor/bin/phpunit --log-junit /tmp/zafira-junit.xml >/dev/null 2>&1 || true
phpunit=$(python3 -c "import xml.etree.ElementTree as E;s=E.parse('/tmp/zafira-junit.xml').getroot().find('testsuite');print(f\"{s.get('tests')} tests, {s.get('assertions')} assertions, failures={s.get('failures')}, errors={s.get('errors')}, skipped={s.get('skipped')}, time={float(s.get('time')):.1f}s\")" 2>/dev/null || echo "gagal dibaca")
node_tests=$(node --test tests/*.test.mjs 2>&1 | grep -E '^ℹ (tests|pass|fail)' | tr '\n' ' ')

cat > "$OUT" <<EOF
ZAFIRA Baseline Report
Generated: $NOW
Commit: $GIT (uncommitted files: $DIRTY)

# Structural
routes: $routes
migrations: $migrations
php_files: $php_files
views: $views
test_files: $test_files
build_assets_bytes: $build_bytes

# Quality
phpunit: $phpunit
node: $node_tests
EOF

cp "$OUT" "$LATEST"
echo "Wrote $OUT"
cat "$OUT"
