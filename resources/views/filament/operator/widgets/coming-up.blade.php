<x-filament-widgets::widget>
    <div class="pms-card">
        <div class="pms-card-head">
            <h2>{{ __('Coming up') }}</h2>
            <span>{{ __('Renew, re-let or fill') }}</span>
        </div>

        @foreach ($sections as $section)
            <div class="pms-subhead">{{ $section['label'] }}<span class="pms-tab-count">{{ $section['count'] }}</span></div>

            @forelse ($section['rows'] as $row)
                <{{ $row['url'] ? 'a' : 'div' }} @if ($row['url']) href="{{ $row['url'] }}" @endif @class(['pms-item', 'is-link' => $row['url']])>
                    <div class="pms-item-main">
                        <b>{{ $row['title'] }}</b>
                        <small>{{ $row['sub'] }}</small>
                    </div>
                    @if ($row['when'])
                        <span class="pms-item-side">{{ $row['when'] }}</span>
                    @endif
                </{{ $row['url'] ? 'a' : 'div' }}>
            @empty
                <p class="pms-empty is-small">{{ $section['empty'] }}</p>
            @endforelse
        @endforeach
    </div>
</x-filament-widgets::widget>
