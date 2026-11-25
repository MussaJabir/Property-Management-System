<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Inspections\Pages;

use App\Filament\Operator\Resources\Inspections\InspectionResource;
use App\Models\Inspection;
use Filament\Resources\Pages\CreateRecord;

class CreateInspection extends CreateRecord
{
    protected static string $resource = InspectionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Completing goes through the Complete action, never the form.
        $data['status'] = Inspection::STATUS_DRAFT;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
