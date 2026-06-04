<?php

namespace App\Filament\Operator\Resources\Renters\Tables;

use App\Models\Lease;
use App\Models\Renter;
use App\Services\Portal\RenterPortalAccountProvisioner;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RentersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable(query: function ($query, string $search) {
                        $query->where('full_name', 'ilike', "%{$search}%")
                            ->orWhere('business_name', 'ilike', "%{$search}%");
                    })
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('full_name', $direction))
                    ->weight('semibold'),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'individual' => 'Individual',
                        'business' => 'Business',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'individual' => 'gray',
                        'business' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('phone')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('email')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('active_leases_count')
                    ->label('Active leases')
                    ->counts([
                        'leases' => fn ($query) => $query->where('status', Lease::STATUS_ACTIVE),
                    ])
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'individual' => 'Individual',
                        'business' => 'Business',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('resendActivation')
                    ->label('Portal activation')
                    ->icon('heroicon-o-key')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Send portal activation link')
                    ->modalDescription('Issues a fresh activation link (and invalidates any previous one). The renter sets their own password through the link. Emailed automatically if an address is on file.')
                    ->modalSubmitActionLabel('Send link')
                    ->action(function (Renter $record): void {
                        $url = app(RenterPortalAccountProvisioner::class)->resendActivation($record);

                        if ($url === null) {
                            Notification::make()
                                ->title('Could not create a portal account')
                                ->body('Add a phone number to this renter first — it is their portal sign-in identifier.')
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Activation link issued')
                            ->body('Emailed to the renter if an address is on file. To share it directly, copy this link:'."\n\n".$url)
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('full_name');
    }
}
