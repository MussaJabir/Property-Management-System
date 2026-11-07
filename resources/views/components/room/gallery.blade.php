@props(['unit', 'photos'])

{{--
    Room photos. Phones: every photo in a strip you swipe, with a "1 / 8"
    counter. Wider screens: one large and up to four small. Tapping any
    photo opens them all full screen. Used by the landlord's room page and
    the marketplace room page; the optional `overlay` slot sits on top of
    the photos (the marketplace's Back and Share buttons on phones).
--}}
<div x-data="{ photos: false, at: 1 }" {{ $attributes->class('relative') }}>
    @if ($photos->isNotEmpty())
        {{-- Phones --}}
        <div class="flex snap-x snap-mandatory overflow-x-auto rounded-2xl [scrollbar-width:none] md:hidden"
             @scroll.debounce.50ms="at = Math.round($el.scrollLeft / $el.clientWidth) + 1">
            @foreach ($photos as $i => $photo)
                <button type="button" @click="photos = true" class="aspect-[4/3] w-full shrink-0 snap-center bg-[#ece9e3]">
                    <img src="{{ $photo->getUrl('card') }}" alt="{{ $unit->publicTitle() }}" class="h-full w-full object-cover" @if ($i > 0) loading="lazy" @endif>
                </button>
            @endforeach
        </div>
        @if ($photos->count() > 1)
            <span class="pointer-events-none absolute bottom-8 right-4 rounded-md bg-slate-900/75 px-2 py-0.5 text-xs font-bold text-white md:hidden" aria-hidden="true">
                <span x-text="at">1</span> / {{ $photos->count() }}
            </span>
        @endif

        {{-- Wider screens --}}
        <div class="grid h-[428px] grid-cols-4 grid-rows-2 gap-2 overflow-hidden rounded-[20px] max-md:hidden">
            @foreach ($photos->take(5) as $i => $photo)
                <button type="button" @click="photos = true" class="{{ $i === 0 ? 'col-span-2 row-span-2' : '' }} bg-[#ece9e3]">
                    <img src="{{ $photo->getUrl('card') }}" alt="{{ $unit->publicTitle() }}" class="h-full w-full object-cover" @if ($i > 0) loading="lazy" @endif>
                </button>
            @endforeach
        </div>
        @if ($photos->count() > 1)
            <button type="button" @click="photos = true"
                    class="absolute bottom-3 right-3 min-h-[40px] rounded-xl border border-[var(--ink)] bg-white px-3.5 text-sm font-semibold max-md:hidden">
                {{ __('Show all :count photos', ['count' => $photos->count()]) }}
            </button>
        @endif

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
        <div class="flex aspect-[4/3] items-center justify-center rounded-[20px] bg-[#ece9e3] md:aspect-[16/7]">
            <x-cms.photo-icon class="h-14 w-14 text-black/30" />
        </div>
    @endif

    {{ $overlay ?? '' }}
</div>
