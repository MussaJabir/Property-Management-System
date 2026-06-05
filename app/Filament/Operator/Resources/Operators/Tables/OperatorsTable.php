<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Operators\Tables;

use App\Models\User;
use Filament\Actions\EditAction;
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
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),

                TextColumn::make('last_login_at')
                    ->label('Last sign-in')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Never')
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('name');
    }
}
