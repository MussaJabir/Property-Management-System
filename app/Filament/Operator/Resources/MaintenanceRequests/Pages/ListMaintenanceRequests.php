<?php

namespace App\Filament\Operator\Resources\MaintenanceRequests\Pages;

use App\Filament\Operator\Resources\MaintenanceRequests\MaintenanceRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceRequests extends ListRecords
{
    protected static string $resource = MaintenanceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New request'),
        ];
    }
}
