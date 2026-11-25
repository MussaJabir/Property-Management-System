<?php

declare(strict_types=1);

namespace App\Reports\Builders;

use App\Filament\Operator\Support\RentFields;
use App\Models\Client;
use App\Models\Lease;
use App\Reports\Contracts\ReportBuilder;
use Illuminate\Support\Collection;

/** Leases ending soon: who, when, and whether a renewal is already in place. */
class LeaseExpiryReport implements ReportBuilder
{
    /** @var Collection<int, Lease>|null */
    private ?Collection $cache = null;

    public function __construct(public int $days = 90, public ?string $propertyId = null) {}

    public function meta(): array
    {
        return [
            'title' => 'Lease Expiry',
            'subtitle' => 'Leases ending in the next '.$this->days.' days, as of '.now()->format('d/m/Y'),
            'client' => tenant('name') ?? optional(Client::find(tenant('id')))->name ?? 'PMS',
            'generated_at' => now()->format('d/m/Y H:i'),
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'tenant', 'label' => 'Tenant'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'end', 'label' => 'Ends'],
            ['key' => 'days_left', 'label' => 'Days left', 'align' => 'right'],
            ['key' => 'rent', 'label' => 'Rent a month', 'align' => 'right'],
            ['key' => 'notice', 'label' => 'Notice'],
            ['key' => 'renewal', 'label' => 'Renewal'],
        ];
    }

    public function rows(): Collection
    {
        return $this->leases()->map(function (Lease $lease): array {
            $months = max(1, RentFields::monthsFor($lease->billing_cycle, $lease->billing_cycle_months));

            return [
                'tenant' => $lease->renter !== null ? $lease->renter->display_name : '—',
                'unit' => ($lease->unit?->property?->name ?? '—').' / '.($lease->unit !== null ? $lease->unit->code : '—'),
                'end' => $lease->end_date?->format('d/m/Y') ?? '—',
                'days_left' => (string) $lease->daysLeft(),
                'rent' => $lease->currency.' '.number_format($lease->rent_amount / $months / 100, 0, '.', ','),
                'notice' => $lease->notice_days ? $lease->notice_days.' days' : '—',
                'renewal' => $lease->renewal !== null ? 'From '.$lease->renewal->start_date?->format('d/m/Y') : 'Not yet',
            ];
        });
    }

    public function summary(): array
    {
        $leases = $this->leases();
        $within = fn (int $days): int => $leases->filter(fn (Lease $l): bool => (int) $l->daysLeft() <= $days)->count();

        return [
            'In 30 days' => number_format($within(30)),
            'In 60 days' => number_format($within(60)),
            'In 90 days' => number_format($within(90)),
            'Not yet renewed' => number_format($leases->filter(fn (Lease $l): bool => $l->renewal === null)->count()),
        ];
    }

    public function filenameSlug(): string
    {
        return 'lease-expiry-'.now()->format('Ymd');
    }

    /** @return Collection<int, Lease> */
    private function leases(): Collection
    {
        return $this->cache ??= Lease::query()
            ->expiringWithin($this->days)
            ->when($this->propertyId, fn ($q) => $q->whereHas('unit', fn ($u) => $u->where('property_id', $this->propertyId)))
            ->with(['renter', 'unit.property', 'renewal'])
            ->orderBy('end_date')
            ->get();
    }
}
