<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} — pixelkraft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance(['nonce' => csp_nonce()])
    @livewireStyles
</head>
<body class="pk-app-shell min-h-screen antialiased text-white">

    @php
        // $navSites and $searchIndex are injected by the AppServiceProvider View composer.
        $activeSite = request()->route('site');
        $expandedSiteId = $activeSite?->id ?? session('expanded_site_id');
    @endphp

    <flux:sidebar sticky collapsible="mobile" class="pk-sidebar border-r">
        <flux:sidebar.header>
            <a href="{{ route('dashboard') }}" class="pk-logo flex items-center gap-2.5 px-1 py-0.5 text-[15px] font-semibold tracking-tight text-white no-underline">
                <span class="pk-logo-mark flex size-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold text-black">P</span>
                pixelkraft
            </a>
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.item icon="home" href="{{ route('dashboard') }}" :current="request()->routeIs('dashboard')" class="text-zinc-300">Dashboard</flux:sidebar.item>
        </flux:sidebar.nav>

        <flux:sidebar.nav>
            <flux:sidebar.group heading="Projects" class="grid">
                <flux:sidebar.item icon="globe-alt" href="{{ route('sites.index') }}" :current="request()->routeIs('sites.index')" class="text-zinc-300">All sites</flux:sidebar.item>
                @foreach ($navSites as $navSite)
                    @php
                        $siteSectionOpen = $expandedSiteId && (string) $expandedSiteId === (string) $navSite->id;
                        $maintOn = (bool) data_get($navSite->maintenance_settings, 'enabled', false);
                        $navSiteRowCurrent = $activeSite && (int) $activeSite->id === (int) $navSite->id;
                    @endphp
                    <flux:sidebar.item
                        href="{{ route('sites.show', $navSite) }}"
                        :current="$navSiteRowCurrent"
                        class="text-zinc-300"
                    >
                        <x-slot:icon>
                            <span @class([
                                'size-2 rounded-full shrink-0',
                                'bg-emerald-400' => $navSite->deploy_status === \App\Enums\DeployStatus::Live,
                                'bg-amber-400' => in_array($navSite->deploy_status, [\App\Enums\DeployStatus::Deploying, \App\Enums\DeployStatus::Queued]),
                                'bg-red-500' => $navSite->deploy_status === \App\Enums\DeployStatus::Failed,
                                'bg-zinc-500' => !in_array($navSite->deploy_status, [\App\Enums\DeployStatus::Live, \App\Enums\DeployStatus::Deploying, \App\Enums\DeployStatus::Queued, \App\Enums\DeployStatus::Failed]),
                            ])></span>
                        </x-slot:icon>
                        {{ $navSite->name }}
                    </flux:sidebar.item>
                    @if ($siteSectionOpen)
                        <div class="ml-6 flex flex-col gap-0.5 border-l border-zinc-600/90 pl-2">
                            <flux:sidebar.item
                                icon="document-duplicate"
                                href="{{ route('sites.pages', $navSite) }}"
                                :current="request()->routeIs('sites.pages', 'editor', 'seo.meta', 'seo.redirects')"
                                class="!py-1.5 text-zinc-400"
                            >
                                Pages &amp; SEO
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="newspaper"
                                href="{{ route('blog.index', $navSite) }}"
                                :current="request()->routeIs('blog.index', 'blog.create', 'blog.edit')"
                                class="!py-1.5 text-zinc-400"
                            >
                                Blog
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="cube"
                                href="{{ route('products.index', $navSite) }}"
                                :current="request()->routeIs('products.index', 'products.create', 'products.edit')"
                                class="!py-1.5 text-zinc-400"
                            >
                                Products
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="rectangle-stack"
                                href="{{ route('templates.index', $navSite) }}"
                                :current="request()->routeIs('templates.index')"
                                class="!py-1.5 text-zinc-400"
                            >
                                Templates
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="users"
                                href="{{ route('sites.subscribers', $navSite) }}"
                                :current="request()->routeIs('sites.subscribers')"
                                class="!py-1.5 text-zinc-400"
                            >
                                Subscribers
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="envelope"
                                href="{{ route('sites.inbox', $navSite) }}"
                                :current="request()->routeIs('sites.inbox')"
                                class="!py-1.5 text-zinc-400"
                            >
                                <span class="flex w-full min-w-0 items-center justify-between gap-2">
                                    <span>Inbox</span>
                                    @if (($navSite->unread_inbox_count ?? 0) > 0)
                                        <span class="inline-flex min-w-[1.25rem] shrink-0 items-center justify-center rounded-md bg-white/10 px-1.5 py-0.5 font-mono text-[10px] font-medium text-zinc-400">
                                            {{ $navSite->unread_inbox_count > 99 ? '99+' : $navSite->unread_inbox_count }}
                                        </span>
                                    @endif
                                </span>
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="clipboard-document"
                                href="{{ route('sites.reports', $navSite) }}"
                                :current="request()->routeIs('sites.reports')"
                                class="!py-1.5 text-zinc-400"
                            >
                                Reports
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="megaphone"
                                href="{{ route('sites.campaigns', $navSite) }}"
                                :current="request()->routeIs('sites.campaigns')"
                                class="!py-1.5 text-zinc-400"
                            >
                                Campaigns
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="banknotes"
                                href="{{ route('sites.expenses', $navSite) }}"
                                :current="request()->routeIs('sites.expenses')"
                                class="!py-1.5 text-zinc-400"
                            >
                                Expenses
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="document-text"
                                href="{{ route('sites.invoices', $navSite) }}"
                                :current="request()->routeIs('sites.invoices')"
                                class="!py-1.5 text-zinc-400"
                            >
                                <span class="flex w-full min-w-0 items-center justify-between gap-2">
                                    <span>Invoices</span>
                                    @if (($navSite->unpaid_invoices_count ?? 0) > 0)
                                        <span class="inline-flex min-w-[1.25rem] shrink-0 items-center justify-center rounded-md bg-white/10 px-1.5 py-0.5 font-mono text-[10px] font-medium text-zinc-400">
                                            {{ $navSite->unpaid_invoices_count > 99 ? '99+' : $navSite->unpaid_invoices_count }}
                                        </span>
                                    @endif
                                </span>
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="clock"
                                href="{{ route('sites.reminders', $navSite) }}"
                                :current="request()->routeIs('sites.reminders')"
                                class="!py-1.5 text-zinc-400"
                            >
                                <span class="flex w-full min-w-0 items-center justify-between gap-2">
                                    <span>Reminders</span>
                                    @if (($navSite->overdue_reminders_count ?? 0) > 0)
                                        <span class="inline-flex min-w-[1.25rem] shrink-0 items-center justify-center rounded-md bg-amber-500/15 px-1.5 py-0.5 font-mono text-[10px] font-medium text-amber-400">
                                            {{ $navSite->overdue_reminders_count > 99 ? '99+' : $navSite->overdue_reminders_count }}
                                        </span>
                                    @endif
                                </span>
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="chart-bar"
                                href="{{ route('sites.analytics', $navSite) }}"
                                :current="request()->routeIs('sites.analytics')"
                                class="!py-1.5 text-zinc-400"
                            >
                                Analytics
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="shield-check"
                                href="{{ route('sites.maintenance', $navSite) }}"
                                :current="request()->routeIs('sites.maintenance')"
                                class="!py-1.5 text-zinc-400"
                            >
                                <span class="flex w-full min-w-0 items-center justify-between gap-2">
                                    <span>Maintenance</span>
                                    @if ($maintOn)
                                        <span class="rounded-md bg-amber-500/15 px-1.5 py-0.5 font-mono text-[10px] font-medium text-amber-400">ON</span>
                                    @endif
                                </span>
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="photo"
                                href="{{ route('sites.files', $navSite) }}"
                                :current="request()->routeIs('sites.files')"
                                class="!py-1.5 text-zinc-400"
                            >
                                Media
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="cog-6-tooth"
                                href="{{ route('sites.settings', $navSite) }}"
                                :current="request()->routeIs('sites.settings')"
                                class="!py-1.5 text-zinc-400"
                            >
                                Site settings
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
                    class="w-full justify-start border border-white/10 bg-white/[0.04] text-zinc-200 hover:bg-white/[0.08] {{ request()->routeIs('sites.create') ? '!border-transparent !bg-emerald-400 hover:!bg-emerald-300 !text-zinc-950 dark:!text-zinc-950' : '' }}"
                >New project</flux:button>
            </div>
        </flux:sidebar.nav>

        <flux:sidebar.spacer />

        <flux:sidebar.nav>
            <flux:sidebar.item icon="cog-6-tooth" href="{{ route('settings') }}" :current="request()->routeIs('settings')" class="text-zinc-300">Settings</flux:sidebar.item>
        </flux:sidebar.nav>

        <div class="max-lg:hidden px-2 pb-2">
            <button
                type="button"
                id="pk-search-trigger"
                class="flex w-full items-center gap-2 rounded-lg border border-white/[0.08] bg-white/[0.03] px-2.5 py-1.5 text-left text-xs text-zinc-500 transition hover:border-white/15 hover:text-zinc-300"
            >
                <flux:icon.magnifying-glass class="size-3 shrink-0 opacity-50" />
                <span>Search</span>
                <span class="ml-auto inline-flex items-center rounded bg-white/[0.07] px-1 py-0.5 font-mono text-[9px] text-zinc-400">⌘K</span>
            </button>
        </div>

        <flux:dropdown position="top" align="start" class="max-lg:hidden">
            <flux:sidebar.profile
                name="{{ auth()->user()->name }}"
                avatar="{{ 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name) . '&background=27272a&color=f4f4f5&size=64&font-size=0.4&bold=true&rounded=false' }}"
            />
            <flux:menu>
                <flux:menu.item href="{{ route('system.diagnostics') }}" icon="server-stack">System</flux:menu.item>
                <flux:menu.item href="{{ route('system.ui') }}" icon="swatch">UI system</flux:menu.item>
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
        <button
            type="button"
            id="pk-search-trigger-mobile"
            aria-label="Search"
            class="flex items-center justify-center rounded-lg p-1.5 text-zinc-400 hover:text-zinc-100"
        >
            <flux:icon.magnifying-glass class="size-5" />
        </button>
        @livewire('layout.notification-bell')
        <flux:dropdown position="top" align="start">
            <flux:profile
                name="{{ auth()->user()->name }}"
                avatar="{{ 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name) . '&background=27272a&color=f4f4f5&size=64&font-size=0.4&bold=true&rounded=false' }}"
            />
            <flux:menu>
                <flux:menu.item href="{{ route('system.diagnostics') }}" icon="server-stack">System</flux:menu.item>
                <flux:menu.item href="{{ route('system.ui') }}" icon="swatch">UI system</flux:menu.item>
                <flux:menu.separator />
                <flux:menu.item href="{{ route('settings') }}" icon="cog-6-tooth">Settings</flux:menu.item>
                <flux:menu.item icon="arrow-right-start-on-rectangle" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Sign out</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    <flux:main class="pk-main">
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

    <div
        id="pk-search-overlay"
        class="fixed inset-0 z-[1000] hidden items-start justify-center bg-black/60 pt-24 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-labelledby="pk-search-title"
    >
        <span id="pk-search-title" class="sr-only">Search</span>
        <div class="w-full max-w-xl rounded-xl border border-white/10 bg-zinc-900 shadow-2xl">
            <div class="flex items-center gap-3 border-b border-white/[0.08] px-5 py-4">
                <flux:icon.magnifying-glass class="size-4 shrink-0 text-zinc-500" />
                <input
                    id="pk-search-input"
                    type="search"
                    autocomplete="off"
                    placeholder="Search sites and sections…"
                    class="w-full border-0 bg-transparent text-sm text-white outline-none placeholder:text-zinc-600"
                />
            </div>
            <div id="pk-search-results" class="max-h-[min(24rem,50vh)] overflow-y-auto py-1 text-sm"></div>
            <div class="flex gap-4 border-t border-white/[0.06] px-5 py-2.5 text-[11px] text-zinc-600">
                <span><kbd class="rounded bg-white/[0.08] px-1.5 py-0.5 font-mono text-zinc-400">Esc</kbd> close</span>
                <span><kbd class="rounded bg-white/[0.08] px-1.5 py-0.5 font-mono text-zinc-400">↵</kbd> open</span>
            </div>
        </div>
    </div>

    <script type="application/json" id="pk-search-data">@json($searchIndex->values())</script>

    <flux:toast />
    @livewireScripts
    @fluxScripts(['nonce' => csp_nonce()])
</body>
</html>
