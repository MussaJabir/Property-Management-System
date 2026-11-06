@php
    $operator = auth('web')->user();
    $signedIn = $operator instanceof \App\Models\User && $operator->type === \App\Models\User::TYPE_OPERATOR;
    $item = 'flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px]';
@endphp

{{-- Phones: Explore · Filters · Log in (Dashboard when a landlord is signed in). --}}
<nav class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-3 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] sm:hidden" aria-label="{{ __('marketplace.title') }}">
    <button type="button" wire:click="clearFilters" @click="window.scrollTo({ top: 0, behavior: 'smooth' })" class="{{ $item }} cursor-pointer font-bold text-teal-700">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        {{ __('marketplace.explore') }}
    </button>
    <button type="button" @click="sheet = true" class="{{ $item }} cursor-pointer font-semibold text-slate-600">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
        {{ __('marketplace.filters') }}
    </button>
    @if ($signedIn)
        <a href="{{ route('filament.operator.pages.dashboard') }}" class="{{ $item }} font-semibold text-slate-600">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
            {{ __('marketplace.my_dashboard_short') }}
        </a>
    @else
        <a href="{{ route('filament.operator.auth.login') }}" class="{{ $item }} font-semibold text-slate-600">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
            {{ __('marketplace.landlord_login_short') }}
        </a>
    @endif
</nav>
