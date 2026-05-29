<div class="inline-flex items-center gap-1 rounded-md border border-zinc-200 bg-white px-1 py-1 text-xs">
    @foreach ($this->supported as $locale)
        <button
            type="button"
            wire:click="switch('{{ $locale }}')"
            @class([
                'rounded px-2 py-1 font-medium uppercase tracking-wide transition',
                'bg-teal-700 text-white' => $this->current === $locale,
                'text-zinc-600 hover:text-zinc-900' => $this->current !== $locale,
            ])
        >
            {{ $locale }}
        </button>
    @endforeach
</div>
