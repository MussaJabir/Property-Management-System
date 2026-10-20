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

<div x-data="{ photos: false, booking: false }" class="flex flex-col gap-6 pb-24 lg:pb-0">
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

    {{-- Photos: one large and up to four small; all of them in an overlay. --}}
    @if ($photos->isNotEmpty())
        <div class="relative">
            <div class="flex snap-x snap-mandatory gap-2 overflow-x-auto rounded-2xl md:grid md:h-[428px] md:grid-cols-4 md:grid-rows-2 md:overflow-hidden md:rounded-[20px]">
                @foreach ($photos->take(5) as $i => $photo)
                    <button type="button" @click="photos = true"
                            class="{{ $i === 0 ? 'md:col-span-2 md:row-span-2' : 'max-md:hidden' }} aspect-[4/3] w-full shrink-0 snap-center bg-[#ece9e3] md:aspect-auto">
                        <img src="{{ $photo->getUrl('card') }}" alt="{{ $unit->publicTitle() }}" class="h-full w-full object-cover" @if ($i > 0) loading="lazy" @endif>
                    </button>
                @endforeach
            </div>
            @if ($photos->count() > 1)
                <button type="button" @click="photos = true"
                        class="absolute bottom-3 right-3 min-h-[40px] rounded-xl border border-[var(--ink)] bg-white px-3.5 text-sm font-semibold">
                    {{ __('Show all :count photos', ['count' => $photos->count()]) }}
                </button>
            @endif
        </div>

        <div x-show="photos" x-cloak x-transition.opacity @keydown.escape.window="photos = false"
             class="fixed inset-0 z-50 overflow-y-auto bg-white" role="dialog" aria-modal="true" aria-label="{{ __('Photos') }}">
            <div class="sticky top-0 flex items-center justify-between border-b border-[var(--line)] bg-white px-4 py-3">
                <span class="font-bold">{{ $unit->publicTitle() }}</span>
                <button type="button" @click="photos = false" class="min-h-[44px] rounded-xl px-4 font-semibold hover:bg-[#f4f2ee]">{{ __('Close') }}</button>
            </div>
            <div class="mx-auto flex max-w-3xl flex-col gap-3 p-4">
                @foreach ($photos as $photo)
                    <img src="{{ $photo->getUrl() }}" alt="{{ $unit->publicTitle() }}" loading="lazy" class="w-full rounded-xl">
                @endforeach
            </div>
        </div>
    @else
        <div class="flex aspect-[16/7] items-center justify-center rounded-[20px] bg-[#ece9e3]">
            <x-cms.photo-icon class="h-14 w-14 text-black/30" />
        </div>
    @endif

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

            @if ($unit->amenityLabels() !== [])
                <section class="flex flex-col gap-3.5 border-t border-[var(--line)] pt-7">
                    <h2 class="text-[22px] font-bold">{{ __('What this room has') }}</h2>
                    <ul class="grid grid-cols-[repeat(auto-fill,minmax(200px,1fr))] gap-x-6 gap-y-3.5 text-base">
                        @foreach ($unit->amenityLabels() as $label)
                            <li class="flex items-center gap-3">
                                <svg class="h-[22px] w-[22px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l4 4L19 6"/></svg>
                                {{ $label }}
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($summary)
                <section id="reviews" x-data="{ all: false }" class="flex scroll-mt-24 flex-col gap-5 border-t border-[var(--line)] pt-7">
                    <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                        <h2 class="inline-flex items-center gap-2 text-[22px] font-bold">
                            <x-cms.star class="h-5 w-5" />
                            {{ number_format($summary['average'], 1) }} · {{ trans_choice(':count review|:count reviews', $summary['count']) }}
                        </h2>
                        <span class="text-sm text-[var(--muted)]">{{ __('From people who stayed. Checked by the owner before they appear.') }}</span>
                    </div>
                    @if ($reviews->isNotEmpty())
                        <div class="grid grid-cols-[repeat(auto-fill,minmax(280px,1fr))] gap-x-10 gap-y-7">
                            @foreach ($reviews as $i => $review)
                                <article @if ($i >= 4) x-show="all" x-cloak @endif class="flex flex-col gap-2">
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-10 w-10 place-items-center rounded-full bg-[#f1efea] text-[15px] font-bold">{{ mb_substr($review->publicName(), 0, 1) }}</span>
                                        <span class="flex flex-col">
                                            <b class="text-[15px]">{{ $review->publicName() }}</b>
                                            <span class="text-[13px] text-[var(--muted)]">{{ $review->created_at?->translatedFormat('F Y') }}</span>
                                        </span>
                                    </div>
                                    <span class="text-[13px] tracking-[2px]" aria-label="{{ __(':score out of 5 stars', ['score' => $review->score]) }}">{{ str_repeat('★', $review->score) }}{{ str_repeat('☆', 5 - $review->score) }}</span>
                                    <p class="whitespace-pre-line text-[15px] leading-relaxed text-[#3d3b37]">{{ $review->comment }}</p>
                                </article>
                            @endforeach
                        </div>
                        @if ($reviews->count() > 4)
                            <button type="button" x-show="! all" @click="all = true"
                                    class="min-h-[44px] self-start rounded-xl border border-[var(--ink)] px-4 text-[15px] font-semibold">
                                {{ __('Show all :count reviews', ['count' => $reviews->count()]) }}
                            </button>
                        @endif
                    @endif
                </section>
            @endif

            <section class="flex flex-col gap-2.5 border-t border-[var(--line)] pt-7">
                <h2 class="text-[22px] font-bold">{{ __('Where it is') }}</h2>
                <p class="text-base text-[#3d3b37]">
                    {{ collect([$unit->property?->name, $where])->filter()->implode(' · ') }}.
                    {{ __('The exact location is shared when your booking is confirmed.') }}
                </p>
                @if ($unit->takesShortStays())
                    <p class="text-[15px] text-[var(--muted)]">
                        {{ __('Check in from :in · check out by :out', ['in' => ShortStays::checkInTime($client), 'out' => ShortStays::checkOutTime($client)]) }}
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
