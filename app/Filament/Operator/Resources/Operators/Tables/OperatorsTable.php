<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Operators\Tables;

use App\Models\User;
use App\Services\Admin\OperatorProvisioner;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class OperatorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->weight('semibold'),

                TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('role')
                    ->badge()
                    ->getStateUsing(fn (User $record): string => $record->roles->pluck('name')->first() ?? '—')
                    ->formatStateUsing(fn (string $state): string => $state === '—' ? $state : Str::headline($state)),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Active',
                        'pending_activation' => 'Pending',
                        'disabled' => 'Disabled',
                        default => Str::headline($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending_activation' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('last_login_at')
                    ->label('Last sign-in')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Never')
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('resendInvite')
                    ->label('Resend invite')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->visible(fn (User $record): bool => $record->status === User::STATUS_PENDING_ACTIVATION)
                    ->requiresConfirmation()
                    ->modalHeading('Resend activation invite')
                    ->modalDescription('Issues a fresh activation link and emails it again. Any previous link stops working.')
                    ->action(function (User $record): void {
                        $url = app(OperatorProvisioner::class)->resend($record);

                        Notification::make()
                            ->title('Invite resent')
                            ->body('Emailed to '.$record->email.'. To share directly, copy this link:'."\n\n".$url)
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->defaultSort('name');
    }
}
