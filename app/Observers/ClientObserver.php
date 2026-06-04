<?php

namespace App\Observers;

use App\Authorization\OperatorPermissions;
use App\Models\Client;
use App\Models\CmsPage;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Notifications\ClientStatusChangedNotification;
use App\Services\Cms\DefaultPageContent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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
        // Roles are team-scoped to this client; give each its permission set.
        // Permissions are global and seeded by migration.
        app(PermissionRegistrar::class)->setPermissionsTeamId($client->id);

        foreach (self::DEFAULT_ROLES as $name) {
            $role = Role::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
                'tenant_id' => $client->id,
            ]);

            $role->syncPermissions(OperatorPermissions::forRole($name));
        }

        // tenant_id is set directly (forceFill): this observer runs in the
        // central super-admin context where there's no active tenant to
        // auto-fill from, and tenant_id is excluded from these models' $fillable.
        foreach (ExpenseCategory::DEFAULT_CATEGORIES as $cat) {
            $category = ExpenseCategory::withoutGlobalScopes()
                ->firstOrNew(['tenant_id' => $client->id, 'name' => $cat['name']]);

            if (! $category->exists) {
                $category->forceFill(['tenant_id' => $client->id, 'color' => $cat['color']])->save();
            }
        }

        foreach (DefaultPageContent::forClient($client->name) as $page) {
            $cmsPage = CmsPage::withoutGlobalScopes()
                ->firstOrNew(['tenant_id' => $client->id, 'slug' => $page['slug']]);

            if (! $cmsPage->exists) {
                $cmsPage->forceFill([
                    'tenant_id' => $client->id,
                    'title' => $page['title'],
                    'subtitle' => $page['subtitle'],
                    'blocks' => $page['blocks'],
                    'published_at' => now(),
                ])->save();
            }
        }
    }

    /**
     * On a status change to/from "suspended", email the client's operators so
     * they know access was cut off (or restored). Sent synchronously; failures
     * are logged but never block the admin save.
     */
    public function updated(Client $client): void
    {
        if (! $client->wasChanged('status')) {
            return;
        }

        $now = $client->status;
        $was = $client->getOriginal('status');

        $suspended = $now === 'suspended';
        $reactivated = $was === 'suspended' && $now === 'active';

        if (! $suspended && ! $reactivated) {
            return;
        }

        // Operators belong to the tenant; query without global scopes since
        // this observer runs in the super-admin (central) context.
        $operators = User::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $client->id)
            ->where('type', User::TYPE_OPERATOR)
            ->whereNotNull('email')
            ->get();

        $recipients = $operators->isNotEmpty()
            ? $operators
            : collect(array_filter([$client->contact_email]))
                ->map(fn (string $email) => Notification::route('mail', $email));

        try {
            foreach ($recipients as $recipient) {
                $recipient->notify(new ClientStatusChangedNotification($client, $suspended));
            }
        } catch (\Throwable $e) {
            Log::warning('Client status-change notification failed', [
                'client_id' => $client->id,
                'suspended' => $suspended,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
