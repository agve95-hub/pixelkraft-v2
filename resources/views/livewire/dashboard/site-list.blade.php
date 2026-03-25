<div>
    {{-- Search --}}
    <div class="mb-4">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search sites..."
            class="input-field max-w-xs"
        >
    </div>

    {{-- Sites Table --}}
    <div class="card overflow-hidden !p-0">
        <table class="w-full">
            <thead>
                <tr class="border-b border-zinc-800">
                    <th class="table-header px-4 py-3">Site</th>
                    <th class="table-header px-4 py-3">Domain</th>
                    <th class="table-header px-4 py-3 hidden md:table-cell">Type</th>
                    <th class="table-header px-4 py-3 hidden lg:table-cell">Pages</th>
                    <th class="table-header px-4 py-3">Status</th>
                    <th class="table-header px-4 py-3 hidden lg:table-cell">Last Deploy</th>
                    <th class="table-header px-4 py-3 hidden xl:table-cell">Uptime</th>
                    <th class="table-header px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sites as $site)
                    <tr class="group hover:bg-zinc-800/30 transition">
                        <td class="table-cell font-medium text-zinc-100">
                            <a href="{{ route('sites.show', $site) }}" class="hover:text-violet-400 transition">
                                {{ $site->name }}
                            </a>
                        </td>
                        <td class="table-cell">
                            @if ($site->domain)
                                <a href="https://{{ $site->domain }}" target="_blank" class="mono text-xs text-zinc-400 hover:text-violet-400 transition">
                                    {{ $site->domain }}
                                </a>
                            @else
                                <span class="text-zinc-600 text-xs">No domain</span>
                            @endif
                        </td>
                        <td class="table-cell hidden md:table-cell">
                            <span class="badge-purple mono">{{ $site->project_type }}</span>
                        </td>
                        <td class="table-cell hidden lg:table-cell mono text-xs">
                            {{ $site->pages_count }}
                        </td>
                        <td class="table-cell">
                            @switch($site->deploy_status)
                                @case('live')
                                    <span class="badge-green">Live</span>
                                    @break
                                @case('building')
                                @case('deploying')
                                    <span class="badge-amber">
                                        <svg class="mr-1 h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        {{ ucfirst($site->deploy_status) }}
                                    </span>
                                    @break
                                @case('failed')
                                    <span class="badge-red">Failed</span>
                                    @break
                                @default
                                    <span class="badge bg-zinc-500/10 text-zinc-500">Idle</span>
                            @endswitch
                        </td>
                        <td class="table-cell hidden lg:table-cell text-xs text-zinc-500">
                            {{ $site->last_deployed_at?->diffForHumans() ?? '—' }}
                        </td>
                        <td class="table-cell hidden xl:table-cell">
                            @if ($site->latestUptimeCheck)
                                @if ($site->latestUptimeCheck->is_up)
                                    <span class="inline-flex items-center gap-1 text-xs text-emerald-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                        {{ $site->latestUptimeCheck->responseTimeFormatted() }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs text-red-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-red-400"></span>
                                        Down
                                    </span>
                                @endif
                            @else
                                <span class="text-xs text-zinc-600">—</span>
                            @endif
                        </td>
                        <td class="table-cell text-right">
                            <a href="{{ route('sites.show', $site) }}" class="btn-ghost text-xs !px-2 !py-1">
                                Manage
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center">
                            <div class="text-zinc-500">
                                <svg class="mx-auto h-10 w-10 mb-3 text-zinc-600" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                                </svg>
                                <p class="text-sm">No sites yet</p>
                                <p class="text-xs mt-1">Add your first site to get started</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
