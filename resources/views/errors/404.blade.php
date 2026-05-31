<x-errors._layout badge="404" badgeBg="#fef3c7" badgeFg="#78350f" :title="__('Page not found')">
    <h1>{{ __('We could not find that page.') }}</h1>
    <p>{{ __('The link may be old, or the page may have moved. Check the URL or head back to the homepage.') }}</p>
    <a class="btn" href="{{ url('/') }}">{{ __('Go to homepage') }}</a>
</x-errors._layout>
