<x-layouts.app>
    <x-slot:title>Sites</x-slot:title>

    @php
        $selectedSite = null;
        $selectedId = request()->query('site');

        if ($selectedId) {
            $selectedSite = \App\Models\Site::query()->find($selectedId);
        }

        if (! $selectedSite) {
            $selectedSite = \App\Models\Site::query()->orderBy('name')->first();
        }
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">Sites</flux:heading>
                <flux:subheading>Add, connect, and deploy from one workspace.</flux:subheading>
            </div>
            <flux:button href="{{ route('sites.create') }}" variant="primary" icon="plus" class="!bg-emerald-500 hover:!bg-emerald-400 !text-zinc-950 dark:!text-zinc-950 shrink-0">
                Add new site
            </flux:button>
        </div>

        <div class="space-y-6">
            <flux:card>
                <flux:heading size="lg">Your sites</flux:heading>
                <flux:subheading>Select a site to view status and deploy controls.</flux:subheading>

                <div class="mt-4">
                    @livewire('dashboard.site-list')
                </div>
            </flux:card>

            @if ($selectedSite)
                <flux:card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <flux:heading size="lg">{{ $selectedSite->name }}</flux:heading>
                            <flux:subheading class="font-mono text-xs mt-1">{{ $selectedSite->repo_url }}</flux:subheading>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:button href="{{ route('sites.show', $selectedSite) }}" variant="subtle" size="sm">Full details</flux:button>
                            <flux:button href="{{ route('sites.settings', $selectedSite) }}" variant="subtle" icon="cog-6-tooth" size="sm">Settings</flux:button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
                        <flux:card size="sm">
                            <flux:subheading size="sm">Pages</flux:subheading>
                            <flux:heading size="xl" class="mt-1 font-mono">{{ $selectedSite->pages()->count() }}</flux:heading>
                        </flux:card>
                        <flux:card size="sm">
                            <flux:subheading size="sm">Type</flux:subheading>
                            <flux:badge color="purple" class="mt-2">{{ $selectedSite->project_type }}</flux:badge>
                        </flux:card>
                        <flux:card size="sm">
                            <flux:subheading size="sm">Last Deploy</flux:subheading>
                            <flux:text class="mt-2">{{ $selectedSite->last_deployed_at?->diffForHumans() ?? 'Never' }}</flux:text>
                        </flux:card>
                        <flux:card size="sm">
                            <flux:subheading size="sm">Last Sync</flux:subheading>
                            <flux:text class="mt-2">{{ $selectedSite->last_synced_at?->diffForHumans() ?? 'Never' }}</flux:text>
                        </flux:card>
                    </div>
                </flux:card>

                @livewire('sites.deploy-controls', ['siteId' => $selectedSite->id], key('deploy-controls-'.$selectedSite->id))
            @else
                <flux:card>
                    <flux:text>No sites yet — use Add new site above to create one and unlock deploy controls here.</flux:text>
                </flux:card>
            @endif
        </div>
    </div>
</x-layouts.app>
