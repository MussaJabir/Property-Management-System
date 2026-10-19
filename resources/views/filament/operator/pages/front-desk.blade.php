<x-filament-panels::page>
    @php
        $stateLabels = [
            'free' => __('Free'),
            'occupied' => __('In house'),
            'leaving' => __('Leaving'),
            'arriving' => __('Arriving'),
            'blocked' => __('Not available'),
        ];
    @endphp

    <div class="pms-desk-figures">
        <div><b>{{ $counts['in_house'] }}</b><span>{{ __('in house') }}</span></div>
        <div><b>{{ $counts['free'] }}</b><span>{{ __('free tonight') }}</span></div>
        <div><b>{{ $arrivals->count() }}</b><span>{{ __('arriving') }}</span></div>
        <div><b>{{ $departures->count() }}</b><span>{{ __('leaving') }}</span></div>
    </div>

    <div class="pms-desk-lists">
        <div class="pms-card">
            <div class="pms-card-head">
                <h2>{{ __('Arriving today') }}</h2>
                <span>{{ trans_choice(':count guest|:count guests', $arrivals->count()) }}</span>
            </div>
            @forelse ($arrivals as $booking)
                <div class="pms-item">
                    <div class="pms-item-main">
                        <b>
                            {{ $booking->guest?->full_name }}
                            @if ($booking->check_in_date->isPast() && ! $booking->check_in_date->isToday())
                                <span class="pms-pill is-warning">{{ __('since :date', ['date' => $booking->check_in_date->format('d/m')]) }}</span>
                            @endif
                        </b>
                        <small>{{ $booking->unit?->code }} · {{ trans_choice(':count night|:count nights', $booking->nights()) }} · {{ __('leaves :date', ['date' => $booking->check_out_date->format('D d/m')]) }}</small>
                    </div>
                    @if ($canWork)
                        {{ ($this->arrivalCheckInAction)(['booking' => $booking->id]) }}
                    @endif
                </div>
            @empty
                <p class="pms-empty">{{ __('No arrivals waiting.') }}</p>
            @endforelse
        </div>

        <div class="pms-card">
            <div class="pms-card-head">
                <h2>{{ __('Leaving today') }}</h2>
                <span>{{ trans_choice(':count guest|:count guests', $departures->count()) }}</span>
            </div>
            @forelse ($departures as $booking)
                <div class="pms-item">
                    <div class="pms-item-main">
                        <b>
                            {{ $booking->guest?->full_name }}
                            @if ($booking->check_out_date->lt(today()))
                                <span class="pms-pill is-danger">{{ __('was due :date', ['date' => $booking->check_out_date->format('d/m')]) }}</span>
                            @elseif ($pastCheckOut)
                                <span class="pms-pill is-danger">{{ __('past :time', ['time' => $checkOutTime]) }}</span>
                            @endif
                            @if (($booking->invoice?->balanceDue() ?? 0) > 0)
                                <span class="pms-pill is-warning">{{ __('owes :amount', ['amount' => $booking->invoice?->formatted_balance]) }}</span>
                            @endif
                        </b>
                        <small>{{ $booking->unit?->code }} · {{ __('since :date', ['date' => $booking->check_in_date->format('D d/m')]) }}</small>
                    </div>
                    @if ($canWork)
                        <div class="pms-item-actions">
                            {{ ($this->stayExtendAction)(['booking' => $booking->id]) }}
                            {{ ($this->stayRequestPaymentAction)(['booking' => $booking->id]) }}
                            {{ ($this->stayCheckPaymentAction)(['booking' => $booking->id]) }}
                            {{ ($this->stayCheckOutAction)(['booking' => $booking->id]) }}
                        </div>
                    @endif
                </div>
            @empty
                <p class="pms-empty">{{ __('Nobody is due to leave.') }}</p>
            @endforelse
        </div>
    </div>

    @forelse ($rooms as $property => $propertyRooms)
        <section class="pms-desk-property">
            <h2 class="pms-subhead">{{ $property }}</h2>
            <div class="pms-rooms">
                @foreach ($propertyRooms as $room)
                    <div @class(['pms-room', 'is-'.$room['state']])>
                        <div class="pms-room-top">
                            <b>{{ $room['code'] }}</b>
                            <span class="pms-room-state">{{ $stateLabels[$room['state']] }}</span>
                        </div>

                        <div class="pms-room-body">
                            @if ($room['booking'])
                                <span class="pms-room-guest">{{ $room['booking']->guest?->full_name }}</span>
                                <small>
                                    @if ($room['state'] === 'arriving')
                                        {{ trans_choice(':count night|:count nights', $room['booking']->nights()) }}
                                    @else
                                        {{ __('until :date', ['date' => $room['booking']->check_out_date->format('D d/m')]) }}
                                    @endif
                                </small>
                            @elseif ($room['state'] === 'blocked')
                                <small>{{ $room['blocked_reason'] }}</small>
                            @else
                                <small>{{ $room['rate'] ? __(':rate a night', ['rate' => $room['rate']]) : __('No rate set') }}</small>
                                @if ($room['next'])
                                    <small>{{ __('Booked from :date', ['date' => $room['next']->check_in_date->format('D d/m')]) }}</small>
                                @endif
                            @endif
                        </div>

                        @if ($canWork)
                            <div class="pms-room-actions">
                                @switch ($room['state'])
                                    @case('free')
                                        {{ ($this->roomCheckInAction)(['unit' => $room['id']]) }}
                                        @break
                                    @case('arriving')
                                        {{ ($this->arrivalCheckInAction)(['booking' => $room['booking']->id]) }}
                                        @break
                                    @case('occupied')
                                    @case('leaving')
                                        {{ ($this->stayExtendAction)(['booking' => $room['booking']->id]) }}
                                        {{ ($this->stayRequestPaymentAction)(['booking' => $room['booking']->id]) }}
                                        {{ ($this->stayCheckPaymentAction)(['booking' => $room['booking']->id]) }}
                                        {{ ($this->stayCheckOutAction)(['booking' => $room['booking']->id]) }}
                                        @break
                                @endswitch
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <div class="pms-card">
            <p class="pms-empty">
                {{ __('No rooms take short stays yet. Open a unit and set "How is it let?" to Short stays or Both.') }}
                @if ($unitsUrl)
                    <br><a href="{{ $unitsUrl }}" class="pms-link">{{ __('Go to units') }} →</a>
                @endif
            </p>
        </div>
    @endforelse
</x-filament-panels::page>
