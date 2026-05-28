# PMS — Property Management System

A multi-tenant property management SaaS for the Tanzanian rental and business-frame market. One codebase serves multiple landlord clients (operators), each accessing their own workspace via path-based tenancy under a single subdomain.

> **Status:** In development. See [`docs/IMPLEMENTATION_PLAN.md`](docs/IMPLEMENTATION_PLAN.md) for current phase and what's next.

## Stack

| Layer | Choice |
|---|---|
| Backend | Laravel 12 LTS + PHP 8.4 |
| Operator + super admin panel | Filament v4 |
| Marketing / renter portal / tenant CMS | Livewire 3 + Flux UI |
| Database | PostgreSQL 16 |
| Cache / queues / sessions | Redis 7 |
| Multi-tenancy | `stancl/tenancy` — single-DB, path-based |
| File storage | Backblaze B2 |
| Reverse proxy (prod) | Caddy + Cloudflare |
| Mobile (v2) | Flutter (Provider) |

## Documentation

- **[`CLAUDE.md`](CLAUDE.md)** — project rules, conventions, do/don't list
- **[`docs/IMPLEMENTATION_PLAN.md`](docs/IMPLEMENTATION_PLAN.md)** — phase-by-phase build plan
- **[`docs/DATA_MODEL.md`](docs/DATA_MODEL.md)** — full schema reference
- **[`docs/SETUP.md`](docs/SETUP.md)** — local development setup (Ubuntu)

## Branch workflow

- `main` — production, **protected**. Only the repo owner merges into it from `develop`.
- `develop` — integration branch. All feature work merges here first.
- Feature branches: `feat/<name>`, `fix/<name>`, `chore/<name>`, `refactor/<name>`, `docs/<name>`.

All PRs target `develop`. Owner promotes `develop` → `main` when ready for release.

## License

Proprietary. See [`LICENSE`](LICENSE).
