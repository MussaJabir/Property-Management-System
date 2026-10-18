<x-filament-panels::page>
    <form wire:submit="send" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-500">{{ __('Urgent? Call or WhatsApp +255 764 764 011.') }}</p>
            <x-filament::button type="submit" icon="heroicon-m-paper-airplane">
                {{ __('Send') }}
            </x-filament::button>
        </div>
    </form>

    @if ($mine->isNotEmpty())
        <div class="pms-card">
            <div class="pms-card-head">
                <h2>{{ __('What you have sent') }}</h2>
            </div>
            @foreach ($mine as $report)
                <div class="pms-item">
                    <div class="pms-item-main">
                        <b>
                            {{ \App\Models\FeedbackReport::typeLabels()[$report->type] ?? $report->type }}
                            <span @class(['pms-pill', 'is-gray' => $report->status !== 'new', 'is-warning' => $report->status === 'new'])>
                                {{ \App\Models\FeedbackReport::statusLabels()[$report->status] ?? $report->status }}
                            </span>
                        </b>
                        <small>{{ \Illuminate\Support\Str::limit($report->message, 140) }}</small>
                        @if ($report->reply)
                            <small class="pms-feedback-reply"><strong>{{ __('BJP') }}:</strong> {{ $report->reply }}</small>
                        @endif
                    </div>
                    <span class="pms-item-side">{{ $report->created_at?->format('d/m/Y') }}</span>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
