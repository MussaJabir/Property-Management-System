@props(['data' => []])

@php
    $heading = $data['heading'] ?? __('Send us a message');
    $note = $data['note'] ?? null;
@endphp

<section class="rounded-2xl bg-white p-8 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
    <h2 class="text-2xl font-semibold">{{ $heading }}</h2>
    @if ($note)
        <p class="mt-1 text-sm text-zinc-500">{{ $note }}</p>
    @endif
    <livewire:public.contact-form />
</section>
