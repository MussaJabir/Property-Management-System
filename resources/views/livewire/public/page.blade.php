<div class="space-y-6">
    @if ($page->title || $page->subtitle)
        <header class="space-y-1">
            <h1 class="text-3xl font-bold sm:text-4xl">{{ $page->title }}</h1>
            @if ($page->subtitle)
                <p class="text-base text-zinc-600 dark:text-zinc-300">{{ $page->subtitle }}</p>
            @endif
        </header>
    @endif

    @if (! empty($page->blocks))
        <x-cms.render-blocks :blocks="$page->blocks" />
    @else
        <div class="rounded-xl bg-white p-8 text-center text-sm text-zinc-500 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
            {{ __('This page has no content yet.') }}
        </div>
    @endif
</div>
