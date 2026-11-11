{{-- Live preview of the website's top banner, beside its fields. --}}
<div>
    <p class="mb-2 text-sm font-medium text-gray-950 dark:text-white">{{ __('Preview') }}</p>
    <div class="relative h-64 overflow-hidden rounded-xl" style="background: var(--primary-600, #0A3A99);">
        @if ($photo)
            <img src="{{ $photo }}" alt="" class="absolute inset-0 h-full w-full object-cover" style="object-position: {{ $position ?? 'center' }}">
        @endif
        <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/20 to-transparent"></div>
        <div class="absolute inset-x-5 bottom-5 flex flex-col gap-2 text-white">
            <p class="text-2xl font-bold leading-tight">{{ $heading }}</p>
            @if (filled($subheading))
                <p class="text-sm text-white/90">{{ $subheading }}</p>
            @endif
            <p><span class="inline-block rounded-full bg-white px-3.5 py-2 text-sm font-semibold text-gray-900">{{ __('See rooms') }} →</span></p>
        </div>
    </div>
    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('How it looks on phones. Updates when you leave a field.') }}</p>
</div>
