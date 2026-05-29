<?php

namespace App\Filament\Admin\Resources\Tenants\Schemas;

use App\Models\Plan;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->description('Slug becomes the URL segment — pms.bjptechnologies.co.tz/{slug}/...')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(150)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set) {
                                if ($operation === 'create' && $state) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(60)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->helperText('Lowercase, alphanumeric + dashes. URL-safe.'),
                    ]),

                Section::make('Contact')
                    ->columns(2)
                    ->components([
                        TextInput::make('contact_email')
                            ->email()
                            ->maxLength(150),

                        TextInput::make('contact_phone')
                            ->tel()
                            ->maxLength(20)
                            ->helperText('E.164 format, e.g. +255712345678'),
                    ]),

                Section::make('Plan & Status')
                    ->columns(2)
                    ->components([
                        Select::make('plan_id')
                            ->label('Plan')
                            ->options(fn () => Plan::pluck('name', 'id'))
                            ->searchable()
                            ->preload(),

                        Select::make('status')
                            ->required()
                            ->options([
                                'trial' => 'Trial',
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('trial')
                            ->native(false),

                        DateTimePicker::make('trial_ends_at')
                            ->label('Trial ends at')
                            ->seconds(false),
                    ]),

                Section::make('Branding')
                    ->columns(2)
                    ->components([
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('local')
                            ->directory('tenant-logos')
                            ->imageEditor(),

                        ColorPicker::make('brand_primary_color')
                            ->label('Brand primary color'),
                    ]),

                Section::make('Internal notes')
                    ->collapsed()
                    ->components([
                        Textarea::make('settings.internal_notes')
                            ->label('Notes (only visible to super admins)')
                            ->rows(3),
                    ]),
            ]);
    }
}
