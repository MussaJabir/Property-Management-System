<?php

namespace App\Filament\Operator\Resources\Invoices\Pages;

use App\Filament\Operator\Resources\Invoices\InvoiceResource;
use App\Services\RentInvoiceGenerator;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Same as the daily run, on demand: useful right after adding
            // leases, or when automatic invoices are turned off.
            Action::make('generateRentInvoices')
                ->label(__('Create rent invoices'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->authorize(fn (): bool => InvoiceResource::canCreate())
                ->requiresConfirmation()
                ->modalHeading(__('Create this period\'s rent invoices?'))
                ->modalDescription(__('Every active lease without an invoice for its current rent period gets one, and the renter is notified. Leases already invoiced are skipped.'))
                ->modalSubmitActionLabel(__('Create invoices'))
                ->action(function (RentInvoiceGenerator $generator): void {
                    $created = $generator->generateForWorkspace();

                    Notification::make()
                        ->success()
                        ->title($created > 0
                            ? trans_choice('{1} :count rent invoice created|[2,*] :count rent invoices created', $created, ['count' => $created])
                            : __('Every active lease is already invoiced for this period'))
                        ->send();
                }),

            CreateAction::make()->label('New invoice'),
        ];
    }
}
