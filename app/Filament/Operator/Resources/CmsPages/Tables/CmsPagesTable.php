<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\CmsPages\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CmsPagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')
                    ->badge()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('published_at')
                    ->label('Published')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => $record->published_at !== null && $record->published_at->isPast()),
                TextColumn::make('updated_at')
                    ->label('Last updated')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('slug')
            ->paginated(false)
            ->recordActions([EditAction::make()]);
    }
}
