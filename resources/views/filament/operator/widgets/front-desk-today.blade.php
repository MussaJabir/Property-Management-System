<x-filament-widgets::widget>
    <div class="pms-card">
        <div class="pms-card-head">
            <h2>{{ __('Front desk today') }}</h2>
            <span>{{ trans_choice(':count guest in house|:count guests in house', $inHouse) }}</span>
        </div>

        <div class="pms-desk-today">
            @foreach ($sections as $section)
                <div>
                    <div class="pms-subhead">{{ $section['label'] }}<span class="pms-tab-count">{{ $section['count'] }}</span></div>
                    @forelse ($section['rows'] as $booking)
                        <a href="{{ $url }}" class="pms-item is-link">
                            <div class="pms-item-main">
                                <b>{{ $booking->guest?->full_name }}</b>
                                <small>{{ $booking->unit?->code }}</small>
                            </div>
                            <span class="pms-item-side">{{ ($section['when'])($booking) }}</span>
                        </a>
                    @empty
                        <p class="pms-empty is-small">{{ $section['empty'] }}</p>
                    @endforelse
                </div>
            @endforeach
        </div>

        <a href="{{ $url }}" class="pms-more">{{ __('Open the front desk') }} →</a>
    </div>
</x-filament-widgets::widget>
