@props(['unit'])

@php
    // One room in a grid: photo, how it is let, name, key facts, price.
    $client = tenant();
    $img = $unit->coverImageUrl('card');
    $price = $unit->publicPrices()[0] ?? null;
    $facts = $unit->publicFacts();
    if ($facts === []) {
        $facts = array_slice(array_values($unit->amenityLabels()), 0, 3);
    }
@endphp

<a href="{{ url('/'.$client->slug.'/units/'.$unit->id) }}" class="group flex flex-col gap-3 text-[var(--ink)]">
    <div class="relative aspect-[4/3] overflow-hidden rounded-2xl bg-[#ece9e3]">
        @if ($img)
            <img src="{{ $img }}" alt="{{ $unit->publicTitle() }}" loading="lazy"
                 class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]">
        @else
            <div class="flex h-full w-full items-center justify-center">
                <x-cms.photo-icon class="h-10 w-10 text-black/30" />
            </div>
        @endif
        <span class="absolute left-3 top-3 rounded-full bg-white px-2.5 py-1 text-xs font-bold text-[var(--ink)] shadow-sm">{{ $unit->publicTag() }}</span>
    </div>
    <div class="flex flex-col gap-0.5">
        <span class="text-base font-bold leading-snug group-hover:underline">{{ $unit->publicTitle() }}</span>
        @if ($facts !== [])
            <span class="text-sm text-[var(--muted)]">{{ implode(' · ', $facts) }}</span>
        @endif
        @if ($price)
            <span class="mt-1 text-[15px]"><b class="font-bold tnum">{{ $price['currency'] }} {{ $price['amount'] }}</b> {{ $price['per'] }}</span>
        @endif
    </div>
</a>
