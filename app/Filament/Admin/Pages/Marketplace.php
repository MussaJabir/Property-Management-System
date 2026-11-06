<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Client;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Services\Marketplace\ListingSearch;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Stancl\Tenancy\Database\TenantScope;

/**
 * BJP's view of the PMS marketplace (/browse): every unit a landlord has
 * listed, across clients. Boosts are switched on here by hand once the
 * landlord has paid by mobile money (no payment code yet), and demo
 * listings can be hidden from the public page in one click.
 */
class Marketplace extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 12;

    protected static ?string $title = 'Marketplace';

    protected string $view = 'filament.admin.pages.marketplace';

    public const BOOST_DAYS = [7 => '7 days', 14 => '14 days', 30 => '30 days'];

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Unit::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('on_marketplace', true)
                ->with(['tenant', 'property.location']))
            ->columns([
                TextColumn::make('code')
                    ->label('Unit')
                    ->description(fn (Unit $record): ?string => $record->property?->name)
                    ->searchable()
                    ->weight('semibold'),
                TextColumn::make('tenant.name')
                    ->label('Client')
                    ->badge(fn (Unit $record): bool => (bool) $record->ownerClient()?->is_demo)
                    ->color('gray')
                    ->description(fn (Unit $record): ?string => $record->ownerClient()?->is_demo ? 'Demo workspace' : null),
                TextColumn::make('area')
                    ->label('Area')
                    ->state(fn (Unit $record): ?string => $record->property?->location
                        ? collect([$record->property->location->name, $record->property->location->region])->filter()->implode(', ')
                        : null),
                TextColumn::make('price')
                    ->label('Price')
                    ->state(fn (Unit $record): array => array_map(
                        fn (array $price): string => $price['currency'].' '.$price['amount'].' / '.$price['per'],
                        $record->publicPrices(),
                    ))
                    ->listWithLineBreaks(),
                IconColumn::make('shown')
                    ->label('On /browse')
                    ->boolean()
                    ->state(fn (Unit $record): bool => $this->shownIds()->contains($record->getKey()))
                    ->tooltip(fn (Unit $record): ?string => $this->shownIds()->contains($record->getKey())
                        ? null
                        : 'Not shown: the unit is let, or the client is not active on a plan with the marketplace, or demo listings are hidden.'),
                TextColumn::make('featured_until')
                    ->label('Featured until')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->color(fn (Unit $record): string => $record->isFeatured() ? 'warning' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('featured')
                    ->label('Featured now')
                    ->queries(
                        true: fn (Builder $q) => $q->where('featured_until', '>', now()),
                        false: fn (Builder $q) => $q->where(fn (Builder $w) => $w->whereNull('featured_until')->orWhere('featured_until', '<=', now())),
                    ),
                SelectFilter::make('tenant_id')
                    ->label('Client')
                    ->options(fn (): array => Client::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('feature')
                    ->label(fn (Unit $record): string => $record->isFeatured() ? 'Extend' : 'Feature')
                    ->icon(Heroicon::OutlinedStar)
                    ->color('warning')
                    ->hidden(fn (Unit $record): bool => (bool) $record->ownerClient()?->is_demo)
                    ->modalHeading(fn (Unit $record): string => 'Feature '.$record->publicTitle())
                    ->modalDescription('Switch this on after the landlord has paid. The unit shows first on /browse with a Featured badge.')
                    ->schema([
                        ToggleButtons::make('days')
                            ->label('For how long')
                            ->options(self::BOOST_DAYS)
                            ->default(7)
                            ->inline()
                            ->required(),
                        TextInput::make('reference')
                            ->label('Payment reference (M-Pesa, Airtel…)')
                            ->placeholder('e.g. QK7HX2P9TL')
                            ->required()
                            ->maxLength(60),
                    ])
                    ->action(fn (Unit $record, array $data) => $this->feature($record, (int) $data['days'], (string) $data['reference'])),
                Action::make('endFeature')
                    ->label('End')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('gray')
                    ->visible(fn (Unit $record): bool => $record->isFeatured())
                    ->requiresConfirmation()
                    ->modalHeading('End this boost now?')
                    ->action(function (Unit $record): void {
                        $record->forceFill(['featured_until' => now()])->save();
                        $this->log($record, 'Marketplace boost ended');
                        Notification::make()->success()->title('Boost ended')->send();
                    }),
            ])
            ->defaultSort('featured_until', 'desc')
            ->emptyStateHeading('No units listed yet')
            ->emptyStateDescription('Landlords list units from their dashboard: Units → "Show this unit on the PMS marketplace".');
    }

    protected function getHeaderActions(): array
    {
        $hidden = $this->demoHidden();

        return [
            Action::make('openBrowse')
                ->label('Open /browse')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(route('marketplace.browse'), shouldOpenInNewTab: true),
            Action::make('toggleDemo')
                ->label($hidden ? 'Show demo listings' : 'Hide demo listings')
                ->icon($hidden ? Heroicon::OutlinedEye : Heroicon::OutlinedEyeSlash)
                ->color($hidden ? 'gray' : 'warning')
                ->requiresConfirmation()
                ->modalDescription($hidden
                    ? 'Demo workspaces show again on /browse, after real listings, with a Demo badge.'
                    : 'Demo workspaces disappear from /browse. Use this once real listings fill the page.')
                ->action(function () use ($hidden): void {
                    SystemSetting::put(ListingSearch::HIDE_DEMO_SETTING, ! $hidden);
                    Notification::make()->success()->title($hidden ? 'Demo listings shown' : 'Demo listings hidden')->send();
                }),
        ];
    }

    public function getSubheading(): ?string
    {
        $shown = app(ListingSearch::class)->listed()->count();
        $featured = app(ListingSearch::class)->listed()->where('units.featured_until', '>', now())->count();

        return "{$shown} on /browse now · {$featured} featured".($this->demoHidden() ? ' · demo listings hidden' : '');
    }

    /** Starts now, or adds to a boost that is still running. */
    public function feature(Unit $unit, int $days, string $reference): void
    {
        $from = $unit->isFeatured() ? $unit->featured_until : now();
        $unit->forceFill(['featured_until' => $from?->copy()->addDays($days)])->save();

        $this->log($unit, 'Marketplace boost', ['days' => $days, 'reference' => trim($reference), 'until' => $unit->featured_until?->toIso8601String()]);

        Notification::make()
            ->success()
            ->title('Featured until '.$unit->featured_until?->format('d/m/Y'))
            ->body($unit->publicTitle())
            ->send();
    }

    /** @param  array<string, mixed>  $properties */
    private function log(Unit $unit, string $message, array $properties = []): void
    {
        // activity_log.subject_id is a bigint and units use UUIDs, so the
        // unit is recorded in the properties instead of as the subject.
        activity('marketplace')
            ->causedBy(Filament::auth()->user())
            ->withProperties(['unit_id' => $unit->getKey(), 'unit' => $unit->publicTitle(), 'tenant_id' => $unit->tenant_id, ...$properties])
            ->log($message);
    }

    private function demoHidden(): bool
    {
        return (bool) SystemSetting::value(ListingSearch::HIDE_DEMO_SETTING, false);
    }

    /** @var Collection<int, string>|null */
    private ?Collection $shown = null;

    /** @return Collection<int, string> */
    private function shownIds(): Collection
    {
        return $this->shown ??= app(ListingSearch::class)->listed()->pluck('units.id')->map(fn ($id): string => (string) $id);
    }
}
