<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Notifications\MaintenanceRequestSubmittedNotification;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Notifies the right operators when a new maintenance request lands.
 *
 * Audience: every active operator on the client with role 'manager' or
 * 'maintenance-staff'. Owners are included too — they often want visibility.
 * Sends to mail + database channel so the panel bell badges instantly.
 *
 * Failure mode: logged + swallowed. Never blocks the request from being
 * saved.
 */
class MaintenanceRequestObserver
{
    public function created(MaintenanceRequest $request): void
    {
        try {
            // Spatie role checks need the team scoped to the client.
            app(PermissionRegistrar::class)->setPermissionsTeamId($request->tenant_id);

            $operators = User::query()
                ->where('tenant_id', $request->tenant_id)
                ->where('type', User::TYPE_OPERATOR)
                ->where('status', 'active')
                ->get()
                ->filter(fn (User $u): bool => $u->hasAnyRole(['owner', 'manager', 'maintenance-staff']));

            foreach ($operators as $op) {
                $op->notify(new MaintenanceRequestSubmittedNotification($request));
            }
        } catch (Throwable $e) {
            Log::warning('Failed to notify operators of new maintenance request', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
