<?php

namespace App\Filament\Operator\Resources\Units\Schemas;

use App\Models\Property;
use App\Models\Unit;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
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
                            ->label('Rent')
                            ->prefix(fn (callable $get) => $get('rent_currency') ?? 'TZS')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->step(1)
                            ->default(0)
                            ->placeholder('350000')
                            ->helperText('Amount in whole shillings — e.g. 350000 for TZS 350,000.')
                            // Form shows whole shillings; DB stores cents for precision.
                            ->dehydrateStateUsing(fn ($state) => $state === null ? 0 : (int) round(((float) $state) * 100))
                            ->afterStateHydrated(function (TextInput $component, $state): void {
                                if ($state !== null) {
                                    $component->state(((int) $state) / 100);
                                }
                            }),

                        Select::make('rent_currency')
                            ->required()
                            ->options([
                                'TZS' => 'TZS',
                                'USD' => 'USD',
                            ])
                            ->default('TZS')
                            ->live()
                            ->native(false),

                        Select::make('billing_cycle')
                            ->label('Billing cycle')
                            ->required()
                            ->options([
                                'monthly' => 'Monthly',
                                'quarterly' => 'Every 3 months (Quarterly)',
                                'semi_annual' => 'Every 6 months',
                                'annual' => 'Yearly',
                                'custom' => 'Custom — pick the months',
                            ])
                            ->default('monthly')
                            ->live()
                            ->native(false),

                        TextInput::make('billing_cycle_months')
                            ->label('Custom cycle (months)')
                            ->placeholder('e.g. 9')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->step(1)
                            ->required(fn (callable $get) => $get('billing_cycle') === 'custom')
                            ->visible(fn (callable $get) => $get('billing_cycle') === 'custom')
                            ->helperText('How often rent is invoiced, in months.')
                            ->columnSpanFull(),
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

                Section::make('Amenities')
                    ->description('What does this unit offer? These show as tags on the public listing and detail page, and renters can filter by them.')
                    ->collapsed()
                    ->components([
                        CheckboxList::make('amenities')
                            ->label('')
                            ->options(Unit::amenityOptions())
                            ->columns(3)
                            ->gridDirection('row')
                            ->bulkToggleable()
                            ->columnSpanFull(),
                    ]),

                Section::make('Photos')
                    ->description('Show off this specific unit — living area, kitchen, bedroom, bathroom. The first photo is the cover. If you add none, the property\'s photos are used automatically.')
                    ->components([
                        SpatieMediaLibraryFileUpload::make('photos')
                            ->label('Unit photos')
                            ->collection('photos')
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->imageEditor()
                            ->maxSize(5120)
                            ->panelLayout('grid')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
