<?php

declare(strict_types=1);

namespace App\Filament\Operator\Widgets;

use App\Filament\Operator\Actions\RecordPaymentAction;
use App\Filament\Operator\Actions\RequestPaymentAction;
use App\Filament\Operator\Resources\Invoices\InvoiceResource;
use App\Filament\Operator\Resources\MaintenanceRequests\MaintenanceRequestResource;
use App\Filament\Operator\Resources\Payments\PaymentResource;
use App\Filament\Operator\Widgets\Concerns\RefreshesAfterPayment;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;

/**
 * "Needs your attention": the lists the operator works through each day,
 * as tabs, each row with its next action. Tenant-scoped via the models.
 */
class NeedsAttentionWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use RefreshesAfterPayment;

    protected string $view = 'filament.operator.widgets.needs-attention';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 3];

    private const LIMIT = 6;

    /** Opened from an overdue row with that row's invoice as argument. */
    public function recordPaymentAction(): Action
    {
        return RecordPaymentAction::make()->size('sm');
    }

    /** Mobile money prompt for an overdue row, when Selcom is on. */
    public function requestPaymentAction(): Action
    {
        return RequestPaymentAction::make()->size('sm')->link();
    }

    public static function canView(): bool
    {
        return InvoiceResource::canViewAny() || MaintenanceRequestResource::canViewAny();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $canPay = PaymentResource::canCreate();

        $overdueQuery = Invoice::query()->overdue();
        $overdue = (clone $overdueQuery)
            ->with(['lease.renter', 'lease.unit.property', 'booking.guest', 'booking.unit.property'])
            ->orderBy('due_date')
            ->limit(self::LIMIT)
            ->get()
            ->map(function (Invoice $invoice) use ($canPay): array {
                $unit = $invoice->lease->unit ?? $invoice->booking->unit ?? null;
                $daysLate = $invoice->due_date ? (int) $invoice->due_date->diffInDays(today()) : 0;

                return [
                    'who' => $invoice->bill_to_name,
                    'sub' => collect([$unit?->code, $unit?->property?->name, $invoice->formatted_balance])->filter()->implode(' · '),
                    'pill' => trans_choice(':count day late|:count days late', $daysLate),
                    'tone' => $daysLate > 7 ? 'danger' : 'warning',
                    // With billing rights: the Record payment modal for this
                    // invoice. Otherwise a link to the invoice, if allowed.
                    'pay' => $canPay ? $invoice->id : null,
                    'action' => __('Open'),
                    'url' => ! $canPay && InvoiceResource::canEdit($invoice) ? InvoiceResource::getUrl('edit', ['record' => $invoice]) : null,
                    'primary' => false,
                ];
            });

        $repairsQuery = MaintenanceRequest::query()->open();
        $repairs = (clone $repairsQuery)
            ->with('unit.property')
            ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'medium' then 2 else 3 end")
            ->orderBy('reported_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (MaintenanceRequest $request): array => [
                'who' => $request->title,
                'sub' => collect([$request->unit?->code, $request->unit?->property?->name, $request->reported_at->diffForHumans()])->filter()->implode(' · '),
                'pill' => __(ucfirst((string) $request->priority)),
                'tone' => match ($request->priority) {
                    MaintenanceRequest::PRIORITY_URGENT, MaintenanceRequest::PRIORITY_HIGH => 'danger',
                    MaintenanceRequest::PRIORITY_MEDIUM => 'warning',
                    default => 'gray',
                },
                'action' => __('Open'),
                'pay' => null,
                'url' => MaintenanceRequestResource::canEdit($request) ? MaintenanceRequestResource::getUrl('edit', ['record' => $request]) : null,
                'primary' => false,
            ]);

        $tabs = [
            'overdue' => [
                'label' => __('Overdue rent'),
                'count' => $overdueQuery->count(),
                'rows' => $overdue,
                'empty' => __('All clear. Nobody is behind on rent.'),
                'all' => InvoiceResource::getUrl('index'),
            ],
            'repairs' => [
                'label' => __('Repairs'),
                'count' => $repairsQuery->count(),
                'rows' => $repairs,
                'empty' => __('No open repair requests.'),
                'all' => MaintenanceRequestResource::getUrl('index'),
            ],
        ];

        // Only the lists this role may open.
        if (! InvoiceResource::canViewAny()) {
            unset($tabs['overdue']);
        }
        if (! MaintenanceRequestResource::canViewAny()) {
            unset($tabs['repairs']);
        }

        return ['tabs' => $tabs];
    }
}
