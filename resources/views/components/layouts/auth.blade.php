<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'pixelkraft' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen flex items-center justify-center bg-white dark:bg-zinc-800 antialiased px-4">

    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <flux:heading size="xl">pixelkraft</flux:heading>
            <flux:text class="mt-1">{{ $subtitle ?? 'Site operations platform' }}</flux:text>
        </div>

        <flux:card>
            @if (session('status'))
                <div class="mb-6">
                    <flux:callout variant="success" icon="check-circle">{{ session('status') }}</flux:callout>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6">
                    <flux:callout variant="danger" icon="exclamation-triangle">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </flux:callout>
                </div>
            @endif

            {{ $slot }}
        </flux:card>

        @if (isset($footer))
            <div class="mt-6 text-center">
                <flux:text size="sm">{{ $footer }}</flux:text>
            </div>
        @endif
    </div>

    @fluxScripts
</body>
</html>
