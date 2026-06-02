<?php

namespace App\Filament\Operator\Resources\MaintenanceRequests\Pages;

use App\Filament\Operator\Resources\MaintenanceRequests\MaintenanceRequestResource;
use App\Models\MaintenanceRequest;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMaintenanceRequests extends ListRecords
{
    protected static string $resource = MaintenanceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New request'),
        ];
    }

    /**
     * Workflow tabs so staff see open work first.
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(MaintenanceRequest::query()->count()),

            'pending' => Tab::make('Pending')
                ->badge(MaintenanceRequest::query()->where('status', MaintenanceRequest::STATUS_PENDING)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', MaintenanceRequest::STATUS_PENDING)),

            'in_progress' => Tab::make('In progress')
                ->badge(MaintenanceRequest::query()->where('status', MaintenanceRequest::STATUS_IN_PROGRESS)->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', MaintenanceRequest::STATUS_IN_PROGRESS)),

            'completed' => Tab::make('Completed')
                ->badge(MaintenanceRequest::query()->where('status', MaintenanceRequest::STATUS_COMPLETED)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', MaintenanceRequest::STATUS_COMPLETED)),
        ];
    }
}
