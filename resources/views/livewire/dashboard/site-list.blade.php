<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search sites..." icon="magnifying-glass" class="max-w-xs" />
        <flux:button href="{{ route('sites.create') }}" size="sm" variant="subtle" icon="plus">Add new site</flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Site</flux:table.column>
            <flux:table.column class="hidden md:table-cell">Pages</flux:table.column>
            <flux:table.column>Domain</flux:table.column>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Last Deploy</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($sites as $site)
                <flux:table.row>
                    <flux:table.cell>
                        <a href="{{ route('sites.index', ['site' => $site->id]) }}" class="flex items-center gap-2 hover:underline underline-offset-2">
                            <span @class([
                                'h-2 w-2 rounded-full shrink-0',
                                'bg-lime-500' => $site->latestUptimeCheck?->is_up === true,
                                'bg-red-500' => $site->latestUptimeCheck?->is_up === false,
                                'bg-zinc-400' => is_null($site->latestUptimeCheck?->is_up),
                            ])></span>
                            <span class="font-medium">{{ $site->name }}</span>
                        </a>
                    </flux:table.cell>
                    <flux:table.cell class="hidden md:table-cell text-xs font-mono">
                        {{ number_format($site->pages_count) }}
                    </flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ $site->domain ?? '—' }}</flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" color="purple">{{ $site->project_type }}</flux:badge></flux:table.cell>
                    <flux:table.cell>
                        @if ($site->deploy_status === \App\Enums\DeployStatus::Live)
                            <flux:badge size="sm" color="lime">Live</flux:badge>
                        @elseif ($site->deploy_status === \App\Enums\DeployStatus::Failed)
                            <flux:badge size="sm" color="red">Failed</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ $site->status }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-xs">{{ $site->last_deployed_at?->diffForHumans() ?? 'Never' }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center justify-end gap-1">
                            <flux:button href="{{ route('sites.show', $site) }}" size="xs" variant="ghost">Open</flux:button>
                            <flux:button href="{{ route('sites.settings', $site) }}" size="xs" variant="ghost" icon="cog-6-tooth" aria-label="Settings for {{ $site->name }}"></flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center py-12">
                        <div class="flex flex-col items-center gap-3">
                            <flux:icon name="globe-alt" variant="outline" class="size-10 text-zinc-400" />
                            <flux:heading>No sites yet</flux:heading>
                            <flux:text size="sm">Add your first site to get started.</flux:text>
                            <flux:button href="{{ route('sites.create') }}" variant="primary" icon="plus" class="mt-2 !bg-emerald-500 hover:!bg-emerald-400 !text-zinc-950 dark:!text-zinc-950">Add your first site</flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
