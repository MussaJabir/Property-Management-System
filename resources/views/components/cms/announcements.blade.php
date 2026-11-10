@props(['data' => []])

@php
    use App\Models\CmsAnnouncement;

    $limit = (int) ($data['limit'] ?? 10);
    // On the home page: a short list of the newest posts, hidden when there are none.
    $compact = ! empty($data['compact']);
    $items = CmsAnnouncement::query()
        ->published()
        ->orderByDesc('published_at')
        ->limit($limit)
        ->get();
@endphp

@if ($compact)
    @if ($items->isNotEmpty())
        <section class="flex flex-col gap-5">
            <div class="flex items-end justify-between gap-4">
                <h2 class="text-2xl font-extrabold tracking-tight sm:text-[32px]">{{ $data['heading'] ?? __('Latest news') }}</h2>
                <a href="{{ url('/'.tenant()?->getKey().'/news') }}" class="shrink-0 text-[15px] font-semibold underline underline-offset-4">{{ __('All news') }}</a>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ($items as $item)
                    <article class="rounded-xl border border-[var(--line)] p-5">
                        <p class="text-xs uppercase tracking-wider text-zinc-500">{{ $item->published_at?->format('d/m/Y') }}</p>
                        <h3 class="mt-1 font-semibold break-words">{{ $item->title }}</h3>
                        @if ($item->excerpt)
                            <p class="mt-2 text-sm text-zinc-600 break-words">{{ $item->excerpt }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif
@else
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
@endif
