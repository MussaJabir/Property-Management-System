<x-filament-widgets::widget>
    <div class="pms-card" x-data="{ tab: @js(array_key_first($tabs)) }">
        <div class="pms-card-head">
            <h2>{{ __('Needs your attention') }}</h2>
            <span>{{ __('Act on it right here') }}</span>
        </div>

        <div class="pms-tabs" role="tablist">
            @foreach ($tabs as $key => $t)
                <button type="button" role="tab" class="pms-tab"
                    x-on:click="tab = @js($key)"
                    x-bind:aria-selected="tab === @js($key) ? 'true' : 'false'">
                    {{ $t['label'] }}<span class="pms-tab-count">{{ $t['count'] }}</span>
                </button>
            @endforeach
        </div>

        @foreach ($tabs as $key => $t)
            <div role="tabpanel" x-show="tab === @js($key)" @if (! $loop->first) x-cloak @endif>
                @forelse ($t['rows'] as $row)
                    <div class="pms-item">
                        <span class="pms-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::of($row['who'])->explode(' ')->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}</span>
                        <div class="pms-item-main">
                            <b>{{ $row['who'] }} <span class="pms-pill is-{{ $row['tone'] }}">{{ $row['pill'] }}</span></b>
                            <small>{{ $row['sub'] }}</small>
                        </div>
                        @if ($row['url'])
                            <x-filament::button tag="a" :href="$row['url']" size="sm" :color="$row['primary'] ? 'primary' : 'gray'">
                                {{ $row['action'] }}
                            </x-filament::button>
                        @endif
                    </div>
                @empty
                    <p class="pms-empty">{{ $t['empty'] }}</p>
                @endforelse

                @if ($t['count'] > count($t['rows']))
                    <a href="{{ $t['all'] }}" class="pms-more">{{ __('See all :count', ['count' => $t['count']]) }} →</a>
                @endif
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
