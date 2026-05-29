<?php

namespace App\Filament\Operator\Resources\Renters\Pages;

use App\Filament\Operator\Resources\Renters\RenterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRenters extends ListRecords
{
    protected static string $resource = RenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
