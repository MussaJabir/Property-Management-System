<?php

namespace App\Filament\Operator\Pages;

use App\Models\Client;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Spatie\Permission\PermissionRegistrar;

/**
 * Workspace Settings — Owner-only Filament page for editing client branding,
 * contact info, and language preference.
 */
class WorkspaceSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.operator.pages.workspace-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?Client $client = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user || ! $user->tenant_id) {
            return false;
        }

        // Defensive: ensure Spatie's team context is set before hasRole().
        // The InitializeTenancyByUser middleware also does this, but canAccess
        // can be called in places where middleware hasn't run yet (sidebar
        // rendering, navigation tree building, etc.).
        app(PermissionRegistrar::class)
            ->setPermissionsTeamId($user->tenant_id);

        return $user->hasRole('owner');
    }

    public function mount(): void
    {
        $tenantId = auth()->user()?->tenant_id;

        if (! $tenantId) {
            abort(404);
        }

        $this->client = Client::find($tenantId);

        if (! $this->client) {
            abort(404);
        }

        $this->form->fill([
            'name' => $this->client->name,
            'contact_email' => $this->client->contact_email,
            'contact_phone' => $this->client->contact_phone,
            'logo_path' => $this->client->logo_path,
            'brand_primary_color' => $this->client->brand_primary_color,
            'default_locale' => $this->client->settings['default_locale'] ?? 'en',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('About your business')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Business name')
                            ->required()
                            ->maxLength(150),
                    ]),

                Section::make('Contact')
                    ->columns(2)
                    ->components([
                        TextInput::make('contact_email')
                            ->label('Email address')
                            ->email()
                            ->maxLength(150),

                        TextInput::make('contact_phone')
                            ->label('Phone number')
                            ->tel()
                            ->maxLength(20)
                            ->helperText('Country code (+255…) or local (0712…).'),
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
                            ->maxSize(2048),

                        ColorPicker::make('brand_primary_color')
                            ->label('Brand colour'),
                    ]),

                Section::make('Defaults')
                    ->components([
                        Select::make('default_locale')
                            ->label('Default language for your workspace')
                            ->options([
                                'en' => 'English',
                                'sw' => 'Kiswahili',
                            ])
                            ->default('en')
                            ->native(false),
                    ]),
            ]);
    }

    public function save(): void
    {
        if (! $this->client) {
            return;
        }

        $data = $this->form->getState();

        $settings = $this->client->settings ?? [];
        $settings['default_locale'] = $data['default_locale'] ?? 'en';

        $this->client->update([
            'name' => $data['name'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'],
            'logo_path' => $data['logo_path'],
            'brand_primary_color' => $data['brand_primary_color'],
            'settings' => $settings,
        ]);

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
