# PMS — Data Model

Complete schema reference. Every table, every column, every relationship. Update in same commit as the migration that introduces or alters anything.

> **Naming**: In code and UI we use **Client** (the SaaS customer = landlord company) and **Renter** (the person renting a unit = mpangaji). The DB-level terminology keeps "tenant" / `tenant_id` because `stancl/tenancy` hard-codes those names. See `CLAUDE.md > Naming glossary`.

> Central tables live in the main `public` schema (no `tenant_id`).
> Client-scoped tables carry `tenant_id` (FK to `tenants.id`, indexed) — read "tenant_id" as "the Client this row belongs to".
> All UUID columns use `bigint`-backed UUIDv7 via Laravel's `uuid()` (sortable, time-ordered).

---

## 1. Central tables (no tenant_id)

### `tenants` (the Client table)
The landlord / property-management company using PMS. UI/code refer to this as **Client**; the DB table name is kept as `tenants` for stancl/tenancy compatibility.

| Column | Type | Notes |
|---|---|---|
| id | UUID PK | UUIDv7 |
| slug | string, unique | URL segment (`/{slug}/…`), lowercase, alphanumeric + dashes |
| name | string | Display name |
| contact_email | string, nullable | Primary contact |
| contact_phone | string, nullable | E.164 |
| logo_path | string, nullable | B2 path |
| brand_primary_color | string, nullable | hex |
| status | enum | `trial` \| `active` \| `suspended` \| `cancelled` |
| plan_id | FK plans.id, nullable | Current plan |
| trial_ends_at | timestamp, nullable | |
| settings | jsonb | Free-form per-tenant prefs |
| created_at, updated_at, deleted_at | timestamps | |

Indexes: `slug` unique, `status`, `plan_id`.

### `domains`
Future-proofing for subdomain mode. Not used in v1 path mode but kept ready.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| tenant_id | FK tenants.id | |
| domain | string, unique | e.g. `bejus.pms.bjptechnologies.co.tz` or custom |
| created_at, updated_at | timestamps | |

### `plans`
Subscription plan templates.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | "Starter", "Pro", "Enterprise" |
| slug | string, unique | |
| price_tzs | bigint | TZS in cents |
| billing_period | enum | `monthly` \| `annual` |
| max_properties | int, nullable | null = unlimited |
| max_units | int, nullable | |
| max_operators | int, nullable | |
| features | jsonb | feature flags |
| is_public | bool | shown on marketing page |
| created_at, updated_at | timestamps | |

### `subscriptions`
Tenant ↔ plan history.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| tenant_id | FK tenants.id | |
| plan_id | FK plans.id | |
| started_at | timestamp | |
| ends_at | timestamp, nullable | |
| status | enum | `active` \| `cancelled` \| `expired` |
| created_at, updated_at | timestamps | |

### `super_admin_users`
BJP team members managing the platform.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | |
| email | string, unique | |
| password | string | hashed |
| two_factor_secret | text, nullable | |
| two_factor_recovery_codes | text, nullable | |
| last_login_at | timestamp, nullable | |
| created_at, updated_at | timestamps | |

### `system_settings`
Global key-value config.

| Column | Type | Notes |
|---|---|---|
| key | string PK | |
| value | jsonb | |
| updated_at | timestamp | |

### `platform_activity_log`
System-level audit (Spatie schema, central).

---

## 2. Tenant-scoped tables (all carry `tenant_id`)

All tables below include:
- `tenant_id` UUID FK → `tenants.id`, indexed
- `created_at`, `updated_at` timestamps
- `deleted_at` nullable (soft deletes) unless noted

### `users`
Both operators and renters. Type distinguishes them.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| tenant_id | UUID FK | |
| type | enum | `operator` \| `renter` |
| name | string | |
| email | string, nullable | Required for operators; optional for renters |
| phone | string, nullable | E.164; required for renters |
| password | string | hashed |
| email_verified_at | timestamp, nullable | |
| phone_verified_at | timestamp, nullable | |
| locale | string(2) | `en` \| `sw`; defaults `en` |
| status | enum | `active` \| `disabled` |
| last_login_at | timestamp, nullable | |
| remember_token | string, nullable | |

Indexes: `(tenant_id, email)` unique partial where email not null, `(tenant_id, phone)` unique partial where phone not null, `(tenant_id, type)`.

### Spatie Permission tables
`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` — all carry `tenant_id` via `team_id` Spatie feature.

Seeded roles (per tenant): `owner`, `manager`, `accountant`, `maintenance_staff`.

### `locations`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| tenant_id | UUID FK | |
| name | string | Display label |
| region | string | |
| district | string | |
| ward | string, nullable | |
| street | string, nullable | |
| notes | text, nullable | |

### `properties`
| Column | Type | Notes |
|---|---|---|
| id | UUID PK | |
| tenant_id | UUID FK | |
| location_id | FK locations.id | |
| name | string | |
| type | enum | `residential` \| `commercial` \| `mixed` |
| address | text, nullable | |
| description | text, nullable | |
| status | enum | `active` \| `inactive` |
| created_at, updated_at, deleted_at | | |

Photos via Spatie Media Library (`media` table, polymorphic).

### `units`
| Column | Type | Notes |
|---|---|---|
| id | UUID PK | |
| tenant_id | UUID FK | |
| property_id | UUID FK | |
| code | string | "Room 5", "Frame 2A" — unique within property |
| type | enum | `room` \| `apartment` \| `business_frame` \| `office` \| `shop` \| `warehouse` |
| rent_amount | bigint | minor units (TZS cents) |
| rent_currency | string(3) | default `TZS` |
| billing_cycle | enum | `monthly` \| `quarterly` \| `annual` |
| status | enum | `vacant` \| `occupied` \| `maintenance` \| `reserved` |
| bedrooms | int, nullable | |
| bathrooms | int, nullable | |
| size_sqm | decimal(10,2), nullable | |
| description | text, nullable | |

Indexes: `(tenant_id, property_id)`, `(tenant_id, status)`.

### `renters`
| Column | Type | Notes |
|---|---|---|
| id | UUID PK | |
| tenant_id | UUID FK | |
| user_id | FK users.id, nullable | Linked once renter portal account exists |
| type | enum | `individual` \| `business` |
| full_name | string | |
| business_name | string, nullable | |
| phone | string | E.164 |
| alt_phone | string, nullable | |
| email | string, nullable | |
| nida_number | string, encrypted, nullable | National ID |
| tin_number | string, encrypted, nullable | Tax ID |
| address | text, nullable | |
| emergency_contact_name | string, nullable | |
| emergency_contact_phone | string, nullable | |
| notes | text, nullable | |

### `leases`
| Column | Type | Notes |
|---|---|---|
| id | UUID PK | |
| tenant_id | UUID FK | |
| renter_id | UUID FK | |
| unit_id | UUID FK | |
| start_date | date | |
| end_date | date, nullable | null = open-ended |
| rent_amount | bigint | TZS cents — snapshot from unit at lease creation |
| currency | string(3) | |
| deposit_amount | bigint | |
| billing_cycle | enum | same as unit |
| payment_due_day | int (1-28) | day of month |
| status | enum | `pending` \| `active` \| `ended` \| `terminated` |
| terms_notes | text, nullable | |
| activated_at | timestamp, nullable | |
| ended_at | timestamp, nullable | |

Indexes: `(tenant_id, status)`, `(tenant_id, unit_id, status)`.

Lease PDF stored via Spatie Media.

### `lease_history`
Audit of lease modifications (renewal, rent change, status change).

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| tenant_id | UUID FK | |
| lease_id | UUID FK | |
| user_id | FK users.id | Who made the change |
| action | string | `created` \| `activated` \| `renewed` \| `rent_changed` \| `terminated` |
| before | jsonb | snapshot |
| after | jsonb | snapshot |
| reason | text, nullable | |
| created_at | timestamp | |

### `invoice_sequences`
Tenant-scoped invoice numbering.

| Column | Type | Notes |
|---|---|---|
| tenant_id | UUID PK part | |
| year | int PK part | |
| last_number | int | |

Format: `INV-{tenant_slug}-{year}-{padded_number}` e.g. `INV-BEJUS-2026-000041`.

### `invoices`
| Column | Type | Notes |
|---|---|---|
| id | UUID PK | |
| tenant_id | UUID FK | |
| lease_id | UUID FK | |
| invoice_number | string, unique within tenant | |
| billing_period_start | date | |
| billing_period_end | date | |
| issued_at | timestamp | |
| due_date | date | |
| subtotal | bigint | TZS cents |
| tax_amount | bigint | |
| total_amount | bigint | |
| amount_paid | bigint | computed, denormalized |
| currency | string(3) | |
| status | enum | `draft` \| `unpaid` \| `partial` \| `paid` \| `overdue` \| `cancelled` |
| paid_at | timestamp, nullable | |
| notes | text, nullable | |

Indexes: `(tenant_id, status, due_date)`, `(tenant_id, lease_id)`.

### `invoice_items`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| invoice_id | UUID FK | |
| description | string | |
| quantity | decimal(10,2) | |
| unit_price | bigint | TZS cents |
| line_total | bigint | |
| type | enum | `rent` \| `utility` \| `fee` \| `deposit` \| `other` |

### `payments`
| Column | Type | Notes |
|---|---|---|
| id | UUID PK | |
| tenant_id | UUID FK | |
| invoice_id | UUID FK | |
| amount | bigint | TZS cents |
| currency | string(3) | |
| payment_date | date | |
| method | enum | `cash` \| `bank_transfer` \| `mobile_money` \| `cheque` \| `card` |
| reference_number | string, nullable | |
| mobile_money_provider | enum, nullable | `mpesa` \| `tigopesa` \| `airtelmoney` \| `halopesa` |
| transaction_id | string, nullable | external txn id |
| received_by_user_id | FK users.id | who recorded it |
| status | enum | `pending` \| `completed` \| `failed` \| `refunded` |
| notes | text, nullable | |

Indexes: `(tenant_id, invoice_id)`, `(tenant_id, payment_date)`.

### `receipts`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| tenant_id | UUID FK | |
| payment_id | UUID FK | |
| receipt_number | string, unique within tenant | |
| pdf_path | string, nullable | B2 path |
| issued_at | timestamp | |
| sent_via_email_at | timestamp, nullable | |
| sent_via_whatsapp_at | timestamp, nullable | |

### `maintenance_requests`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| tenant_id | UUID FK | |
| unit_id | UUID FK | |
| reported_by_user_id | FK users.id | could be renter |
| title | string | |
| description | text | |
| priority | enum | `low` \| `medium` \| `high` \| `urgent` |
| status | enum | `pending` \| `in_progress` \| `completed` \| `cancelled` |
| assigned_to_user_id | FK users.id, nullable | |
| reported_at | timestamp | |
| started_at | timestamp, nullable | |
| completed_at | timestamp, nullable | |
| cost | bigint, nullable | TZS cents |

Photos via Spatie Media on the model.

### `maintenance_updates`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| tenant_id | UUID FK | |
| maintenance_request_id | FK | |
| user_id | FK users.id | |
| note | text | |
| status_change | enum, nullable | new status if changed |
| created_at | timestamp | |

### `expense_categories`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| tenant_id | UUID FK | |
| name | string | |
| color | string, nullable | hex |

Seeded per tenant: `Repair`, `Cleaning`, `Security`, `Utilities`, `Tax`, `Other`.

### `expenses`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| tenant_id | UUID FK | |
| property_id | UUID FK, nullable | null = general overhead |
| category_id | FK expense_categories.id | |
| amount | bigint | TZS cents |
| currency | string(3) | |
| expense_date | date | |
| description | text, nullable | |
| recorded_by_user_id | FK users.id | |

Receipt attachment via Spatie Media.

### `cms_pages`
Per-tenant editable public pages.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| tenant_id | UUID FK | |
| slug | string | `landing` \| `about` \| `news` \| `contact` |
| title | jsonb | `{ "en": "...", "sw": "..." }` |
| meta_description | jsonb, nullable | |
| content | jsonb | array of block objects: `[{type, data}, …]` |
| status | enum | `draft` \| `published` |
| updated_by_user_id | FK users.id | |

Unique: `(tenant_id, slug)`.

Block types (v1): `hero`, `rich_text`, `image_gallery`, `featured_units`, `announcement_list`, `contact_form`.

### `cms_announcements`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| tenant_id | UUID FK | |
| slug | string | unique within tenant |
| title | jsonb | bilingual |
| body | jsonb | bilingual rich text |
| published_at | timestamp, nullable | null = draft |
| author_user_id | FK users.id | |

### `contact_messages`
Inbound from public contact form.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| tenant_id | UUID FK | |
| name | string | |
| email | string, nullable | |
| phone | string, nullable | |
| subject | string, nullable | |
| message | text | |
| handled_at | timestamp, nullable | |
| handled_by_user_id | FK users.id, nullable | |

### `notifications` (Laravel default + tenant_id)
| Column | Type | Notes |
|---|---|---|
| id | UUID PK | |
| tenant_id | UUID FK | |
| type | string | notification class FQN |
| notifiable_type | string | morph type |
| notifiable_id | bigint | morph id |
| data | jsonb | |
| read_at | timestamp, nullable | |
| created_at, updated_at | | |

### `notification_preferences`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| tenant_id | UUID FK | |
| user_id | FK users.id | |
| channel | enum | `email` \| `sms` \| `whatsapp` \| `in_app` |
| event_type | string | e.g. `invoice.issued`, `payment.received` |
| enabled | bool | |

### `activity_log` (Spatie, tenant-scoped)
Standard Spatie schema with added `tenant_id`.

---

## 3. Relationships diagram

```
tenants ─┬─< users (operators, renters)
         ├─< locations ─< properties ─< units ─< leases >─ renters
         │                                          │
         │                                          ▼
         │                                       invoices ─< invoice_items
         │                                          │
         │                                          ▼
         │                                       payments ─── receipts
         │                                          
         ├─< properties ─< expenses
         ├─< units ─< maintenance_requests ─< maintenance_updates
         ├─< cms_pages
         ├─< cms_announcements
         └─< contact_messages
```

---

## 4. Implementation notes

- **Money columns** are `bigint` storing minor units (TZS cents). Casts use `cknow/laravel-money` to convert to/from `Money` objects.
- **Phone columns** validated and normalized to E.164 via `propaganistas/laravel-phone`. Default country: `TZ`.
- **UUIDs** use Laravel's built-in `HasUuids` trait with UUIDv7 for time-sortable primary keys (better B-tree locality than v4).
- **JSONB for translations** instead of separate translation tables. Read with: `$page->title['en']` or via `__()`/`trans()` helpers wired to current locale.
- **CMS content blocks** validated against a schema per block type. Filament Builder field handles the editor UI.
- **Tenant scoping** enforced via a global scope in `TenantScopedModel`. Always-on; bypass only via super-admin context with `Tenant::withoutScope()` explicitly.
- **Sequence tables** (`invoice_sequences`) use Postgres advisory locks during increment to prevent race conditions when invoices are created in parallel.
- **Soft deletes** preserve historical data (a deleted renter still appears on old invoices). Reports always filter on `deleted_at` as appropriate.

---

Last updated: 2026-05-28
