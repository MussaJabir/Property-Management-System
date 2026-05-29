<?php

namespace App\Filament\Operator\Resources\Renters\Schemas;

use App\Models\Renter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RenterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Who is renting?')
                    ->columns(2)
                    ->components([
                        Select::make('type')
                            ->label('Renter type')
                            ->required()
                            ->options([
                                Renter::TYPE_INDIVIDUAL => 'Individual',
                                Renter::TYPE_BUSINESS => 'Business / Company',
                            ])
                            ->default(Renter::TYPE_INDIVIDUAL)
                            ->live()
                            ->native(false),

                        TextInput::make('full_name')
                            ->label(fn (callable $get) => $get('type') === Renter::TYPE_BUSINESS
                                ? 'Contact person'
                                : 'Full name')
                            ->required()
                            ->maxLength(150),

                        TextInput::make('business_name')
                            ->label('Business / Company name')
                            ->maxLength(150)
                            ->required(fn (callable $get) => $get('type') === Renter::TYPE_BUSINESS)
                            ->visible(fn (callable $get) => $get('type') === Renter::TYPE_BUSINESS)
                            ->columnSpanFull(),
                    ]),

                Section::make('Contact details')
                    ->columns(2)
                    ->components([
                        TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->required()
                            ->placeholder('+255712345678 or 0712345678')
                            ->helperText('Tanzanian numbers are auto-formatted to international (+255…).')
                            ->maxLength(20),

                        TextInput::make('alt_phone')
                            ->label('Alternative phone')
                            ->tel()
                            ->placeholder('Optional')
                            ->maxLength(20),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(150),

                        Textarea::make('address')
                            ->label('Residential / business address')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Identification')
                    ->description('Stored encrypted. Visible only to authorized operators.')
                    ->columns(2)
                    ->components([
                        TextInput::make('nida_number')
                            ->label('NIDA number')
                            ->visible(fn (callable $get) => $get('type') === Renter::TYPE_INDIVIDUAL)
                            ->maxLength(50),

                        TextInput::make('tin_number')
                            ->label('TIN number')
                            ->maxLength(50),
                    ]),

                Section::make('Emergency contact')
                    ->columns(2)
                    ->collapsed()
                    ->components([
                        TextInput::make('emergency_contact_name')->maxLength(150),
                        TextInput::make('emergency_contact_phone')->tel()->maxLength(20),
                    ]),

                Section::make('Notes')
                    ->collapsed()
                    ->components([
                        Textarea::make('notes')
                            ->hiddenLabel()
                            ->rows(3)
                            ->placeholder('Internal notes — not visible to the renter.'),
                    ]),
            ]);
    }
}
