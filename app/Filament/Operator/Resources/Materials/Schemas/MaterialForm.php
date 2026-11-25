<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Materials\Schemas;

use App\Models\Material;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MaterialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label(__('Material'))
                    ->required()
                    ->maxLength(150)
                    ->placeholder(__('e.g. Tap washer 1/2"')),

                Select::make('unit_of_measure')
                    ->label(__('Counted in'))
                    ->options(Material::UNITS)
                    ->default('piece')
                    ->required()
                    ->native(false),

                TextInput::make('unit_cost')
                    ->label(__('Cost of one'))
                    ->prefix('TZS')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->formatStateUsing(fn ($state) => $state === null ? null : (int) round(((int) $state) / 100))
                    ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100)),

                TextInput::make('stock_quantity')
                    ->label(__('In stock'))
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),

                TextInput::make('reorder_level')
                    ->label(__('Reorder when stock is at or below'))
                    ->numeric()
                    ->minValue(0),
            ]);
    }
}
