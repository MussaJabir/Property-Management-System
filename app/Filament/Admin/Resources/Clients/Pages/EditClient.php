<?php

namespace App\Filament\Admin\Resources\Clients\Pages;

use App\Filament\Admin\Resources\Clients\Actions\PurgeClientAction;
use App\Filament\Admin\Resources\Clients\ClientResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Delete = soft archive (recoverable). Purge = permanent wipe with
            // typed-name confirmation, only on already-archived clients.
            DeleteAction::make(),
            RestoreAction::make(),
            PurgeClientAction::make(),
        ];
    }
}
