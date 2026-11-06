@props(['unit', 'rating' => null, 'query' => []])

@php
    /** @var \App\Models\Unit $unit */
    // Phones: a compact card (square photo, name, area, price) so several
    // show at once, in the area rows and the two-per-row grid.
    $client = $unit->ownerClient();
    $isDemo = (bool) $client?->is_demo;
    $roomUrl = route('marketplace.room', ['tenant' => $client?->slug, 'unit' => $unit->id]);
    $url = $query === [] ? $roomUrl : $roomUrl.'?'.http_build_query($query);
    $img = $unit->coverImageUrl('card');
    $price = $unit->publicPrices()[0] ?? null;
    $area = $unit->property?->location?->name;
    $tag = $isDemo ? 'div' : 'a';
@endphp

<{{ $tag }} @unless ($isDemo) href="{{ $url }}" @endunless {{ $attributes->class('flex min-w-0 flex-col gap-0.5 text-slate-900') }} wire:key="mini-{{ $unit->id }}">
    <div class="relative aspect-square overflow-hidden rounded-2xl bg-slate-200">
        @if ($img)
            <img src="{{ $img }}" alt="{{ $unit->publicTitle() }}" loading="lazy" class="h-full w-full object-cover">
        @else
            <div class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                <x-cms.photo-icon class="h-8 w-8 text-slate-400" />
            </div>
        @endif
        @if ($isDemo)
            <span class="absolute left-2 top-2 rounded-full bg-slate-900 px-2 py-0.5 text-[11px] font-bold text-white">{{ __('marketplace.demo') }}</span>
        @elseif ($unit->isFeatured())
            <span class="absolute left-2 top-2 rounded-full bg-white px-2 py-0.5 text-[11px] font-bold text-amber-800 shadow-sm">★ {{ __('marketplace.featured') }}</span>
        @endif
        <span class="absolute bottom-2 left-2 rounded-full bg-white/95 px-2 py-0.5 text-[11px] font-bold">{{ $unit->publicTag() }}</span>
    </div>
    <b class="mt-1.5 truncate text-sm">{{ $unit->publicTitle() }}</b>
    @if ($area)
        <span class="truncate text-xs text-slate-500">{{ $area }}</span>
    @endif
    <span class="text-[13px] text-slate-700">
        @if ($price)
            <b class="tnum text-slate-900">{{ $price['currency'] }} {{ $price['amount'] }}</b> / {{ $price['per'] }}
        @endif
        @if ($rating)
            · ★ {{ number_format($rating['average'], 1) }}
        @endif
    </span>
</{{ $tag }}>
