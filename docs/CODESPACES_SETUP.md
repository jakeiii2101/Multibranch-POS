# Test the original SniperPOS in GitHub Codespaces

This is the single POS baseline. The runtime is PHP 8.4, Laravel 13, Livewire 3/Volt, MySQL 8.4, Node 22 and Vite. No branch sales or inventory migration is part of this setup.

## Start a fresh development environment

1. Create a **new** Codespace on this repository's `main` branch after this setup PR is merged. Codespaces will detect `.devcontainer/devcontainer.json`. Or check out the PR branch in an existing Codespace and select **Codespaces: Rebuild Container** from the command palette. A rebuild replaces the container; back up any data and your private `.env` before doing it. The named MySQL volume survives a normal rebuild within this Codespace, but a deleted Codespace is not a production backup.
2. Wait for `postCreateCommand` to finish. It installs Composer dependencies, Node dependencies, assets, creates `.env` only when absent, generates a key only when missing, and runs normal migrations. It does not seed an account or import legacy stock.
3. In the Codespaces terminal, verify the exact runtime and database. Do not paste `.env`, credentials or generated keys into an issue:

   ```bash
   php -v
   php -m | grep -i '^pdo_mysql$'
   mysql -h mysql -u simple_pos_user -p simple_pos -e 'SELECT 1;'
   php artisan migrate:status
   npm run build
   php artisan test --compact
   ```

   The password for the isolated development database is `codespaces_pos_local_only`. These credentials are only for this devcontainer's internal MySQL service. Choose separate private secrets for every nonlocal environment.
4. Start the application:

   ```bash
   php artisan serve --host=0.0.0.0 --port=8080
   ```

   Open port 8080 from the Codespaces **Ports** tab. Keep the port private. The `.env` uses `APP_ENV=local` and `APP_DEBUG=true`, which are for development only. Expect `/` and `/login` to load; `/up` checks the app process. The UI is not evidence of a working sale until an admin, product, invoice configuration and test transaction are set up.

The sample `APP_URL` is `http://localhost:8080`; Laravel trusts the forwarded HTTPS host in Codespaces. Replace `APP_URL` with the actual forwarded URL if URL generation outside an HTTP request (for example, queued mail) is used. Do not copy an old Codespace hostname into the repo.

## Existing Codespace or database

If an existing `.env` is present, setup preserves it and stops before migration when `DB_HOST` is not `mysql`. Review and edit that file to point at this devcontainer's `mysql` service, then run `php artisan config:clear` and `php artisan migrate`. Do not regenerate a nonempty `APP_KEY`: changing it can invalidate encrypted data and sessions.

A database from a previous Codespace is not automatically moved into the new MySQL volume. Do not run `migrate:fresh`, import data, or seed the default `Test User` into a populated database. Back up the old database and rehearse its import separately if you need historical records.

If any step fails, capture the failing command and its error, plus the newest relevant lines in `storage/logs/laravel.log`, after removing secrets. `could not find driver` means the active PHP lacks `pdo_mysql`; `Connection refused` means MySQL is down or the host is wrong; `Access denied` means the user/password or grants do not match; `No application encryption key` means `.env` or cached config has no key.

## Baseline gate before branch work

Run the existing full suite and frontend build in the Codespace. GitHub Actions also checks a clean SQLite migration/test run and a clean MySQL migration/test run, and builds the development image with `pdo_mysql`. Then use local test records to verify login, catalog, legacy POS checkout, receipt, inventory movement, and report totals. Do not process real sales from this development Codespace. Branch work begins only after the baseline is repeatable and its failures are recorded.
