<div class="space-y-4">
    <div class="ui-list-toolbar">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search projects..." icon="magnifying-glass" class="ui-list-toolbar-control" />
        <x-ui.button href="{{ route('sites.create') }}" size="sm" variant="outline" icon="plus">New project</x-ui.button>
    </div>

    <x-ui.table>
            <thead>
                <tr>
                    <th>Site</th>
                    <th>Client</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>SSL</th>
                    <th class="hidden md:table-cell">Pages</th>
                    <th class="text-right">Last deploy</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sites as $site)
                    @php
                        $isUp = $site->latestUptimeCheck?->is_up;
                        $dot = $isUp === true ? 'bg-emerald-400' : ($isUp === false ? 'bg-red-400' : 'bg-amber-400');
                        $statusVariant = $site->deploy_status === \App\Enums\DeployStatus::Live
                            ? 'success'
                            : ($site->deploy_status === \App\Enums\DeployStatus::Failed ? 'destructive' : 'warning');
                        $sslVariant = $site->ssl_status === 'active' ? 'success' : 'warning';
                    @endphp
                    <tr class="clickable" onclick="window.location='{{ route('sites.show', $site) }}'">
                        <td>
                            <div class="site-name">
                                <span class="site-dot {{ $dot }}"></span>
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-zinc-100">{{ $site->name }}</div>
                                    <div class="truncate font-mono text-[11px] text-zinc-500">{{ $site->domain ?: $site->repo_url ?: 'Draft project' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="text-sm text-zinc-400">{{ $site->clientDisplayName() }}</td>
                        <td><span class="tag">{{ $site->project_type_label }}</span></td>
                        <td><x-ui.badge :variant="$statusVariant" dot>{{ $site->status }}</x-ui.badge></td>
                        <td><x-ui.badge :variant="$sslVariant" dot>{{ $site->ssl_status === 'active' ? 'Active' : 'Pending' }}</x-ui.badge></td>
                        <td class="hidden font-mono text-xs text-zinc-400 md:table-cell">{{ number_format($site->pages_count) }}</td>
                        <td class="text-right text-xs text-zinc-500">{{ $site->last_deployed_at?->diffForHumans() ?? 'Never' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <x-ui.empty icon="globe-alt" title="No sites yet" description="Create your first managed project to start monitoring, deploying, and invoicing.">
                                <x-ui.button href="{{ route('sites.create') }}" icon="plus">Add your first site</x-ui.button>
                            </x-ui.empty>
                        </td>
                    </tr>
                @endforelse
            </tbody>
    </x-ui.table>
</div>
