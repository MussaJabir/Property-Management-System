<?php

namespace App\Filament\Operator\Resources\Units\Pages;

use App\Filament\Operator\Resources\Units\UnitResource;
use App\Models\Unit;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUnits extends ListRecords
{
    protected static string $resource = UnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Status tabs with live counts so an operator can jump straight to
     * "what's vacant" without opening the filter panel.
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(Unit::query()->count()),

            'vacant' => Tab::make('Vacant')
                ->badge(Unit::query()->where('status', Unit::STATUS_VACANT)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', Unit::STATUS_VACANT)),

            'occupied' => Tab::make('Occupied')
                ->badge(Unit::query()->where('status', Unit::STATUS_OCCUPIED)->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', Unit::STATUS_OCCUPIED)),

            'maintenance' => Tab::make('Maintenance')
                ->badge(Unit::query()->where('status', Unit::STATUS_MAINTENANCE)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', Unit::STATUS_MAINTENANCE)),
        ];
    }
}
