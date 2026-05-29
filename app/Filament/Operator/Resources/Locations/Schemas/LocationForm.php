<?php

namespace App\Filament\Operator\Resources\Locations\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Where is it?')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Location name')
                            ->placeholder('e.g. Kariakoo Heights area')
                            ->required()
                            ->maxLength(150)
                            ->helperText('A friendly label you\'ll use when adding properties.'),

                        TextInput::make('region')
                            ->placeholder('e.g. Dar es Salaam')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('district')
                            ->placeholder('e.g. Ilala')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('ward')
                            ->placeholder('e.g. Kariakoo')
                            ->maxLength(100),

                        TextInput::make('street')
                            ->placeholder('e.g. Uhuru Street')
                            ->maxLength(150)
                            ->columnSpanFull(),
                    ]),

                Section::make('Notes')
                    ->collapsed()
                    ->components([
                        Textarea::make('notes')
                            ->label(false)
                            ->placeholder('Landmarks, access notes, anything else.')
                            ->rows(3),
                    ]),
            ]);
    }
}
