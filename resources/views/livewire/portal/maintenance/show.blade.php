@php
    $colors = [
        'pending' => 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
        'in_progress' => 'bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
        'completed' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
        'cancelled' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
    ];
    $cls = $colors[$request->status] ?? 'bg-zinc-100 text-zinc-700';
@endphp

<div class="mx-auto max-w-3xl">
    <a href="{{ url('/'.tenant()->slug.'/portal/maintenance') }}" class="text-sm text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-200">
        ← {{ __('All requests') }}
    </a>

    <div class="mt-4 rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">{{ $request->title }}</h1>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ $request->unit?->code }} · {{ $request->unit?->property?->name }} · {{ __('Reported :date', ['date' => $request->reported_at?->format('d/m/Y H:i')]) }}
                </p>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-medium {{ $cls }}">{{ str_replace('_', ' ', ucfirst($request->status)) }}</span>
        </div>

        <p class="mt-4 whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $request->description }}</p>

        @if ($request->getMedia('photos')->isNotEmpty())
            <div class="mt-4 grid grid-cols-3 gap-2">
                @foreach ($request->getMedia('photos') as $photo)
                    <a href="{{ $photo->getFullUrl() }}" target="_blank" class="block overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-800">
                        <img src="{{ $photo->getFullUrl() }}" alt="" class="h-32 w-full object-cover">
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <h2 class="mt-8 text-sm font-semibold uppercase tracking-wider text-zinc-500">{{ __('Activity') }}</h2>
    <ol class="mt-3 space-y-3">
        @forelse ($request->updates as $u)
            <li class="rounded-lg bg-white p-4 text-sm shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
                <div class="flex items-center justify-between text-xs text-zinc-500">
                    <span>{{ $u->user?->name ?? __('Landlord') }}</span>
                    <span>{{ $u->created_at?->format('d/m/Y H:i') }}</span>
                </div>
                @if ($u->status_change)
                    <p class="mt-1 text-xs"><span class="rounded bg-zinc-100 px-1.5 py-0.5 font-medium dark:bg-zinc-800">{{ str_replace('_', ' ', ucfirst($u->status_change)) }}</span></p>
                @endif
                @if ($u->note)
                    <p class="mt-2 whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $u->note }}</p>
                @endif
            </li>
        @empty
            <li class="rounded-lg bg-white p-4 text-center text-sm text-zinc-500 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
                {{ __('No updates yet. We will post here as the request is worked on.') }}
            </li>
        @endforelse
    </ol>
</div>
