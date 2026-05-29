@php
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\LeaseHistory> $history */

    // Friendly verbs + colors + icons for each action — keeps the template
    // free of branching logic and easy to extend (renewed, rent_changed, …).
    $actionMeta = [
        'created' => ['label' => 'Lease created', 'color' => 'gray', 'icon' => 'heroicon-m-document-plus'],
        'activated' => ['label' => 'Lease activated', 'color' => 'success', 'icon' => 'heroicon-m-play-circle'],
        'renewed' => ['label' => 'Lease renewed', 'color' => 'info', 'icon' => 'heroicon-m-arrow-path'],
        'rent_changed' => ['label' => 'Rent changed', 'color' => 'warning', 'icon' => 'heroicon-m-banknotes'],
        'ended' => ['label' => 'Lease ended', 'color' => 'gray', 'icon' => 'heroicon-m-check-circle'],
        'terminated' => ['label' => 'Lease terminated', 'color' => 'danger', 'icon' => 'heroicon-m-x-circle'],
    ];

    $statusColors = [
        'pending' => 'warning',
        'active' => 'success',
        'ended' => 'gray',
        'terminated' => 'danger',
    ];

    $fmtStatus = fn (?string $s): string => $s ? ucfirst($s) : '—';
@endphp

<div style="display:flex;flex-direction:column;gap:0.75rem;">
    @forelse ($history as $entry)
        @php
            $meta = $actionMeta[$entry->action] ?? $actionMeta['created'];
            $fromStatus = $entry->before['status'] ?? null;
            $toStatus = $entry->after['status'] ?? null;
            $hasStatusChange = $fromStatus && $toStatus && $fromStatus !== $toStatus;
        @endphp

        <x-filament::section>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                <div style="display:flex;flex-direction:column;gap:0.25rem;">
                    <x-filament::badge :color="$meta['color']" :icon="$meta['icon']">
                        {{ $meta['label'] }}
                    </x-filament::badge>
                    <div style="font-size:0.875rem;color:var(--fi-color-gray-600);">
                        by <strong>{{ $entry->user?->name ?? 'System' }}</strong>
                    </div>
                </div>

                <div style="text-align:right;font-size:0.8125rem;color:var(--fi-color-gray-500);white-space:nowrap;">
                    <div>{{ $entry->created_at?->format('d/m/Y') }}</div>
                    <div>{{ $entry->created_at?->format('H:i') }}</div>
                </div>
            </div>

            @if ($hasStatusChange)
                <div style="margin-top:0.75rem;display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;font-size:0.875rem;">
                    <span style="color:var(--fi-color-gray-600);">Status:</span>
                    <x-filament::badge :color="$statusColors[$fromStatus] ?? 'gray'" size="sm">
                        {{ $fmtStatus($fromStatus) }}
                    </x-filament::badge>
                    <span style="color:var(--fi-color-gray-400);">→</span>
                    <x-filament::badge :color="$statusColors[$toStatus] ?? 'gray'" size="sm">
                        {{ $fmtStatus($toStatus) }}
                    </x-filament::badge>
                </div>
            @endif

            @if ($entry->reason)
                <div style="margin-top:0.75rem;padding:0.625rem 0.75rem;background:var(--fi-color-gray-50);border-left:3px solid var(--fi-color-gray-300);border-radius:0.375rem;font-size:0.875rem;color:var(--fi-color-gray-700);">
                    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--fi-color-gray-500);margin-bottom:0.25rem;">Reason</div>
                    {{ $entry->reason }}
                </div>
            @endif
        </x-filament::section>
    @empty
        <div style="text-align:center;padding:2rem 1rem;color:var(--fi-color-gray-500);font-size:0.875rem;">
            No history yet for this lease.
        </div>
    @endforelse
</div>
