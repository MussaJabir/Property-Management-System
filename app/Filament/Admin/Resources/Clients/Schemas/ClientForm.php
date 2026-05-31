<?php

namespace App\Filament\Admin\Resources\Clients\Schemas;

use App\Models\Plan;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('About this client')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Client name')
                            ->placeholder('e.g. Bejundas Properties')
                            ->required()
                            ->maxLength(150)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set) {
                                if ($operation === 'create' && $state) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('Workspace URL')
                            ->prefix('pms.bjptechnologies.co.tz/')
                            ->placeholder('bejundas')
                            ->required()
                            ->maxLength(60)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->disabledOn('edit')
                            ->dehydrated()
                            ->helperText(fn (string $operation): ?string => $operation === 'create'
                                ? 'Pick carefully — this becomes their permanent web address and cannot be changed later.'
                                : null)
                            ->suffixAction(
                                Action::make('copyWorkspaceUrl')
                                    ->icon('heroicon-m-clipboard-document')
                                    ->color('gray')
                                    ->tooltip('Copy full URL')
                                    ->visible(fn (string $operation): bool => $operation === 'edit')
                                    ->action(function ($state, $livewire) {
                                        $url = rtrim(config('app.url'), '/').'/'.$state;
                                        $livewire->js('navigator.clipboard.writeText('.json_encode($url).')');
                                        Notification::make()
                                            ->title('Workspace URL copied')
                                            ->body($url)
                                            ->success()
                                            ->send();
                                    })
                            ),
                    ]),

                Section::make('Contact details')
                    ->columns(2)
                    ->components([
                        TextInput::make('contact_email')
                            ->label('Email address')
                            ->placeholder('owner@example.co.tz')
                            ->email()
                            ->maxLength(150),

                        TextInput::make('contact_phone')
                            ->label('Phone number')
                            ->placeholder('+255 712 345 678')
                            ->tel()
                            ->maxLength(20)
                            ->helperText('Country code (+255…) or local (0712…) both work.'),
                    ]),

                Section::make('Subscription & access')
                    ->columns(2)
                    ->components([
                        Select::make('plan_id')
                            ->label('Subscription plan')
                            ->options(fn () => Plan::pluck('name', 'id'))
                            ->placeholder('Choose a plan')
                            ->searchable()
                            ->preload(),

                        Select::make('status')
                            ->label('Account status')
                            ->required()
                            ->options([
                                'trial' => 'Trial — evaluating',
                                'active' => 'Active — paying customer',
                                'suspended' => 'Suspended — temporarily blocked',
                                'cancelled' => 'Cancelled — no longer using PMS',
                            ])
                            ->default('trial')
                            ->native(false),

                        DateTimePicker::make('trial_ends_at')
                            ->label('Trial ends on')
                            ->displayFormat('d/m/Y H:i')
                            ->format('Y-m-d H:i:s')
                            ->seconds(false)
                            ->placeholder('Pick a date')
                            ->helperText('Only set this if the status is Trial.'),
                    ]),

                Section::make('Look & feel')
                    ->columns(2)
                    ->components([
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('local')
                            ->directory('client-logos')
                            ->imageEditor()
                            ->maxSize(2048)
                            ->helperText('Square works best (e.g. 512×512). Max 2 MB.'),

                        ColorPicker::make('brand_primary_color')
                            ->label('Brand colour')
                            ->placeholder('#0F766E'),
                    ]),

                Section::make('Owner account')
                    ->description('Optional. Fill these to provision the first operator user automatically and email them sign-in credentials.')
                    ->columns(2)
                    ->visibleOn('create')
                    ->components([
                        TextInput::make('owner_name')
                            ->label('Owner name')
                            ->placeholder('e.g. Bejus Properties Director')
                            ->dehydrated(false)
                            ->maxLength(150),
                        TextInput::make('owner_email')
                            ->label('Owner email')
                            ->email()
                            ->placeholder('owner@bejus-properties.co.tz')
                            ->dehydrated(false)
                            ->maxLength(160),
                        TextInput::make('owner_phone')
                            ->label('Owner phone')
                            ->placeholder('+255712345678')
                            ->dehydrated(false)
                            ->maxLength(30)
                            ->helperText('Leave name + email blank to skip — you can create the user manually later.'),
                    ]),

                Section::make('Internal notes (admin only)')
                    ->description('Only visible to BJP super admins. The client never sees this.')
                    ->collapsed()
                    ->components([
                        Textarea::make('settings.internal_notes')
                            ->label(false)
                            ->placeholder('Billing quirks, key contacts, sales-cycle stage, anything worth remembering.')
                            ->rows(4),
                    ]),
            ]);
    }
}
