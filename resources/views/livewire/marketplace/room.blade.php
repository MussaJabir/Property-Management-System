@php
    use App\Filament\Operator\Support\ShortStays;

    /** @var \App\Models\Unit $unit */
    $available = $unit->isListedPublicly();
    $prices = $unit->publicPrices();
    $headline = $prices[0] ?? null;
    $facts = $unit->publicFacts();
    $location = $unit->property?->location;
    $area = $location ? collect([$location->name, $location->district, $location->region])->filter()->unique()->implode(', ') : '';
    $photos = $gallery->values();
    $whatsapp = $client->whatsappUrl(__('marketplace.room_whatsapp_text', ['room' => $unit->publicTitle()]));
    $shareUrl = route('marketplace.room', ['tenant' => $client->slug, 'unit' => $unit->id]);
    $share = 'https://wa.me/?text='.rawurlencode(__('marketplace.share_text', [
        'title' => $unit->publicTitle(),
        'price' => $headline ? $headline['currency'].' '.$headline['amount'].' / '.$headline['per'] : '',
        'url' => $shareUrl,
    ]));
    $round = 'inline-flex h-11 w-11 items-center justify-center rounded-full bg-white shadow-[0_2px_8px_rgba(15,23,42,0.18)]';
@endphp

<div x-data="{ booking: false }" class="mx-auto flex max-w-6xl flex-col gap-6 px-4 pb-28 pt-4 sm:px-6 sm:pt-6 lg:pb-12">

    {{-- Phones: the photo comes first, edge to edge, with Back and Share on it. --}}
    <x-room.gallery :unit="$unit" :photos="$photos" class="-mx-4 -mt-4 sm:mx-0 sm:mt-0 max-sm:[&>div:first-child]:rounded-none">
        <x-slot:overlay>
            <a href="{{ $backUrl }}" class="{{ $round }} absolute left-4 top-4 text-slate-900 sm:hidden" aria-label="{{ __('marketplace.back_to_results') }}">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
            <a href="{{ $share }}" target="_blank" rel="noopener" class="{{ $round }} absolute right-4 top-4 text-green-700 sm:hidden" aria-label="{{ __('marketplace.share') }}">
                <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><path d="m16 6-4-4-4 4"/><path d="M12 2v13"/></svg>
            </a>
            @if ($unit->isFeatured())
                <span class="absolute bottom-3 left-4 rounded-full bg-white px-2.5 py-1 text-xs font-bold text-amber-800 shadow-sm sm:hidden">★ {{ __('marketplace.featured') }}</span>
            @endif
        </x-slot:overlay>
    </x-room.gallery>

    <div class="-order-1 flex flex-col gap-1.5 max-sm:order-none">
        <a href="{{ $backUrl }}" class="inline-flex min-h-[44px] items-center gap-1.5 self-start text-sm font-semibold text-teal-700 hover:text-teal-900 max-sm:hidden">
            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            {{ __('marketplace.back_to_results') }}
        </a>
        <h1 class="text-[26px] font-extrabold leading-tight sm:text-[34px]">{{ $unit->publicTitle() }}</h1>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[15px]">
            @if ($area !== '')
                <span class="text-slate-600">{{ $area }}</span>
            @endif
            @if ($summary)
                <a href="#reviews" class="inline-flex items-center gap-1 font-semibold">
                    <x-cms.star class="h-4 w-4" />{{ number_format($summary['average'], 1) }}
                    <span class="font-normal underline">{{ trans_choice(':count review|:count reviews', $summary['count']) }}</span>
                </a>
            @endif
            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold">{{ $unit->publicTag() }}</span>
            @if ($unit->isFeatured())
                <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800 max-sm:hidden">★ {{ __('marketplace.featured') }}</span>
            @endif
            @if ($available)
                <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-700">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l4 4L19 6"/></svg>
                    {{ __('marketplace.free_now') }}
                </span>
            @endif
            <a href="{{ $share }}" target="_blank" rel="noopener" class="ml-auto inline-flex min-h-[44px] items-center gap-1.5 text-sm font-semibold underline max-sm:hidden">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><path d="m16 6-4-4-4 4"/><path d="M12 2v13"/></svg>
                {{ __('marketplace.share') }}
            </a>
        </div>
    </div>

    <div class="flex flex-wrap items-start gap-12">
        <div class="flex min-w-0 flex-[999_1_560px] flex-col gap-7">
            @if ($facts !== [])
                <div class="flex flex-wrap gap-x-7 gap-y-2 text-base">
                    @foreach ($facts as $fact)
                        <span>{{ $fact }}</span>
                    @endforeach
                </div>
            @endif

            {{-- Who lists it. --}}
            <div class="flex items-center gap-3.5 rounded-2xl border border-slate-200 p-4">
                <x-cms.brand-mark :client="$client" size="h-12 w-12" class="text-lg" />
                <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                    <b class="text-base">{{ __('marketplace.listed_by', ['name' => $client->name]) }}</b>
                    <span class="text-sm text-slate-600">{{ trans_choice('marketplace.landlord_places', $landlordCount) }}@if ($whatsapp) · {{ __('marketplace.replies_whatsapp') }}@endif</span>
                </div>
                <a href="{{ $landlordUrl }}" class="inline-flex min-h-[44px] shrink-0 items-center text-sm font-semibold underline max-sm:hidden">{{ __('marketplace.see_their_places') }}</a>
                <a href="{{ $landlordUrl }}" class="inline-flex h-11 w-11 shrink-0 items-center justify-center sm:hidden" aria-label="{{ __('marketplace.see_their_places') }}">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                </a>
            </div>

            @if ($unit->description)
                <section class="flex flex-col gap-2.5 border-t border-slate-200 pt-7">
                    <h2 class="text-[22px] font-bold">{{ __('marketplace.about_place') }}</h2>
                    <p class="max-w-[62ch] whitespace-pre-line text-base leading-relaxed text-slate-700">{{ $unit->description }}</p>
                </section>
            @endif

            <x-room.amenities :unit="$unit" :title="__('marketplace.what_it_has')" />

            <x-room.reviews :summary="$summary" :reviews="$reviews" />

            <section class="flex flex-col gap-2.5 border-t border-slate-200 pt-7">
                <h2 class="text-[22px] font-bold">{{ __('marketplace.where_it_is') }}</h2>
                <p class="text-base text-slate-700">
                    {{ collect([$unit->property?->name, $area])->filter()->implode(' · ') }}.
                    {{ __('marketplace.exact_location') }}
                </p>
                @if ($unit->takesShortStays())
                    <p class="text-[15px] text-slate-600">
                        {{ __('Check in from :in · check out by :out', ['in' => ShortStays::checkInTime($client), 'out' => ShortStays::checkOutTime($client)]) }}
                    </p>
                @endif
            </section>
        </div>

        {{-- Booking box: the same form as the landlord's own page. --}}
        <aside id="book" x-init="new IntersectionObserver(([e]) => booking = e.isIntersecting).observe($el)"
               class="flex w-full min-w-0 max-w-[400px] flex-[1_1_340px] scroll-mt-24 flex-col gap-4 rounded-[20px] border border-slate-200 p-6 shadow-[0_8px_28px_rgba(15,23,42,0.10)] lg:sticky lg:top-6">
            @foreach ($prices as $i => $price)
                <div class="flex items-baseline gap-1.5">
                    <b class="{{ $i === 0 ? 'text-2xl' : 'text-lg' }} font-extrabold tnum">{{ $price['currency'] }} {{ $price['amount'] }}</b>
                    <span class="text-base text-slate-600">/ {{ $price['per'] }}</span>
                </div>
            @endforeach

            @if ($available)
                <livewire:public.request-booking :unit="$unit" :key="'request-'.$unit->id" />
            @else
                <p class="rounded-xl bg-slate-100 px-4 py-3 text-[15px] font-semibold">{{ __('Not available right now') }}</p>
            @endif
            <p class="text-center text-[13px] text-slate-500">{{ __('marketplace.no_fees') }}</p>
        </aside>
    </div>

    @if ($nearby->isNotEmpty())
        <section class="flex flex-col gap-6 border-t border-slate-200 pt-10">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 class="text-[22px] font-bold">
                    {{ $location?->region ? __('marketplace.more_near', ['area' => $location->region]) : __('marketplace.more_places') }}
                </h2>
                @if ($regionUrl)
                    <a href="{{ $regionUrl }}" class="text-sm font-semibold underline">{{ __('marketplace.see_all_in', ['area' => $location->region]) }}</a>
                @endif
            </div>
            <div class="grid grid-cols-1 gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($nearby as $near)
                    <x-marketplace.listing-card :unit="$near" :rating="$ratings[(string) $near->id] ?? null" />
                @endforeach
            </div>
        </section>
    @endif

    <a href="{{ $backUrl }}" class="inline-flex min-h-[44px] items-center gap-1.5 self-start text-[15px] font-semibold text-teal-700 hover:text-teal-900">
        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        {{ __('marketplace.back_to_all') }}
    </a>

    {{-- Phones: price, WhatsApp and Enquire always at hand. --}}
    @if ($available && $headline)
        <div x-show="! booking" x-transition.opacity class="fixed inset-x-0 bottom-0 z-40 flex items-center gap-2.5 border-t border-slate-200 bg-white px-4 pb-[max(16px,env(safe-area-inset-bottom))] pt-3 shadow-[0_-4px_16px_rgba(15,23,42,0.06)] lg:hidden">
            <div class="flex min-w-0 flex-1 flex-col">
                <span class="text-base"><b class="font-extrabold tnum">{{ $headline['currency'] }} {{ $headline['amount'] }}</b> / {{ $headline['per'] }}</span>
                <span class="text-xs text-slate-500">{{ __('marketplace.no_agent_fees') }}</span>
            </div>
            @if ($whatsapp)
                <a href="{{ $whatsapp }}" target="_blank" rel="noopener" aria-label="{{ __('Book on WhatsApp') }}"
                   class="grid h-12 w-12 shrink-0 place-items-center rounded-xl border border-slate-900">
                    <x-cms.whatsapp-icon class="h-[22px] w-[22px]" />
                </a>
            @endif
            <a href="#book" class="inline-flex min-h-[48px] shrink-0 items-center rounded-xl bg-teal-700 px-5 text-[15px] font-bold text-white hover:bg-teal-800">
                {{ $unit->takesShortStays() ? __('Book') : __('marketplace.enquire') }}
            </a>
        </div>
    @endif
</div>
