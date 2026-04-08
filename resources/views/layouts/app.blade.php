<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} — pixelkraft</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
    @livewireStyles
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800 antialiased">

    <flux:sidebar sticky collapsible="mobile" class="bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.header>
            <flux:sidebar.brand href="{{ route('dashboard') }}" name="pixelkraft" />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.item icon="home" href="{{ route('dashboard') }}" :current="request()->routeIs('dashboard')">Dashboard</flux:sidebar.item>

            <flux:sidebar.group expandable heading="Pages" class="grid">
                <flux:sidebar.item icon="globe-alt" href="{{ route('sites.index') }}" :current="request()->routeIs('sites.*')">Sites</flux:sidebar.item>
                <flux:sidebar.item icon="chart-bar" href="{{ route('analytics') }}" :current="request()->routeIs('analytics')">Analytics</flux:sidebar.item>
                <flux:sidebar.item icon="inbox" href="{{ route('inbox') }}" :current="request()->routeIs('inbox')">Form Inbox</flux:sidebar.item>
                <flux:sidebar.item icon="users" href="{{ route('subscribers') }}" :current="request()->routeIs('subscribers')">Subscribers</flux:sidebar.item>
                <flux:sidebar.item icon="envelope" href="{{ route('newsletters') }}" :current="request()->routeIs('newsletters')">Newsletters</flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>

        <flux:sidebar.spacer />

        <flux:sidebar.nav>
            <flux:sidebar.item icon="server-stack" href="{{ route('system.diagnostics') }}" :current="request()->routeIs('system.diagnostics')">System</flux:sidebar.item>
            <flux:sidebar.item icon="cog-6-tooth" href="{{ route('settings') }}" :current="request()->routeIs('settings')">Settings</flux:sidebar.item>
        </flux:sidebar.nav>

        <flux:dropdown position="top" align="start" class="max-lg:hidden">
            <flux:sidebar.profile name="{{ auth()->user()->name }}" />
            <flux:menu>
                <flux:menu.item href="{{ route('system.diagnostics') }}" icon="server-stack">System</flux:menu.item>
                <flux:menu.separator />
                <flux:menu.item href="{{ route('settings') }}" icon="cog-6-tooth">Settings</flux:menu.item>
                <flux:menu.item icon="arrow-right-start-on-rectangle" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Sign out</flux:menu.item>
            </flux:menu>
        </flux:dropdown>

        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
    </flux:sidebar>

    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        @livewire('layout.notification-bell')
        <flux:spacer />
        <flux:dropdown position="top" align="start">
            <flux:profile name="{{ auth()->user()->name }}" />
            <flux:menu>
                <flux:menu.item href="{{ route('system.diagnostics') }}" icon="server-stack">System</flux:menu.item>
                <flux:menu.separator />
                <flux:menu.item href="{{ route('settings') }}" icon="cog-6-tooth">Settings</flux:menu.item>
                <flux:menu.item icon="arrow-right-start-on-rectangle" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Sign out</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    <flux:main>
        @if (session('success'))
            <div class="mb-6" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                <flux:callout variant="success" icon="check-circle" dismissible>{{ session('success') }}</flux:callout>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6">
                <flux:callout variant="danger" icon="exclamation-triangle" dismissible>{{ session('error') }}</flux:callout>
            </div>
        @endif

        {{ $slot }}
    </flux:main>

    <flux:toast />
    @livewireScripts
    @fluxScripts
</body>
</html>
