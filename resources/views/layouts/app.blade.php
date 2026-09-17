<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Batang Surigaonon Scholar's App</title>
  <link rel="manifest" href="{{ asset('manifest.json') }}">
  <meta name="theme-color" content="#2fa76a">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="Batang Surigaonon">
  <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
  <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  @vite(['resources/css/styles.css'])
  @stack('styles')
</head>
<body>
  <div id="connection-status" class="connection-status" hidden role="status" aria-live="polite"></div>
  <div id="nav-loading" class="nav-loading" hidden role="status" aria-live="polite" aria-busy="false">
    <div class="nav-loading-card">
      <div class="nav-loading-spinner" aria-hidden="true"></div>
      <p>Loading…</p>
    </div>
  </div>
  @yield('content')
  <div id="avatar-preview-modal" class="avatar-preview-modal" hidden>
    <button type="button" class="avatar-preview-backdrop" data-avatar-preview-close aria-label="Close photo preview"></button>
    <div class="avatar-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="avatar-preview-title">
      <button type="button" class="avatar-preview-close" data-avatar-preview-close aria-label="Close photo preview">&times;</button>
      <img id="avatar-preview-image" class="avatar-preview-image" alt="">
      <p id="avatar-preview-title" class="avatar-preview-caption"></p>
    </div>
  </div>
  @yield('scripts')
  <script>
    (function () {
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
          navigator.serviceWorker.register('/sw.js')
            .then(function (registration) {
              console.log('Service Worker registered:', registration.scope);
            })
            .catch(function (error) {
              console.error('Service Worker registration failed:', error);
            });
        });
      }

      var bar = document.getElementById('connection-status');
      var onlineHideTimer = null;

      function setConnectionStatus(online) {
        if (!bar) return;
        if (onlineHideTimer) {
          clearTimeout(onlineHideTimer);
          onlineHideTimer = null;
        }
        if (!online) {
          bar.hidden = false;
          bar.className = 'connection-status is-offline';
          bar.textContent = 'Offline — Some features require an internet connection.';
          return;
        }
        bar.hidden = false;
        bar.className = 'connection-status is-online';
        bar.textContent = 'Online';
        onlineHideTimer = setTimeout(function () {
          if (navigator.onLine) bar.hidden = true;
        }, 2200);
      }

      window.addEventListener('online', function () { setConnectionStatus(true); });
      window.addEventListener('offline', function () { setConnectionStatus(false); });
      if (!navigator.onLine) setConnectionStatus(false);

      document.addEventListener('submit', function (event) {
        if (navigator.onLine) return;
        event.preventDefault();
        setConnectionStatus(false);
        if (bar) {
          bar.textContent = 'Offline — An internet connection is required to save or submit this.';
        }
      }, true);

      var loading = document.getElementById('nav-loading');
      function showNavLoading() {
        if (!loading) return;
        loading.hidden = false;
        loading.setAttribute('aria-busy', 'true');
        document.body.classList.add('is-navigating');
      }
      function hideNavLoading() {
        if (!loading) return;
        loading.hidden = true;
        loading.setAttribute('aria-busy', 'false');
        document.body.classList.remove('is-navigating');
      }
      function isModifiedClick(event) {
        return event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0;
      }
      document.addEventListener('click', function (event) {
        var link = event.target.closest('a[href]');
        if (!link || isModifiedClick(event)) return;
        if (link.hasAttribute('download') || link.getAttribute('data-no-loading') === 'true') return;
        if (link.target && link.target !== '_self') return;
        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
        try {
          var url = new URL(link.href, window.location.href);
          if (url.origin !== window.location.origin) return;
          if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;
        } catch (error) {
          return;
        }
        showNavLoading();
      });
      document.addEventListener('submit', function (event) {
        if (!navigator.onLine) return;
        var form = event.target;
        if (!form || form.getAttribute('data-no-loading') === 'true') return;
        if (form.target && form.target !== '_self') return;
        showNavLoading();
      });
      window.addEventListener('pageshow', function (event) {
        if (event.persisted) hideNavLoading();
      });
    })();
  </script>
</body>
</html>
