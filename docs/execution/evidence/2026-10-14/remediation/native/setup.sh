#!/bin/bash
set -euo pipefail
repo=$(cd "$(dirname "$0")/../.." && pwd -P)
base=/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode
root=${MANUAL_ROOT:-}
if [[ -z "$root" || "$root" != "$base"/zafira-manual-* ]]; then
  echo 'MANUAL_ROOT must name a new isolated path under the approved opencode temp directory.' >&2
  exit 2
fi
[[ ! -e "$root" ]] || { echo 'Refusing existing root; choose a new path.' >&2; exit 2; }
python3 "$repo/scripts/manual/preflight.py"
mkdir -p "$base"
mkdir "$root"
rsync -a --exclude='.git/' --exclude='.env' --exclude='.env.*' --exclude='storage/' --exclude='database/*.sqlite*' --exclude='*.sql' --exclude='private-runtime/' --exclude='bootstrap/cache/' --exclude='vendor/' --exclude='node_modules/' --exclude='.agents/' --exclude='.claude/' --exclude='.codex/' --exclude='.superpowers/' --exclude='scratch/' --exclude='docs/' --exclude='.playwright-cli/' --exclude='.phpunit.result.cache' "$repo/" "$root/"
cp -R "$repo/vendor" "$root/vendor"
[[ ! -d "$repo/node_modules" ]] || ln -s "$repo/node_modules" "$root/node_modules"
mkdir -p "$root"/{database,storage/framework/cache/data,storage/framework/sessions,storage/framework/views,storage/logs,bootstrap/cache,private-runtime}
: > "$root/database.sqlite"
key=$(cd "$root" && env -i PATH="$PATH" HOME="$HOME" php artisan key:generate --show)
cat > "$root/.env" <<ENV
APP_NAME=ZAFIRA-MANUAL
APP_ENV=local
APP_KEY=$key
APP_DEBUG=false
APP_URL=http://127.0.0.1:8017
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
(cd "$root" && env -i PATH="$PATH" HOME="$HOME" APP_ENV=local APP_URL=http://127.0.0.1:8017 DB_CONNECTION=sqlite DB_DATABASE="$root/database.sqlite" DB_URL= LARAVEL_STORAGE_PATH="$root/storage" APP_CONFIG_CACHE="$root/bootstrap/cache/config.php" APP_SERVICES_CACHE="$root/bootstrap/cache/services.php" APP_PACKAGES_CACHE="$root/bootstrap/cache/packages.php" APP_ROUTES_CACHE="$root/bootstrap/cache/routes.php" APP_EVENTS_CACHE="$root/bootstrap/cache/events.php" VIEW_COMPILED_PATH="$root/storage/framework/views" php artisan migrate --force)
# Fingerprint copied source files, then prove the live repo has identical bytes for the same paths.
manifest=$root/private-runtime/source-manifest.sha256
(cd "$root" && find . -type f ! -path './vendor/*' ! -path './storage/*' ! -path './bootstrap/cache/*' ! -path './private-runtime/*' ! -name '.env' ! -name 'database.sqlite' ! -name 'identity.json' -print0 | LC_ALL=C sort -z | xargs -0 shasum -a 256) > "$manifest"
# Other agents may edit concurrently: record drift instead of hiding it; refresh with a new root.
(cd "$repo" && shasum -a 256 -c "$manifest" 2>/dev/null | grep -v ': OK$' || true) > "$root/private-runtime/source-drift.txt"
shasum -a 256 "$manifest" | awk '{print $1}' > "$root/private-runtime/source-fingerprint.txt"
wc -l < "$manifest" | tr -d ' ' > "$root/private-runtime/source-file-count.txt"
printf 'Isolated checkout ready: %s\n' "$root"
