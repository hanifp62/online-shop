#!/usr/bin/env bash
# AM-01/02/06/10 acceptance on a NEW isolated fixture. Proposed mapping, not an official AMADIS rubric.
# Usage: scripts/amadis/acceptance_isolated.sh EVIDENCE_DIR [PORT]
set -u
cd "$(dirname "$0")/../.." || exit 1
OUT=${1:?evidence dir required}
PORT=${2:-8042}
[ -e "$OUT" ] && { echo "refusing to overwrite $OUT" >&2; exit 2; }
mkdir -p "$OUT" || exit 1
: "${AM_PLAYWRIGHT:?path to playwright package}" "${AM_CHROMIUM:?chromium executable}"
LOG="$OUT/commands.tsv"
printf 'step\tstart_utc\tend_utc\trc\tcommand\n' > "$LOG" || exit 1
step() { # step NAME CMD... ; stdout+stderr to $OUT/NAME.log
  local name=$1; shift
  local start end; start=$(date -u +%FT%TZ) || return 1
  "$@" > "$OUT/$name.log" 2>&1; local rc=$?
  end=$(date -u +%FT%TZ) || return 1
  printf '%s\t%s\t%s\t%s\t%s\n' "$name" "$start" "$end" "$rc" "$*" >> "$LOG" || return 1
  return $rc
}

ROOT=$(scripts/amadis/launch.sh init) || exit 1
export AMADIS_ROOT=$ROOT
printf '%s\n' "$ROOT" > "$OUT/root.txt" || exit 1
step migrate scripts/amadis/launch.sh migrate || exit 1
step seed scripts/amadis/launch.sh seed || exit 1
step provision scripts/amadis/launch.sh provision || exit 1
DB="file:$ROOT/database.sqlite?mode=ro"

# AM-01: read-only queries on the seeded fixture.
step am01 sqlite3 "$DB" "select 'products', count(*), sum(is_active) from products;
select 'category', c.cat_title, count(p.product_id), sum(p.is_active) from categories c left join products p on p.product_cat=c.cat_id group by c.cat_id order by c.cat_id;
select 'stock37', product_id, stock from products where product_slug='amadis-demo-stock-37';" || exit 1
AM01=$(sqlite3 "$DB" "select (select count(*) from products where is_active=1)=76
  and (select count(*) from categories)=4
  and not exists(select 1 from categories c where (select count(*) from products p where p.product_cat=c.cat_id and p.is_active=1)<>19)
  and (select stock from products where product_slug='amadis-demo-stock-37')=37;") || exit 1

# Escaping half of AM-06 is server-side; covered by the existing feature test (in-memory DB).
step am06_escape_phpunit scripts/amadis/launch.sh test --filter=AmadisCatalogTest
ESCAPE_RC=$?

AM_ACCEPTANCE_URL=http://127.0.0.1:$PORT AM_ACCEPTANCE_OUTPUT="$OUT/browser-results.json" \
  step browser python3 scripts/amadis/acceptance_server.py "$PORT" "$OUT/serve.log" node scripts/amadis/acceptance_isolated.cjs
BROWSER_RC=$?

# AM-10 invariants: units sold <= 37, stock >= 0, stock + net sold = 37.
step am10_db sqlite3 "$DB" "select 'stock', stock from products where product_slug='amadis-demo-stock-37';
select 'order', o.id, o.status, i.quantity from orders o join order_items i on i.order_id=o.id join products p on p.product_id=i.product_id where p.product_slug='amadis-demo-stock-37' order by o.id;" || exit 1
AM10=$(sqlite3 "$DB" "with s as (select product_id, stock from products where product_slug='amadis-demo-stock-37'),
  sold as (select coalesce(sum(i.quantity),0) q from order_items i join orders o on o.id=i.order_id, s where i.product_id=s.product_id and o.status<>'cancelled')
  select (select q from sold)<=37 and (select stock from s)>=0 and (select stock from s)+(select q from sold)=37 from s;") || exit 1

printf '{"AM-01_db_invariant": %s, "AM-10_db_invariant": %s, "browser_rc": %s, "escape_rc": %s}\n' \
  "${AM01:-0}" "${AM10:-0}" "$BROWSER_RC" "$ESCAPE_RC" > "$OUT/invariants.json" || exit 1
cp "$ROOT/identity.json" "$OUT/fixture-identity.json" || exit 1
(
  cd "$OUT" || exit 1
  files=()
  for file in *; do
    if [ "$file" != SHA256SUMS ]; then files+=("$file"); fi
  done
  shasum -a 256 -- "${files[@]}" > SHA256SUMS
) || exit 1
[ "${AM01:-0}" = 1 ] && [ "${AM10:-0}" = 1 ] && [ "$BROWSER_RC" = 0 ] && [ "$ESCAPE_RC" = 0 ]
