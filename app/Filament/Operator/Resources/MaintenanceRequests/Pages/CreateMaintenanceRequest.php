<?php

namespace App\Filament\Operator\Resources\MaintenanceRequests\Pages;

use App\Filament\Operator\Concerns\RedirectsToIndex;
use App\Filament\Operator\Resources\MaintenanceRequests\MaintenanceRequestResource;
use App\Models\MaintenanceRequest;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenanceRequest extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = MaintenanceRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Always pending on create. Status is governed by start() / complete()
        // / cancel() — never by the form.
        $data['status'] = MaintenanceRequest::STATUS_PENDING;
        $data['reported_at'] = now();
        $data['reported_by_user_id'] ??= auth()->id();

        return $data;
    }
}
