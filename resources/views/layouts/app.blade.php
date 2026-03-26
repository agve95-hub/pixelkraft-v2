<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} — pixelkraft</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
    @livewireStyles
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">

    <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <flux:brand href="{{ route('dashboard') }}" name="pixelkraft" class="px-2" />

        <flux:navlist variant="outline">
            <flux:navlist.item
                icon="home"
                href="{{ route('dashboard') }}"
                :current="request()->routeIs('dashboard')"
            >
                Dashboard
            </flux:navlist.item>

            <flux:navlist.item
                icon="globe-alt"
                href="{{ route('sites.index') }}"
                :current="request()->routeIs('sites.*')"
            >
                Sites
            </flux:navlist.item>

            <flux:navlist.item
                icon="chart-bar"
                href="{{ route('analytics') }}"
                :current="request()->routeIs('analytics')"
            >
                Analytics
            </flux:navlist.item>
        </flux:navlist>

        <flux:navlist variant="outline">
            <flux:navlist.group heading="Email" class="mt-4">
                <flux:navlist.item
                    icon="inbox"
                    href="{{ route('inbox') }}"
                    :current="request()->routeIs('inbox')"
                >
                    Form Inbox
                </flux:navlist.item>

                <flux:navlist.item
                    icon="users"
                    href="{{ route('subscribers') }}"
                    :current="request()->routeIs('subscribers')"
                >
                    Subscribers
                </flux:navlist.item>

                <flux:navlist.item
                    icon="envelope"
                    href="{{ route('newsletters') }}"
                    :current="request()->routeIs('newsletters')"
                >
                    Newsletters
                </flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>

        <flux:spacer />

        <flux:navlist variant="outline">
            <flux:navlist.item
                icon="cog-6-tooth"
                href="{{ route('settings') }}"
                :current="request()->routeIs('settings')"
            >
                Settings
            </flux:navlist.item>
        </flux:navlist>

        <flux:separator />

        <flux:dropdown position="top" align="start">
            <flux:profile name="{{ auth()->user()->name }}" />

            <flux:menu>
                <flux:menu.item href="{{ route('settings') }}" icon="cog-6-tooth">Settings</flux:menu.item>
                <flux:separator />
                <flux:menu.item
                    icon="arrow-right-start-on-rectangle"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                >
                    Sign out
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>

        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
    </flux:sidebar>

    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:brand name="pixelkraft" />

        <flux:spacer />

        @livewire('layout.notification-bell')
    </flux:header>

    <flux:main>
        {{-- Flash messages --}}
        @if (session('success'))
            <div class="mb-4" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                <flux:callout variant="success" icon="check-circle" dismissible>
                    {{ session('success') }}
                </flux:callout>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4">
                <flux:callout variant="danger" icon="exclamation-triangle" dismissible>
                    {{ session('error') }}
                </flux:callout>
            </div>
        @endif

        {{ $slot }}
    </flux:main>

    <flux:toast />

    @livewireScripts
    @fluxScripts
</body>
</html>
