<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\Site;
use App\Models\TrackingInstallation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TrackingScriptService
{
    public function ensureInstallation(Site $site): TrackingInstallation
    {
        return app(SiteProvisioningService::class)->ensureDefaultTrackingInstallation($site);
    }

    public function trackerScript(Site $site): string
    {
        $collectorUrl = route('tracking.collect', ['site' => $site]);
        $siteKey = $site->id;

        return <<<JS
(function () {
  const siteId = {$this->json($siteKey)};
  const endpoint = {$this->json($collectorUrl)};
  const storageKey = 'pk_vid_' + siteId;
  const sessionKey = 'pk_sid_' + siteId;
  const visitorId = localStorage.getItem(storageKey) || crypto.randomUUID();
  const sessionId = sessionStorage.getItem(sessionKey) || crypto.randomUUID();
  localStorage.setItem(storageKey, visitorId);
  sessionStorage.setItem(sessionKey, sessionId);

  function send(eventName, payload) {
    const body = {
      event_name: eventName,
      path: location.pathname + location.search,
      referrer: document.referrer || null,
      visitor_id: visitorId,
      session_id: sessionId,
      payload: payload || {},
    };

    navigator.sendBeacon(endpoint, new Blob([JSON.stringify(body)], { type: 'application/json' }));
  }

  window.pixelkraftTrack = send;
  send('page_view', { title: document.title });

  document.addEventListener('click', function (event) {
    const target = event.target.closest('[data-track], a, button, [type="submit"]');
    if (!target) return;

    const name = target.getAttribute('data-track') || 'interaction';
    send(name, {
      text: (target.innerText || target.textContent || '').trim().slice(0, 180),
      href: target.getAttribute('href'),
      id: target.id || null,
      classes: target.className || null
    });
  }, { passive: true });

  document.addEventListener('submit', function (event) {
    const form = event.target;
    send('form_submit', {
      action: form.getAttribute('action'),
      id: form.id || null,
      name: form.getAttribute('name') || null
    });
  }, true);
})();
JS;
    }

    public function injectIntoDirectory(Site $site, string $directory): int
    {
        if (! File::isDirectory($directory)) {
            return 0;
        }

        $this->ensureInstallation($site);

        $htmlFiles = File::allFiles($directory);
        $count = 0;

        foreach ($htmlFiles as $file) {
            if (! in_array(strtolower($file->getExtension()), ['html', 'htm'], true)) {
                continue;
            }

            $path = $file->getPathname();
            $html = File::get($path);
            $patched = $this->injectIntoHtml($site, $html);

            if ($patched !== $html) {
                File::put($path, $patched);
                $count++;
            }
        }

        return $count;
    }

    public function injectIntoHtml(Site $site, string $html): string
    {
        $scriptUrl = route('tracking.script', ['site' => $site]);
        $scriptTag = '<script defer src="'.e($scriptUrl).'"></script>';
        $gaSnippet = $this->gaSnippet($site);
        $gtmSnippet = $this->gtmSnippet($site);
        $bundle = $scriptTag.$gaSnippet.$gtmSnippet;

        if (str_contains($html, $scriptUrl)) {
            return $html;
        }

        if (stripos($html, '</head>') !== false) {
            return preg_replace('/<\/head>/i', $bundle."\n</head>", $html, 1) ?? $html;
        }

        if (stripos($html, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $bundle."\n</body>", $html, 1) ?? $html;
        }

        return $html.$bundle;
    }

    public function recordEvent(Site $site, array $payload, Request $request): AnalyticsEvent
    {
        $path = trim((string) ($payload['path'] ?? '/'));

        $page = $site->pages()
            ->where('url_path', Str::before($path, '?'))
            ->first();

        return AnalyticsEvent::create([
            'site_id' => $site->id,
            'page_id' => $page?->id,
            'event_name' => (string) ($payload['event_name'] ?? 'page_view'),
            'path' => $path !== '' ? $path : '/',
            'visitor_id' => (string) ($payload['visitor_id'] ?? ''),
            'session_id' => (string) ($payload['session_id'] ?? ''),
            'referrer' => (string) ($payload['referrer'] ?? ''),
            'ip_hash' => hash('sha256', (string) $request->ip()),
            'user_agent' => (string) $request->userAgent(),
            'payload' => is_array($payload['payload'] ?? null) ? $payload['payload'] : [],
            'occurred_at' => now(),
        ]);
    }

    private function gaSnippet(Site $site): string
    {
        if (! $site->ga_property_id) {
            return '';
        }

        // Only inject if the stored ID matches the expected GA4 format.
        if (! preg_match('/^G-[A-Z0-9]{4,20}$/i', (string) $site->ga_property_id)) {
            return '';
        }

        $measurementId = e($site->ga_property_id);

        return <<<HTML
<script async src="https://www.googletagmanager.com/gtag/js?id={$measurementId}"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{$measurementId}');</script>
HTML;
    }

    private function gtmSnippet(Site $site): string
    {
        if (! $site->gtm_id) {
            return '';
        }

        // Only inject if the stored ID matches the expected GTM format.
        if (! preg_match('/^GTM-[A-Z0-9]{4,12}$/i', (string) $site->gtm_id)) {
            return '';
        }

        $containerId = e($site->gtm_id);

        return <<<HTML
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$containerId}');</script>
HTML;
    }

    private function json(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
