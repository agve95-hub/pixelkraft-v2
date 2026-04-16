<x-layouts.app>
    <x-slot:title>Analytics — {{ $site->name }}</x-slot:title>

    @php
        $trendLabel = is_null($trafficTrendPercent)
            ? 'No baseline data'
            : (($trafficTrendPercent >= 0 ? '+' : '') . $trafficTrendPercent . '% vs prev');
        $trendPositive = ! is_null($trafficTrendPercent) && $trafficTrendPercent >= 0;
    @endphp

    <div class="space-y-6 text-zinc-100">

        {{-- Header --}}
        <div>
            <a href="{{ route('sites.show', $site) }}" class="text-xs text-zinc-500 hover:text-violet-400 transition">← {{ $site->name }}</a>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <flux:heading size="xl">Analytics</flux:heading>
                    <flux:subheading>Traffic, uptime, and performance over the last 30 days</flux:subheading>
                </div>
                <select class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-xs text-zinc-300 opacity-50 cursor-not-allowed" disabled title="Date range filtering coming soon">
                    <option>Last 30 days</option>
                </select>
            </div>
        </div>

        {{-- Top-level stats --}}
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/85 px-4 py-3">
                <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-500">Visitors</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-100">{{ number_format($trafficTotal) }}</p>
                <p class="mt-0.5 text-xs {{ $trendPositive ? 'text-emerald-400' : 'text-zinc-500' }}">{{ $trendLabel }}</p>
            </div>
            <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/85 px-4 py-3">
                <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-500">Uptime</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-100">{{ rtrim(rtrim(number_format($uptimePercent, 1, '.', ''), '0'), '.') }}<span class="text-sm text-zinc-500">%</span></p>
                <p class="mt-0.5 text-xs text-zinc-500">
                    {{ $upDays }}d up@if ($degradedDays), {{ $degradedDays }}d degraded@endif@if ($downDays), <span class="text-red-400">{{ $downDays }}d down</span>@endif
                </p>
            </div>
            <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/85 px-4 py-3">
                <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-500">Avg response</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-100">{{ $avgResponseMs }}<span class="text-sm text-zinc-500">ms</span></p>
                <p class="mt-0.5 text-xs text-zinc-500">P95: {{ $p95ResponseMs }}ms</p>
            </div>
            <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/85 px-4 py-3">
                <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-500">Deploys</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-100">{{ $deployCount }}</p>
                <p class="mt-0.5 text-xs text-zinc-500">Recent deploy history</p>
            </div>
        </div>

        {{-- Events + Release --}}
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/85 p-5">
                <div class="flex items-center justify-between gap-2 mb-4">
                    <p class="text-sm font-semibold text-zinc-200">First-party events</p>
                    <span class="font-mono text-xs text-zinc-400">{{ number_format($eventSummary['total_events']) }} total</span>
                </div>
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div class="rounded-lg border border-zinc-800 bg-zinc-950/60 px-3 py-2">
                        <p class="text-[10px] uppercase tracking-[0.12em] text-zinc-500">Page views</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums">{{ number_format($eventSummary['page_views']) }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-800 bg-zinc-950/60 px-3 py-2">
                        <p class="text-[10px] uppercase tracking-[0.12em] text-zinc-500">Forms</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums">{{ number_format($eventSummary['forms']) }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-800 bg-zinc-950/60 px-3 py-2">
                        <p class="text-[10px] uppercase tracking-[0.12em] text-zinc-500">Interactions</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums">{{ number_format($eventSummary['interactions']) }}</p>
                    </div>
                </div>
                <div class="divide-y divide-zinc-800/80">
                    @forelse ($eventSummary['top_events'] as $event)
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <div class="min-w-0">
                                <p class="truncate text-[13px] font-medium text-zinc-200">{{ $event['event_name'] }}</p>
                                <p class="text-[11px] text-zinc-500">Captured by pixelkraft tracker</p>
                            </div>
                            <span class="shrink-0 font-mono text-sm text-zinc-300">{{ number_format($event['count']) }}</span>
                        </div>
                    @empty
                        <div class="py-8 text-center text-sm text-zinc-500">No custom events yet</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/85 p-5">
                <div class="flex items-center justify-between gap-2 mb-4">
                    <p class="text-sm font-semibold text-zinc-200">Release status</p>
                    <span class="font-mono text-xs text-zinc-400">{{ $releaseCount }} release{{ $releaseCount === 1 ? '' : 's' }}</span>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="rounded-lg border border-zinc-800 bg-zinc-950/60 px-3 py-2">
                        <p class="text-[10px] uppercase tracking-[0.12em] text-zinc-500">Current</p>
                        <p class="mt-1 text-lg font-semibold">{{ $currentRelease?->status ? ucfirst($currentRelease->status) : 'None' }}</p>
                        <p class="mt-0.5 text-[11px] text-zinc-500">{{ $currentRelease?->activated_at?->diffForHumans() ?? 'No active release' }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-800 bg-zinc-950/60 px-3 py-2">
                        <p class="text-[10px] uppercase tracking-[0.12em] text-zinc-500">Tracking</p>
                        <p class="mt-1 text-lg font-semibold">{{ $currentRelease?->tracking_version ?: 'Pending' }}</p>
                        <p class="mt-0.5 text-[11px] text-zinc-500">{{ $currentRelease?->artifact_path ? 'artifact ready' : 'no artifact recorded' }}</p>
                    </div>
                </div>
                <div class="rounded-lg border border-zinc-800 bg-zinc-950/60 px-3 py-2.5">
                    <p class="text-[12px] text-zinc-300">Commit: <span class="font-mono">{{ $currentRelease?->source_commit_sha ? \Illuminate\Support\Str::limit($currentRelease->source_commit_sha, 12, '') : 'n/a' }}</span></p>
                    <p class="mt-0.5 text-[11px] text-zinc-500">Branch: {{ $currentRelease?->source_branch ?: $site->branch }}</p>
                </div>
            </div>
        </div>

        {{-- Visitors chart --}}
        <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/85 p-5">
            <div class="flex items-center justify-between gap-2 mb-4">
                <p class="text-sm font-semibold text-zinc-200">Visitors</p>
                <span class="font-mono text-xs text-zinc-400">{{ number_format($trafficTotal) }} total</span>
            </div>
            <div class="h-36 w-full">
                @if ($trafficChart['line_path'] === '')
                    <div class="flex h-full items-center justify-center text-sm text-zinc-500">No traffic data yet</div>
                @else
                    <svg class="h-full w-full" viewBox="0 0 {{ $trafficChart['width'] }} {{ $trafficChart['height'] }}" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="analyticsTrafficFill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="rgb(52 211 153)" stop-opacity="0.28" />
                                <stop offset="100%" stop-color="rgb(52 211 153)" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <path d="{{ $trafficChart['area_path'] }}" fill="url(#analyticsTrafficFill)" />
                        <path d="{{ $trafficChart['line_path'] }}" fill="none" stroke="rgb(52 211 153)" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                @endif
            </div>
        </div>

        {{-- Uptime + Response time --}}
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/85 p-5">
                <div class="flex items-center justify-between gap-2 mb-4">
                    <p class="text-sm font-semibold text-zinc-200">Uptime</p>
                    <span class="font-mono text-xs {{ $uptimePercent >= 99.9 ? 'text-emerald-400' : 'text-amber-400' }}">{{ rtrim(rtrim(number_format($uptimePercent, 1, '.', ''), '0'), '.') }}%</span>
                </div>
                <div class="flex gap-0.5">
                    @foreach ($dailyBars as $bar)
                        <span class="flex-1 rounded-sm h-6 @switch($bar)
                            @case('up') bg-emerald-400 @break
                            @case('degraded') bg-amber-400 @break
                            @case('down') bg-red-400 @break
                            @default bg-zinc-700/50
                        @endswitch"></span>
                    @endforeach
                </div>
                <div class="mt-3 flex gap-4 text-[11px] text-zinc-500">
                    <span class="flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full bg-emerald-400"></span>Up</span>
                    <span class="flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full bg-amber-400"></span>Degraded</span>
                    <span class="flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full bg-red-400"></span>Down</span>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/85 p-5">
                <div class="flex items-center justify-between gap-2 mb-4">
                    <p class="text-sm font-semibold text-zinc-200">Response time</p>
                    <span class="font-mono text-xs text-zinc-400">avg {{ $avgResponseMs }}ms · p95 {{ $p95ResponseMs }}ms</span>
                </div>
                <div class="h-24 w-full">
                    @if ($responseChart['path'] === '')
                        <div class="flex h-full items-center justify-center text-sm text-zinc-500">No response samples yet</div>
                    @else
                        <svg class="h-full w-full" viewBox="0 0 {{ $responseChart['width'] }} {{ $responseChart['height'] }}" preserveAspectRatio="none">
                            <path d="{{ $responseChart['path'] }}" fill="none" stroke="rgb(52 211 153)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    @endif
                </div>
            </div>
        </div>

        {{-- Recent deploys --}}
        <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/85 p-5">
            <p class="text-sm font-semibold text-zinc-200 mb-4">Recent deploys</p>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Commit</flux:table.column>
                    <flux:table.column class="hidden sm:table-cell">Duration</flux:table.column>
                    <flux:table.column>When</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($deploys as $deploy)
                        <flux:table.row>
                            <flux:table.cell>
                                <flux:badge size="sm" color="{{ $deploy->isSuccess() ? 'lime' : 'red' }}">
                                    {{ $deploy->isSuccess() ? 'Success' : 'Failed' }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="font-mono text-xs">{{ $deploy->hash ?: 'snapshot' }}</flux:table.cell>
                            <flux:table.cell class="hidden sm:table-cell text-xs text-zinc-400">{{ $deploy->duration }}</flux:table.cell>
                            <flux:table.cell class="text-xs text-zinc-400">{{ $deploy->created_at?->diffForHumans() ?? 'recently' }}</flux:table.cell>
                            <flux:table.cell>
                                <button type="button" class="text-xs text-zinc-600 opacity-50 cursor-not-allowed" disabled title="Deploy log viewer coming soon">Log</button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="py-8 text-center text-sm text-zinc-500">No deploy history yet</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>
</x-layouts.app>
