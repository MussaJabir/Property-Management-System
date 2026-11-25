<?php

declare(strict_types=1);

namespace App\Filament\Operator\Pages;

use App\Filament\Operator\Navigation\NavGroup;
use App\Models\Activity;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * The audit trail: who created, changed or deleted which record, and what
 * changed (old → new). Read-only. For owners, managers and auditors.
 */
class ActivityLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::SettingsTeam;

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'activity-log';

    protected string $view = 'filament.operator.pages.activity-log';

    /** Record types people can filter by, as people say them. */
    private const TYPES = [
        'leases' => 'Leases',
        'invoices' => 'Invoices',
        'payments' => 'Payments',
        'deposit_transactions' => 'Deposits',
        'expenses' => 'Expenses',
        'maintenance_requests' => 'Maintenance',
        'renters' => 'Renters',
        'properties' => 'Properties',
        'units' => 'Units',
    ];

    public static function getNavigationLabel(): string
    {
        return __('Activity log');
    }

    public function getTitle(): string
    {
        return __('Activity log');
    }

    public function getSubheading(): string
    {
        return __('Who did what, and when. Nothing here can be changed.');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->tenant_id) {
            return false;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        return $user->hasAnyRole(['owner', 'manager', 'auditor']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query()->with('causer'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('When'))->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('causer.name')->label(__('Who'))->placeholder(__('System')),
                TextColumn::make('what')
                    ->label(__('What'))
                    ->state(fn (Activity $record): string => self::describe($record)),
                TextColumn::make('changes')
                    ->label(__('Changes'))
                    ->state(fn (Activity $record): string => self::changes($record))
                    ->wrap()
                    ->limit(240),
            ])
            ->filters([
                SelectFilter::make('log_name')->label(__('Record type'))->options(collect(self::TYPES)->map(fn (string $label): string => __($label))->all()),
                SelectFilter::make('causer_id')->label(__('Person'))
                    ->options(fn (): array => User::query()->where('tenant_id', auth()->user()?->tenant_id)->where('type', User::TYPE_OPERATOR)->orderBy('name')->pluck('name', 'id')->all()),
                Filter::make('dates')
                    ->schema([
                        DatePicker::make('from')->label(__('From'))->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label(__('To'))->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->paginated([25, 50, 100]);
    }

    /** "Changed payment" / "Created lease" */
    private static function describe(Activity $activity): string
    {
        $type = Str::singular(mb_strtolower(__(self::TYPES[$activity->log_name] ?? Str::headline((string) $activity->log_name))));

        return match ($activity->event) {
            'created' => __('Created :type', ['type' => $type]),
            'deleted' => __('Deleted :type', ['type' => $type]),
            'restored' => __('Restored :type', ['type' => $type]),
            default => __('Changed :type', ['type' => $type]),
        };
    }

    /** "status: completed → reversed · reversal_reason: — → Cheque bounced" */
    private static function changes(Activity $activity): string
    {
        $changes = $activity->attribute_changes;
        $new = (array) ($changes['attributes'] ?? []);
        $old = (array) ($changes['old'] ?? []);

        return collect($new)
            ->map(fn ($value, string $field): string => $activity->event === 'updated'
                ? Str::headline($field).': '.self::value($old[$field] ?? null).' → '.self::value($value)
                : Str::headline($field).': '.self::value($value))
            ->implode(' · ');
    }

    private static function value(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '—',
            is_bool($value) => $value ? __('yes') : __('no'),
            is_array($value) => (string) json_encode($value),
            default => Str::limit((string) $value, 60),
        };
    }
}
