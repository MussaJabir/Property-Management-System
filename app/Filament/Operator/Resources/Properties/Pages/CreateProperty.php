<?php

namespace App\Filament\Operator\Resources\Properties\Pages;

use App\Filament\Operator\Concerns\RedirectsToIndex;
use App\Filament\Operator\Resources\Properties\PropertyResource;
use App\Filament\Operator\Resources\Units\UnitResource;
use App\Models\Property;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/**
 * A new property has no units yet, so saving goes straight on to adding
 * them, with this property already chosen.
 */
class CreateProperty extends CreateRecord
{
    use RedirectsToIndex {
        getRedirectUrl as indexUrl;
    }

    protected static string $resource = PropertyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] ??= 'active';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return UnitResource::canCreate()
            ? UnitResource::getUrl('create', ['property' => $this->getRecord()->getKey()])
            : $this->indexUrl();
    }

    protected function getCreatedNotification(): ?Notification
    {
        /** @var Property $property */
        $property = $this->getRecord();

        return Notification::make()->success()
            ->title(__(':name saved', ['name' => $property->name]))
            ->body(UnitResource::canCreate() ? __('Now add its units.') : null);
    }
}
