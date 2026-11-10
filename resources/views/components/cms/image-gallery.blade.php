@props(['data' => []])

@php
    use App\Support\Files;

    $heading = $data['heading'] ?? null;
    // Uploaded photos carry a `path`; older ones a web address in `url`.
    $images = collect($data['images'] ?? [])
        ->map(fn ($image): array => [
            'url' => ! empty($image['path']) ? Files::publicUrl($image['path']) : ($image['url'] ?? null),
            'caption' => $image['caption'] ?? '',
        ])
        ->filter(fn (array $image): bool => filled($image['url']))
        ->values();
@endphp

@if ($images->isNotEmpty())
    <section class="flex flex-col gap-5">
        @if ($heading)
            <h2 class="text-2xl font-extrabold tracking-tight sm:text-[32px]">{{ $heading }}</h2>
        @endif
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach ($images as $img)
                <a href="{{ $img['url'] }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-xl">
                    <img src="{{ $img['url'] }}" alt="{{ $img['caption'] ?: tenant()?->name }}" loading="lazy" class="aspect-[4/3] w-full object-cover transition hover:scale-105">
                </a>
            @endforeach
        </div>
    </section>
@endif
