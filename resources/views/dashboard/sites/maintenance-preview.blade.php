@php
    $defaults = [
        'enabled' => false,
        'heading' => "We'll be back soon",
        'message' => "We're performing scheduled maintenance to improve your experience. Please check back shortly.",
        'showCountdown' => false,
        'countdownTo' => '',
        'bgColor' => '#18181b',
        'textColor' => '#ffffff',
        'accentColor' => '#34d399',
        'showLogo' => true,
    ];
    $m = array_merge($defaults, is_array($site->maintenance_settings) ? $site->maintenance_settings : []);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance — {{ $site->name }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: {{ $m['bgColor'] }}; color: {{ $m['textColor'] }}; font-family: Inter, system-ui, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .maintenance-preview { max-width: 28rem; text-align: center; }
        .maintenance-logo { align-items: center; background: linear-gradient(135deg, {{ $m['accentColor'] }}, #06b6d4); border-radius: 10px; color: #000; display: inline-flex; font-size: 14px; font-weight: 700; height: 40px; justify-content: center; margin-bottom: 20px; width: 40px; }
        .maintenance-title { color: {{ $m['textColor'] }}; font-size: 1.35rem; font-weight: 600; margin-bottom: 10px; }
        .maintenance-message { font-size: 0.9rem; line-height: 1.55; opacity: 0.78; }
        .maintenance-countdown { font-family: ui-monospace, monospace; font-size: 11px; margin-top: 16px; opacity: 0.55; }
    </style>
    @if (filled($m['customCSS'] ?? ''))
        <style>{!! $m['customCSS'] !!}</style>
    @endif
</head>
<body>
    <div class="maintenance-preview">
        @if ($m['showLogo'] ?? true)
            <div class="maintenance-logo">U</div>
        @endif
        <h1 class="maintenance-title">{{ $m['heading'] }}</h1>
        <p class="maintenance-message">{{ $m['message'] }}</p>
        @if (! empty($m['showCountdown']) && filled($m['countdownTo'] ?? ''))
            <p class="maintenance-countdown">{{ $m['countdownTo'] }}</p>
        @endif
    </div>
</body>
</html>
