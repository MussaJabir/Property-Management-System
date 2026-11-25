<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\RentalApplications\Pages;

use App\Filament\Operator\Resources\RentalApplications\RentalApplicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRentalApplications extends ListRecords
{
    protected static string $resource = RentalApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('New application')),
        ];
    }
}
