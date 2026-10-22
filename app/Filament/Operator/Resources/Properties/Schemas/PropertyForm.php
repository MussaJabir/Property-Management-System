<?php

namespace App\Filament\Operator\Resources\Properties\Schemas;

use App\Models\Location;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PropertyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            // One column, top to bottom, in the order it is filled in.
            ->columns(1)
            ->components([
                Section::make('About this property')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Property name')
                            ->placeholder('e.g. Kariakoo Heights')
                            ->required()
                            ->maxLength(150),

                        Select::make('location_id')
                            ->label('Location')
                            // "Njiro · Arusha City, Arusha", so same-named areas differ.
                            ->options(fn () => Location::query()->orderBy('name')->get()
                                ->mapWithKeys(fn (Location $location): array => [
                                    $location->id => $location->name.' · '.collect([$location->district, $location->region])->filter()->implode(', '),
                                ]))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->createOptionModalHeading(__('Add a location'))
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label(__('Area'))
                                    ->placeholder(__('e.g. Njiro, Mikocheni, Kariakoo'))
                                    ->required(),
                                TextInput::make('district')
                                    ->label(__('District'))
                                    ->placeholder(__('e.g. Arusha City, Kinondoni'))
                                    ->required(),
                                TextInput::make('region')
                                    ->label(__('Region'))
                                    ->placeholder(__('e.g. Arusha, Dar es Salaam'))
                                    ->required(),
                            ])
                            ->createOptionUsing(fn (array $data) => Location::create($data)->id),

                        Select::make('type')
                            ->required()
                            ->options([
                                'residential' => 'Residential — homes / apartments',
                                'commercial' => 'Commercial — shops / offices / business frames',
                                'mixed' => 'Mixed — both residential and commercial',
                            ])
                            ->default('residential')
                            ->native(false),

                        Select::make('status')
                            ->required()
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive — hidden from listings',
                            ])
                            ->default('active')
                            ->native(false)
                            // New properties start active; change it later.
                            ->hiddenOn('create'),

                        Textarea::make('address')
                            ->label('Street address')
                            ->placeholder('Building number, street, any extra location details.')
                            ->rows(2)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->placeholder('Anything renters or staff should know.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Photos')
                    ->description('Square or landscape photos work best. Max 5 MB each.')
                    ->collapsed(fn (string $operation): bool => $operation === 'create')
                    ->components([
                        SpatieMediaLibraryFileUpload::make('photos')
                            ->hiddenLabel()
                            ->collection('photos')
                            ->multiple()
                            ->reorderable()
                            ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->imageEditor()
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
