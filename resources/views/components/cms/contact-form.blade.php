@props(['data' => []])

@php
    $client = tenant();
    $heading = $data['heading'] ?? __('Send us a message');
    $note = $data['note'] ?? null;
    $whatsapp = $client?->whatsappUrl(__('Hello :name, I found you on your website.', ['name' => $client->name]));
@endphp

<section class="rounded-2xl bg-white p-8 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
    <h2 class="text-2xl font-semibold">{{ $heading }}</h2>
    @if ($note)
        <p class="mt-1 text-sm text-zinc-500">{{ $note }}</p>
    @endif

    @if ($client?->contact_phone || $client?->contact_email)
        <div class="mt-4 flex flex-wrap gap-3 text-sm">
            @if ($client->contact_phone)
                <a href="tel:{{ preg_replace('/\s+/', '', $client->contact_phone) }}" class="inline-flex min-h-[44px] items-center rounded-xl border border-[var(--line)] px-4 font-semibold">{{ __('Call :phone', ['phone' => \App\Support\RequestAlerts::display($client->contact_phone)]) }}</a>
            @endif
            @if ($whatsapp)
                <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="inline-flex min-h-[44px] items-center gap-2 rounded-xl border border-[var(--line)] px-4 font-semibold"><x-cms.whatsapp-icon class="h-4 w-4" /> WhatsApp</a>
            @endif
            @if ($client->contact_email)
                <a href="mailto:{{ $client->contact_email }}" class="inline-flex min-h-[44px] items-center rounded-xl border border-[var(--line)] px-4 font-semibold break-all">{{ $client->contact_email }}</a>
            @endif
        </div>
    @endif

    <livewire:public.contact-form />
</section>
