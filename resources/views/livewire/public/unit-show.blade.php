@php
    use App\Filament\Operator\Support\ShortStays;

    $client = tenant();
    $available = $unit->isListedPublicly();
    $prices = $unit->publicPrices();
    $headline = $prices[0] ?? null;
    $facts = $unit->publicFacts();
    $location = $unit->property?->location;
    $where = collect([$location?->district, $location?->region])->filter()->implode(', ');
    $photos = $gallery->values();
    $whatsapp = $client->whatsappUrl(__('Hello, I am interested in :room. Is it free?', ['room' => $unit->publicTitle()]));
@endphp

<div x-data="{ booking: false }" class="flex flex-col gap-6 pb-24 lg:pb-0">
    <div class="flex flex-col gap-1.5">
        <a href="{{ url('/'.$client->slug.'/units') }}" class="text-sm text-[var(--muted)] hover:text-[var(--ink)]">← {{ __('All rooms') }}</a>
        <h1 class="text-[26px] font-extrabold leading-tight tracking-tight sm:text-[34px]">{{ $unit->publicTitle() }}</h1>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[15px]">
            @if ($where !== '')
                <span class="text-[var(--muted)]">{{ $where }}</span>
            @endif
            @if ($summary)
                <a href="#reviews" class="inline-flex items-center gap-1 font-semibold">
                    <x-cms.star class="h-4 w-4" />{{ number_format($summary['average'], 1) }}
                    <span class="font-normal underline">{{ trans_choice(':count review|:count reviews', $summary['count']) }}</span>
                </a>
            @endif
            <span class="rounded-full bg-[#f1efea] px-2.5 py-0.5 text-xs font-bold">{{ $unit->publicTag() }}</span>
        </div>
    </div>

    <x-room.gallery :unit="$unit" :photos="$photos" />

    <div class="flex flex-wrap items-start gap-12">
        <div class="flex min-w-0 flex-[999_1_560px] flex-col gap-8">
            @if ($facts !== [])
                <div class="flex flex-wrap gap-x-7 gap-y-2 text-base">
                    @foreach ($facts as $fact)
                        <span>{{ $fact }}</span>
                    @endforeach
                </div>
            @endif

            @if ($unit->description)
                <section class="flex flex-col gap-2.5 border-t border-[var(--line)] pt-7">
                    <h2 class="text-[22px] font-bold">{{ __('About this room') }}</h2>
                    <p class="max-w-[62ch] whitespace-pre-line text-base leading-relaxed text-[#3d3b37]">{{ $unit->description }}</p>
                </section>
            @endif

            <x-room.amenities :unit="$unit" :title="__('What this room has')" />

            <x-room.reviews :summary="$summary" :reviews="$reviews" />

            <section class="flex flex-col gap-2.5 border-t border-[var(--line)] pt-7">
                <h2 class="text-[22px] font-bold">{{ __('Where it is') }}</h2>
                <p class="text-base text-[#3d3b37]">
                    {{ collect([$unit->property?->name, $where])->filter()->implode(' · ') }}.
                    {{ __('The exact location is shared when your booking is confirmed.') }}
                </p>
                @if ($unit->takesShortStays())
                    <p class="text-[15px] text-[var(--muted)]">
                        {{ __('Check in from :in · check out by :out', ['in' => ShortStays::checkInLabel($client), 'out' => ShortStays::checkOutLabel($client)]) }}
                    </p>
                @endif
            </section>
        </div>

        {{-- Booking box --}}
        <aside id="book" x-init="new IntersectionObserver(([e]) => booking = e.isIntersecting).observe($el)" class="flex w-full min-w-0 max-w-[400px] flex-[1_1_340px] scroll-mt-24 flex-col gap-4 rounded-[20px] border border-[var(--line)] p-6 shadow-[0_8px_28px_rgba(29,29,27,0.10)] lg:sticky lg:top-24">
            @foreach ($prices as $i => $price)
                <div class="flex items-baseline gap-1.5">
                    <b class="{{ $i === 0 ? 'text-2xl' : 'text-lg' }} font-extrabold tnum">{{ $price['currency'] }} {{ $price['amount'] }}</b>
                    <span class="text-base text-[var(--muted)]">{{ $price['per'] }}</span>
                </div>
            @endforeach

            @if ($available)
                <livewire:public.request-booking :unit="$unit" :key="'request-'.$unit->id" />
            @else
                <p class="rounded-xl bg-[#f4f2ee] px-4 py-3 text-[15px] font-semibold">{{ __('Not available right now') }}</p>
                <a href="{{ url('/'.$client->slug.'/units') }}" class="text-center text-sm font-semibold underline">{{ __('See rooms that are free') }}</a>
            @endif
        </aside>
    </div>

    {{-- More rooms --}}
    @if ($more->isNotEmpty())
        <section class="flex flex-col gap-6 border-t border-[var(--line)] pt-10">
            <h2 class="text-[22px] font-bold">{{ __('More rooms') }}</h2>
            <div class="grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-x-6 gap-y-8">
                @foreach ($more as $u)
                    <x-cms.room-card :unit="$u" :rating="$ratings[$u->id] ?? null" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Phones: price and booking always at hand. --}}
    @if ($available && $headline)
        <div x-show="! booking" x-transition.opacity class="fixed inset-x-0 bottom-0 z-40 flex items-center gap-2.5 border-t border-[var(--line)] bg-white px-4 pb-[max(16px,env(safe-area-inset-bottom))] pt-3 shadow-[0_-4px_16px_rgba(29,29,27,0.06)] lg:hidden">
            <div class="min-w-0 flex-1 text-base">
                <b class="font-extrabold tnum">{{ $headline['currency'] }} {{ $headline['amount'] }}</b> {{ $headline['per'] }}
            </div>
            @if ($whatsapp)
                <a href="{{ $whatsapp }}" target="_blank" rel="noopener" aria-label="{{ __('Book on WhatsApp') }}"
                   class="grid h-12 w-12 shrink-0 place-items-center rounded-xl border border-[var(--ink)]">
                    <x-cms.whatsapp-icon class="h-[22px] w-[22px]" />
                </a>
            @endif
            <a href="#book" class="inline-flex min-h-[48px] shrink-0 items-center rounded-xl px-5 text-[15px] font-bold text-white" style="background-color: var(--brand);">
                {{ $unit->takesShortStays() ? __('Book') : __('Enquire') }}
            </a>
        </div>
    @endif
</div>
