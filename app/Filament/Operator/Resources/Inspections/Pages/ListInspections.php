<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Inspections\Pages;

use App\Filament\Operator\Resources\Inspections\InspectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInspections extends ListRecords
{
    protected static string $resource = InspectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('New inspection')),
        ];
    }
}
