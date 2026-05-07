<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search projects..." icon="magnifying-glass" class="max-w-xs" />
        <flux:button href="{{ route('sites.create') }}" size="sm" variant="subtle" icon="plus">New project</flux:button>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th class="px-4 py-2.5">Site</th>
                    <th class="px-4 py-2.5">Client</th>
                    <th class="px-4 py-2.5">Type</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th class="px-4 py-2.5">SSL</th>
                    <th class="hidden px-4 py-2.5 md:table-cell">Pages</th>
                    <th class="px-4 py-2.5 text-right">Last deploy</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sites as $site)
                    @php
                        $isUp = $site->latestUptimeCheck?->is_up;
                        $dot = $isUp === true ? 'bg-emerald-400' : ($isUp === false ? 'bg-red-400' : 'bg-amber-400');
                        $statusClass = $site->deploy_status === \App\Enums\DeployStatus::Live
                            ? 'pill-green'
                            : ($site->deploy_status === \App\Enums\DeployStatus::Failed ? 'pill-red' : 'pill-yellow');
                        $sslClass = $site->ssl_status === 'active' ? 'pill-green' : 'pill-yellow';
                    @endphp
                    <tr class="clickable" onclick="window.location='{{ route('sites.show', $site) }}'">
                        <td class="px-4 py-3">
                            <div class="site-name">
                                <span class="site-dot {{ $dot }}"></span>
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-zinc-100">{{ $site->name }}</div>
                                    <div class="truncate font-mono text-[11px] text-zinc-500">{{ $site->domain ?: $site->repo_url ?: 'Draft project' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-400">{{ $site->clientDisplayName() }}</td>
                        <td class="px-4 py-3"><span class="tag">{{ $site->project_type_label }}</span></td>
                        <td class="px-4 py-3"><span class="pill {{ $statusClass }}">{{ $site->status }}</span></td>
                        <td class="px-4 py-3"><span class="pill {{ $sslClass }}">{{ $site->ssl_status === 'active' ? 'Active' : 'Pending' }}</span></td>
                        <td class="hidden px-4 py-3 font-mono text-xs text-zinc-400 md:table-cell">{{ number_format($site->pages_count) }}</td>
                        <td class="px-4 py-3 text-right text-xs text-zinc-500">{{ $site->last_deployed_at?->diffForHumans() ?? 'Never' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center">
                            <div class="empty">
                                <div class="empty-icon"><flux:icon name="globe-alt" class="size-4" /></div>
                                <div>No sites yet</div>
                                <flux:button href="{{ route('sites.create') }}" variant="primary" icon="plus" class="mt-2 !bg-emerald-500 hover:!bg-emerald-400 !text-zinc-950 dark:!text-zinc-950">Add your first site</flux:button>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
