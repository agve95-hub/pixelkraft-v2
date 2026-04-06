<x-layouts.app>
    <x-slot:title>{{ $site->name }}</x-slot:title>

    @php
        $shouldAutoRefresh = is_null($site->last_synced_at) || in_array($site->deploy_status, ['building', 'deploying']);
    @endphp

    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <flux:heading size="xl">{{ $site->name }}</flux:heading>
                    @switch($site->deploy_status)
                        @case('live') <flux:badge color="lime">Live</flux:badge> @break
                        @case('building') @case('deploying') <flux:badge color="yellow">{{ ucfirst($site->deploy_status) }}</flux:badge> @break
                        @case('failed') <flux:badge color="red">Failed</flux:badge> @break
                        @default <flux:badge color="zinc">Idle</flux:badge>
                    @endswitch
                </div>
                @if ($site->domain)
                    <flux:link href="https://{{ $site->domain }}" target="_blank" variant="subtle" class="font-mono text-sm">{{ $site->domain }} ↗</flux:link>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('system.diagnostics') }}" variant="subtle" icon="server-stack" size="sm">Diagnostics</flux:button>
                <flux:button href="{{ route('sites.settings', $site) }}" variant="subtle" icon="cog-6-tooth" size="sm">Settings</flux:button>
            </div>
        </div>

        {{-- Quick Stats --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <flux:card size="sm">
                <flux:subheading size="sm">Pages</flux:subheading>
                <flux:heading size="xl" class="mt-1 font-mono">{{ $site->pages()->count() }}</flux:heading>
            </flux:card>
            <flux:card size="sm">
                <flux:subheading size="sm">Type</flux:subheading>
                <flux:badge color="purple" class="mt-2">{{ $site->project_type }}</flux:badge>
            </flux:card>
            <flux:card size="sm">
                <flux:subheading size="sm">Last Deploy</flux:subheading>
                <flux:text class="mt-2">{{ $site->last_deployed_at?->diffForHumans() ?? 'Never' }}</flux:text>
            </flux:card>
            <flux:card size="sm">
                <flux:subheading size="sm">Last Sync</flux:subheading>
                <flux:text class="mt-2">{{ $site->last_synced_at?->diffForHumans() ?? 'Never' }}</flux:text>
            </flux:card>
        </div>

        {{-- Deploy Controls --}}
        @livewire('sites.deploy-controls', ['siteId' => $site->id])

        {{-- Content Navigation --}}
        <div class="flex flex-wrap gap-2">
            <flux:button href="{{ route('blog.index', $site) }}" variant="subtle" size="sm" icon="document-text">
                Blog Posts <flux:badge size="sm" color="zinc" inset="top bottom">{{ $site->blogPosts()->count() }}</flux:badge>
            </flux:button>
            <flux:button href="{{ route('products.index', $site) }}" variant="subtle" size="sm" icon="shopping-bag">
                Products <flux:badge size="sm" color="zinc" inset="top bottom">{{ $site->productListings()->count() }}</flux:badge>
            </flux:button>
            <flux:button href="{{ route('templates.index', $site) }}" variant="subtle" size="sm" icon="squares-2x2">
                Templates <flux:badge size="sm" color="zinc" inset="top bottom">{{ $site->contentTemplates()->count() }}</flux:badge>
            </flux:button>
            <flux:button href="{{ route('seo.redirects', $site) }}" variant="subtle" size="sm" icon="arrows-right-left">
                Redirects <flux:badge size="sm" color="zinc" inset="top bottom">{{ $site->redirects()->count() }}</flux:badge>
            </flux:button>
            <flux:button href="{{ route('sites.files', $site) }}" variant="subtle" size="sm" icon="folder">
                Files
            </flux:button>
        </div>

        {{-- Pages List --}}
        @livewire('sites.page-listing', ['siteId' => $site->id])
    </div>

    @if ($shouldAutoRefresh)
        <script>
            setTimeout(() => window.location.reload(), 5000);
        </script>
    @endif
</x-layouts.app>
