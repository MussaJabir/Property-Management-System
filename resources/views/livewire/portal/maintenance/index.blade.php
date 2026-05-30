@php $client = tenant(); @endphp

<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ __('Maintenance') }}</h1>
        <a href="{{ url('/'.$client->slug.'/portal/maintenance/create') }}"
           class="rounded-md px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
           style="background-color: var(--brand);">
            {{ __('New request') }}
        </a>
    </div>

    <div class="mt-4 space-y-3">
        @forelse ($requests as $r)
            @php
                $colors = [
                    'pending' => 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
                    'in_progress' => 'bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
                    'completed' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
                    'cancelled' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
                ];
                $cls = $colors[$r->status] ?? 'bg-zinc-100 text-zinc-700';
            @endphp
            <a href="{{ url('/'.$client->slug.'/portal/maintenance/'.$r->id) }}"
               class="block rounded-xl bg-white p-4 shadow-sm transition hover:shadow-md dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold">{{ $r->title }}</h3>
                        <p class="mt-1 text-xs text-zinc-500">
                            {{ $r->unit?->code }} · {{ $r->unit?->property?->name }} · {{ $r->reported_at?->format('d/m/Y H:i') }}
                        </p>
                    </div>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $cls }}">{{ str_replace('_', ' ', ucfirst($r->status)) }}</span>
                </div>
            </a>
        @empty
            <div class="rounded-xl bg-white p-8 text-center text-sm text-zinc-500 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
                {{ __('No maintenance requests yet. Click "New request" to submit one.') }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $requests->links() }}
    </div>
</div>
