<?php

namespace App\Services\Clients;

use App\Models\Client;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Removes everything tied to a Client that the database FK cascade can NOT
 * reach, so a force-delete leaves nothing orphaned.
 *
 * Every tenant-scoped table cascades on `tenant_id`, so deleting the `tenants`
 * row wipes all child rows automatically. Two things live outside that reach:
 *
 *   1. Uploaded files on Backblaze B2 — the cascade deletes the `media` rows
 *      but the actual objects in object storage are never touched. Worse, the
 *      DB-level cascade bypasses Eloquent, so Spatie's own media cleanup never
 *      fires for the child models. We must delete the media here, first.
 *   2. Spatie role rows — the `roles` table is team-scoped by `tenant_id` but
 *      has no FK to `tenants`, so the cascade leaves them behind.
 *
 * Wired into Client's `forceDeleting` event (see ClientObserver) so every
 * force-delete path — the Filament purge action, bulk actions, tinker — is
 * cleaned the same way before the row (and its cascade) is removed.
 */
class ClientPurger
{
    /**
     * Tenant-scoped models that attach Spatie media (files live on B2).
     *
     * @var list<class-string<Model>>
     */
    private const MEDIA_MODELS = [
        Property::class,
        Unit::class,
        Lease::class,
        Expense::class,
        MaintenanceRequest::class,
    ];

    /**
     * Delete the B2 files and Spatie roles belonging to this client.
     *
     * File deletion is best-effort: a storage hiccup is logged, not thrown, so
     * a transient B2 error never blocks removing the client. Anything left
     * behind is an orphaned file (a cost issue), not a data-integrity one.
     */
    public function cleanExternalArtifacts(Client $client): void
    {
        $this->deleteMediaFiles($client);
        $this->deleteRoles($client);
    }

    private function deleteMediaFiles(Client $client): void
    {
        foreach (self::MEDIA_MODELS as $modelClass) {
            // withoutGlobalScopes() drops both the tenant scope and the
            // soft-delete scope, so we catch media on archived rows too.
            $ids = $modelClass::withoutGlobalScopes()
                ->where('tenant_id', $client->id)
                ->pluck('id');

            if ($ids->isEmpty()) {
                continue;
            }

            $morphType = (new $modelClass)->getMorphClass();

            Media::query()
                ->where('model_type', $morphType)
                ->whereIn('model_id', $ids)
                ->get()
                ->each(function (Media $media) use ($client): void {
                    try {
                        // Deleting the Media model removes the B2 object too.
                        $media->delete();
                    } catch (\Throwable $e) {
                        Log::warning('Client purge: media file deletion failed', [
                            'client_id' => $client->id,
                            'media_id' => $media->getKey(),
                            'error' => $e->getMessage(),
                        ]);
                    }
                });
        }
    }

    private function deleteRoles(Client $client): void
    {
        // model_has_roles / role_has_permissions pivots cascade off the role's
        // FK, so deleting the role rows is enough to clean the pivots too.
        Role::query()
            ->where('tenant_id', $client->id)
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
