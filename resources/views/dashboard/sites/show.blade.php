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
                <button class="btn-primary text-sm" disabled>Deploy</button>
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

        {{-- Pages List --}}
        @livewire('sites.page-listing', ['siteId' => $site->id])
    </div>
</x-layouts.app>
