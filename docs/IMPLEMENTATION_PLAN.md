# PMS — Implementation Plan

Source of truth for what gets built, in what order, and why. Update as scope evolves.

> See `CLAUDE.md` for stack and conventions. See `DATA_MODEL.md` for tables. See `SETUP.md` for day-1 commands.

---

## 1. Project Summary

PMS is a multi-tenant property management SaaS commissioned by BJP Technologies for the Tanzanian rental + business-frame market. One codebase, many **clients** (landlord / property-management companies), each accessing their own workspace via a path-based subdomain (`pms.bjptechnologies.co.tz/{client}/…`). Future migration to wildcard subdomains when a dedicated SaaS domain is bought.

> **Terminology:** "Client" = the SaaS customer. "Renter" / "Mpangaji" = the person renting a unit. See `CLAUDE.md > Naming glossary` for the full convention (DB-level "tenant" terminology is kept where stancl/tenancy requires it).

**Primary users**
1. **Super Admin** (BJP team) — provisions clients, manages plans, monitors platform.
2. **Operator** (landlord / property manager / their staff) — runs day-to-day property management inside a client workspace.
3. **Renter / Mpangaji** — logs into a self-service portal to view invoices, pay rent, request maintenance.
4. **Public visitor** — sees the client's CMS-managed public landing page and vacant unit listings.

**Success criteria for v1**
- Boss can log in as super admin, create a client, and hand off a working URL to a prospective landlord.
- That client can manage properties → units → renters → leases → invoices → payments → receipts end-to-end.
- The client's renters can log into the portal and see what they owe.
- The client has a public landing page they can edit (hero, about, news, contact).
- Everything is mobile-responsive and bilingual (EN default, SW togglable).

---

## 2. High-Level Architecture

All application services run inside Docker containers on the production VPS. Local dev uses Laravel Sail (same containers, Sail-provided compose). Cloudflare and Backblaze B2 are external managed services.

```
                ┌──────────────────────────────┐
                │    Cloudflare (DNS/WAF/CDN)  │
                └──────────────┬───────────────┘
                               ▼
        ╔══════════════════════════════════════════════╗
        ║  DOCKER COMPOSE — Production VPS (8 GB)      ║
        ║  ┌────────────────────────────────────────┐  ║
        ║  │  Caddy (reverse proxy, auto-SSL)       │  ║
        ║  └──────────────┬─────────────────────────┘  ║
        ║                 ▼                            ║
        ║  ┌────────────────────────────────────────┐  ║
        ║  │  app:  Laravel 12 + PHP 8.4 FPM        │  ║
        ║  │   Filament v4 · Livewire 3 + Flux      │  ║
        ║  │   Sanctum · stancl/tenancy (path)      │  ║
        ║  └─┬──────┬───────┬──────┬────────────────┘  ║
        ║    ▼      ▼       ▼      │                   ║
        ║  ┌─────┐┌─────┐┌──────┐  │                   ║
        ║  │pgsql││redis││meili │  │                   ║
        ║  │ 16  ││  7  ││search│  │                   ║
        ║  └─────┘└──┬──┘└──────┘  │                   ║
        ║            ▼              │                   ║
        ║  ┌────────────────────┐   │                   ║
        ║  │ worker: Horizon    │   │                   ║
        ║  │ scheduler: cron    │   │                   ║
        ║  └─────────┬──────────┘   │                   ║
        ╚════════════│══════════════│═══════════════════╝
                     │              │
                     ▼              ▼
              ┌────────────┐  ┌────────────┐
              │  Resend    │  │ Backblaze  │
              │  Beem SMS  │  │     B2     │
              │  WhatsApp  │  │  (files)   │
              │  Selcom v2 │  └────────────┘
              └────────────┘
```

URL surfaces under `pms.bjptechnologies.co.tz`:

| Path | Purpose | Auth |
|---|---|---|
| `/` | PMS marketing/landing | Public |
| `/admin` | Super admin panel | Super admin |
| `/{tenant}` | Tenant public landing (CMS) | Public |
| `/{tenant}/about` | CMS page | Public |
| `/{tenant}/units` | Vacant units listing | Public |
| `/{tenant}/news` | Announcements | Public |
| `/{tenant}/contact` | Contact form | Public |
| `/{tenant}/manage` | Operator (landlord) panel | Operator |
| `/{tenant}/portal` | Renter portal | Renter |

---

## 3. Module Inventory & Versioning

### v1 (MVP — must ship before first client demo)

| Module | Notes |
|---|---|
| Tenant provisioning | Super admin creates tenant, generates slug, sets plan |
| Operator auth & RBAC | Owner, Manager, Accountant, Maintenance Staff |
| Tenant settings | Logo, brand color, contact info, currency default |
| Locations | Region/district/ward catalog per tenant |
| Properties | Buildings/compounds, with photos via B2 |
| Units | Rooms, apartments, business frames, offices, shops, warehouses |
| Renters | Individual + business, NIDA/TIN encrypted |
| Leases | Renter-to-unit assignment, lease PDF generation |
| Invoices | Manual creation v1; auto-generation v1.5 (cron) |
| Payments | Manual recording (cash, bank, mobile money — no integration yet) |
| Receipts | Auto-numbered, PDF download |
| Maintenance requests | Operator can create + assign; renter can submit from portal |
| Expenses | Categorized property expenses |
| Reports v1 | Monthly Rent Collection, Outstanding Rent, Occupancy, Property Income, Expense, Profit Summary, Renter Payment History |
| Renter portal v1 | Phone + password auth, dashboard, invoices, payment history, maintenance request, profile |
| Tenant CMS pages | Landing, About, Units (auto), News, Contact — Filament Builder blocks |
| Notifications (in-app + email) | Invoice issued, payment received, overdue |
| Bilingual EN/SW | All UI strings, runtime locale switch |
| Mobile responsive | Real-device QA before demo |
| Branding placeholder | "PMS" everywhere, single rename later |

### v2 (post first client signed)

| Module | Notes |
|---|---|
| Selcom Pay integration | M-Pesa TZ, Tigo Pesa, Airtel Money, cards |
| SMS reminders (Beem) | Rent due, overdue, payment confirmation |
| WhatsApp reminders | Receipts, reminders |
| OTP login for renters | Phone OTP via Beem |
| Auto invoice generation | Cron job, configurable per tenant |
| Auto overdue detection | Status promotion + notifications |
| Advanced reports | Custom date ranges, export to Excel + PDF |
| Lease renewal workflow | Reminders 30/60/90 days before expiry |
| Maintenance SLA tracking | Time-to-resolve dashboards |
| Subscription billing (you bill landlords) | Filament panel for super admin |
| Reverb websockets | Live dashboard updates, notification toasts |

### v3 (scale features)

| Module | Notes |
|---|---|
| Mobile apps (Flutter) | Operator app + Renter app, Sanctum API |
| Multi-database mode | Per-tenant DB isolation for enterprise clients |
| Marketplace / listings | Cross-tenant vacancy listings |
| Multi-currency reporting | Conversion to a base currency for analytics |
| Custom branding per tenant | White-label colors, logo, custom domain (CNAME) |
| Tenant branches/divisions | One landlord, multiple regional sub-offices |
| Public API for third-party integrations | OAuth + scoped tokens |
| Advanced analytics dashboard | Per-portfolio KPIs, trends, forecasting |

---

## 4. Phase / Sprint Breakdown

Phases are sequenced by dependency. No strict day count — solo dev pace.

### Phase 0 — Foundation
1. Docker Engine + Compose plugin installed on host
2. Laravel 12 project scaffolded via `laravelsail/php84-composer` one-shot container
3. Laravel Sail installed with `pgsql,redis,meilisearch,mailpit` services — `sail up -d` brings up the whole stack
4. `.env` configured to point at Sail container hostnames (`pgsql`, `redis`, `meilisearch`, `mailpit`)
5. Sanctum, Horizon, Pulse, Scout installed
6. Spatie Permission + Activity Log + Media Library installed
7. Filament v4 installed with two panels (`admin`, `operator`)
8. Livewire 3 + Flux UI installed
9. `stancl/tenancy` installed and configured for path-based mode
10. B2 disk configured in `config/filesystems.php` and set as default
11. `App\Models\Concerns\TenantScopedModel` trait/base created
12. Bilingual scaffolding: `lang/en/` and `lang/sw/` directories, locale middleware, switcher component
13. CI updated: GitHub Actions runs Pint + Larastan + Pest inside Docker (matches Sail)

### Phase 1 — Super Admin & Tenant Provisioning
1. `tenants`, `domains`, `plans`, `subscriptions` migrations (central DB)
2. Super admin Filament panel at `/admin`
3. Tenant resource: create / suspend / activate / view
4. Plan resource
5. Tenant resolution middleware: parses `/{tenant}` from URL, sets tenant context
6. Tenant 404 page for unknown slugs

### Phase 2 — Operator Auth & Workspace Shell
1. Tenant-scoped `users` table with type (`operator` | `renter`)
2. Operator login at `/{tenant}/manage/login`
3. Roles: Owner, Manager, Accountant, Maintenance Staff
4. Tenant settings: logo, brand color, contact info (used in CMS + receipts)
5. Operator dashboard shell (cards: properties, units, occupancy %, monthly expected/collected)
6. Locale switcher in topbar

### Phase 3 — Properties & Units
1. `locations` resource
2. `properties` resource with photo upload to B2
3. `units` resource with type/status/rent/billing-cycle
4. Vacant units helper query for CMS use later
5. Soft delete behavior tested

### Phase 4 — Renters & Leases
1. `renters` resource (individual + business)
2. NIDA/TIN encrypted columns
3. `leases` resource with wizard: pick renter → pick unit → set terms → confirm
4. Lease PDF generation via Browsershot
5. Lease status transitions (pending → active → ended/terminated)
6. Unit status auto-updates when lease activates/ends

### Phase 5 — Billing
1. `invoices` + `invoice_items` migrations
2. Invoice number sequence (tenant-scoped yearly counter)
3. Manual invoice creation UI
4. Manual payment recording
5. Auto status transitions (unpaid → partial → paid; due-date → overdue)
6. `receipts` table + PDF generation
7. Email receipts via Resend (Notifications channel)

### Phase 6 — Maintenance & Expenses
1. `maintenance_requests` + `maintenance_updates` resources
2. Priority + status workflow
3. Assignment to staff
4. `expense_categories` + `expenses` resources

### Phase 7 — Reports & Dashboard
1. Dashboard widgets (Filament): occupancy %, monthly collected vs expected, overdue count, top 5 unpaid invoices, recent payments
2. Reports module with date-range pickers
3. PDF + Excel exports for each report

### Phase 8 — Renter Portal (v1)
1. Renter registration triggered when lease is activated (auto-create `users` row with portal access)
2. Renter login at `/{tenant}/portal/login` (phone + password)
3. Renter dashboard: active lease, next due date, balance
4. Invoices list + receipts download
5. Maintenance request submission + status view
6. Profile / password change
7. Locale switcher

### Phase 9 — Tenant CMS & Public Pages
1. `cms_pages` table with JSON block content
2. Filament Builder field for editing block layouts
3. Block components: Hero, Rich Text, Image Gallery, Featured Units, Announcement List, Contact Form
4. Public routes: `/{tenant}`, `/{tenant}/about`, `/{tenant}/units`, `/{tenant}/news`, `/{tenant}/contact`
5. Vacant units auto-rendered with filters (price, type, location)
6. Contact form submissions → tenant inbox + email notification
7. `cms_announcements` resource for News page

### Phase 10 — Notifications & Polish
1. Email notifications: invoice issued, payment received, overdue, new maintenance request
2. In-app notification bell (Filament + Livewire)
3. Notification preferences per user
4. Seed data command for demos (3 properties, 12 units, 8 renters, 20 invoices, sample CMS content)
5. Mobile responsive QA on real Android phone
6. Performance pass: query N+1 check via Telescope, Redis cache warm
7. Sentry integration
8. Error pages styled

### Phase 11 — Production Deploy
1. Provision 8 GB VPS (Hetzner CX32 or Oracle upgrade)
2. Install Docker Engine + Compose plugin + ufw + fail2ban on host (no native PHP/Postgres/Redis)
3. Build production `docker/Dockerfile.app` — multi-stage (Composer install → npm build → minimal PHP-FPM runtime), no dev deps, healthcheck baked in
4. Author `docker/docker-compose.production.yml` — `caddy`, `app`, `worker` (Horizon), `scheduler`, `pgsql`, `redis`, `meilisearch` services with named volumes for data
5. Author production `docker/Caddyfile` — `pms.bjptechnologies.co.tz` with auto-SSL via Let's Encrypt DNS-01 (Cloudflare API token)
6. Configure Cloudflare DNS + proxy
7. GitHub Actions deploy pipeline: build image → push to GHCR with git-SHA tag → SSH to VPS → `docker compose pull && up -d` → `docker compose exec app php artisan migrate --force`
8. Backup automation: cron container runs nightly `pg_dump` → upload to B2 (30-day retention), Postgres volume snapshot weekly
9. Uptime Kuma monitoring + alerts to email/WhatsApp
10. Rollback procedure documented + tested: revert to previous SHA tag, `docker compose pull && up -d`
11. First tenant provisioned, demo URL handed to boss

### Post-v1 backlog (loose order)
- Selcom Pay integration
- SMS / WhatsApp notifications
- OTP login
- Auto invoice generation
- Reverb websockets (after server upgrade if RAM allows)
- Subscription billing
- Mobile apps

---

## 5. Module Dependencies

```
Foundation ──► Super Admin ──► Operator Auth ──► Properties ──► Units
                                       │                          │
                                       ▼                          ▼
                              Tenant Settings              Renters ──► Leases ──► Invoices ──► Payments ──► Receipts
                                       │                                                                    │
                                       ▼                                                                    ▼
                                CMS Pages ◄── Vacant Units                                          Notifications
                                                                                                            │
                                                                          Renter Portal ◄──────────────────┘
                                                                                  │
                                                                          Maintenance ──► Expenses ──► Reports
```

Don't start a downstream module before its upstream is stable. Cross-module hacks invariably become tech debt.

---

## 6. Testing Strategy

- **Pest** for all tests
- **Feature tests** for every Filament resource (create/edit/delete/list happy path, validation)
- **Feature tests** for every Livewire component (mount, primary user action, edge case)
- **Multi-tenancy tests**: every tenant-scoped feature must verify isolation (tenant A cannot see tenant B's data)
- **Auth tests**: each role must have one test confirming what it can do AND one confirming what it cannot
- **HTTP tests** for renter portal endpoints
- **Larastan level 8** on `app/`
- Coverage target: 70% line coverage on `app/`, 100% on payment + invoice number logic
- Run on every push via GitHub Actions

---

## 7. Deployment Plan

### Local dev
Docker via **Laravel Sail**. See `SETUP.md`. Local URL: `http://localhost/{tenant}/…` — Sail handles PHP, Postgres, Redis, Meilisearch, Mailpit. No native installs on the host beyond Docker itself.

### Staging
Deferred until first VPS provisioned. Optionally a `staging.pms.bjptechnologies.co.tz` subdomain on the same VPS (separate compose project, separate DB volume).

### Production
- Dedicated 8 GB VPS, separate from the existing shared Oracle box
- Host runs Docker only (plus SSH, ufw, fail2ban, unattended-upgrades)
- All services via `docker-compose.production.yml`: `caddy`, `app`, `worker`, `scheduler`, `pgsql`, `redis`, `meilisearch`
- Caddy container handles auto-SSL via Let's Encrypt DNS-01 (Cloudflare API token)
- Cloudflare in front (proxy enabled)
- GitHub Actions deploy pipeline: build image → push to GHCR with git-SHA tag → SSH to VPS → `docker compose pull && up -d` → migrate
- Zero-downtime via rolling app container restart + Horizon graceful restart
- Postgres + B2 backed up nightly

### Rollback
- Image tags = git short SHAs (never `:latest` in prod)
- Rollback = `docker compose pull <previous-sha> && up -d` — under 60 seconds
- Postgres backups retained 30 days on B2
- Never run destructive migrations without a tested rollback migration

---

## 8. Risk Register

| Risk | Mitigation |
|---|---|
| Server runs out of RAM | Don't deploy to shared 1 GB Oracle box. Get dedicated 8 GB VPS before launch. |
| Tenant data leak (cross-tenant query) | TenantScopedModel base class + middleware + isolation tests on every resource |
| Files lost | B2 from day one; never local disk |
| Wildcard SSL fails | Path-based tenancy avoids this entirely v1 |
| Renter portal abuse (spam, scraping) | Rate-limit auth endpoints, captcha on portal registration |
| Sensitive PII exposure (NIDA, TIN) | Encrypted columns via Laravel `encrypted` cast |
| Solo-dev bus factor | Documentation in this repo, conventions enforced via static analysis |
| Scope creep before MVP demo | This document is the line. New features land in v2 backlog. |
| Boss changes requirements mid-build | Re-discuss scope, update this doc, don't silently absorb |
| Dev/prod drift | All work via Docker (Sail dev, custom compose prod); same image versions; CI uses Docker services |
| Deploy failure mid-rollout | Image-tag-based rollback (`docker compose pull <prev-sha> && up -d`) — under 60 seconds |

---

## 9. Open Questions (revisit before they block work)

- Domain & branding: when does boss greenlight buying a SaaS domain?
- Selcom Pay merchant account: who registers (BJP or each tenant)? — affects payment routing
- Per-tenant custom domains (CNAME): v3, but worth a flag in the data model now
- Tax invoicing for tenant subscriptions: TRA EFD integration scope (v3)
- Support / customer success workflow: how does a tenant reach BJP for help?

---

## 10. Living Document Notice

This file is updated as decisions evolve. When you (or any future Claude session) make a scope or architecture decision, update this file in the same commit as the code change. Avoid scattered decision history.

Last updated: 2026-05-28 (Docker / Sail added)
