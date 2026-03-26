<div>
    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search sites..." icon="magnifying-glass" size="sm" class="max-w-xs" />
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Site</flux:table.column>
            <flux:table.column>Domain</flux:table.column>
            <flux:table.column class="hidden md:table-cell">Type</flux:table.column>
            <flux:table.column class="hidden lg:table-cell">Pages</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column class="hidden lg:table-cell">Last Deploy</flux:table.column>
            <flux:table.column class="hidden xl:table-cell">Uptime</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($sites as $site)
                <flux:table.row>
                    <flux:table.cell class="font-medium">
                        <flux:link href="{{ route('sites.show', $site) }}" variant="subtle">{{ $site->name }}</flux:link>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($site->domain)
                            <flux:link href="https://{{ $site->domain }}" target="_blank" variant="subtle" class="font-mono text-xs">{{ $site->domain }}</flux:link>
                        @else
                            <flux:subheading size="sm">No domain</flux:subheading>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell class="hidden md:table-cell">
                        <flux:badge size="sm" color="purple">{{ $site->project_type }}</flux:badge>
                    </flux:table.cell>

                    <flux:table.cell class="hidden lg:table-cell font-mono text-xs">
                        {{ $site->pages_count }}
                    </flux:table.cell>

                    <flux:table.cell>
                        @switch($site->deploy_status)
                            @case('live')
                                <flux:badge size="sm" color="lime">Live</flux:badge>
                                @break
                            @case('building')
                            @case('deploying')
                                <flux:badge size="sm" color="yellow">{{ ucfirst($site->deploy_status) }}</flux:badge>
                                @break
                            @case('failed')
                                <flux:badge size="sm" color="red">Failed</flux:badge>
                                @break
                            @default
                                <flux:badge size="sm" color="zinc">Idle</flux:badge>
                        @endswitch
                    </flux:table.cell>

                    <flux:table.cell class="hidden lg:table-cell text-xs">
                        {{ $site->last_deployed_at?->diffForHumans() ?? '—' }}
                    </flux:table.cell>

                    <flux:table.cell class="hidden xl:table-cell">
                        @if ($site->latestUptimeCheck)
                            @if ($site->latestUptimeCheck->is_up)
                                <flux:badge size="sm" color="lime" inset="top bottom">{{ $site->latestUptimeCheck->responseTimeFormatted() }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="red" inset="top bottom">Down</flux:badge>
                            @endif
                        @else
                            <span class="text-xs text-zinc-400">—</span>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:button href="{{ route('sites.show', $site) }}" size="xs" variant="ghost">Manage</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8" class="text-center py-12">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="globe-alt" variant="outline" class="size-10 text-zinc-400" />
                            <flux:subheading>No sites yet</flux:subheading>
                            <flux:text size="sm">Add your first site to get started.</flux:text>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
