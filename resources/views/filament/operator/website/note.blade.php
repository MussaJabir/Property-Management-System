<p class="rounded-lg bg-gray-50 px-3 py-2.5 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-300">
    {{ $text }}
    @if (! empty($link))
        <a href="{{ $link }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">{{ $linkLabel ?? "" }}</a>
    @endif
</p>
