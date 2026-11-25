<?php

namespace App\Filament\Operator\Resources\MaintenanceRequests\Pages;

use App\Filament\Operator\Resources\Contractors\ContractorResource;
use App\Filament\Operator\Resources\MaintenanceRequests\MaintenanceRequestResource;
use App\Filament\Operator\Resources\MaintenanceSchedules\MaintenanceScheduleResource;
use App\Filament\Operator\Resources\Materials\MaterialResource;
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
            Action::make('schedules')
                ->label(__('Schedules'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->visible(fn (): bool => MaintenanceScheduleResource::canAccess())
                ->url(fn (): string => MaintenanceScheduleResource::getUrl()),
            Action::make('materials')
                ->label(__('Materials'))
                ->icon(Heroicon::OutlinedCube)
                ->color('gray')
                ->visible(fn (): bool => MaterialResource::canAccess())
                ->url(fn (): string => MaterialResource::getUrl()),
            CreateAction::make()->label('New request'),
        ];
    }
}
