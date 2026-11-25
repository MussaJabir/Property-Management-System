<?php

declare(strict_types=1);

namespace App\Reports\Builders;

use App\Filament\Operator\Support\RentFields;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Unit;
use App\Reports\Contracts\ReportBuilder;
use App\Services\Deposits\DepositLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rent roll: every unit let on a lease, who is in it, on what terms, what
 * they owe and the deposit held. A snapshot as of today.
 */
class RentRollReport implements ReportBuilder
{
    /** @var list<array<string, mixed>>|null */
    private ?array $cache = null;

    public function __construct(public ?string $propertyId = null) {}

    public function meta(): array
    {
        return [
            'title' => 'Rent Roll',
            'subtitle' => 'Units, tenants and rent as of '.now()->format('d/m/Y'),
            'client' => tenant('name') ?? optional(Client::find(tenant('id')))->name ?? 'PMS',
            'generated_at' => now()->format('d/m/Y H:i'),
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'tenant', 'label' => 'Tenant'],
            ['key' => 'start', 'label' => 'Lease start'],
            ['key' => 'end', 'label' => 'Lease end'],
            ['key' => 'rent', 'label' => 'Rent a month', 'align' => 'right'],
            ['key' => 'owed', 'label' => 'Owed', 'align' => 'right'],
            ['key' => 'deposit', 'label' => 'Deposit held', 'align' => 'right'],
            ['key' => 'last_paid', 'label' => 'Last paid'],
        ];
    }

    public function rows(): Collection
    {
        return collect($this->data())->map(fn (array $row): array => array_intersect_key($row, array_flip(array_column($this->columns(), 'key'))));
    }

    public function summary(): array
    {
        $data = collect($this->data());
        $currency = $data->pluck('currency')->filter()->first() ?? 'TZS';
        $let = $data->where('let', true);

        return [
            'Units' => number_format($data->count()),
            'Let' => number_format($let->count()).' ('.($data->count() > 0 ? round($let->count() / $data->count() * 100) : 0).'%)',
            'Rent a month' => $currency.' '.self::money((int) $let->sum('rent_minor')),
            'Owed' => $currency.' '.self::money((int) $data->sum('owed_minor')),
            'Deposits held' => $currency.' '.self::money((int) $data->sum('deposit_minor')),
        ];
    }

    public function filenameSlug(): string
    {
        return 'rent-roll-'.now()->format('Ymd');
    }

    /** @return list<array<string, mixed>> */
    private function data(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $ledger = app(DepositLedger::class);

        $units = Unit::query()
            ->longTerm()
            ->when($this->propertyId, fn ($q) => $q->where('property_id', $this->propertyId))
            ->with(['property', 'leases' => fn ($q) => $q->where('status', Lease::STATUS_ACTIVE)->with('renter')->orderBy('start_date')])
            ->get()
            ->sortBy(fn (Unit $unit): string => ($unit->property?->name ?? '').' '.$unit->code)
            ->values();

        return $this->cache = $units->map(function (Unit $unit) use ($ledger): array {
            // The lease running today (a renewal waiting to start comes after it).
            $lease = $unit->leases->first(fn (Lease $l): bool => $l->start_date === null || $l->start_date->lte(today())) ?? $unit->leases->first();
            $monthly = $lease !== null ? (int) round($lease->rent_amount / max(1, RentFields::monthsFor($lease->billing_cycle, $lease->billing_cycle_months))) : 0;
            $owed = $lease !== null
                ? (int) Invoice::query()->where('lease_id', $lease->id)->outstanding()->get()->sum(fn (Invoice $i): int => $i->balanceDue())
                : 0;
            $deposit = $lease !== null ? $ledger->held($lease) : 0;
            $lastPaid = $lease?->renter_id !== null
                ? Payment::query()->where('renter_id', $lease->renter_id)->where('status', Payment::STATUS_COMPLETED)->max('payment_date')
                : null;

            return [
                'unit' => ($unit->property?->name ?? '—').' / '.$unit->code,
                'status' => $lease !== null ? 'Let' : ucfirst($unit->status),
                'tenant' => $lease?->renter !== null ? $lease->renter->display_name : '—',
                'start' => $lease?->start_date?->format('d/m/Y') ?? '—',
                'end' => $lease !== null ? ($lease->end_date?->format('d/m/Y') ?? 'Open') : '—',
                'rent' => $lease !== null ? $lease->currency.' '.self::money($monthly) : '—',
                'owed' => $owed > 0 ? self::money($owed) : '—',
                'deposit' => $deposit > 0 ? self::money($deposit) : '—',
                'last_paid' => $lastPaid !== null ? Carbon::parse($lastPaid)->format('d/m/Y') : '—',
                'let' => $lease !== null,
                'currency' => $lease?->currency,
                'rent_minor' => $monthly,
                'owed_minor' => $owed,
                'deposit_minor' => $deposit,
            ];
        })->values()->all();
    }

    private static function money(int $minor): string
    {
        return number_format($minor / 100, 0, '.', ',');
    }
}
