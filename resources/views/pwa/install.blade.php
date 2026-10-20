{{-- "Install app" pill. Shows when the browser offers installing (Android,
     desktop Chrome/Edge), or a one-line hint on iPhone Safari. Hidden once
     installed or dismissed (remembered on this device). --}}
<div id="pms-install" hidden
     style="position:fixed;inset-inline:16px;bottom:16px;z-index:60;display:flex;justify-content:flex-end;pointer-events:none">
    <div style="pointer-events:auto;display:flex;align-items:center;gap:10px;max-width:420px;padding:10px 12px 10px 14px;border-radius:14px;background:#0b4f4a;color:#fff;box-shadow:0 8px 24px rgb(0 0 0 / 20%);font:500 14px/1.35 ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,sans-serif">
        <img src="/images/pwa/icon-192.png" alt="" width="32" height="32" style="border-radius:8px;flex:none">
        <span id="pms-install-text" style="flex:1">{{ __('Install :name on this device for one-tap access.', ['name' => $name ?? 'PMS']) }}</span>
        <button type="button" id="pms-install-go" style="flex:none;border:0;border-radius:8px;padding:7px 12px;background:#fff;color:#0b4f4a;font:inherit;font-weight:650;cursor:pointer">{{ __('Install') }}</button>
        <button type="button" id="pms-install-close" aria-label="{{ __('Not now') }}" style="flex:none;border:0;background:none;color:#fff;font-size:20px;line-height:1;cursor:pointer;opacity:.8">×</button>
    </div>
</div>
<script>
(function () {
    var KEY = 'pms-install-dismissed';
    var box = document.getElementById('pms-install');
    if (!box) return;
    var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    var dismissed = false;
    try { dismissed = localStorage.getItem(KEY) === '1'; } catch (e) {}
    if (standalone || dismissed) return;

    var prompt = null;
    var go = document.getElementById('pms-install-go');
    var hide = function (remember) {
        box.hidden = true;
        if (remember) { try { localStorage.setItem(KEY, '1'); } catch (e) {} }
    };

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        prompt = e;
        box.hidden = false;
    });
    window.addEventListener('appinstalled', function () { hide(true); });
    go.addEventListener('click', function () {
        if (!prompt) { hide(true); return; }
        prompt.prompt();
        prompt.userChoice.finally(function () { prompt = null; hide(true); });
    });
    document.getElementById('pms-install-close').addEventListener('click', function () { hide(true); });

    // iPhone/iPad Safari has no install prompt: explain the Share menu once.
    var ios = /iphone|ipad|ipod/i.test(navigator.userAgent) && !/crios|fxios/i.test(navigator.userAgent);
    if (ios) {
        document.getElementById('pms-install-text').textContent = @js(__('To install: tap Share, then "Add to Home Screen".'));
        go.hidden = true;
        box.hidden = false;
    }
})();
</script>
