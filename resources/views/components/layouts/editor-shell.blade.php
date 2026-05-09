@props([
    'title' => null,
])

{{--
    Full-screen editor chrome for the editor workspace.
    Intentionally separate from layouts.app so the page editor can occupy the viewport
    without the dashboard sidebar because the editor needs a focused surface, not the
    standard three-column dashboard.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Editor' }} - Universal Tool</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance(['nonce' => csp_nonce()])
    @livewireStyles
</head>
<body class="ui-editor-shell-body min-h-screen overflow-hidden antialiased text-zinc-100">

    {{ $slot }}

    <flux:toast />
    @livewireScripts
    @fluxScripts(['nonce' => csp_nonce()])
</body>
</html>
