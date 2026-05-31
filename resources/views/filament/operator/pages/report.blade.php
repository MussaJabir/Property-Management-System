<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Filters</x-slot>

        <form wire:submit.prevent>
            {{ $this->form }}
        </form>
    </x-filament::section>

    @php
        $columns = $this->getColumns();
        $rows = $this->getRows();
        $summary = $this->getSummary();
        $meta = $this->getMeta();
    @endphp

    <x-filament::section>
        <x-slot name="heading">{{ $meta['title'] ?? 'Report' }}</x-slot>
        @if (! empty($meta['subtitle']) || ! empty($meta['period']))
            <x-slot name="description">
                {{ $meta['subtitle'] ?? '' }}
                @if (! empty($meta['subtitle']) && ! empty($meta['period']))
                    &middot;
                @endif
                {{ $meta['period'] ?? '' }}
            </x-slot>
        @endif

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                <thead>
                    <tr>
                        @foreach ($columns as $col)
                            <th style="text-align:{{ ($col['align'] ?? 'left') }};padding:8px 10px;background:#0f766e;color:#fff;font-weight:600;font-size:0.8125rem;">
                                {{ $col['label'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr style="border-bottom:1px solid var(--fi-color-gray-200);">
                            @foreach ($columns as $col)
                                <td style="padding:8px 10px;text-align:{{ ($col['align'] ?? 'left') }};">
                                    {{ $row[$col['key']] ?? '' }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" style="padding:30px 10px;text-align:center;color:var(--fi-color-gray-500);font-style:italic;">
                                No data for the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (! empty($summary))
            <div style="margin-top:1rem;display:flex;justify-content:flex-end;">
                <table style="border-collapse:collapse;font-size:0.875rem;min-width:280px;">
                    @foreach ($summary as $label => $value)
                        @php $isLast = $loop->last; @endphp
                        <tr @if ($isLast) style="border-top:2px solid #0f766e;font-weight:700;" @endif>
                            <td style="padding:6px 10px;color:var(--fi-color-gray-600);">{{ $label }}</td>
                            <td style="padding:6px 10px;text-align:right;font-weight:600;">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
