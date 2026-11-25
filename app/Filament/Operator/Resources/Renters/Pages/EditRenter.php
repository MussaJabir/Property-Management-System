<?php

namespace App\Filament\Operator\Resources\Renters\Pages;

use App\Filament\Operator\Actions\RecordPaymentAction;
use App\Filament\Operator\Resources\Renters\RenterResource;
use App\Models\Invoice;
use App\Models\Renter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
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
            Action::make('statement')
                ->label(__('Statement'))
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('gray')
                ->url(fn (Renter $record): string => RenterResource::getUrl('statement', ['record' => $record])),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
