<?php

namespace App\Filament\Admin\Resources\Clients\Actions;

use App\Models\Client;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * The deliberate, irreversible "Purge permanently" action.
 *
 * Only offered on already-archived (soft-deleted) clients — you must archive
 * before you can purge, so a destructive wipe can never be one mis-click away.
 * Confirmation requires typing the client's exact name. The force-delete it
 * triggers runs ClientPurger (via the Client observer) to remove B2 files and
 * roles, then the FK cascade wipes the rest.
 *
 * Shared by the table row action and the edit-page header so both surfaces
 * behave identically.
 */
class PurgeClientAction
{
    public static function make(): Action
    {
        return Action::make('purge')
            ->label('Purge permanently')
            ->icon('heroicon-o-fire')
            ->color('danger')
            ->visible(fn (Client $record): bool => $record->trashed())
            ->modalHeading('Purge client permanently')
            ->modalDescription(fn (Client $record): string => "This permanently erases \"{$record->name}\" and EVERYTHING it owns — properties, units, renters, leases, invoices, payments, receipts, expenses, maintenance, website pages, staff accounts, and every uploaded file. This cannot be undone.")
            ->modalSubmitActionLabel('Purge forever')
            ->modalIcon('heroicon-o-fire')
            ->schema([
                TextInput::make('confirmation')
                    ->label(fn (Client $record): string => "Type the client name to confirm: {$record->name}")
                    ->required()
                    ->autocomplete(false)
                    ->rule(fn (Client $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        if ($value !== $record->name) {
                            $fail('The name does not match. Type it exactly to confirm.');
                        }
                    }),
            ])
            ->action(function (Client $record): void {
                $name = $record->name;

                $record->forceDelete();

                Notification::make()
                    ->success()
                    ->title('Client purged')
                    ->body("\"{$name}\" and all its data have been permanently removed.")
                    ->send();
            });
    }
}
