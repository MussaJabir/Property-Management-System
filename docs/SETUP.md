# PMS — Local Dev Setup

Step-by-step to get PMS running on Ubuntu locally. Mirrors the production stack so cutover is just env swap + deploy.

> Target OS: Ubuntu 24.04 (Dutch's local machine). PHP via `ondrej/php` PPA. Caddy as local proxy so `pms.test/{tenant}/…` works.

---

## 1. Prerequisites

```bash
# PHP 8.4 (via ondrej PPA)
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.4 php8.4-fpm php8.4-cli php8.4-common \
  php8.4-pgsql php8.4-zip php8.4-gd php8.4-mbstring php8.4-curl \
  php8.4-xml php8.4-bcmath php8.4-intl php8.4-redis php8.4-pcov

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# PostgreSQL 16
sudo apt install -y postgresql-16 postgresql-contrib-16
sudo systemctl enable --now postgresql

# Redis 7
sudo apt install -y redis-server
sudo systemctl enable --now redis-server

# Node 22 (for Vite + Tailwind build)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

# Caddy (local reverse proxy for tenant URL testing)
sudo apt install -y debian-keyring debian-archive-keyring apt-transport-https
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | sudo tee /etc/apt/sources.list.d/caddy-stable.list
sudo apt update && sudo apt install -y caddy

# Meilisearch (optional for v1 — install when search lands)
curl -L https://install.meilisearch.com | sh
sudo mv ./meilisearch /usr/local/bin/

# verify
php -v             # expect 8.4.x
psql --version     # expect 16.x
redis-cli ping     # expect PONG
node -v            # expect 22.x
caddy version
```

---

## 2. Database setup

```bash
sudo -u postgres psql <<'SQL'
CREATE USER pms_dev WITH PASSWORD 'change_me_locally';
CREATE DATABASE pms_local OWNER pms_dev;
GRANT ALL PRIVILEGES ON DATABASE pms_local TO pms_dev;
\c pms_local
GRANT ALL ON SCHEMA public TO pms_dev;
SQL
```

Test:
```bash
psql -U pms_dev -h 127.0.0.1 -d pms_local -c '\dt'
```

---

## 3. Backblaze B2 setup (do this before first file upload)

1. Sign up at https://www.backblaze.com/cloud-storage
2. Create two buckets: `pms-dev-uploads` (dev), `pms-prod-uploads` (prod). Mark them **Private** (not public).
3. Create an Application Key scoped to the dev bucket. Save: `keyID`, `applicationKey`, `endpoint`, `region`.
4. The B2 S3-compatible endpoint will look like `https://s3.us-west-002.backblazeb2.com` (region varies).
5. Plug into `.env` (next section).

---

## 4. Laravel project init

```bash
cd "/home/j4bir/Dev/BJP/Projects/Systems/Property Management System/PMS"

# Scaffold Laravel into ./app
composer create-project laravel/laravel:^12.0 app
cd app
```

### Install required packages

```bash
# Core SaaS framework packages
composer require filament/filament:"^4.0"
composer require stancl/tenancy
composer require livewire/livewire:"^3.6"
composer require livewire/flux
composer require laravel/sanctum
composer require laravel/horizon
composer require laravel/pulse
composer require laravel/scout

# Spatie packages
composer require spatie/laravel-permission
composer require spatie/laravel-activitylog
composer require spatie/laravel-medialibrary
composer require spatie/browsershot

# Storage + utility
composer require league/flysystem-aws-s3-v3
composer require maatwebsite/excel
composer require cknow/laravel-money
composer require propaganistas/laravel-phone
composer require intervention/image
composer require meilisearch/meilisearch-php

# Dev tooling
composer require --dev pestphp/pest
composer require --dev pestphp/pest-plugin-laravel
composer require --dev larastan/larastan
composer require --dev laravel/pint
composer require --dev laravel/telescope
```

> If `livewire/flux` requires registration, follow the Flux UI install docs at https://fluxui.dev. Flux's free tier covers all components needed for v1.

### Init Pest

```bash
php artisan pest:install
```

---

## 5. `.env` configuration (local)

Edit `app/.env`:

```ini
APP_NAME=PMS
APP_ENV=local
APP_DEBUG=true
APP_URL=http://pms.test
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_TIMEZONE="Africa/Dar_es_Salaam"

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pms_local
DB_USERNAME=pms_dev
DB_PASSWORD=change_me_locally

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_CLIENT=phpredis

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=null

# B2 as default disk — no local storage!
FILESYSTEM_DISK=b2
B2_KEY=your_b2_key_id
B2_SECRET=your_b2_application_key
B2_BUCKET=pms-dev-uploads
B2_REGION=us-west-002
B2_ENDPOINT=https://s3.us-west-002.backblazeb2.com
B2_URL=https://pms-dev-uploads.s3.us-west-002.backblazeb2.com

MAIL_MAILER=log              # use Resend in staging/prod
RESEND_API_KEY=

SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=

SENTRY_LARAVEL_DSN=
```

Add B2 disk to `config/filesystems.php`:

```php
'b2' => [
    'driver' => 's3',
    'key' => env('B2_KEY'),
    'secret' => env('B2_SECRET'),
    'region' => env('B2_REGION'),
    'bucket' => env('B2_BUCKET'),
    'endpoint' => env('B2_ENDPOINT'),
    'url' => env('B2_URL'),
    'use_path_style_endpoint' => true,
    'throw' => true,
],
```

---

## 6. Local Caddy setup for tenant URL testing

Add to `/etc/hosts`:

```
127.0.0.1   pms.test
```

Local Caddyfile at `/etc/caddy/Caddyfile` (or `~/Caddyfile` if running unprivileged):

```caddy
pms.test {
    reverse_proxy 127.0.0.1:8000
    encode zstd gzip
}
```

Run Laravel + Caddy:

```bash
# Terminal 1
cd app
php artisan serve --port=8000

# Terminal 2
sudo systemctl reload caddy
```

Visit `http://pms.test` — should hit Laravel.

> Caddy will try to auto-issue TLS for `pms.test`. Either accept the local self-signed flow or prefix with `http://` in the Caddyfile to skip TLS locally: `http://pms.test { … }`.

---

## 7. First-run scaffolding

```bash
cd app

php artisan key:generate

# Publish + run tenancy migrations
php artisan vendor:publish --provider="Stancl\Tenancy\TenancyServiceProvider"
# (Review generated files — we customize for path-based mode)

# Sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Spatie Permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

# Activity Log
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider"

# Media Library
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"

# Horizon
php artisan horizon:install

# Pulse
php artisan pulse:install

# Filament panels
php artisan filament:install --panels
# Creates app/Providers/Filament/AdminPanelProvider.php — rename + add OperatorPanelProvider

# Run all migrations
php artisan migrate
```

---

## 8. Verify everything works

```bash
# Lint
vendor/bin/pint

# Static analysis
vendor/bin/phpstan analyse

# Tests
vendor/bin/pest

# Queue worker
php artisan horizon          # full dashboard at /horizon

# Or simpler queue
php artisan queue:work --queue=critical,default,low

# Vite dev server (for Filament/Livewire assets)
npm run dev
```

---

## 9. Common dev commands

```bash
# Create a tenant for local testing (after Phase 1 lands)
php artisan tinker
>>> \App\Models\Tenant::create(['slug' => 'demo', 'name' => 'Demo Properties'])

# Visit
# http://pms.test/admin              ← super admin
# http://pms.test/demo               ← tenant public landing
# http://pms.test/demo/manage        ← tenant operator panel
# http://pms.test/demo/portal        ← renter portal

# Reset & reseed
php artisan migrate:fresh --seed

# Telescope (dev debugging)
# http://pms.test/telescope

# Pulse
# http://pms.test/pulse

# Horizon
# http://pms.test/horizon
```

---

## 10. Gotchas & tips

- **Always run `php artisan migrate --pretend` before merging a migration.** Catches syntax errors without touching DB.
- **`stancl/tenancy` path mode** needs a tenant-resolver middleware. We'll write a custom resolver in Phase 1 that reads `$request->segment(1)` and resolves to `tenants.slug`.
- **B2 is the default disk from day one.** Resist temptation to switch to `local` in dev — keep behaviour identical to prod.
- **Filament asset publishing**: after each `composer update`, run `php artisan filament:upgrade`.
- **PHP-FPM not needed locally** — `php artisan serve` is sufficient. Caddy proxies to it.
- **Postgres `uuid_generate_v7()` doesn't exist natively yet.** Use Laravel's `Str::uuid7()` or the package's UUID helper. The `HasUuids` trait will be customized for v7.
- **Spatie Permission with multi-tenancy**: use Spatie's "teams" feature where `team_id` = `tenant_id`. Config flag: `'teams' => true`.
- **Browsershot needs Chrome/Chromium + Node**: `sudo apt install -y chromium-browser` and `npm install puppeteer` in the app folder.
- **Activity log polymorphic**: tag with `tenant_id` via a custom log subject resolver.

---

## 11. Pre-deploy checklist (for when we ship)

- [ ] All migrations re-run cleanly on a fresh DB (`migrate:fresh`)
- [ ] All tests pass on CI
- [ ] Pint + Larastan clean
- [ ] `php artisan optimize` succeeds
- [ ] `php artisan filament:optimize` succeeds
- [ ] Horizon config tuned for production worker count
- [ ] Sentry DSN set
- [ ] B2 prod bucket key set
- [ ] Cloudflare DNS record for `pms.bjptechnologies.co.tz`
- [ ] Caddyfile on prod server configured
- [ ] Postgres backups cron installed
- [ ] At least one super-admin user seeded
- [ ] Smoke test: create tenant → log in as operator → create property → unit → renter → lease → invoice → payment → receipt PDF

---

Last updated: 2026-05-28
