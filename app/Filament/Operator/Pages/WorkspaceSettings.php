<?php

namespace App\Filament\Operator\Pages;

use App\Models\Client;
use App\Models\Subscription;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Workspace Settings — Owner-only Filament page. Consolidates everything a
 * landlord configures into one tabbed screen: business profile, branding,
 * preferences, account security (password), and a read-only view of their
 * PMS subscription.
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

    public ?Subscription $subscription = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user || ! $user->tenant_id) {
            return false;
        }

        // Defensive: ensure Spatie's team context is set before hasRole().
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

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

        $this->subscription = $this->client->subscriptions()
            ->with('plan')
            ->latest('started_at')
            ->first();

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
                Tabs::make('Settings')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Business')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Business name')
                                    ->required()
                                    ->maxLength(150),

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

                        Tab::make('Branding')
                            ->icon('heroicon-o-swatch')
                            ->schema([
                                FileUpload::make('logo_path')
                                    ->label('Logo')
                                    ->image()
                                    ->disk('local')
                                    ->directory('client-logos')
                                    ->imageEditor()
                                    ->maxSize(2048),

                                ColorPicker::make('brand_primary_color')
                                    ->label('Brand colour')
                                    ->helperText('Used across your public website and renter portal.'),
                            ]),

                        Tab::make('Preferences')
                            ->icon('heroicon-o-language')
                            ->schema([
                                Select::make('default_locale')
                                    ->label('Default language for your workspace')
                                    ->options([
                                        'en' => 'English',
                                        'sw' => 'Kiswahili',
                                    ])
                                    ->default('en')
                                    ->native(false),
                            ]),

                        Tab::make('Security')
                            ->icon('heroicon-o-lock-closed')
                            ->schema([
                                Placeholder::make('security_note')
                                    ->label('')
                                    ->content('Change the password for your own account. Leave blank to keep your current password.'),

                                TextInput::make('new_password')
                                    ->label('New password')
                                    ->password()
                                    ->revealable()
                                    ->minLength(8)
                                    ->confirmed()
                                    ->autocomplete('new-password')
                                    ->dehydrated(false),

                                TextInput::make('new_password_confirmation')
                                    ->label('Confirm new password')
                                    ->password()
                                    ->revealable()
                                    ->autocomplete('new-password')
                                    ->dehydrated(false),
                            ]),

                        Tab::make('Subscription')
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                Placeholder::make('plan')
                                    ->label('Current plan')
                                    ->content(fn (): string => $this->subscription?->plan?->name ?? '—'),

                                Placeholder::make('sub_status')
                                    ->label('Status')
                                    ->content(fn (): string => ucfirst($this->subscription?->status ?? 'none')),

                                Placeholder::make('renews')
                                    ->label('Renews / expires')
                                    ->content(fn (): string => $this->subscription?->ends_at?->format('d/m/Y') ?? '—'),

                                Placeholder::make('sub_help')
                                    ->label('')
                                    ->content('To change your plan or settle your subscription, contact the BJP Technologies team.'),
                            ]),
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

        // Security tab: update the signed-in user's password if a new one was
        // entered. Fields are dehydrated(false), so read raw form state.
        $newPassword = $this->data['new_password'] ?? null;
        if (filled($newPassword)) {
            $user = auth()->user();
            $user->forceFill([
                'password' => Hash::make($newPassword),
                'must_change_password' => false,
            ])->save();

            $this->data['new_password'] = null;
            $this->data['new_password_confirmation'] = null;
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
