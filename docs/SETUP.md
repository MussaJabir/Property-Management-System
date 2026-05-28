# PMS — Local Dev Setup

Get PMS running locally on Ubuntu via **Laravel Sail** (Docker). Production runs the same containers (with different configs), so cutover is mostly env-swap and image-build.

> Target OS: Ubuntu 24.04. Sail handles PHP, Postgres, Redis, Mailpit, Meilisearch — no native installs of those needed.

---

## 1. Host prerequisites

```bash
# Docker Engine + Compose plugin
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
newgrp docker   # or log out / back in
docker --version
docker compose version

# Verify Docker works without sudo
docker run --rm hello-world
```

```bash
# Git (probably already installed)
sudo apt install -y git

# Optional: gh CLI for PRs from terminal
sudo apt install -y gh
gh auth login
```

That's it for the host. **No PHP, no Composer, no Postgres, no Redis installed natively.** Everything runs in containers.

---

## 2. Clone the repo

```bash
cd ~/Dev/BJP/Projects/Systems/Property\ Management\ System/PMS
git clone https://github.com/MussaJabir/Property-Management-System.git app
cd app
git checkout develop
```

(Once Laravel is installed in Phase 0, the `app/` folder will contain the actual Laravel project.)

---

## 3. First-time Laravel + Sail install (Phase 0 task — placeholder)

When Phase 0 lands, the bootstrap is:

```bash
# From host (one-time, uses a temporary container just to scaffold Laravel)
docker run --rm -v "$(pwd)":/opt/src -w /opt/src laravelsail/php84-composer:latest \
  composer create-project laravel/laravel:^12.0 .

# Install Sail
docker run --rm -v "$(pwd)":/opt/src -w /opt/src laravelsail/php84-composer:latest \
  composer require laravel/sail --dev

# Publish Sail's docker-compose.yml with the services we need
docker run --rm -v "$(pwd)":/opt/src -w /opt/src laravelsail/php84-composer:latest \
  php artisan sail:install --with=pgsql,redis,meilisearch,mailpit

# Alias for convenience
alias sail="./vendor/bin/sail"
echo 'alias sail="./vendor/bin/sail"' >> ~/.bashrc
```

After that, everything runs through `sail`:

```bash
sail up -d                # start the stack
sail down                 # stop everything
sail artisan migrate      # run migrations inside the container
sail composer require ... # install packages inside the container
sail npm install          # install JS deps inside the container
sail npm run dev          # Vite dev server (with HMR)
sail pest                 # run tests
sail pint                 # format
sail phpstan analyse      # static analysis
sail shell                # open a shell inside the app container
```

---

## 4. `.env` configuration (local)

`.env` is gitignored — never commit it. After Sail bootstrap, it's auto-generated. Adjust these keys:

```ini
APP_NAME=PMS
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_TIMEZONE="Africa/Dar_es_Salaam"

# These point to Sail container hostnames (NOT 127.0.0.1)
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=pms
DB_USERNAME=sail
DB_PASSWORD=password

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_CLIENT=phpredis

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=null

# B2 as default disk from day one — no local file storage!
FILESYSTEM_DISK=b2
B2_KEY=your_b2_key_id
B2_SECRET=your_b2_application_key
B2_BUCKET=pms-dev-uploads
B2_REGION=us-west-002
B2_ENDPOINT=https://s3.us-west-002.backblazeb2.com
B2_URL=https://pms-dev-uploads.s3.us-west-002.backblazeb2.com

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025

RESEND_API_KEY=        # only when testing real email

SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=

SENTRY_LARAVEL_DSN=    # local: leave blank
```

> **Important:** `DB_HOST=pgsql`, `REDIS_HOST=redis`, `MEILISEARCH_HOST=meilisearch` — those are Docker network hostnames, not localhost. Sail's compose file wires them.

---

## 5. Backblaze B2 setup (do this before first file upload)

1. Sign up at https://www.backblaze.com/cloud-storage
2. Create two buckets: `pms-dev-uploads` (dev) and `pms-prod-uploads` (prod). Mark **Private**.
3. Create an Application Key scoped to the dev bucket. Note: `keyID`, `applicationKey`, `endpoint`, `region`.
4. Put those into `.env` (section above).
5. Add the disk config to `config/filesystems.php`:

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

## 6. Spin up the stack

```bash
sail up -d
sail artisan key:generate
sail artisan migrate
```

Visit:
- App: `http://localhost`
- Tenant URL: `http://localhost/{tenant}/...` (path-based tenancy — no `/etc/hosts` edits needed locally)
- Mailpit (catch-all SMTP): `http://localhost:8025`
- Meilisearch: `http://localhost:7700`
- Horizon: `http://localhost/horizon`
- Telescope: `http://localhost/telescope`
- Pulse: `http://localhost/pulse`

---

## 7. Required packages (installed during Phase 0)

```bash
sail composer require filament/filament:"^4.0"
sail composer require stancl/tenancy
sail composer require livewire/livewire:"^3.6"
sail composer require livewire/flux
sail composer require laravel/sanctum
sail composer require laravel/horizon
sail composer require laravel/pulse
sail composer require laravel/scout
sail composer require spatie/laravel-permission
sail composer require spatie/laravel-activitylog
sail composer require spatie/laravel-medialibrary
sail composer require spatie/browsershot
sail composer require maatwebsite/excel
sail composer require league/flysystem-aws-s3-v3
sail composer require cknow/laravel-money
sail composer require propaganistas/laravel-phone
sail composer require intervention/image
sail composer require meilisearch/meilisearch-php

sail composer require --dev pestphp/pest
sail composer require --dev pestphp/pest-plugin-laravel
sail composer require --dev larastan/larastan
sail composer require --dev laravel/pint
sail composer require --dev laravel/telescope

sail artisan pest:install
sail artisan horizon:install
sail artisan pulse:install
sail artisan telescope:install
sail artisan filament:install --panels
```

---

## 8. Common dev workflows

```bash
# Create a tenant for local testing (after Phase 1 lands)
sail artisan tinker
>>> \App\Models\Tenant::create(['slug' => 'demo', 'name' => 'Demo Properties'])

# URLs
# http://localhost/admin              ← super admin
# http://localhost/demo               ← tenant public landing
# http://localhost/demo/manage        ← operator panel
# http://localhost/demo/portal        ← renter portal

# Reset & reseed
sail artisan migrate:fresh --seed

# Watch logs
sail logs -f laravel.test            # app
sail logs -f                         # all services

# Run a specific test
sail pest tests/Feature/LeaseTest.php

# Run linters before committing
sail pint                            # auto-fix style
sail pint --test                     # verify only
sail phpstan analyse                 # static analysis
sail pest --parallel                 # full test suite
```

---

## 9. Gotchas & tips

- **Always use `sail <cmd>`, never host `php`/`composer`/`artisan`.** Otherwise dev drifts from CI/prod.
- **DB hostname is `pgsql`, not `127.0.0.1`.** Same for `redis`, `meilisearch`. They're Docker network names.
- **B2 from day one — even in dev.** Don't switch to `local` disk for "convenience" — you'll re-test everything when you flip back.
- **Sail's `vendor/bin/sail` isn't available until `composer install` runs.** First time, use the `docker run --rm laravelsail/php84-composer` trick above.
- **Browsershot (PDF generation) needs Chromium.** Sail's PHP image doesn't include it. We'll add a custom Dockerfile in Phase 5 or use a remote browser service.
- **Tenant URLs work via path** (`/demo/...`), so no `/etc/hosts` or local DNS hack needed.
- **Spatie Permission with multi-tenancy**: use Spatie's "teams" feature where `team_id` = `tenant_id`. Config flag: `'teams' => true`.
- **Stop everything cleanly**: `sail down`. To wipe DB volumes too: `sail down -v`.

---

## 10. Pre-deploy checklist (Phase 11)

- [ ] `sail artisan migrate:fresh` runs cleanly on a fresh DB
- [ ] `sail pest` passes
- [ ] `sail pint --test` clean
- [ ] `sail phpstan analyse` clean
- [ ] `sail artisan optimize` succeeds
- [ ] `sail artisan filament:optimize` succeeds
- [ ] Production `Dockerfile.app` builds without errors (`docker build -f docker/Dockerfile.app .`)
- [ ] Production `docker-compose.production.yml` validates (`docker compose -f docker/docker-compose.production.yml config`)
- [ ] B2 prod bucket key set
- [ ] Cloudflare DNS record for `pms.bjptechnologies.co.tz`
- [ ] At least one super-admin user seeded
- [ ] Smoke test: create tenant → log in as operator → create property → unit → renter → lease → invoice → payment → receipt PDF

---

Last updated: 2026-05-28
