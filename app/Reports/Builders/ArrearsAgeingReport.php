<?php

declare(strict_types=1);

namespace App\Reports\Builders;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Reports\Contracts\ReportBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Arrears by age: what each tenant owes, split by how long it is overdue
 * (counted from each invoice's due date). Invoices not yet due, credit and
 * deposits are left out.
 */
class ArrearsAgeingReport implements ReportBuilder
{
    /** Upper day of each bucket; the last has no upper end. */
    public const BUCKETS = ['1–30' => 30, '31–60' => 60, '61–90' => 90, '90+' => null];

    /** @var list<array<string, mixed>>|null */
    private ?array $cache = null;

    public function __construct(public ?string $propertyId = null, public ?Carbon $asOf = null) {}

    public function meta(): array
    {
        return [
            'title' => 'Arrears by Age',
            'subtitle' => 'Who owes and for how long, as of '.$this->date()->format('d/m/Y'),
            'client' => tenant('name') ?? optional(Client::find(tenant('id')))->name ?? 'PMS',
            'generated_at' => now()->format('d/m/Y H:i'),
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'tenant', 'label' => 'Tenant'],
            ['key' => 'unit', 'label' => 'Unit'],
            ...collect(array_keys(self::BUCKETS))->map(fn (string $b): array => ['key' => 'b'.$b, 'label' => $b.' days', 'align' => 'right'])->all(),
            ['key' => 'total', 'label' => 'Total', 'align' => 'right'],
            ['key' => 'last_paid', 'label' => 'Last paid'],
        ];
    }

    public function rows(): Collection
    {
        return collect($this->data())->map(function (array $row): array {
            $out = ['tenant' => $row['tenant'], 'unit' => $row['unit']];
            foreach (array_keys(self::BUCKETS) as $bucket) {
                $out['b'.$bucket] = $row['buckets'][$bucket] > 0 ? self::money($row['buckets'][$bucket]) : '';
            }

            return [...$out, 'total' => self::money($row['total']), 'last_paid' => $row['last_paid']];
        })->values();
    }

    public function summary(): array
    {
        $data = collect($this->data());
        $currency = $data->pluck('currency')->filter()->first() ?? 'TZS';
        $summary = [];
        foreach (array_keys(self::BUCKETS) as $bucket) {
            $summary[$bucket.' days'] = $currency.' '.self::money((int) $data->sum(fn (array $r): int => $r['buckets'][$bucket]));
        }

        return [...$summary, 'Total owed' => $currency.' '.self::money((int) $data->sum('total'))];
    }

    public function filenameSlug(): string
    {
        return 'arrears-by-age-'.$this->date()->format('Ymd');
    }

    /** Which bucket an invoice falls in, by whole days past its due date. */
    public static function bucketFor(int $daysOverdue): string
    {
        foreach (self::BUCKETS as $label => $upTo) {
            if ($upTo === null || $daysOverdue <= $upTo) {
                return $label;
            }
        }

        return '90+';
    }

    /** @return list<array<string, mixed>> */
    private function data(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $asOf = $this->date();

        $invoices = Invoice::query()
            ->outstanding()
            ->whereNotNull('lease_id')
            ->whereDate('due_date', '<', $asOf->toDateString())
            ->when($this->propertyId, fn ($q) => $q->whereHas('lease.unit', fn ($u) => $u->where('property_id', $this->propertyId)))
            ->with(['lease.renter', 'lease.unit.property'])
            ->get();

        return $this->cache = $invoices
            ->groupBy(fn (Invoice $i): string => (string) $i->lease_id)
            ->map(function (Collection $group) use ($asOf): array {
                /** @var Invoice $first */
                $first = $group->first();
                $buckets = array_fill_keys(array_keys(self::BUCKETS), 0);
                foreach ($group as $invoice) {
                    $days = (int) $invoice->due_date?->copy()->startOfDay()->diffInDays($asOf->copy()->startOfDay());
                    $buckets[self::bucketFor($days)] += $invoice->balanceDue();
                }
                $renterId = $first->lease?->renter_id;
                $lastPaid = $renterId !== null
                    ? Payment::query()->where('renter_id', $renterId)->where('status', Payment::STATUS_COMPLETED)->max('payment_date')
                    : null;

                return [
                    'tenant' => $first->bill_to_name,
                    'unit' => ($first->lease?->unit?->property?->name ?? '—').' / '.($first->lease?->unit !== null ? $first->lease->unit->code : '—'),
                    'buckets' => $buckets,
                    'total' => array_sum($buckets),
                    'currency' => $first->currency,
                    'last_paid' => $lastPaid !== null ? Carbon::parse($lastPaid)->format('d/m/Y') : '—',
                ];
            })
            ->filter(fn (array $row): bool => $row['total'] > 0)
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    private function date(): Carbon
    {
        return ($this->asOf ?? today())->copy();
    }

    private static function money(int $minor): string
    {
        return number_format($minor / 100, 0, '.', ',');
    }
}
