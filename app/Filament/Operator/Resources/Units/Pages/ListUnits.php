<?php

namespace App\Filament\Operator\Resources\Units\Pages;

use App\Filament\Operator\Concerns\ShowsPlanUsage;
use App\Filament\Operator\Resources\Units\UnitResource;
use App\Services\Plans\PlanUsage;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUnits extends ListRecords
{
    use ShowsPlanUsage;

    protected static string $resource = UnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->requestUpgradeAction(),
            CreateAction::make()
                // Filament's button only checks the policy; follow the plan limit too.
                ->visible(fn (): bool => static::getResource()::canCreate()),
        ];
    }

    protected function planResource(): string
    {
        return PlanUsage::UNITS;
    }
}
