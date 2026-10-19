#!/usr/bin/env bash
# AM-01/02/06/10 acceptance on a NEW isolated fixture. Proposed mapping, not an official AMADIS rubric.
# Usage: scripts/amadis/acceptance_isolated.sh EVIDENCE_DIR [PORT]
set -u
cd "$(dirname "$0")/../.."
OUT=${1:?evidence dir required}
PORT=${2:-8042}
[ -e "$OUT" ] && { echo "refusing to overwrite $OUT" >&2; exit 2; }
mkdir -p "$OUT"
: "${AM_PLAYWRIGHT:?path to playwright package}" "${AM_CHROMIUM:?chromium executable}"
LOG="$OUT/commands.tsv"
printf 'step\tstart_utc\tend_utc\trc\tcommand\n' > "$LOG"
step() { # step NAME CMD... ; stdout+stderr to $OUT/NAME.log
  local name=$1; shift
  local start; start=$(date -u +%FT%TZ)
  "$@" > "$OUT/$name.log" 2>&1; local rc=$?
  printf '%s\t%s\t%s\t%s\t%s\n' "$name" "$start" "$(date -u +%FT%TZ)" "$rc" "$*" >> "$LOG"
  return $rc
}

ROOT=$(scripts/amadis/launch.sh init) || exit 1
export AMADIS_ROOT=$ROOT
echo "$ROOT" > "$OUT/root.txt"
step migrate scripts/amadis/launch.sh migrate || exit 1
step seed scripts/amadis/launch.sh seed || exit 1
step provision scripts/amadis/launch.sh provision || exit 1
DB="file:$ROOT/database.sqlite?mode=ro"

# AM-01: read-only queries on the seeded fixture.
step am01 sqlite3 "$DB" "select 'products', count(*), sum(is_active) from products;
select 'category', c.cat_title, count(p.product_id), sum(p.is_active) from categories c left join products p on p.product_cat=c.cat_id group by c.cat_id order by c.cat_id;
select 'stock37', product_id, stock from products where product_slug='amadis-demo-stock-37';"
AM01=$(sqlite3 "$DB" "select (select count(*) from products where is_active=1)=76
  and (select count(*) from categories)=4
  and not exists(select 1 from categories c where (select count(*) from products p where p.product_cat=c.cat_id and p.is_active=1)<>19)
  and (select stock from products where product_slug='amadis-demo-stock-37')=37;")

# Escaping half of AM-06 is server-side; covered by the existing feature test (in-memory DB).
step am06_escape_phpunit scripts/amadis/launch.sh test --filter=AmadisCatalogTest

scripts/amadis/launch.sh serve "$PORT" 4 > "$OUT/serve.log" 2>&1 &
SERVER=$!
trap 'pkill -P $SERVER 2>/dev/null; kill $SERVER 2>/dev/null; pkill -f "php -S 127.0.0.1:$PORT" 2>/dev/null' EXIT
for _ in $(seq 1 30); do curl -fs -o /dev/null "http://127.0.0.1:$PORT/koleksi" && break; sleep 0.5; done

AM_ACCEPTANCE_URL=http://127.0.0.1:$PORT AM_ACCEPTANCE_OUTPUT="$OUT/browser-results.json" \
  step browser node scripts/amadis/acceptance_isolated.cjs
BROWSER_RC=$?

# AM-10 invariants: units sold <= 37, stock >= 0, stock + net sold = 37.
step am10_db sqlite3 "$DB" "select 'stock', stock from products where product_slug='amadis-demo-stock-37';
select 'order', o.id, o.status, i.quantity from orders o join order_items i on i.order_id=o.id join products p on p.product_id=i.product_id where p.product_slug='amadis-demo-stock-37' order by o.id;"
AM10=$(sqlite3 "$DB" "with s as (select product_id, stock from products where product_slug='amadis-demo-stock-37'),
  sold as (select coalesce(sum(i.quantity),0) q from order_items i join orders o on o.id=i.order_id, s where i.product_id=s.product_id and o.status<>'cancelled')
  select (select q from sold)<=37 and (select stock from s)>=0 and (select stock from s)+(select q from sold)=37 from s;")

printf '{"AM-01_db_invariant": %s, "AM-10_db_invariant": %s, "browser_rc": %s}\n' \
  "${AM01:-0}" "${AM10:-0}" "$BROWSER_RC" > "$OUT/invariants.json"
cp "$ROOT/identity.json" "$OUT/fixture-identity.json"
(cd "$OUT" && shasum -a 256 $(ls | grep -v '^SHA256SUMS$') > SHA256SUMS)
[ "${AM01:-0}" = 1 ] && [ "${AM10:-0}" = 1 ] && [ "$BROWSER_RC" = 0 ]
