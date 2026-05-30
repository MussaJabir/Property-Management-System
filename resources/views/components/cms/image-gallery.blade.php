@props(['data' => []])

@php
    $heading = $data['heading'] ?? null;
    $images = $data['images'] ?? [];
@endphp

<section class="rounded-2xl bg-white p-8 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
    @if ($heading)
        <h2 class="text-2xl font-semibold">{{ $heading }}</h2>
    @endif
    @if (count($images))
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($images as $img)
                <a href="{{ $img['url'] ?? '#' }}" target="_blank" class="block overflow-hidden rounded-lg">
                    <img src="{{ $img['url'] ?? '' }}" alt="{{ $img['caption'] ?? '' }}" class="h-48 w-full object-cover transition hover:scale-105">
                </a>
            @endforeach
        </div>
    @else
        <p class="mt-3 text-sm text-zinc-500">{{ __('No images yet.') }}</p>
    @endif
</section>
