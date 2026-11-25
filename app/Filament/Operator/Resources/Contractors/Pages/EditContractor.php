<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Contractors\Pages;

use App\Filament\Operator\Resources\Contractors\ContractorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContractor extends EditRecord
{
    protected static string $resource = ContractorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
