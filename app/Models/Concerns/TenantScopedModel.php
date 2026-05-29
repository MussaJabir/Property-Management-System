<?php

namespace App\Models\Concerns;

use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Trait for tenant-scoped Eloquent models.
 *
 * Wraps stancl/tenancy's BelongsToTenant trait so the project has a single,
 * project-namespaced concern to depend on. If we ever swap the tenancy
 * library, only this trait changes — call sites stay untouched.
 *
 * Usage:
 *
 *     class Property extends Model
 *     {
 *         use TenantScopedModel;
 *     }
 *
 * Behavior provided by the underlying trait:
 *   - Global scope filters queries by current tenant_id (set by tenancy middleware).
 *   - Auto-fills tenant_id on model creation when a tenant is active.
 *   - Defines a belongsTo relationship: $model->tenant.
 *
 * Migration requirement: every model using this trait MUST have a `tenant_id`
 * column (UUID, FK to tenants.id, indexed). See CLAUDE.md conventions.
 */
trait TenantScopedModel
{
    use BelongsToTenant;
}
