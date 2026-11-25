<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\RentalApplications\Pages;

use App\Filament\Operator\Resources\RentalApplications\RentalApplicationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRentalApplication extends EditRecord
{
    protected static string $resource = RentalApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
