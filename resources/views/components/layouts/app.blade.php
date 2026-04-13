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
<body class="min-h-screen bg-white antialiased dark:bg-[#121212]">

    @php
        $navSites = \App\Support\SiteAccess::query()
            ->select('id', 'name', 'slug', 'deploy_status')
            ->withCount([
                'inboxMessages as unread_inbox_count' => function ($q) {
                    $q->where('direction', 'inbound')->where('is_read', false);
                },
                'invoices as unpaid_invoices_count' => function ($q) {
                    $q->where('status', 'unpaid');
                },
            ])
            ->orderBy('name')
            ->get();
        $activeSite = request()->route('site');
    @endphp

    <flux:sidebar sticky collapsible="mobile" class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-[#0f0f0f]">
        <flux:sidebar.header>
            <flux:sidebar.brand href="{{ route('dashboard') }}" name="pixelkraft" />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.item icon="home" href="{{ route('dashboard') }}" :current="request()->routeIs('dashboard')">Dashboard</flux:sidebar.item>
        </flux:sidebar.nav>

        <flux:sidebar.nav>
            <flux:sidebar.group heading="Projects" class="grid">
                <flux:sidebar.item icon="globe-alt" href="{{ route('sites.index') }}" :current="request()->routeIs('sites.index')">All sites</flux:sidebar.item>
                @foreach ($navSites as $navSite)
                    <flux:sidebar.item
                        href="{{ route('sites.show', $navSite) }}"
                        :current="request()->routeIs('sites.show') && $activeSite?->id === $navSite->id"
                    >
                        <x-slot:icon>
                            <span @class([
                                'size-2 rounded-full shrink-0',
                                'bg-lime-500' => $navSite->deploy_status === 'live',
                                'bg-amber-400' => in_array($navSite->deploy_status, ['deploying', 'queued']),
                                'bg-red-500' => $navSite->deploy_status === 'failed',
                                'bg-zinc-400' => !in_array($navSite->deploy_status, ['live', 'deploying', 'queued', 'failed']),
                            ])></span>
                        </x-slot:icon>
                        {{ $navSite->name }}
                    </flux:sidebar.item>
                    @if ($activeSite?->id === $navSite->id)
                        <div class="ml-6 flex flex-col gap-0.5 border-l border-zinc-700/80 pl-2">
                            <flux:sidebar.item
                                icon="envelope"
                                href="{{ route('sites.inbox', $navSite) }}"
                                :current="request()->routeIs('sites.inbox')"
                                class="!py-1.5"
                            >
                                <span class="flex w-full min-w-0 items-center justify-between gap-2">
                                    <span>Inbox</span>
                                    @if (($navSite->unread_inbox_count ?? 0) > 0)
                                        <span class="inline-flex min-w-[1.25rem] shrink-0 items-center justify-center rounded-md bg-emerald-500/20 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-400">
                                            {{ $navSite->unread_inbox_count > 99 ? '99+' : $navSite->unread_inbox_count }}
                                        </span>
                                    @endif
                                </span>
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="document-text"
                                href="{{ route('sites.invoices', $navSite) }}"
                                :current="request()->routeIs('sites.invoices')"
                                class="!py-1.5"
                            >
                                <span class="flex w-full min-w-0 items-center justify-between gap-2">
                                    <span>Invoices</span>
                                    @if (($navSite->unpaid_invoices_count ?? 0) > 0)
                                        <span class="inline-flex min-w-[1.25rem] shrink-0 items-center justify-center rounded-md bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-semibold text-amber-400">
                                            {{ $navSite->unpaid_invoices_count > 99 ? '99+' : $navSite->unpaid_invoices_count }}
                                        </span>
                                    @endif
                                </span>
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="banknotes"
                                href="{{ route('sites.expenses', $navSite) }}"
                                :current="request()->routeIs('sites.expenses')"
                                class="!py-1.5"
                            >
                                Expenses
                            </flux:sidebar.item>
                        </div>
                    @endif
                @endforeach
            </flux:sidebar.group>

            <div class="px-3 pt-1">
                <flux:button
                    href="{{ route('sites.create') }}"
                    variant="{{ request()->routeIs('sites.create') ? 'primary' : 'subtle' }}"
                    size="sm"
                    icon="plus"
                    class="w-full justify-start {{ request()->routeIs('sites.create') ? '!bg-emerald-500 hover:!bg-emerald-400 !text-zinc-950 dark:!text-zinc-950' : '' }}"
                >New project</flux:button>
            </div>
        </flux:sidebar.nav>

        <flux:sidebar.spacer />

        <flux:sidebar.nav>
            <flux:sidebar.item icon="cog-6-tooth" href="{{ route('settings') }}" :current="request()->routeIs('settings')">Settings</flux:sidebar.item>
        </flux:sidebar.nav>

        <flux:dropdown position="top" align="start" class="max-lg:hidden">
            <flux:sidebar.profile
                name="{{ auth()->user()->name }}"
                avatar="{{ 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name) . '&background=27272a&color=f4f4f5&size=64&font-size=0.4&bold=true&rounded=false' }}"
            />
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
        <flux:spacer />
        @livewire('layout.notification-bell')
        <flux:dropdown position="top" align="start">
            <flux:profile
                name="{{ auth()->user()->name }}"
                avatar="{{ 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name) . '&background=27272a&color=f4f4f5&size=64&font-size=0.4&bold=true&rounded=false' }}"
            />
            <flux:menu>
                <flux:menu.item href="{{ route('system.diagnostics') }}" icon="server-stack">System</flux:menu.item>
                <flux:menu.separator />
                <flux:menu.item href="{{ route('settings') }}" icon="cog-6-tooth">Settings</flux:menu.item>
                <flux:menu.item icon="arrow-right-start-on-rectangle" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Sign out</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    <flux:main class="dark:bg-[#121212]">
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
