<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'pixelkraft' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-zinc-950 flex items-center justify-center px-4">

    <div class="w-full max-w-md">
        {{-- Logo --}}
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold tracking-tight text-zinc-100">pixelkraft</h1>
            <p class="mt-1 text-sm text-zinc-500 mono">{{ $subtitle ?? 'Site operations platform' }}</p>
        </div>

        {{-- Card --}}
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-8">
            {{-- Status / Errors --}}
            @if (session('status'))
                <div class="mb-6 rounded-lg bg-emerald-500/10 border border-emerald-500/20 px-4 py-3 text-sm text-emerald-400">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-lg bg-red-500/10 border border-red-500/20 px-4 py-3 text-sm text-red-400">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{ $slot }}
        </div>

        {{-- Footer --}}
        @if (isset($footer))
            <div class="mt-6 text-center text-sm text-zinc-500">
                {{ $footer }}
            </div>
        @endif
    </div>

</body>
</html>
