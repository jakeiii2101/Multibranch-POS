# SniperPOS Web/PWA V1 Deployment Runbook

## Development / QA

The current GitHub Codespaces preview is:

`https://psychic-succotash-g4r7vr95prvq3vjr6-8080.app.github.dev/`

Laravel links should remain relative so the current HTTPS host is preserved. Do not hardcode localhost URLs in Blade templates.

Typical Codespaces refresh after pulling changes:

```bash
cd /workspaces/simple-pos-v1
git pull origin main
sudo service mysql start
composer install --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan test
php artisan serve --host=0.0.0.0 --port=8080
```

## Production prerequisites

Use a stable HTTPS domain before mobile packaging. Do not ship Android/iOS builds that point to the temporary Codespaces hostname.

SniperPOS production also requires a persistent, externally reachable MySQL 8 database. The MySQL server running inside Codespaces is development-only and must not be used as the Vercel production database.

Minimum production environment values:

```dotenv
APP_NAME="SniperPOS"
APP_ENV=production
APP_DEBUG=false
APP_KEY=<generated-production-key>
APP_URL=https://your-stable-domain.example
APP_TIMEZONE=Asia/Manila

DB_CONNECTION=mysql
DB_HOST=<remote-mysql-host>
DB_PORT=3306
DB_DATABASE=sniperpos
DB_USERNAME=<production-user>
DB_PASSWORD=<production-secret>

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

LOG_CHANNEL=stderr
LOG_LEVEL=info

VITE_APP_NAME="SniperPOS"
```

Generate the production `APP_KEY` once and store it only in the hosting environment:

```bash
php artisan key:generate --show
```

Never commit the generated key or production database credentials.

## Vercel container deployment

SniperPOS is a Laravel + Livewire application. Vite only builds the frontend assets, so production deployment must use the repository's `Dockerfile.vercel` container instead of deploying the project as a static Vite site.

The Vercel container uses:

- PHP 8.4
- FrankenPHP / Caddy
- Composer production dependencies
- Vite production assets
- `pdo_mysql`
- Vercel's runtime `PORT`

The container is stateless. Do not rely on local container storage for persistent uploads, backups, or evidence files. Persistent application data belongs in MySQL or an external object-storage service.

### Vercel project settings

Use the repository root (`./`) and the Container application preset. Vercel auto-detects `Dockerfile.vercel` from the repository root.

Add all production secrets through Vercel Environment Variables. Do not copy the Codespaces `.env` values directly because the Codespaces URL and `127.0.0.1` database host are development-only.

### First production database migration

After the Vercel project is configured with the production environment values, run migrations from a trusted workstation or Codespace using those production values:

```bash
php artisan migrate --force
```

If using the Vercel CLI, link the repository first and run the migration with the production environment injected into the command rather than writing secrets into a committed file.

Never run `php artisan migrate:fresh` against production.

### BIR readiness before live issuance

Before enabling live invoice issuance against the production database, run:

```bash
php artisan database:backup
php artisan bir:preflight --production
```

Complete `docs/BIR_FINAL_ACCEPTANCE.md`, retain the evidence pack, and obtain the applicable taxpayer/RDO approval before live invoice issuance. A successful technical preflight does not constitute BIR accreditation.

## Post-deploy smoke test

Confirm the following on the final HTTPS production URL:

1. Landing page and login load without mixed-content or redirect errors.
2. Admin and Cashier authentication works.
3. Dashboard local date/time is correct for `Asia/Manila`.
4. Products, Inventory, POS, Discounts, and Cash/GCash/Card/Other payments work.
5. One controlled test sale creates the correct receipt, stock movement, payment record, sales history, report totals, and audit log.
6. `/up` returns a successful health response.
7. `/manifest.webmanifest` and `/service-worker.js` are served over HTTPS.
8. Installed PWA opens in standalone mode and the offline fallback does not expose authenticated business data.

For the controlled sale, use a test product/account and reverse or otherwise reconcile the test transaction according to the site's operational procedure; do not directly edit completed financial records in the database.

## Production update sequence

For later releases, Git pushes rebuild and redeploy the Vercel container automatically. If a release contains database migrations, run the migrations deliberately against the production database after reviewing them:

```bash
php artisan migrate --force
```

Do not bake production secrets or Laravel config cache into the Docker image. Environment-specific values must remain runtime environment variables.

## Rollback principle

Application code may be rolled back to the previous known-good Vercel deployment, but do not automatically roll back destructive database migrations after live financial data exists. Restore from a verified backup only when a database rollback is genuinely required and the data impact has been assessed.
