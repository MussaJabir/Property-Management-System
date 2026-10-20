@props(['data' => []])

@php
    use App\Models\Unit;

    $heading = $data['heading'] ?? __('Available now');
    $limit = (int) ($data['limit'] ?? 6);
    $client = tenant();

    $units = Unit::query()
        ->listedPublicly()
        ->with(['property.location', 'media', 'property.media'])
        ->orderByDesc('updated_at')
        ->limit($limit)
        ->get();
@endphp

<section class="flex flex-col gap-6">
    <div class="flex items-end justify-between gap-4">
        <h2 class="text-2xl font-extrabold tracking-tight sm:text-[32px]">{{ $heading }}</h2>
        <a href="{{ url('/'.$client->slug.'/units') }}" class="shrink-0 text-[15px] font-semibold underline underline-offset-4">{{ __('See all rooms') }}</a>
    </div>

    @if ($units->isEmpty())
        <div class="flex flex-col items-center gap-3 rounded-2xl border border-[var(--line)] px-6 py-12 text-center">
            <h3 class="text-xl font-bold">{{ __('Everything is taken right now.') }}</h3>
            <p class="max-w-md text-[var(--muted)]">{{ __('Message us and we will tell you when a room opens up.') }}</p>
            @if ($client->whatsappUrl())
                <a href="{{ $client->whatsappUrl(__('Hello, please tell me when a room is free.')) }}" target="_blank" rel="noopener"
                   class="inline-flex min-h-[44px] items-center gap-2 rounded-xl border border-[var(--ink)] px-5 text-sm font-bold">
                    <x-cms.whatsapp-icon class="h-5 w-5" /> {{ __('Message us') }}
                </a>
            @else
                <a href="{{ url('/'.$client->slug.'/contact') }}" class="inline-flex min-h-[44px] items-center rounded-xl border border-[var(--ink)] px-5 text-sm font-bold">{{ __('Contact us') }}</a>
            @endif
        </div>
    @else
        <div class="grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-x-6 gap-y-8">
            @foreach ($units as $unit)
                <x-cms.room-card :unit="$unit" />
            @endforeach
        </div>
    @endif
</section>
