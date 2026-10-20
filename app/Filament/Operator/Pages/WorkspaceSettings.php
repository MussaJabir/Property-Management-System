<?php

namespace App\Filament\Operator\Pages;

use App\Exceptions\SelcomUnavailable;
use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Support\ShortStays;
use App\Models\Client;
use App\Models\SelcomAccount;
use App\Models\Subscription;
use App\Services\Payments\Selcom\SelcomClient;
use App\Services\RentInvoiceGenerator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Workspace Settings — Owner-only Filament page. Consolidates everything a
 * landlord configures into one tabbed screen: business profile, branding,
 * preferences, Selcom payment keys, account security (password), and a
 * read-only view of their PMS subscription.
 */
class WorkspaceSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Settings';

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::SettingsTeam;

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.operator.pages.workspace-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?Client $client = null;

    public ?Subscription $subscription = null;

    public ?SelcomAccount $selcom = null;

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

        $this->selcom = SelcomAccount::forClient($this->client);

        $this->form->fill([
            'name' => $this->client->name,
            'contact_email' => $this->client->contact_email,
            'contact_phone' => $this->client->contact_phone,
            'logo_path' => $this->client->logo_path,
            'brand_primary_color' => $this->client->brand_primary_color,
            'default_locale' => $this->client->settings['default_locale'] ?? 'en',
            'auto_invoices' => RentInvoiceGenerator::enabledFor($this->client),
            'check_in_time' => ShortStays::checkInTime($this->client),
            'check_out_time' => ShortStays::checkOutTime($this->client),
            'selcom_enabled' => $this->selcom !== null && $this->selcom->enabled,
            'selcom_mode' => $this->selcom->mode ?? SelcomAccount::MODE_SANDBOX,
            'selcom_vendor' => $this->selcom?->vendor,
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
                                    ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
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

                                Grid::make(2)
                                    ->visible(fn (): bool => ShortStays::enabled())
                                    ->schema([
                                        TimePicker::make('check_in_time')
                                            ->label(__('Guests check in from'))
                                            ->seconds(false)
                                            ->native(false)
                                            ->format('H:i')
                                            ->displayFormat('H:i'),
                                        TimePicker::make('check_out_time')
                                            ->label(__('Guests check out by'))
                                            ->seconds(false)
                                            ->native(false)
                                            ->format('H:i')
                                            ->displayFormat('H:i')
                                            ->helperText(__('Guests still in after this on their last day show as late at the front desk.')),
                                    ]),

                                Toggle::make('auto_invoices')
                                    ->label(__('Create rent invoices automatically'))
                                    ->helperText(__('Each active lease gets its rent invoice when a new period starts, and its first invoice (with the deposit) when it is activated.')),
                            ]),

                        Tab::make(__('Payments'))
                            ->icon('heroicon-o-device-phone-mobile')
                            ->badge(SelcomAccount::platformReady() ? null : __('Coming soon'))
                            ->schema(SelcomAccount::platformReady() ? $this->paymentsTab() : [
                                Placeholder::make('selcom_coming_soon')
                                    ->label('')
                                    ->content(__('Automatic mobile money payments are coming soon: renters and guests will get a payment prompt on their phone, and payments will be recorded for you. Until then, record payments with "Record payment".')),
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
                                    ->content(fn (): string => $this->subscription->plan->name ?? '—'),

                                Placeholder::make('sub_status')
                                    ->label('Status')
                                    ->content(fn (): string => ucfirst($this->subscription->status ?? 'none')),

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

    /**
     * Selcom keys. The key and secret are never shown again once saved;
     * leaving them blank keeps the saved ones. Payments can only be turned
     * on after Test connection has worked with the saved keys.
     *
     * @return array<int, mixed>
     */
    private function paymentsTab(): array
    {
        $savedHint = fn (): ?string => $this->selcom?->hasCredentials() ? __('Saved. Leave blank to keep it.') : null;

        return [
            Placeholder::make('selcom_intro')
                ->label('')
                ->content(__('Let renters and guests pay invoices by mobile money through your own Selcom merchant account. Money goes straight to your Selcom account; ask Selcom for your vendor ID, API key and API secret.')),

            Placeholder::make('selcom_status')
                ->label(__('Connection'))
                ->content(fn (): string => $this->selcomStatus()),

            Select::make('selcom_mode')
                ->label(__('Account type'))
                ->options([
                    SelcomAccount::MODE_SANDBOX => __('Sandbox (testing, no real money)'),
                    SelcomAccount::MODE_LIVE => __('Live (real payments)'),
                ])
                ->default(SelcomAccount::MODE_SANDBOX)
                ->required()
                ->native(false),

            TextInput::make('selcom_vendor')
                ->label(__('Vendor ID'))
                ->maxLength(50),

            Grid::make(2)->schema([
                TextInput::make('selcom_api_key')
                    ->label(__('API key'))
                    ->password()
                    ->revealable()
                    ->autocomplete('off')
                    ->maxLength(255)
                    ->placeholder($savedHint),
                TextInput::make('selcom_api_secret')
                    ->label(__('API secret'))
                    ->password()
                    ->revealable()
                    ->autocomplete('off')
                    ->maxLength(255)
                    ->placeholder($savedHint),
            ]),

            Actions::make([
                Action::make('testSelcom')
                    ->label(__('Test connection'))
                    ->icon('heroicon-o-signal')
                    ->color('gray')
                    ->disabled(fn (): bool => ! $this->selcom?->hasCredentials())
                    ->tooltip(fn (): ?string => $this->selcom?->hasCredentials() ? null : __('Save your keys first.'))
                    ->action(fn () => $this->testSelcom()),
            ]),

            Toggle::make('selcom_enabled')
                ->label(__('Take payments through Selcom'))
                ->helperText(__('Shows "Request payment" on invoices. Works once Test connection has passed.')),
        ];
    }

    private function selcomStatus(): string
    {
        if (! $this->selcom?->hasCredentials()) {
            return __('Not set up');
        }

        if ($this->selcom->verified_at === null) {
            return __('Keys saved, not tested yet');
        }

        return __('Connected (tested :date)', ['date' => $this->selcom->verified_at->format('d/m/Y H:i')]);
    }

    /**
     * Ask Selcom for today's orders with the saved keys: a read-only call
     * that only works when the vendor, key and secret are right.
     */
    public function testSelcom(): void
    {
        $account = $this->selcom;

        if ($account === null || ! $account->hasCredentials()) {
            Notification::make()->warning()->title(__('Save your Selcom keys first'))->send();

            return;
        }

        try {
            $today = Carbon::today();
            $response = SelcomClient::for($account)->listOrders($today, $today);
        } catch (SelcomUnavailable $e) {
            Notification::make()->danger()->title(__('Connection failed'))->body($e->getMessage())->send();

            return;
        }

        if (! $response->successful()) {
            Notification::make()->danger()->title(__('Selcom refused the keys'))
                ->body(trim($response->resultCode.' '.$response->message))->send();

            return;
        }

        $account->forceFill(['verified_at' => now()])->save();

        Notification::make()->success()->title(__('Connected to Selcom'))
            ->body(__('You can now turn on payments through Selcom.'))->send();
    }

    /**
     * Save the Payments tab. Changing the account type or any key clears
     * the last test, and payments stay off until a test passes.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveSelcom(array $data): void
    {
        // Coming soon: the tab has no fields, so there is nothing to save.
        if (! SelcomAccount::platformReady()) {
            return;
        }

        $vendor = filled($data['selcom_vendor'] ?? null) ? trim((string) $data['selcom_vendor']) : null;
        $key = $this->data['selcom_api_key'] ?? null;
        $secret = $this->data['selcom_api_secret'] ?? null;
        $wantsEnabled = (bool) ($data['selcom_enabled'] ?? false);

        $account = $this->selcom ?? new SelcomAccount;

        if (! $account->exists && $vendor === null && blank($key) && blank($secret) && ! $wantsEnabled) {
            return;
        }

        $account->forceFill(['tenant_id' => $this->client?->getKey()]);
        $account->mode = (string) ($data['selcom_mode'] ?? SelcomAccount::MODE_SANDBOX);
        $account->vendor = $vendor;
        if (filled($key)) {
            $account->api_key = trim((string) $key);
        }
        if (filled($secret)) {
            $account->api_secret = trim((string) $secret);
        }

        // The encrypted casts re-encrypt on every set, so compare the
        // plain values rather than trusting isDirty() for the key/secret.
        $changed = $account->isDirty(['mode', 'vendor'])
            || (filled($key) && $key !== $account->getOriginal('api_key'))
            || (filled($secret) && $secret !== $account->getOriginal('api_secret'));

        if ($changed) {
            $account->verified_at = null;
        }

        $account->enabled = $wantsEnabled && $account->hasCredentials() && $account->verified_at !== null;
        $account->save();

        $this->selcom = $account;
        $this->data['selcom_api_key'] = null;
        $this->data['selcom_api_secret'] = null;
        $this->data['selcom_enabled'] = $account->enabled;

        if ($wantsEnabled && ! $account->enabled) {
            Notification::make()->warning()->title(__('Selcom payments are still off'))
                ->body(__('Press Test connection, then turn payments on and save again.'))->send();
        }
    }

    public function save(): void
    {
        if (! $this->client) {
            return;
        }

        $data = $this->form->getState();

        $settings = $this->client->settings ?? [];
        $settings['default_locale'] = $data['default_locale'] ?? 'en';
        $settings['auto_invoices'] = (bool) ($data['auto_invoices'] ?? true);
        if (ShortStays::enabled()) {
            $settings['check_in_time'] = $data['check_in_time'] ?? ShortStays::DEFAULT_CHECK_IN;
            $settings['check_out_time'] = $data['check_out_time'] ?? ShortStays::DEFAULT_CHECK_OUT;
        }

        $this->client->update([
            'name' => $data['name'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'],
            'logo_path' => $data['logo_path'],
            'brand_primary_color' => $data['brand_primary_color'],
            'settings' => $settings,
        ]);

        $this->saveSelcom($data);

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
