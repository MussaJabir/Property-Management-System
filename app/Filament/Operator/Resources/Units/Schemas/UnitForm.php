<?php

namespace App\Filament\Operator\Resources\Units\Schemas;

use App\Models\Property;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Which unit?')
                    ->columns(2)
                    ->components([
                        Select::make('property_id')
                            ->label('Property')
                            ->options(fn () => Property::pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),

                        TextInput::make('code')
                            ->label('Unit code')
                            ->placeholder('e.g. Room 5, Frame 2A, Shop 1')
                            ->required()
                            ->maxLength(50)
                            ->helperText('A short label that identifies this unit inside its property.'),

                        Select::make('type')
                            ->required()
                            ->options([
                                'room' => 'Room',
                                'apartment' => 'Apartment',
                                'business_frame' => 'Business frame',
                                'office' => 'Office',
                                'shop' => 'Shop',
                                'warehouse' => 'Warehouse',
                            ])
                            ->default('room')
                            ->native(false),

                        Select::make('status')
                            ->required()
                            ->options([
                                'vacant' => 'Vacant — available to rent',
                                'occupied' => 'Occupied — has an active lease',
                                'maintenance' => 'Under maintenance',
                                'reserved' => 'Reserved',
                            ])
                            ->default('vacant')
                            ->native(false),
                    ]),

                Section::make('Rent')
                    ->columns(3)
                    ->components([
                        TextInput::make('rent_amount')
                            ->label('Rent (TZS cents)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('In cents. e.g. 35,000,000 = TZS 350,000.'),

                        Select::make('rent_currency')
                            ->required()
                            ->options([
                                'TZS' => 'TZS',
                                'USD' => 'USD',
                            ])
                            ->default('TZS')
                            ->native(false),

                        Select::make('billing_cycle')
                            ->required()
                            ->options([
                                'monthly' => 'Monthly',
                                'quarterly' => 'Quarterly',
                                'annual' => 'Annual',
                            ])
                            ->default('monthly')
                            ->native(false),
                    ]),

                Section::make('Specs')
                    ->columns(3)
                    ->collapsed()
                    ->components([
                        TextInput::make('bedrooms')->numeric()->minValue(0),
                        TextInput::make('bathrooms')->numeric()->minValue(0),
                        TextInput::make('size_sqm')->label('Size (sqm)')->numeric()->minValue(0)->step(0.01),

                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
