{{-- Installable app (PWA): manifest, phone theme colour, home-screen icon,
     and the service worker. $manifest is the manifest URL for this area. --}}
<link rel="manifest" href="{{ $manifest }}">
<meta name="theme-color" content="{{ $theme ?? \App\Http\Controllers\PwaController::THEME }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $title ?? 'PMS' }}">
<link rel="apple-touch-icon" href="/images/pwa/apple-touch-icon.png">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/service-worker.js', { scope: '/' }).catch(function () {});
        });
    }
</script>
