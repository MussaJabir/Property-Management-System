<?php

namespace App\Filament\Operator\Resources\Properties\Pages;

use App\Filament\Operator\Concerns\ShowsPlanUsage;
use App\Filament\Operator\Resources\Locations\LocationResource;
use App\Filament\Operator\Resources\Properties\PropertyResource;
use App\Services\Plans\PlanUsage;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListProperties extends ListRecords
{
    use ShowsPlanUsage;

    protected static string $resource = PropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('locations')
                ->label(__('Locations'))
                ->icon(Heroicon::OutlinedMapPin)
                ->color('gray')
                ->visible(fn (): bool => LocationResource::canAccess())
                ->url(fn (): string => LocationResource::getUrl()),
            $this->requestUpgradeAction(),
            CreateAction::make()
                // Filament's button only checks the policy; follow the plan limit too.
                ->visible(fn (): bool => static::getResource()::canCreate()),
        ];
    }

    protected function planResource(): string
    {
        return PlanUsage::PROPERTIES;
    }
}
