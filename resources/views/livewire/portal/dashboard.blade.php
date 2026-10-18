<div>
    <h1 class="text-2xl font-semibold">{{ __('Welcome, :name', ['name' => $renter?->full_name ?? '']) }}</h1>
    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Here is a quick view of your tenancy.') }}</p>

    @if (! $lease)
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
            {{ __('You do not have an active lease on file. Please contact your landlord if you believe this is a mistake.') }}
        </div>
    @else
        <div class="mt-6 grid gap-4 sm:grid-cols-3" data-tour="renter-summary">
            <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
                <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">{{ __('Unit') }}</p>
                <p class="mt-2 text-lg font-semibold">{{ $lease->unit?->code ?? '—' }}</p>
                <p class="text-xs text-zinc-500">{{ $lease->unit?->property?->name }}</p>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
                <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">{{ __('Next due') }}</p>
                @if ($nextDue)
                    <p class="mt-2 text-lg font-semibold">{{ $nextDue->due_date?->format('d/m/Y') }}</p>
                    <p class="text-xs text-zinc-500">{{ $nextDue->invoice_number ?? __('Draft invoice') }}</p>
                @else
                    <p class="mt-2 text-lg font-semibold text-emerald-600">{{ __('Nothing due') }}</p>
                    <p class="text-xs text-zinc-500">{{ __('You are all paid up.') }}</p>
                @endif
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
                <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">{{ __('Outstanding') }}</p>
                <p class="mt-2 text-lg font-semibold">{{ $outstanding }}</p>
                <p class="text-xs text-zinc-500">{{ __('Across all open invoices') }}</p>
            </div>
        </div>

        <div class="mt-6 rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500">{{ __('Lease details') }}</h2>
            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-zinc-500">{{ __('Rent') }}</dt>
                    <dd class="font-medium">{{ $lease->formatted_rent }} / {{ $lease->billing_cycle_label }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">{{ __('Start date') }}</dt>
                    <dd class="font-medium">{{ $lease->start_date?->format('d/m/Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">{{ __('End date') }}</dt>
                    <dd class="font-medium">{{ $lease->end_date?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">{{ __('Deposit') }}</dt>
                    <dd class="font-medium">{{ $lease->formatted_deposit }}</dd>
                </div>
            </dl>
        </div>

        {{-- Plain form post (not Livewire): tenancy is set by the URL. --}}
        <div class="mt-6 rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500">{{ __('How are we doing?') }}</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                @if ($rating)
                    {{ __('You rated us :score out of 5. You can change it any time.', ['score' => $rating->score]) }}
                @else
                    {{ __('Rate living here from 1 to 5. Your landlord sees it with your name.') }}
                @endif
            </p>
            <form method="POST" action="{{ route('portal.rating.store', ['tenant' => tenant('id')]) }}" class="mt-3 space-y-3">
                @csrf
                @include('rating._stars', ['score' => $rating?->score])
                <textarea name="comment" rows="3" maxlength="2000" placeholder="{{ __('Anything to tell us? (optional)') }}"
                    class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-950">{{ old('comment', $rating?->comment) }}</textarea>
                <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90" style="background-color: var(--brand);">
                    {{ $rating ? __('Update my rating') : __('Send my rating') }}
                </button>
            </form>
        </div>
    @endif
</div>
