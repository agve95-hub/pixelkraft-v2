<div>
    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search sites..." icon="magnifying-glass" class="max-w-xs" />
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Site</flux:table.column>
            <flux:table.column>Domain</flux:table.column>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Last Deploy</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($sites as $site)
                <flux:table.row>
                    <flux:table.cell class="font-medium">{{ $site->name }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ $site->domain ?? '—' }}</flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" color="purple">{{ $site->project_type }}</flux:badge></flux:table.cell>
                    <flux:table.cell>
                        @if ($site->deploy_status === 'live')
                            <flux:badge size="sm" color="lime">Live</flux:badge>
                        @elseif ($site->deploy_status === 'failed')
                            <flux:badge size="sm" color="red">Failed</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ ucfirst($site->deploy_status ?? 'idle') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-xs">{{ $site->last_deployed_at?->diffForHumans() ?? 'Never' }}</flux:table.cell>
                    <flux:table.cell><flux:button href="{{ route('sites.index', ['site' => $site->id]) }}" size="xs" variant="ghost">Open</flux:button></flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center py-12">
                        <div class="flex flex-col items-center gap-3">
                            <flux:icon name="globe-alt" variant="outline" class="size-10 text-zinc-400" />
                            <flux:heading>No sites yet</flux:heading>
                            <flux:text size="sm">Add your first site to get started.</flux:text>
                            <flux:button href="#add-site" variant="primary" icon="plus" class="mt-2">Add your first site</flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
