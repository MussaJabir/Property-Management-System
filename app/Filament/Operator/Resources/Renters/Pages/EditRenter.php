<?php

namespace App\Filament\Operator\Resources\Renters\Pages;

use App\Filament\Operator\Actions\RecordPaymentAction;
use App\Filament\Operator\Resources\Renters\RenterResource;
use App\Models\Invoice;
use App\Models\Renter;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Builder;

class EditRenter extends EditRecord
{
    protected static string $resource = RenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Only when this renter owes something.
            RecordPaymentAction::make()
                ->visible(fn (Renter $record): bool => Invoice::query()
                    ->outstanding()
                    ->whereHas('lease', fn (Builder $q) => $q->where('renter_id', $record->getKey()))
                    ->exists()),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
