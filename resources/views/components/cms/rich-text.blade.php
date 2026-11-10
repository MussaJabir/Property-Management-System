@props(['data' => []])

@php
    $heading = $data['heading'] ?? null;
    $body = $data['body'] ?? '';
    // Old starter pages came with made-up text; never show it. No text, no section.
    if (\App\Services\Cms\PageEditor::isOldStarterText($body)) {
        $body = '';
        $heading = null;
    }
@endphp

@if (filled($body))
<section class="overflow-hidden rounded-2xl bg-white p-8 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
    @if ($heading)
        <h2 class="text-2xl font-semibold break-words">{{ $heading }}</h2>
    @endif
    <div class="prose prose-zinc mt-3 max-w-none whitespace-pre-line break-words text-sm dark:prose-invert" style="overflow-wrap: anywhere;">
        {{ $body }}
    </div>
</section>
@endif
