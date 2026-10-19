#!/bin/zsh
# Isolated ZAFIRA QA server: all mutable Laravel state in temp storage. No .env edits.
QA=/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-qa
REPO=/Users/hanifpriyambodo/Documents/online-shop
cd "$REPO/public" || exit 1
exec env APP_ENV=local DB_CONNECTION=sqlite DB_DATABASE="$QA/db.sqlite" \
  SESSION_DRIVER=file CACHE_STORE=array LARAVEL_STORAGE_PATH="$QA/storage" \
  /opt/homebrew/bin/php -S 127.0.0.1:8015 "$QA/run/router-isolated.php"
