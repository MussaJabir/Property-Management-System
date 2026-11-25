<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Buildings\Pages;

use App\Filament\Operator\Resources\Buildings\BuildingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBuilding extends EditRecord
{
    protected static string $resource = BuildingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
