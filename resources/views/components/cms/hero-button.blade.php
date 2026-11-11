@props(['href'])
{{-- The banner's button: always "See rooms", to the rooms page. --}}
<a href="{{ $href }}"
   {{ $attributes->class('group inline-flex min-h-[48px] cursor-pointer items-center gap-3 rounded-full bg-white px-6 text-sm font-bold tracking-tight text-zinc-900 shadow-lg ring-1 ring-black/5 transition-transform hover:-translate-y-0.5') }}>
    {{ __('See rooms') }}
    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full transition-transform group-hover:translate-x-0.5" style="background-color: var(--brand);">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 text-white" aria-hidden="true">
            <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
    </span>
</a>
