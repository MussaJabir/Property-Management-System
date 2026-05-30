@props(['data' => []])

@php
    use App\Models\Unit;

    $heading = $data['heading'] ?? __('Available units');
    $limit = (int) ($data['limit'] ?? 6);
    $client = tenant();

    $units = Unit::query()
        ->where('status', Unit::STATUS_VACANT)
        ->with(['property.location'])
        ->orderByDesc('updated_at')
        ->limit($limit)
        ->get();
@endphp

<section class="rounded-2xl bg-white p-8 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold">{{ $heading }}</h2>
        <a href="{{ url('/'.$client->slug.'/units') }}" class="text-sm font-medium" style="color: var(--brand);">
            {{ __('See all') }} →
        </a>
    </div>

    @if ($units->isEmpty())
        <p class="mt-4 text-sm text-zinc-500">{{ __('No vacant units listed at the moment.') }}</p>
    @else
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($units as $unit)
                <article class="overflow-hidden rounded-xl border border-zinc-200 transition hover:shadow-md dark:border-zinc-800">
                    <div class="aspect-[16/10] w-full bg-zinc-100 dark:bg-zinc-800"></div>
                    <div class="p-4">
                        <h3 class="text-base font-semibold">{{ $unit->code }}</h3>
                        <p class="text-xs text-zinc-500">{{ $unit->property?->name }} · {{ $unit->property?->location?->region }}</p>
                        <p class="mt-2 text-sm font-medium" style="color: var(--brand);">
                            {{ $unit->rent_currency }} {{ number_format($unit->rent_amount / 100, 0, '.', ',') }}
                            <span class="text-xs font-normal text-zinc-500">/ {{ str_replace('_', ' ', $unit->billing_cycle) }}</span>
                        </p>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>
