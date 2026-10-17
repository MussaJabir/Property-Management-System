<?php

namespace App\Filament\Operator\Resources\Units\Pages;

use App\Filament\Operator\Concerns\RedirectsToIndex;
use App\Filament\Operator\Resources\Units\UnitResource;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\Url;

class CreateUnit extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = UnitResource::class;

    /** `?property=<id>` preselects the property, also for "create another". */
    #[Url]
    public ?string $property = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] ??= 'vacant';

        return $data;
    }
}
