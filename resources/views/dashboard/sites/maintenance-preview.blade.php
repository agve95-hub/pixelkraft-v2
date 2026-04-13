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
        body { font-family: Inter, system-ui, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
    </style>
    @if (filled($m['customCSS'] ?? ''))
        <style>{!! $m['customCSS'] !!}</style>
    @endif
</head>
<body style="background: {{ $m['bgColor'] }}; color: {{ $m['textColor'] }};">
    <div style="text-align: center; max-width: 28rem;">
        @if ($m['showLogo'] ?? true)
            <div style="display: inline-flex; width: 40px; height: 40px; align-items: center; justify-content: center; border-radius: 10px; font-weight: 700; font-size: 14px; color: #000; margin-bottom: 20px; background: linear-gradient(135deg, {{ $m['accentColor'] }}, #06b6d4);">P</div>
        @endif
        <h1 style="font-size: 1.35rem; font-weight: 600; margin-bottom: 10px; color: {{ $m['textColor'] }};">{{ $m['heading'] }}</h1>
        <p style="font-size: 0.9rem; line-height: 1.55; opacity: 0.78;">{{ $m['message'] }}</p>
        @if (! empty($m['showCountdown']) && filled($m['countdownTo'] ?? ''))
            <p style="margin-top: 16px; font-family: ui-monospace, monospace; font-size: 11px; opacity: 0.55;">{{ $m['countdownTo'] }}</p>
        @endif
    </div>
</body>
</html>
