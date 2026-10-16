@php
    $user = auth()->user();
    $client = $user?->client;
    $name = $client?->name ?? config('app.name');
    $tile = $client?->brand_primary_color ?: null;
@endphp
<div class="pms-brand">
    <span class="pms-brand-tile" @if ($tile) style="background-color: {{ $tile }}" @endif>{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
    <span class="pms-brand-text">
        <span class="pms-brand-name">{{ $name }}</span>
        <span class="pms-brand-sub">{{ __('Workspace') }}</span>
    </span>
</div>
