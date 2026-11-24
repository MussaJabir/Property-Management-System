<x-filament-panels::page>
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center gap-3">
            <x-filament::badge :color="$statusColor" size="lg">{{ ucfirst($lease->status) }}</x-filament::badge>
            @if ($renewedFromUrl)
                <a href="{{ $renewedFromUrl }}" class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">{{ __('Renews an earlier lease') }}</a>
            @endif
            @if ($renewalUrl)
                <a href="{{ $renewalUrl }}" class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">
                    {{ __('Renewal starts :date', ['date' => $lease->renewal->start_date?->format('d/m/Y')]) }}
                </a>
            @endif
        </div>

        @if ($endingSoon)
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm dark:border-warning-500/20 dark:bg-warning-500/10">
                <b class="text-warning-800 dark:text-warning-300">
                    {{ $daysLeft < 0
                        ? __('Ended :count days ago (:date).', ['count' => abs($daysLeft), 'date' => $lease->end_date?->format('d/m/Y')])
                        : trans_choice('Ends in :count day (:date).|Ends in :count days (:date).', $daysLeft, ['date' => $lease->end_date?->format('d/m/Y')]) }}
                </b>
                <span class="text-warning-900 dark:text-warning-200">{{ __('Renew it, or plan the move-out. You are reminded 90, 60 and 30 days before.') }}</span>
            </div>
        @endif

        <x-filament::section :heading="__('Terms')">
            <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($terms as $label => $value)
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($lease->terms_notes)
                <p class="mt-4 whitespace-pre-line rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:bg-white/5 dark:text-gray-300">{{ $lease->terms_notes }}</p>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('History')" collapsible>
            @include('leases.history-modal', ['history' => $history])
        </x-filament::section>
    </div>
</x-filament-panels::page>
