{{-- How the post's card looks on the News list, beside the form. --}}
<div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
    @if ($photo)
        <img src="{{ $photo }}" alt="" class="h-32 w-full object-cover">
    @endif
    <div class="flex flex-col gap-1 px-4 py-3">
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ now()->format('d/m/Y') }}</p>
        <p class="font-bold text-gray-950 dark:text-white">{{ filled($title) ? $title : __('Your title') }}</p>
    </div>
</div>
