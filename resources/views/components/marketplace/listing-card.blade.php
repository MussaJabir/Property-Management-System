@props(['unit', 'rating' => null])

@php
    /** @var \App\Models\Unit $unit */
    $client = $unit->ownerClient();
    $isDemo = (bool) $client?->is_demo;
    $url = url('/'.$client?->slug.'/units/'.$unit->id);
    $img = $unit->coverImageUrl('card');
    $prices = $unit->publicPrices();
    $price = $prices[0] ?? null;
    $location = $unit->property?->location;
    $area = $location ? collect([$location->name, $location->district, $location->region])->filter()->unique()->implode(', ') : null;
    $facts = $unit->publicFacts();
    if ($facts === []) {
        $facts = array_slice(array_values($unit->amenityLabels()), 0, 3);
    }
    $share = 'https://wa.me/?text='.rawurlencode(__('marketplace.share_text', [
        'title' => $unit->publicTitle(),
        'price' => $price ? $price['currency'].' '.$price['amount'].' / '.$price['per'] : '',
        'url' => $url,
    ]));
@endphp

<article class="flex flex-col gap-2.5" wire:key="listing-{{ $unit->id }}">
    <div class="relative aspect-[4/3] overflow-hidden rounded-2xl bg-slate-200">
        @unless ($img)
            <div class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                <x-cms.photo-icon class="h-10 w-10 text-slate-400" />
            </div>
        @endunless
        @if ($isDemo)
            @if ($img)
                <img src="{{ $img }}" alt="{{ $unit->publicTitle() }}" loading="lazy" class="h-full w-full object-cover">
            @endif
        @else
            <a href="{{ $url }}" class="relative block h-full w-full" tabindex="-1" aria-hidden="true">
                @if ($img)
                    <img src="{{ $img }}" alt="" loading="lazy" class="h-full w-full object-cover transition-transform duration-500 hover:scale-[1.03]">
                @endif
            </a>
        @endif
        <div class="pointer-events-none absolute left-3 top-3 flex flex-wrap gap-1.5">
            @if ($unit->isFeatured() && ! $isDemo)
                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-amber-800 shadow-sm">★ {{ __('marketplace.featured') }}</span>
            @endif
            @if ($isDemo)
                <span class="rounded-full bg-slate-900 px-2.5 py-1 text-xs font-bold text-white">{{ __('marketplace.demo') }}</span>
            @endif
            <span class="rounded-full bg-white/95 px-2.5 py-1 text-xs font-semibold text-slate-900 shadow-sm">{{ $unit->publicTag() }}</span>
        </div>
        @unless ($isDemo)
            <a href="{{ $share }}" target="_blank" rel="noopener" aria-label="{{ __('marketplace.share') }}"
               class="absolute right-2.5 top-2.5 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white text-green-700 shadow-sm hover:bg-green-50">
                <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><path d="m16 6-4-4-4 4"/><path d="M12 2v13"/></svg>
            </a>
        @endunless
    </div>

    <div class="flex items-start justify-between gap-3">
        <h2 class="text-[15px] font-bold leading-snug">
            @if ($isDemo)
                {{ $unit->publicTitle() }}
            @else
                <a href="{{ $url }}" class="hover:underline">{{ $unit->publicTitle() }}</a>
            @endif
        </h2>
        @if ($rating)
            <span class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold" aria-label="{{ __('marketplace.reviews', ['score' => $rating['average'], 'count' => $rating['count']]) }}">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg>
                {{ number_format($rating['average'], 1) }} ({{ $rating['count'] }})
            </span>
        @endif
    </div>
    @if ($area)
        <p class="-mt-1 text-sm text-slate-600">{{ $area }}</p>
    @endif
    @if ($facts !== [])
        <p class="-mt-1.5 text-sm text-slate-500">{{ implode(' · ', $facts) }}</p>
    @endif
    @if ($price)
        <p class="text-[15px]">
            <b class="font-bold tnum">{{ $price['currency'] }} {{ $price['amount'] }}</b>
            <span class="text-slate-600">/ {{ $price['per'] }}</span>
            @if (isset($prices[1]))
                <span class="text-slate-500"> · <span class="tnum">{{ $prices[1]['currency'] }} {{ $prices[1]['amount'] }}</span> / {{ $prices[1]['per'] }}</span>
            @endif
        </p>
    @endif
    @if ($isDemo)
        <p class="rounded-xl bg-slate-100 p-3 text-sm text-slate-700">{{ __('marketplace.demo_note') }}</p>
    @else
        <p class="text-xs text-slate-500">{{ __('marketplace.listed_by', ['name' => $client?->name]) }}</p>
        <a href="{{ $url }}" class="mt-1 inline-flex h-11 items-center justify-center rounded-xl bg-teal-700 text-sm font-bold text-white hover:bg-teal-800 sm:hidden">{{ __('marketplace.view_room') }}</a>
    @endif
</article>
