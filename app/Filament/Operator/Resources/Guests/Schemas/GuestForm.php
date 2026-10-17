<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Guests\Schemas;

use App\Models\Guest;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GuestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Guest'))
                    ->columns(2)
                    ->components([
                        TextInput::make('full_name')
                            ->label(__('Full name'))
                            ->required()
                            ->maxLength(150),
                        TextInput::make('phone')
                            ->label(__('Phone'))
                            ->tel()
                            ->required()
                            ->maxLength(20),
                        TextInput::make('email')
                            ->label(__('Email'))
                            ->email()
                            ->maxLength(150),
                        TextInput::make('nationality')
                            ->label(__('Nationality'))
                            ->placeholder('TZ')
                            ->helperText(__('Two-letter country code, e.g. TZ, KE, US.'))
                            ->maxLength(2)
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null ? strtoupper($state) : null),
                    ]),

                Section::make(__('Identification'))
                    ->description(__('Stored encrypted. Visible only to staff with front desk access.'))
                    ->columns(2)
                    ->components([
                        Select::make('id_type')
                            ->label(__('ID type'))
                            ->options(Guest::idTypeOptions())
                            ->native(false),
                        TextInput::make('id_number')
                            ->label(__('ID number'))
                            ->maxLength(50),
                    ]),

                Section::make(__('Notes'))
                    ->collapsed()
                    ->components([
                        Textarea::make('notes')
                            ->hiddenLabel()
                            ->rows(3)
                            ->placeholder(__('Preferences, special requests…')),
                    ]),
            ]);
    }
}
