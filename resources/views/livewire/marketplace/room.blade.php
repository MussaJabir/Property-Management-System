@php
    use App\Filament\Operator\Support\ShortStays;
    use App\Support\AmenityIcons;

    /** @var \App\Models\Unit $unit */
    $available = $unit->isListedPublicly();
    $prices = $unit->publicPrices();
    $headline = $prices[0] ?? null;
    $facts = $unit->publicFacts();
    $location = $unit->property?->location;
    $area = $location ? collect([$location->name, $location->district, $location->region])->filter()->unique()->implode(', ') : '';
    $photos = $gallery->values();
    $cover = $unit->coverImageUrl('card');
    $whatsapp = $client->whatsappUrl(__('marketplace.room_whatsapp_text', ['room' => $unit->publicTitle()]));
    $shareUrl = route('marketplace.room', ['tenant' => $client->slug, 'unit' => $unit->id]);
    $share = 'https://wa.me/?text='.rawurlencode(__('marketplace.share_text', [
        'title' => $unit->publicTitle(),
        'price' => $headline ? $headline['currency'].' '.$headline['amount'].' / '.$headline['per'] : '',
        'url' => $shareUrl,
    ]));
    $round = 'inline-flex h-11 w-11 items-center justify-center rounded-full bg-white shadow-[0_2px_8px_rgba(15,23,42,0.18)]';
    $icon = 'h-6 w-6 shrink-0';
@endphp

{{--
    Marketplace room page. Phones: photo at the top, a rounded panel over
    it, highlights, and the booking form in a full-screen sheet opened from
    the bottom bar. Wider screens: the photo grid and the booking box on
    the right. `data-sheet` drives the sheet with CSS so nothing flashes
    before the page's script starts.
--}}
<div x-data="{ sheet: false }" :data-sheet="sheet ? 'open' : 'closed'" data-sheet="closed"
     x-effect="document.documentElement.classList.toggle('overflow-hidden', sheet)"
     @keydown.escape.window="sheet = false"
     class="group/room mx-auto flex max-w-6xl flex-col gap-6 px-4 pb-28 sm:px-6 md:pt-6 lg:pb-12">

    <x-room.gallery :unit="$unit" :photos="$photos" class="-mx-4 sm:-mx-6 md:mx-0 max-md:[&>div:first-child]:rounded-none">
        <x-slot:overlay>
            <a href="{{ $backUrl }}" class="{{ $round }} absolute left-4 top-4 text-slate-900 md:hidden" aria-label="{{ __('marketplace.back_to_results') }}">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
            <a href="{{ $share }}" target="_blank" rel="noopener" class="{{ $round }} absolute right-4 top-4 text-green-700 md:hidden" aria-label="{{ __('marketplace.share') }}">
                <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><path d="m16 6-4-4-4 4"/><path d="M12 2v13"/></svg>
            </a>
        </x-slot:overlay>
    </x-room.gallery>

    {{-- Phones: a rounded panel over the photo, everything centred. --}}
    <div class="relative -mx-4 -mt-12 flex flex-col items-center gap-1.5 rounded-t-[22px] bg-white px-4 pt-6 text-center sm:-mx-6 sm:px-6 md:hidden">
        @if ($unit->isFeatured())
            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800">★ {{ __('marketplace.featured') }}</span>
        @endif
        <h1 class="text-[25px] font-extrabold leading-tight">{{ $unit->publicTitle() }}</h1>
        @if ($area !== '')
            <span class="text-[15px] text-slate-600">{{ $area }}</span>
        @endif
        @if ($facts !== [])
            <span class="text-[15px] text-slate-600">{{ implode(' · ', $facts) }}</span>
        @endif
        <span class="flex flex-wrap items-center justify-center gap-x-1.5 text-sm font-semibold">
            @if ($summary)
                <a href="#reviews" class="inline-flex items-center gap-1"><x-cms.star class="h-3.5 w-3.5" />{{ number_format($summary['average'], 1) }} · <span class="font-normal underline">{{ trans_choice(':count review|:count reviews', $summary['count']) }}</span></a>
                <span aria-hidden="true">·</span>
            @endif
            <span>{{ $unit->publicTag() }}</span>
            @if ($available)
                <span aria-hidden="true">·</span>
                <span class="text-emerald-700">{{ __('marketplace.free_now') }}</span>
            @endif
        </span>
    </div>

    {{-- Wider screens: Back, title and details above the photos. --}}
    <div class="-order-1 flex flex-col gap-1.5 max-md:hidden">
        <a href="{{ $backUrl }}" class="inline-flex min-h-[44px] items-center gap-1.5 self-start text-sm font-semibold text-teal-700 hover:text-teal-900">
            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            {{ __('marketplace.back_to_results') }}
        </a>
        <h1 class="text-[34px] font-extrabold leading-tight">{{ $unit->publicTitle() }}</h1>
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
                <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800">★ {{ __('marketplace.featured') }}</span>
            @endif
            @if ($available)
                <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-700">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l4 4L19 6"/></svg>
                    {{ __('marketplace.free_now') }}
                </span>
            @endif
            <a href="{{ $share }}" target="_blank" rel="noopener" class="ml-auto inline-flex min-h-[44px] items-center gap-1.5 text-sm font-semibold underline">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><path d="m16 6-4-4-4 4"/><path d="M12 2v13"/></svg>
                {{ __('marketplace.share') }}
            </a>
        </div>
    </div>

    <div class="flex flex-wrap items-start gap-12">
        <div class="flex min-w-0 flex-[999_1_560px] flex-col gap-7">
            @if ($facts !== [])
                <div class="flex flex-wrap gap-x-7 gap-y-2 text-base max-md:hidden">
                    @foreach ($facts as $fact)
                        <span>{{ $fact }}</span>
                    @endforeach
                </div>
            @endif

            {{-- Who lists it. --}}
            <div class="flex items-center gap-3.5 rounded-2xl border border-slate-200 p-4 max-md:border-x-0 max-md:border-b-0 max-md:rounded-none max-md:px-0 max-md:pb-0 max-md:pt-6">
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

            {{-- Phones: three highlights from the room's own data. --}}
            <ul class="flex flex-col gap-5 border-t border-slate-200 pt-6 md:hidden">
                @foreach ($highlights as $row)
                    <li class="flex items-start gap-4">
                        @if (str_starts_with($row['icon'], 'amenity:'))
                            <svg class="{{ $icon }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">{!! AmenityIcons::path(substr($row['icon'], 8)) !!}</svg>
                        @elseif ($row['icon'] === 'pin')
                            <svg class="{{ $icon }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-6 7-12a7 7 0 0 0-14 0c0 6 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg>
                        @else
                            <svg class="{{ $icon }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                        @endif
                        <span class="flex flex-col gap-0.5">
                            <b class="text-[15px]">{{ $row['title'] }}</b>
                            <span class="text-sm leading-snug text-slate-500">{{ $row['text'] }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>

            @if ($unit->description)
                <section x-data="{ more: false }" :data-more="more ? 'open' : 'closed'" data-more="closed" class="group/more flex flex-col gap-2.5 border-t border-slate-200 pt-7">
                    <h2 class="text-[22px] font-bold">{{ __('marketplace.about_place') }}</h2>
                    <p class="max-w-[62ch] whitespace-pre-line text-base leading-relaxed text-slate-700 max-md:line-clamp-4 group-data-[more=open]/more:line-clamp-none">{{ $unit->description }}</p>
                    @if (mb_strlen($unit->description) > 200)
                        <button type="button" x-show="! more" @click="more = true" class="min-h-11 cursor-pointer self-start text-[15px] font-bold underline md:hidden">{{ __('marketplace.read_more') }}</button>
                    @endif
                </section>
            @endif

            <x-room.amenities :unit="$unit" :title="__('marketplace.what_it_has')" :limit="6" />

            <x-room.reviews :summary="$summary" :reviews="$reviews" :swipe="true" />

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

        {{--
            Booking: the box on the right on wide screens; below that, a
            full-screen sheet opened from the bottom bar. One form either way.
        --}}
        <aside id="book" role="dialog" aria-modal="true" aria-label="{{ __('marketplace.ask_about_room') }}"
               class="flex w-full min-w-0 max-w-[400px] flex-[1_1_340px] scroll-mt-24 flex-col gap-4 rounded-[20px] border border-slate-200 p-6 shadow-[0_8px_28px_rgba(15,23,42,0.10)] lg:sticky lg:top-6
                      max-lg:fixed max-lg:inset-0 max-lg:z-50 max-lg:hidden max-lg:max-w-none max-lg:gap-0 max-lg:overflow-y-auto max-lg:rounded-none max-lg:border-0 max-lg:bg-white max-lg:p-0 max-lg:shadow-none
                      max-lg:group-data-[sheet=open]/room:flex">
            <div class="sticky top-0 z-10 flex items-center gap-3 border-b border-slate-100 bg-white px-4 py-3 lg:hidden">
                <button type="button" @click="sheet = false" class="inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border border-slate-300" aria-label="{{ __('Close') }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
                <b class="text-base">{{ __('marketplace.ask_about_room') }}</b>
            </div>

            <div class="flex flex-col gap-4 max-lg:p-4">
                {{-- Sheet: which room this is about. --}}
                <div class="flex items-center gap-3 rounded-2xl border border-slate-200 p-2.5 lg:hidden">
                    <span class="h-[72px] w-[72px] shrink-0 overflow-hidden rounded-xl bg-slate-200">
                        @if ($cover)
                            <img src="{{ $cover }}" alt="" class="h-full w-full object-cover">
                        @endif
                    </span>
                    <span class="flex min-w-0 flex-col gap-0.5">
                        <b class="truncate text-[15px]">{{ $unit->publicTitle() }}</b>
                        <span class="truncate text-[13px] text-slate-500">{{ $client->name }}</span>
                        @if ($headline)
                            <span class="text-sm"><b class="tnum">{{ $headline['currency'] }} {{ $headline['amount'] }}</b> / {{ $headline['per'] }}</span>
                        @endif
                    </span>
                </div>

                @foreach ($prices as $i => $price)
                    <div class="flex items-baseline gap-1.5 max-lg:hidden">
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
            </div>
        </aside>
    </div>

    @if ($nearby->isNotEmpty())
        <section class="flex flex-col gap-5 border-t border-slate-200 pt-8 md:gap-6 md:pt-10">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-xl font-extrabold md:text-[22px] md:font-bold">
                    {{ $location?->region ? __('marketplace.more_near', ['area' => $location->region]) : __('marketplace.more_places') }}
                </h2>
                @if ($regionUrl)
                    <a href="{{ $regionUrl }}" class="text-sm font-semibold underline max-md:hidden">{{ __('marketplace.see_all_in', ['area' => $location->region]) }}</a>
                    <a href="{{ $regionUrl }}" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 md:hidden" aria-label="{{ __('marketplace.see_all_in', ['area' => $location->region]) }}">
                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                    </a>
                @endif
            </div>

            {{-- Phones: small cards in a sideways row. --}}
            <div class="-mx-4 flex snap-x gap-3 overflow-x-auto px-4 pb-1 [scrollbar-width:none] sm:-mx-6 sm:px-6 md:hidden">
                @foreach ($nearby as $near)
                    <x-marketplace.mini-card :unit="$near" :rating="$ratings[(string) $near->id] ?? null" class="w-[42%] shrink-0 snap-start" />
                @endforeach
            </div>

            <div class="grid grid-cols-2 gap-x-5 gap-y-8 max-md:hidden lg:grid-cols-4">
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

    {{-- Below wide screens: price, WhatsApp and Enquire always at hand. --}}
    @if ($available && $headline)
        <div x-show="! sheet" class="fixed inset-x-0 bottom-0 z-40 flex items-center gap-2.5 border-t border-slate-200 bg-white px-4 pb-[max(16px,env(safe-area-inset-bottom))] pt-3 shadow-[0_-4px_16px_rgba(15,23,42,0.06)] lg:hidden">
            <div class="flex min-w-0 flex-1 flex-col items-start gap-0.5">
                <span class="text-[17px]"><b class="font-extrabold tnum">{{ $headline['currency'] }} {{ $headline['amount'] }}</b> / {{ $headline['per'] }}</span>
                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">✓ {{ __('marketplace.no_agent_fees') }}</span>
            </div>
            @if ($whatsapp)
                <a href="{{ $whatsapp }}" target="_blank" rel="noopener" aria-label="{{ __('Book on WhatsApp') }}"
                   class="grid h-12 w-12 shrink-0 place-items-center rounded-xl border border-slate-900">
                    <x-cms.whatsapp-icon class="h-[22px] w-[22px]" />
                </a>
            @endif
            <button type="button" @click="sheet = true" class="inline-flex min-h-[50px] shrink-0 cursor-pointer items-center rounded-xl bg-teal-700 px-6 text-base font-extrabold text-white hover:bg-teal-800">
                {{ $unit->takesShortStays() ? __('Book') : __('marketplace.enquire') }}
            </button>
        </div>
    @endif
</div>
