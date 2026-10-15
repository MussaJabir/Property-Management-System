<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Operators\Pages;

use App\Filament\Operator\Concerns\ShowsPlanUsage;
use App\Filament\Operator\Resources\Operators\OperatorResource;
use App\Services\Plans\PlanUsage;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperators extends ListRecords
{
    use ShowsPlanUsage;

    protected static string $resource = OperatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->requestUpgradeAction(),
            CreateAction::make()->label('Invite member')
                // Filament's button only checks the policy; follow the plan limit too.
                ->visible(fn (): bool => static::getResource()::canCreate()),
        ];
    }

    protected function planResource(): string
    {
        return PlanUsage::STAFF;
    }
}
