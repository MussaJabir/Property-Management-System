<x-errors._layout badge="500" badgeBg="#fee2e2" badgeFg="#7f1d1d" :title="__('Something went wrong')">
    <h1>{{ __('Something broke on our side.') }}</h1>
    <p>{{ __('We have been notified and will look into it. Please try again in a moment.') }}</p>
    <a class="btn" href="{{ url('/') }}">{{ __('Back to safety') }}</a>
</x-errors._layout>
