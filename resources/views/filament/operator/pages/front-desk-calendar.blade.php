<x-filament-panels::page>
    <div class="pms-cal-nav">
        <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-left" wire:click="shift(-7)">{{ __('Week before') }}</x-filament::button>
        <x-filament::button color="gray" size="sm" wire:click="goToday">{{ __('Today') }}</x-filament::button>
        <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-right" icon-position="after" wire:click="shift(7)">{{ __('Week after') }}</x-filament::button>
        <span class="pms-cal-range">{{ $range }}</span>
    </div>

    @if ($rows->isEmpty())
        <div class="pms-card"><p class="pms-empty">{{ __('No rooms take short stays yet.') }}</p></div>
    @else
        <div class="pms-card pms-cal-wrap">
            <table class="pms-cal">
                <thead>
                    <tr>
                        <th class="pms-cal-room">{{ __('Room') }}</th>
                        @foreach ($days as $day)
                            <th @class(['is-today' => $day->isToday(), 'is-weekend' => $day->isWeekend()])>
                                <span>{{ $day->translatedFormat('D') }}</span>
                                <b>{{ $day->format('d') }}</b>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <th class="pms-cal-room" scope="row">
                                <b>{{ $row['code'] }}</b>
                                <small>{{ $row['property'] }}</small>
                            </th>
                            @foreach ($row['cells'] as $cell)
                                @if ($cell['booking'])
                                    @php($b = $cell['booking'])
                                    <td colspan="{{ $cell['span'] }}">
                                        <div @class(['pms-cal-stay', 'is-'.$b->status]) title="{{ $b->guest?->full_name }} · {{ $b->check_in_date->format('d/m') }} → {{ $b->check_out_date->format('d/m') }}">
                                            {{ $b->guest?->full_name }}
                                        </div>
                                    </td>
                                @elseif ($canWork && ! $cell['past'])
                                    <td class="is-free">
                                        <button type="button" class="pms-cal-free"
                                            wire:click="mountAction('cellBook', @js(['unit' => $row['id'], 'date' => $cell['date']]))"
                                            aria-label="{{ __('Book :room on :date', ['room' => $row['code'], 'date' => $cell['date']]) }}">+</button>
                                    </td>
                                @else
                                    <td class="is-free"></td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="pms-cal-legend">
            <span class="pms-cal-stay is-checked_in">{{ __('In house') }}</span>
            <span class="pms-cal-stay is-reserved">{{ __('Reserved') }}</span>
            <span>{{ __('Tap + on a free night to book it.') }}</span>
        </p>
    @endif
</x-filament-panels::page>
