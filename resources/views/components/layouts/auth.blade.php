<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Universal Tool' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance(['nonce' => csp_nonce()])
    @livewireStyles
</head>
<body class="ui-app-shell min-h-screen antialiased text-white">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <a href="{{ route('login') }}" class="inline-flex items-center gap-2.5 text-[15px] font-semibold tracking-tight text-white no-underline">
                    <span class="ui-logo-mark flex size-8 shrink-0 items-center justify-center text-xs font-bold text-black">U</span>
                    Universal Tool
                </a>
                @isset($subtitle)
                    <p class="mt-2 text-sm text-zinc-400">{{ $subtitle }}</p>
                @endisset
            </div>

            <div class="ui-card p-8">
                {{ $slot }}
            </div>

            @isset($footer)
                <p class="mt-6 text-center text-sm text-zinc-500">
                    {!! $footer !!}
                </p>
            @endisset
        </div>
    </div>

    <flux:toast />
    @livewireScripts
    @fluxScripts(['nonce' => csp_nonce()])
</body>
</html>
