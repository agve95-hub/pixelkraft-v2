<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'pixelkraft' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen flex items-center justify-center bg-white dark:bg-zinc-800 px-4">

    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <flux:heading size="xl">pixelkraft</flux:heading>
            <flux:subheading>{{ $subtitle ?? 'Site operations platform' }}</flux:subheading>
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
                <flux:subheading>{{ $footer }}</flux:subheading>
            </div>
        @endif
    </div>

    @fluxScripts
</body>
</html>
