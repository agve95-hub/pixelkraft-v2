<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} — pixelkraft</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-zinc-950" x-data="{ sidebarOpen: false }">

    <div class="flex h-full">

        {{-- ── Sidebar (desktop) ──────────────────── --}}
        <aside class="hidden lg:flex lg:flex-col lg:w-64 lg:border-r lg:border-zinc-800 lg:bg-zinc-900/50">
            <x-layout.sidebar />
        </aside>

        {{-- ── Mobile sidebar overlay ─────────────── --}}
        <div
            x-show="sidebarOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-40 lg:hidden"
            x-cloak
        >
            <div class="fixed inset-0 bg-black/60" x-on:click="sidebarOpen = false"></div>
            <aside class="fixed inset-y-0 left-0 z-50 w-64 border-r border-zinc-800 bg-zinc-900">
                <x-layout.sidebar />
            </aside>
        </div>

        {{-- ── Main content ───────────────────────── --}}
        <div class="flex flex-1 flex-col min-w-0">

            {{-- Top bar --}}
            <header class="flex h-14 items-center gap-4 border-b border-zinc-800 px-4 lg:px-6">
                {{-- Mobile menu button --}}
                <button
                    type="button"
                    class="lg:hidden -ml-1 text-zinc-400 hover:text-zinc-200"
                    x-on:click="sidebarOpen = true"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                {{-- Page title --}}
                <h1 class="text-sm font-semibold text-zinc-200 truncate">
                    {{ $title ?? 'Dashboard' }}
                </h1>

                <div class="ml-auto flex items-center gap-3">
                    {{-- Notifications bell --}}
                    @livewire('layout.notification-bell')

                    {{-- User menu --}}
                    <div x-data="{ open: false }" class="relative">
                        <button
                            x-on:click="open = !open"
                            class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200 transition"
                        >
                            <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <div
                            x-show="open"
                            x-on:click.outside="open = false"
                            x-transition
                            class="absolute right-0 mt-2 w-48 rounded-lg border border-zinc-800 bg-zinc-900 py-1 shadow-xl z-50"
                            x-cloak
                        >
                            <a href="{{ route('settings') }}" class="block px-4 py-2 text-sm text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">
                                Settings
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            {{-- Content area --}}
            <main class="flex-1 overflow-y-auto p-4 lg:p-6">
                {{-- Flash messages --}}
                @if (session('success'))
                    <div class="mb-4 rounded-lg bg-emerald-500/10 border border-emerald-500/20 px-4 py-3 text-sm text-emerald-400">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-4 rounded-lg bg-red-500/10 border border-red-500/20 px-4 py-3 text-sm text-red-400">
                        {{ session('error') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
