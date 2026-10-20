@props(['unit', 'size' => 'md'])

@php
    // Nightly rate and/or rent, from Unit::publicPrices(). The first price
    // is the headline; a second one (rooms let both ways) sits below it.
    $prices = $unit->publicPrices();
    $amountClass = match ($size) {
        'lg' => 'text-4xl',
        'sm' => 'text-2xl',
        default => 'text-3xl',
    };
@endphp

<div {{ $attributes->class('space-y-1.5') }}>
    @foreach ($prices as $i => $price)
        <div class="flex items-baseline gap-1.5">
            <span @class([
                'font-display font-bold leading-none tracking-tight tnum',
                $amountClass => $i === 0,
                'text-lg' => $i > 0,
            ]) style="color: var(--brand);">{{ $price['amount'] }}</span>
            <span class="font-mono-ui text-xs uppercase tracking-[0.1em] text-zinc-500">{{ $price['currency'] }} / {{ $price['per'] }}</span>
        </div>
    @endforeach
</div>
