@props(['data' => []])

@php
    use App\Models\CmsAnnouncement;

    $limit = (int) ($data['limit'] ?? 10);
    $items = CmsAnnouncement::query()
        ->published()
        ->orderByDesc('published_at')
        ->limit($limit)
        ->get();
@endphp

<section class="space-y-3">
    @forelse ($items as $item)
        <article class="overflow-hidden rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
            <p class="text-xs uppercase tracking-wider text-zinc-500">{{ $item->published_at?->format('d M Y') }}</p>
            <h3 class="mt-1 text-lg font-semibold break-words">{{ $item->title }}</h3>
            @if ($item->excerpt)
                <p class="mt-2 text-sm text-zinc-600 break-words dark:text-zinc-300">{{ $item->excerpt }}</p>
            @endif
            <div class="prose prose-zinc mt-3 max-w-none whitespace-pre-line break-words text-sm dark:prose-invert" style="overflow-wrap: anywhere;">
                {{ $item->body }}
            </div>
        </article>
    @empty
        <div class="rounded-xl bg-white p-8 text-center text-sm text-zinc-500 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
            {{ __('No announcements yet.') }}
        </div>
    @endforelse
</section>
