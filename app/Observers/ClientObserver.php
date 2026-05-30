<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\CmsPage;
use App\Models\ExpenseCategory;
use App\Services\Cms\DefaultPageContent;
use Spatie\Permission\Models\Role;

/**
 * Seed sensible defaults whenever a new Client is created so the workspace
 * is usable out of the box:
 *
 *   - four default roles (Spatie teams scoped to tenant_id)
 *   - six default expense categories (Repair, Cleaning, Security, …)
 *   - five default CMS pages (home/about/units/news/contact) with sample
 *     block content so the public site renders immediately
 *
 * Tenancy global scopes inject tenant_id automatically inside a tenant
 * context — we set it explicitly here because this observer also fires
 * when the super-admin creates a client outside any tenant context.
 */
class ClientObserver
{
    public const DEFAULT_ROLES = [
        'owner',
        'manager',
        'accountant',
        'maintenance-staff',
    ];

    public function created(Client $client): void
    {
        foreach (self::DEFAULT_ROLES as $name) {
            Role::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
                'tenant_id' => $client->id,
            ]);
        }

        foreach (ExpenseCategory::DEFAULT_CATEGORIES as $cat) {
            ExpenseCategory::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $client->id, 'name' => $cat['name']],
                ['color' => $cat['color']],
            );
        }

        foreach (DefaultPageContent::forClient($client->name) as $page) {
            CmsPage::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $client->id, 'slug' => $page['slug']],
                [
                    'title' => $page['title'],
                    'subtitle' => $page['subtitle'],
                    'blocks' => $page['blocks'],
                    'published_at' => now(),
                ],
            );
        }
    }
}
