<x-layouts.app>
    <x-slot:title>{{ $site->name }}</x-slot:title>

    <div class="space-y-6">
        {{-- Site Header --}}
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-semibold text-zinc-100">{{ $site->name }}</h2>
                    @switch($site->deploy_status)
                        @case('live')
                            <span class="badge-green">Live</span>
                            @break
                        @case('building')
                        @case('deploying')
                            <span class="badge-amber">{{ ucfirst($site->deploy_status) }}</span>
                            @break
                        @case('failed')
                            <span class="badge-red">Failed</span>
                            @break
                        @default
                            <span class="badge bg-zinc-500/10 text-zinc-500">Idle</span>
                    @endswitch
                </div>
                @if ($site->domain)
                    <a href="https://{{ $site->domain }}" target="_blank" class="mono text-sm text-zinc-500 hover:text-violet-400 transition">
                        {{ $site->domain }} ↗
                    </a>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('sites.settings', $site) }}" class="btn-secondary text-sm">Settings</a>
            </div>
        </div>

        {{-- Quick Stats --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="card">
                <p class="text-xs text-zinc-500 uppercase tracking-wider">Pages</p>
                <p class="text-2xl font-bold text-zinc-100 mt-1">{{ $site->pages()->count() }}</p>
            </div>
            <div class="card">
                <p class="text-xs text-zinc-500 uppercase tracking-wider">Type</p>
                <p class="text-sm font-semibold text-zinc-100 mt-2 mono">{{ $site->project_type }}</p>
            </div>
            <div class="card">
                <p class="text-xs text-zinc-500 uppercase tracking-wider">Last Deploy</p>
                <p class="text-sm text-zinc-100 mt-2">{{ $site->last_deployed_at?->diffForHumans() ?? 'Never' }}</p>
            </div>
            <div class="card">
                <p class="text-xs text-zinc-500 uppercase tracking-wider">Last Sync</p>
                <p class="text-sm text-zinc-100 mt-2">{{ $site->last_synced_at?->diffForHumans() ?? 'Never' }}</p>
            </div>
        </div>

        {{-- Deploy Controls --}}
        @livewire('sites.deploy-controls', ['siteId' => $site->id])

        {{-- Content Navigation --}}
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('blog.index', $site) }}" class="btn-secondary text-xs">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6V7.5Z" /></svg>
                Blog Posts
                <span class="mono text-[10px] text-zinc-600">{{ $site->blogPosts()->count() }}</span>
            </a>
            <a href="{{ route('products.index', $site) }}" class="btn-secondary text-xs">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" /></svg>
                Products
                <span class="mono text-[10px] text-zinc-600">{{ $site->productListings()->count() }}</span>
            </a>
            <a href="{{ route('templates.index', $site) }}" class="btn-secondary text-xs">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" /></svg>
                Templates
                <span class="mono text-[10px] text-zinc-600">{{ $site->contentTemplates()->count() }}</span>
            </a>
            <a href="{{ route('seo.redirects', $site) }}" class="btn-secondary text-xs">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                Redirects
                <span class="mono text-[10px] text-zinc-600">{{ $site->redirects()->count() }}</span>
            </a>
        </div>

        {{-- Pages List --}}
        @livewire('sites.page-listing', ['siteId' => $site->id])
    </div>
</x-layouts.app>
