@props(['unit', 'photos'])

{{--
    Room photos: one large and up to four small (a swipe strip on phones),
    all of them in a full-screen overlay. Used by the landlord's room page
    and the marketplace room page. The optional `overlay` slot sits on top
    of the photos (the marketplace's Back and Share buttons on phones).
--}}
<div x-data="{ photos: false }" {{ $attributes->class('relative') }}>
    @if ($photos->isNotEmpty())
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
        <div class="flex aspect-[16/9] items-center justify-center rounded-[20px] bg-[#ece9e3] md:aspect-[16/7]">
            <x-cms.photo-icon class="h-14 w-14 text-black/30" />
        </div>
    @endif

    {{ $overlay ?? '' }}
</div>
