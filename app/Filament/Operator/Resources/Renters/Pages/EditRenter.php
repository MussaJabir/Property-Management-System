<?php

namespace App\Filament\Operator\Resources\Renters\Pages;

use App\Filament\Operator\Resources\Renters\RenterResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditRenter extends EditRecord
{
    protected static string $resource = RenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
