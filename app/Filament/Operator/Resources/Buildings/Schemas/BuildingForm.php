<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Buildings\Schemas;

use App\Models\Property;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BuildingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('property_id')
                    ->label(__('Property'))
                    ->options(fn () => Property::query()->orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload(),

                TextInput::make('name')
                    ->label(__('Building name'))
                    ->required()
                    ->maxLength(100)
                    ->placeholder(__('e.g. Block A')),

                TextInput::make('floors')
                    ->label(__('Number of floors'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(200),

                Textarea::make('notes')
                    ->label(__('Notes'))
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
