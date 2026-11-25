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

        <div class="grid gap-6 lg:grid-cols-5">
        <x-filament::section :heading="__('Terms')" class="lg:col-span-3">
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

        <x-filament::section :heading="__('Deposit')" :description="__('Kept apart from rent. It is the tenant\'s money until refunded.')" class="lg:col-span-2">
            <div class="flex items-baseline justify-between rounded-lg bg-primary-50 px-4 py-3 dark:bg-primary-500/10">
                <span class="font-semibold text-primary-800 dark:text-primary-300">{{ __('Held now') }}</span>
                <span class="text-xl font-bold text-primary-800 dark:text-primary-300">{{ $depositHeld }}</span>
            </div>
            @if ($depositEntries->isNotEmpty())
                <ul class="mt-4 divide-y divide-gray-100 text-sm dark:divide-white/5">
                    @foreach ($depositEntries as $entry)
                        <li class="flex justify-between gap-3 py-2.5">
                            <div class="min-w-0">
                                <span class="font-medium text-gray-950 dark:text-white">{{ $entry->label() }}</span>
                                <span class="text-gray-500 dark:text-gray-400">· {{ $entry->occurred_on?->format('d/m/Y') }}</span>
                                @if ($entry->reason)
                                    <div class="break-words text-gray-500 dark:text-gray-400">{{ $entry->reason }}</div>
                                @endif
                            </div>
                            <span class="shrink-0 font-semibold tabular-nums">{{ $entry->signedAmount() < 0 ? '−' : '' }}{{ $money($entry->amount) }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('Nothing received yet. It counts once the invoice with the deposit is paid.') }}</p>
            @endif
            <div class="mt-4 flex flex-wrap gap-2">
                @if ($this->receiveDepositAction->isVisible())
                    {{ $this->receiveDepositAction }}
                @endif
                @if ($this->deductDepositAction->isVisible())
                    {{ $this->deductDepositAction }}
                @endif
                @if ($this->refundDepositAction->isVisible())
                    {{ $this->refundDepositAction }}
                @endif
            </div>
        </x-filament::section>
        </div>

        <x-filament::section :heading="__('History')" collapsible>
            @include('leases.history-modal', ['history' => $history])
        </x-filament::section>
    </div>
</x-filament-panels::page>
