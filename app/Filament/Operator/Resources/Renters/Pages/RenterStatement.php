<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Renters\Pages;

use App\Filament\Operator\Resources\Renters\RenterResource;
use App\Models\Renter;
use App\Notifications\TenantStatementNotification;
use App\Services\Payments\TenantStatement;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification as Notifier;
use Livewire\Attributes\Url;
use Throwable;

/** A renter's statement: charges, payments and reversals with the running balance. */
class RenterStatement extends Page
{
    use InteractsWithRecord;

    protected static string $resource = RenterResource::class;

    protected string $view = 'filament.operator.resources.renters.statement';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();

        $this->from = $this->validDate($this->from) ?? today()->subMonthsNoOverflow(3)->startOfMonth()->toDateString();
        $this->to = $this->validDate($this->to) ?? today()->toDateString();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(RenterResource::canView($this->getRecord()), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return __('Statement · :name', ['name' => $this->renter()->display_name]);
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [
            RenterResource::getUrl() => __('Renters'),
            RenterResource::getUrl('edit', ['record' => $this->renter()]) => $this->renter()->display_name,
            __('Statement'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')
                ->label(__('Send to tenant'))
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('gray')
                ->visible(fn (): bool => filled($this->renter()->email))
                ->requiresConfirmation()
                ->modalHeading(__('Email this statement?'))
                ->modalDescription(fn (): string => __('A PDF goes to :email.', ['email' => $this->renter()->email]))
                ->action(function (): void {
                    $renter = $this->renter();
                    [$from, $to] = [Carbon::parse($this->from), Carbon::parse($this->to)];
                    $tenantId = $renter->tenant_id;

                    // Rendering the PDF takes a while: send after the response.
                    defer(function () use ($renter, $from, $to, $tenantId): void {
                        tenancy()->initialize($tenantId);
                        try {
                            Notifier::route('mail', (string) $renter->email)->notify(new TenantStatementNotification($renter, $from, $to));
                        } catch (Throwable $e) {
                            report($e);
                        }
                    });

                    Notification::make()->success()->title(__('Statement on its way'))->send();
                }),
            Action::make('pdf')
                ->label(__('Download PDF'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->url(fn (): string => route('filament.operator.renters.statement.pdf', ['renter' => $this->renter()->getKey(), 'from' => $this->from, 'to' => $this->to]), shouldOpenInNewTab: true),
        ];
    }

    /** The date filters, shown DD/MM/YYYY. */
    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 2, 'md' => 4])->schema([
                DatePicker::make('from')->label(__('From'))->native(false)->displayFormat('d/m/Y')->live()->required(),
                DatePicker::make('to')->label(__('To'))->native(false)->displayFormat('d/m/Y')->live()->required(),
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'statement' => app(TenantStatement::class)->build(
                $this->renter(),
                Carbon::parse($this->validDate($this->from) ?? today()->subMonthsNoOverflow(3)->startOfMonth()->toDateString()),
                Carbon::parse($this->validDate($this->to) ?? today()->toDateString()),
            ),
        ];
    }

    private function renter(): Renter
    {
        /** @var Renter $renter */
        $renter = $this->getRecord();

        return $renter;
    }

    private function validDate(string $value): ?string
    {
        try {
            return $value !== '' ? Carbon::parse($value)->toDateString() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
