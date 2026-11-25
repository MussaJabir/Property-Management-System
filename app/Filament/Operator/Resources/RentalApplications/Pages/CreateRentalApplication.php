<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\RentalApplications\Pages;

use App\Filament\Operator\Concerns\RedirectsToIndex;
use App\Filament\Operator\Resources\RentalApplications\RentalApplicationResource;
use App\Models\RentalApplication;
use Filament\Resources\Pages\CreateRecord;

class CreateRentalApplication extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = RentalApplicationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Decisions go through Approve / Decline, never the form.
        $data['status'] = RentalApplication::STATUS_RECEIVED;

        return $data;
    }
}
