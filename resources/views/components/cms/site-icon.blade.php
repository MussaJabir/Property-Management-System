@props(['client', 'touch' => true])
{{-- Browser tab and phone home-screen icon: the client's own, else PMS's.
     The renter portal keeps its app icon for the home screen (touch: false). --}}
@php($icon = $client?->iconUrl())
<link rel="icon" href="{{ $icon ?? asset('favicon.ico') }}">
@if ($icon && $touch)
    <link rel="apple-touch-icon" href="{{ $icon }}">
@endif
