<?php

namespace App\Filament\Operator\Resources\MaintenanceRequests\Pages;

use App\Filament\Operator\Resources\Contractors\ContractorResource;
use App\Filament\Operator\Resources\MaintenanceRequests\MaintenanceRequestResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMaintenanceRequests extends ListRecords
{
    protected static string $resource = MaintenanceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('contractors')
                ->label(__('Contractors'))
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('gray')
                ->visible(fn (): bool => ContractorResource::canAccess())
                ->url(fn (): string => ContractorResource::getUrl()),
            CreateAction::make()->label('New request'),
        ];
    }
}
