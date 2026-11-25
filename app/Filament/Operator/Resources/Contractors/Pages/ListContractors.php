<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Contractors\Pages;

use App\Filament\Operator\Resources\Contractors\ContractorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContractors extends ListRecords
{
    protected static string $resource = ContractorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
