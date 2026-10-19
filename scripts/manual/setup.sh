#!/bin/bash
set -euo pipefail
umask 077
repo=$(cd "$(dirname "$0")/../.." && pwd -P)
base=/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode
root=${MANUAL_ROOT:-}
if [[ -z "$root" || "$root" != "$base"/zafira-manual-* ]]; then
  echo 'MANUAL_ROOT must name a new isolated path under the approved opencode temp directory.' >&2
  exit 2
fi
[[ ! -e "$root" ]] || { echo 'Refusing existing root; choose a new path.' >&2; exit 2; }
port=${MANUAL_PORT:-8017}
python3 "$repo/scripts/manual/preflight.py" setup
mkdir -p "$base"
mkdir "$root"
rsync -a --exclude='.git/' --exclude='.env' --exclude='.env.*' --exclude='storage/' --exclude='database/*.sqlite*' --exclude='*.sql' --exclude='private-runtime/' --exclude='bootstrap/cache/' --exclude='vendor/' --exclude='node_modules/' --exclude='.agents/' --exclude='.claude/' --exclude='.codex/' --exclude='.superpowers/' --exclude='scratch/' --exclude='docs/' --exclude='.playwright-cli/' --exclude='.phpunit.result.cache' --exclude='public/build/' --exclude='public/hot' "$repo/" "$root/"
# Materialize only known public links; no runtime path may point back into the repo.
for asset in app.css app.js; do
  [[ -L "$root/public/$asset" ]] || { echo 'Expected public asset link missing.' >&2; exit 2; }
  rm "$root/public/$asset"
  cp "$repo/prototype/$asset" "$root/public/$asset"
done
[[ -L "$root/public/prototype" ]] || { echo 'Expected prototype link missing.' >&2; exit 2; }
rm "$root/public/prototype"
cp -R "$repo/prototype" "$root/public/prototype"
cp -R "$repo/vendor" "$root/vendor"
# Build Vite assets from the copied (possibly uncommitted) source, never the repo's public/build.
cp -R "$repo/node_modules" "$root/node_modules"
(cd "$root" && npm run build --silent >/dev/null)
rm -rf "$root/node_modules"
[[ -f "$root/public/build/manifest.json" && ! -e "$root/public/hot" ]] || { echo 'Vite build manifest missing.' >&2; exit 2; }
# Runtime checkout deliberately has no shared dependency or storage symlinks.
mkdir -p "$root"/{database,storage/framework/cache/data,storage/framework/sessions,storage/framework/views,storage/logs,bootstrap/cache,private-runtime,public/manual}
openssl rand -hex 32 > "$root/public/manual/identity.txt"
: > "$root/database.sqlite"
key="base64:$(openssl rand -base64 32)"
cat > "$root/.env" <<ENV
APP_NAME=ZAFIRA-MANUAL
APP_ENV=local
APP_KEY=$key
APP_DEBUG=false
APP_URL=http://127.0.0.1:$port
LOG_CHANNEL=single
LOG_LEVEL=warning
DB_CONNECTION=sqlite
DB_DATABASE=$root/database.sqlite
DB_URL=
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
MAIL_MAILER=log
FILESYSTEM_DISK=local
LARAVEL_STORAGE_PATH=$root/storage
APP_CONFIG_CACHE=$root/bootstrap/cache/config.php
APP_SERVICES_CACHE=$root/bootstrap/cache/services.php
APP_PACKAGES_CACHE=$root/bootstrap/cache/packages.php
APP_ROUTES_CACHE=$root/bootstrap/cache/routes.php
APP_EVENTS_CACHE=$root/bootstrap/cache/events.php
VIEW_COMPILED_PATH=$root/storage/framework/views
ENV
chmod 600 "$root/.env"
printf '{"root":"%s","database":"%s"}\n' "$root" "$root/database.sqlite" > "$root/identity.json"
chmod 600 "$root/identity.json"
(cd "$root" && MANUAL_ROOT="$root" python3 scripts/manual/preflight.py migrate && php artisan migrate --force)
python3 - "$root" <<'PY'
import hashlib, json, pathlib, sys
cache = pathlib.Path(sys.argv[1]) / 'bootstrap/cache'
hashes = {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in cache.iterdir() if p.is_file()}
assert all(name in ('services.php', 'packages.php') for name in hashes)
(cache.parent.parent / 'private-runtime/cache-hashes.json').write_text(json.dumps(hashes))
PY
# Fingerprint copied source files, then prove the live repo has identical bytes for the same paths.
manifest=$root/private-runtime/source-manifest.sha256
(cd "$root" && find . -type f ! -path './vendor/*' ! -path './storage/*' ! -path './bootstrap/cache/*' ! -path './private-runtime/*' ! -path './public/manual/*' ! -path './public/build/*' ! -name '.env' ! -name 'database.sqlite' ! -name 'identity.json' -print0 | LC_ALL=C sort -z | xargs -0 shasum -a 256) > "$manifest"
# Other agents may edit concurrently: record drift instead of hiding it; refresh with a new root.
(cd "$repo" && shasum -a 256 -c "$manifest" 2>/dev/null | grep -v ': OK$' || true) > "$root/private-runtime/source-drift.txt"
shasum -a 256 "$manifest" | awk '{print $1}' > "$root/private-runtime/source-fingerprint.txt"
wc -l < "$manifest" | tr -d ' ' > "$root/private-runtime/source-file-count.txt"
printf 'Isolated checkout ready: %s\n' "$root"
