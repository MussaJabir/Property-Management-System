# PMS — Property Management SaaS

Multi-tenant property management SaaS for the Tanzanian rental + business-frame market. Commissioned by BJP Technologies. Codename **PMS** (final brand TBD). One codebase serves multiple landlord clients via path-based tenancy.

For full scope see `docs/IMPLEMENTATION_PLAN.md`. For data model see `docs/DATA_MODEL.md`. For dev setup see `docs/SETUP.md`.

---

## Naming glossary (IMPORTANT — read before working on tenancy code)

The word "tenant" is overloaded in this domain. To avoid confusion, PMS adopts the following terminology in **all user-facing surfaces, code, and documentation**:

- **Client** = the landlord / property-management company using PMS (our SaaS customer)
- **Renter** (or `mpangaji` in Swahili) = the person renting a unit from a client

The underlying multi-tenancy library (`stancl/tenancy`) calls the SaaS-level concept "tenant" internally. We **cannot fight that** without forking the package, so the following names retain the legacy "tenant" terminology — treat them as implementation details:

| Stays as "tenant" | Why |
|---|---|
| DB table `tenants` | stancl scopes by this table name |
| FK column `tenant_id` on every tenant-scoped table | stancl's `BelongsToTenant` trait queries this column |
| `App\Models\Concerns\TenantScopedModel` trait | Wraps stancl's `BelongsToTenant`; renaming requires changing every model |
| `tenant()` helper function | stancl-provided global |
| `routes/tenant.php` file name | Matches `InitializeTenancyByPath` middleware naming |
| `Stancl\Tenancy\…` namespace, middleware, exceptions | Vendor code |

**Everything else is "Client"**: `App\Models\Client`, `App\Filament\Admin\Resources\Clients\*`, Filament labels, route URLs (`/admin/clients`), translation keys (`client`, `clients`, `mteja`, `wateja`), documentation prose, error views.

When you see `tenant_id` in a migration or `BelongsToTenant` in a model, mentally translate to "the SaaS-level Client this row belongs to" — it is **never** the renter.

---

## Locked stack (do not change without re-discussion)

- **Backend**: Laravel 12 LTS + PHP 8.4
- **Database**: PostgreSQL 16
- **Cache / queue / sessions / broadcasting**: Redis 7
- **Operator + super admin panel**: Filament v5
- **Marketing site + renter portal + tenant CMS public pages**: Livewire 3 + Flux UI
- **Auth**: Laravel Sanctum (web sessions + API tokens for future mobile)
- **Multi-tenancy**: `stancl/tenancy` (latest v3.x) — single-database mode, `tenant_id` scoping, **path-based** URLs (`pms.bjptechnologies.co.tz/{tenant}/…`); switchable to subdomain later
- **Real-time**: Laravel Reverb (defer until 8 GB server provisioned)
- **Background jobs**: Laravel Horizon with queues `critical`, `default`, `low`
- **Search**: Meilisearch + Laravel Scout
- **File storage**: Backblaze B2 via `league/flysystem-aws-s3-v3` — **NEVER store files on local disk**, even in dev (use a B2 dev bucket)
- **Media**: Spatie Media Library + Intervention Image
- **PDF**: Spatie Browsershot
- **Excel**: Maatwebsite Laravel Excel
- **RBAC**: Spatie Permission
- **Audit log**: Spatie Activity Log
- **Reverse proxy (prod)**: Caddy on dedicated VPS; Cloudflare in front
- **Email**: Resend
- **SMS**: Beem Africa
- **WhatsApp**: WhatsApp Cloud API (Meta direct)
- **Payments (v2)**: Selcom Pay aggregator
- **Errors**: Sentry · **Metrics**: Laravel Pulse · **Uptime**: Uptime Kuma
- **Mobile (v2)**: Flutter + Provider, talks to Sanctum API
- **Containerization**: Docker — **Laravel Sail** for local dev, custom **Docker Compose** for production (Caddy + PHP-FPM + Postgres + Redis + Horizon + Scheduler containers). All dev and prod work runs inside containers. No native PHP/Postgres/Redis on the host.

## Mandatory conventions

- Every client-scoped model uses the `App\Models\Concerns\TenantScopedModel` trait (wraps `stancl/tenancy`'s `BelongsToTenant`: global scope + `tenant_id` auto-fill + belongsTo Client relationship). See "Naming glossary" above for why the trait keeps the "Tenant" name.
- All migrations index `tenant_id` first, plus commonly queried columns
- Phone numbers stored as **E.164** (`+255712345678`) via `propaganistas/laravel-phone`; accept `0712…` on input and normalize
- Money via `cknow/laravel-money` — **never floats** for currency
- UUID primary keys for `Property`, `Unit`, `Renter`, `Lease`, `Invoice`, `Payment`, `Tenant`
- Soft deletes on all business models (not on logs)
- Activity log on `Lease`, `Invoice`, `Payment`, `MaintenanceRequest` mutations
- All user-facing strings via `__()` translation keys — bilingual EN (default) + SW (user-switchable)
- Filament resources organized under `app/Filament/Operator/*` (per-tenant landlord panel) and `app/Filament/Admin/*` (super admin panel)
- API endpoints rate-limited via Laravel's built-in throttler
- Tests written in Pest

## Locale & formatting

- Default locale: **English** (user can switch to Swahili anywhere)
- Timezone: `Africa/Dar_es_Salaam` (UTC+3) — set in `config/app.php`
- Date display: `DD/MM/YYYY` — provide a helper / Blade directive, do not format inline
- Default currency: **TZS** — `Lease` and `Invoice` carry a `currency` column to support USD commercial leases
- Number display: Tanzanian convention (thousands separator with comma, 2 decimal places for money)

## Multi-tenancy rules

- **NEVER** write a query that crosses client boundaries unless explicitly in super-admin context (and even then, prefer scoped helpers)
- When testing operator/renter features manually, sign in inside that client workspace — not as super admin
- Adding a new client-scoped table requires: `tenant_id` FK (legacy column name — see glossary), index, model uses `TenantScopedModel` trait
- Client resolution middleware reads the first URL segment (`/{client}/…`) and resolves via `Client::find($slug)` (slug is the primary key)

## Run before claiming any task done

All commands run **inside Sail** (don't invoke host PHP / Composer / artisan directly):

```bash
./vendor/bin/sail pint                       # format
./vendor/bin/sail phpstan analyse            # Larastan level 8
./vendor/bin/sail pest                       # tests
./vendor/bin/sail artisan migrate --pretend  # catch migration errors
```

Convenience: add `alias sail="./vendor/bin/sail"` to your shell.

If any of the above fails, the task is not done.

## Docker conventions

- **Local dev: Laravel Sail.** Single `docker-compose.yml` at project root (Sail-generated). Spin up with `sail up -d`, shut down with `sail down`. Never run `php artisan ...` against host PHP.
- **Production: custom `docker/` folder.** Multi-stage `Dockerfile.app` (build assets → install vendor → minimal runtime image), `docker-compose.production.yml`, hardened `Caddyfile`, `php.ini` overrides. Added in Phase 11 (Production Deploy), not now.
- **No bind mounts in prod.** Production uses named volumes for Postgres data and Redis snapshots. Code is baked into the image, not mounted.
- **One container per concern.** Don't run Horizon worker inside the web container. Separate `app`, `worker`, `scheduler`, `reverb` (when added) services.
- **Image tags = git SHAs in prod.** Never `:latest`. Tag = short SHA so rollback is `docker compose pull <previous-sha> && up -d`.
- **Secrets via environment, not files in image.** B2 keys, DB passwords, Resend API keys — all passed via `.env` (gitignored) or your secret manager. Never `COPY` them into the image.

## Don'ts

- Don't store any user-uploaded file on local disk — always B2
- Don't introduce Next.js, React, Vue, or any second frontend framework
- Don't suggest switching tenancy modes (single-DB ↔ multi-DB) mid-project — that's a v3 decision
- Don't enable Reverb on the dev box without checking RAM headroom
- Don't add packages without discussion — prefer Spatie / Laravel ecosystem packages over generic PHP packages
- Don't hardcode English strings — every label goes through `__()`
- Don't put files on the shared Oracle box that hosts the 6 existing client systems (`bejus, bms, demo, mufindipower, mwpt, vikundi`) — PMS goes on its own VPS
- Don't run `php` / `composer` / `artisan` against the host. Always `sail <command>` so dev environment matches CI and prod
- Don't use `:latest` Docker tags in production — always pin to a git SHA so rollback is deterministic
- Don't bake secrets into Docker images — pass via environment variables only

## Production server context (for when we deploy)

- Currently planned: dedicated 8 GB / 4 vCPU VPS (Hetzner CX32 or Oracle upgrade)
- Host runs: **Docker only** (plus SSH, ufw, fail2ban, unattended-upgrades). No native PHP, Postgres, Redis, or Nginx on the host.
- Reverse proxy: **Caddy in a container** (auto-SSL via Let's Encrypt DNS-01 challenge through Cloudflare)
- Application stack: `docker compose -f docker-compose.production.yml up -d` runs Caddy + PHP-FPM + Postgres + Redis + Horizon + Scheduler
- Domain (v1): `pms.bjptechnologies.co.tz` with path-based tenants
- DNS / CDN / WAF: Cloudflare
- Storage: Backblaze B2 (separate buckets per environment)
- Do **NOT** deploy PMS onto the existing shared Oracle box — protects the 6 production client sites from PMS resource pressure
