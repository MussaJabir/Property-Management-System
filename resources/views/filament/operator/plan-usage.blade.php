@php
    $usage = \App\Services\Plans\PlanUsage::forCurrentUser();
    $plan = $usage?->plan();
    $used = $usage?->used(\App\Services\Plans\PlanUsage::UNITS) ?? 0;
    $limit = $usage?->limit(\App\Services\Plans\PlanUsage::UNITS);
    $percent = $limit ? min(100, (int) round($used / max(1, $limit) * 100)) : null;
@endphp
@if ($plan)
    <div class="pms-plan" x-show="$store.sidebar.isOpen" x-cloak>
        <div class="pms-plan-text">
            <strong>{{ __(':plan plan', ['plan' => $plan->name]) }}</strong>
            ·
            @if ($limit)
                {{ __(':used of :limit units used', ['used' => number_format($used), 'limit' => number_format($limit)]) }}
            @else
                {{ __(':used units', ['used' => number_format($used)]) }}
            @endif
        </div>
        @if ($percent !== null)
            <div class="pms-plan-meter" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Units used') }}">
                <span @class(['pms-plan-bar', 'is-near' => $percent >= 80]) style="width: {{ $percent }}%"></span>
            </div>
        @endif
    </div>
@endif
