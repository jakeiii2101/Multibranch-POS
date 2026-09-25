#!/usr/bin/env bash
set -euo pipefail

cd /workspaces/Multibranch-POS

php -r 'if (! extension_loaded("pdo_mysql")) {fwrite(STDERR, "pdo_mysql is missing from the active PHP.\n"); exit(1);}'
composer install --no-interaction --prefer-dist --no-progress
npm ci
npm run build

if [[ ! -f .env ]]; then
    cp .env.codespaces.example .env
fi

if ! grep -qx 'DB_HOST=mysql' .env; then
    echo 'Existing .env does not target the Codespaces MySQL service. Check DB_HOST, DB_DATABASE, DB_USERNAME and DB_PASSWORD; then run php artisan migrate.' >&2
    exit 1
fi

php artisan config:clear
if ! grep -Eq '^APP_KEY=.+$' .env; then
    php artisan key:generate --no-interaction
fi

php artisan migrate --no-interaction

echo 'SniperPOS baseline ready. Run: php artisan serve --host=0.0.0.0 --port=8080'
