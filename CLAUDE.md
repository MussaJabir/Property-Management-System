# PMS — Property Management SaaS

Multi-tenant property management SaaS for the Tanzanian rental + business-frame market. Commissioned by BJP Technologies. Codename **PMS** (final brand TBD). One codebase serves multiple landlord clients via path-based tenancy.

For full scope see `docs/IMPLEMENTATION_PLAN.md`. For data model see `docs/DATA_MODEL.md`. For dev setup see `docs/SETUP.md`.

---

## Locked stack (do not change without re-discussion)

- **Backend**: Laravel 12 LTS + PHP 8.4
- **Database**: PostgreSQL 16
- **Cache / queue / sessions / broadcasting**: Redis 7
- **Operator + super admin panel**: Filament v4
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

## Mandatory conventions

- Every tenant-scoped model extends `App\Models\Concerns\TenantScopedModel` (global scope + `tenant_id` auto-fill)
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

- **NEVER** write a query that crosses tenant boundaries unless explicitly in super-admin context (and even then, prefer scoped helpers)
- When testing operator/renter features manually, sign in as that tenant — not as super admin
- Adding a new tenant-scoped table requires: `tenant_id` FK, index, model extends `TenantScopedModel`, registered in `config/tenancy.php` if needed
- Tenant resolution middleware reads the first URL segment (`/{tenant}/…`) and resolves via `tenants.slug`

## Run before claiming any task done

```bash
vendor/bin/pint                       # format
vendor/bin/phpstan analyse            # Larastan level 8
vendor/bin/pest                       # tests
php artisan migrate --pretend         # catch migration errors
```

If any of the above fails, the task is not done.

## Don'ts

- Don't store any user-uploaded file on local disk — always B2
- Don't introduce Next.js, React, Vue, or any second frontend framework
- Don't suggest switching tenancy modes (single-DB ↔ multi-DB) mid-project — that's a v3 decision
- Don't enable Reverb on the dev box without checking RAM headroom
- Don't add packages without discussion — prefer Spatie / Laravel ecosystem packages over generic PHP packages
- Don't hardcode English strings — every label goes through `__()`
- Don't put files on the shared Oracle box that hosts the 6 existing client systems (`bejus, bms, demo, mufindipower, mwpt, vikundi`) — PMS goes on its own VPS

## Production server context (for when we deploy)

- Currently planned: dedicated 8 GB / 4 vCPU VPS (Hetzner CX32 or Oracle upgrade)
- Reverse proxy: Caddy (auto-SSL, simpler than Apache)
- Domain (v1): `pms.bjptechnologies.co.tz` with path-based tenants
- DNS / CDN / WAF: Cloudflare
- Storage: Backblaze B2 (separate buckets per environment)
- Do **NOT** deploy PMS onto the existing shared Oracle box — protects the 6 production client sites from PMS resource pressure
