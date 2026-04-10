<x-layouts.app>
    <x-slot:title>{{ $site->name }}</x-slot:title>

    @php
        $shouldAutoRefresh = is_null($site->last_synced_at) || in_array($site->deploy_status, ['building', 'deploying', 'queued'], true);

        $deployStatus = (string) $site->deploy_status;
        $deployStatusLabel = match ($deployStatus) {
            'live' => 'Live',
            'building' => 'Building',
            'deploying' => 'Deploying',
            'queued' => 'Queued',
            'failed' => 'Failed',
            default => 'Idle',
        };

        $deployStatusClasses = match ($deployStatus) {
            'live' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/25',
            'building', 'deploying', 'queued' => 'bg-amber-500/15 text-amber-300 border-amber-500/25',
            'failed' => 'bg-red-500/15 text-red-300 border-red-500/25',
            default => 'bg-zinc-700/40 text-zinc-300 border-zinc-600/50',
        };

        $sslStatus = (string) ($site->ssl_status ?? 'pending');
        $sslStatusLabel = match ($sslStatus) {
            'active' => 'Active',
            'expired' => 'Expired',
            'error' => 'Error',
            default => 'Pending',
        };

        $sslClasses = match ($sslStatus) {
            'active' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/25',
            'expired', 'error' => 'bg-red-500/15 text-red-300 border-red-500/25',
            default => 'bg-amber-500/15 text-amber-300 border-amber-500/25',
        };

        $seoIssueCount = (int) $seoIssues->sum('count');
        $warningCount = (int) $seoIssues->where('severity', 'warning')->sum('count');
        $latestResponseLabel = $latestResponseMs ? $latestResponseMs . 'ms' : '—';
        $p95ResponseLabel = $p95ResponseMs ? $p95ResponseMs . 'ms' : '—';

        $trendClass = ($visitorsTrendPercent ?? 0) >= 0 ? 'text-emerald-400' : 'text-red-400';
        $trendLabel = is_null($visitorsTrendPercent)
            ? 'No baseline data'
            : (($visitorsTrendPercent >= 0 ? '+' : '') . $visitorsTrendPercent . '% vs last week');
    @endphp

    <div class="space-y-7">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <a href="{{ route('sites.index') }}" class="inline-flex items-center gap-1 text-xs text-zinc-500 transition hover:text-zinc-300">
                    <flux:icon name="chevron-left" class="size-3.5" />
                    Sites
                </a>
                <div class="flex flex-wrap items-center gap-2.5">
                    <flux:heading size="xl">{{ $site->name }}</flux:heading>
                    <span class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium {{ $deployStatusClasses }}">
                        <span class="size-1.5 rounded-full bg-current"></span>{{ $deployStatusLabel }}
                    </span>
                    <span class="inline-flex items-center rounded-md bg-zinc-700/40 px-2 py-0.5 text-xs font-semibold text-zinc-300">{{ str($site->project_type ?? 'project')->lower() }}</span>
                    @if (filled($site->client_first_name) || filled($site->client_last_name) || filled($site->client_company))
                        <span class="text-sm text-zinc-400">{{ $site->clientDisplayName() }}</span>
                    @endif
                </div>
                @if (filled($site->domain))
                    <a href="https://{{ $site->domain }}" target="_blank" class="inline-flex items-center gap-1 text-xs font-mono text-zinc-500 transition hover:text-zinc-300">
                        {{ $site->domain }}
                        <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                    </a>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button href="{{ route('sites.inbox', $site) }}" variant="subtle" size="sm" icon="envelope">
                    @if (($site->inbox_unread_count ?? 0) > 0)
                        <span class="inline-flex min-w-[1.2rem] items-center justify-center rounded bg-emerald-500/20 px-1 py-0.5 text-[10px] font-semibold text-emerald-300">
                            {{ $site->inbox_unread_count > 99 ? '99+' : $site->inbox_unread_count }}
                        </span>
                    @endif
                </flux:button>
                <flux:button href="{{ route('sites.invoices', $site) }}" variant="subtle" size="sm" icon="document-text">
                    Invoices
                    @if (($site->invoices_unpaid_count ?? 0) > 0)
                        <span class="inline-flex min-w-[1.2rem] items-center justify-center rounded bg-amber-500/20 px-1 py-0.5 text-[10px] font-semibold text-amber-300">
                            {{ $site->invoices_unpaid_count > 99 ? '99+' : $site->invoices_unpaid_count }}
                        </span>
                    @endif
                </flux:button>
                <flux:button href="#deploy-controls" variant="subtle" size="sm" icon="clock">
                    {{ $site->deploy_logs_count }} deploy{{ $site->deploy_logs_count === 1 ? '' : 's' }}
                </flux:button>
                <flux:button href="#deploy-controls" variant="primary" size="sm" icon="cloud-arrow-up" class="!bg-emerald-500 hover:!bg-emerald-400 !text-zinc-950 dark:!text-zinc-950">
                    Deploy now
                </flux:button>
                <flux:button href="{{ route('sites.settings', $site) }}" variant="subtle" icon="cog-6-tooth" size="sm">Settings</flux:button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <div class="rounded-xl border border-zinc-800/90 bg-zinc-900/85 p-4">
                <p class="text-[11px] uppercase tracking-[0.12em] text-zinc-500">Visitors today</p>
                <p class="mt-1 font-mono text-3xl font-semibold text-zinc-100">{{ number_format($visitorsToday) }}</p>
                <p class="mt-1 text-xs {{ $trendClass }}">{{ $trendLabel }}</p>
            </div>

            <div class="rounded-xl border border-zinc-800/90 bg-zinc-900/85 p-4">
                <p class="text-[11px] uppercase tracking-[0.12em] text-zinc-500">Uptime</p>
                <p class="mt-1 font-mono text-3xl font-semibold text-zinc-100">{{ $uptimePercent !== null ? $uptimePercent . '%' : '—' }}</p>
                <p class="mt-1 text-xs text-zinc-500">{{ $uptimePercent === 100.0 ? 'No downtime' : 'Rolling 50 checks' }}</p>
            </div>

            <div class="rounded-xl border border-zinc-800/90 bg-zinc-900/85 p-4">
                <p class="text-[11px] uppercase tracking-[0.12em] text-zinc-500">SSL</p>
                <div class="mt-2">
                    <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium {{ $sslClasses }}">{{ $sslStatusLabel }}</span>
                </div>
                <p class="mt-2 text-xs text-zinc-500">{{ $sslStatus === 'active' ? 'Certificate valid' : 'Not provisioned' }}</p>
            </div>

            <div class="rounded-xl border border-zinc-800/90 bg-zinc-900/85 p-4">
                <p class="text-[11px] uppercase tracking-[0.12em] text-zinc-500">SEO issues</p>
                <p class="mt-1 font-mono text-3xl font-semibold text-amber-300">{{ $seoIssueCount }}</p>
                <p class="mt-1 text-xs text-zinc-500">{{ $warningCount }} warning{{ $warningCount === 1 ? '' : 's' }}</p>
            </div>

            <div class="rounded-xl border border-zinc-800/90 bg-zinc-900/85 p-4">
                <p class="text-[11px] uppercase tracking-[0.12em] text-zinc-500">Errors</p>
                <p class="mt-1 font-mono text-3xl font-semibold {{ $errorCount > 0 ? 'text-red-300' : 'text-zinc-100' }}">{{ $errorCount }}</p>
                <p class="mt-1 text-xs text-zinc-500">{{ $errorCount > 0 ? 'Needs attention' : 'No active errors' }}</p>
            </div>

            <div class="rounded-xl border border-zinc-800/90 bg-zinc-900/85 p-4">
                <p class="text-[11px] uppercase tracking-[0.12em] text-zinc-500">Response</p>
                <p class="mt-1 font-mono text-3xl font-semibold text-zinc-100">{{ $latestResponseLabel }}</p>
                <p class="mt-1 text-xs text-zinc-500">P95: {{ $p95ResponseLabel }}</p>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-5">
            <div class="rounded-xl border border-zinc-800/90 bg-zinc-900/85 p-4 xl:col-span-3">
                <div class="mb-3 flex items-center gap-2">
                    <flux:icon name="x-circle" class="size-4 text-red-400" />
                    <h2 class="text-lg font-semibold text-zinc-100">Errors</h2>
                </div>
                <div class="space-y-1">
                    @forelse ($errorItems as $error)
                        <div class="flex items-start gap-3 rounded-lg border border-zinc-800/80 bg-zinc-950/60 px-3 py-2.5">
                            <span class="mt-1 inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-red-500/15">
                                <flux:icon name="exclamation-circle" class="size-3.5 text-red-400" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-zinc-100">{{ $error->title }}</p>
                                <p class="text-xs text-zinc-500">
                                    {{ \Illuminate\Support\Str::limit((string) ($error->body ?? 'Issue reported by system checks.'), 80) }}
                                    · {{ $error->created_at?->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-zinc-800/70 bg-zinc-950/40 px-3 py-5 text-center text-sm text-zinc-500">
                            No recent error reports.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-zinc-800/90 bg-zinc-900/85 p-4 xl:col-span-2">
                <div class="mb-3 flex items-center gap-2">
                    <flux:icon name="magnifying-glass" class="size-4 text-zinc-500" />
                    <h2 class="text-lg font-semibold text-zinc-100">SEO issues</h2>
                </div>
                <div class="space-y-1.5">
                    @forelse ($seoIssues as $issue)
                        <div class="flex items-start gap-3 rounded-lg border border-zinc-800/80 bg-zinc-950/60 px-3 py-2.5">
                            @if ($issue['severity'] === 'warning')
                                <span class="mt-0.5 inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase bg-amber-400/15 text-amber-300">Warning</span>
                            @else
                                <span class="mt-0.5 inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase bg-sky-400/15 text-sky-300">Info</span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-zinc-100">{{ $issue['message'] }}</p>
                            </div>
                            <span class="text-sm font-mono tabular-nums text-zinc-400">{{ $issue['count'] }}</span>
                        </div>
                    @empty
                        <div class="rounded-lg border border-zinc-800/70 bg-zinc-950/40 px-3 py-5 text-center text-sm text-zinc-500">
                            No SEO issues found.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-800/90 bg-zinc-900/85 p-4">
            <div class="mb-4 flex flex-wrap items-center gap-5 border-b border-zinc-800 pb-2">
                <span class="inline-flex items-center gap-2 border-b-2 border-zinc-200 pb-2 text-sm font-medium text-zinc-100">
                    Pages
                    <span class="rounded bg-zinc-700/60 px-1.5 py-0.5 text-[10px] text-zinc-300">{{ $site->pages_count }}</span>
                </span>
                <a href="{{ route('blog.index', $site) }}" class="inline-flex items-center gap-2 pb-2 text-sm text-zinc-400 transition hover:text-zinc-200">
                    Blog posts
                    <span class="rounded bg-zinc-700/40 px-1.5 py-0.5 text-[10px] text-zinc-400">{{ $site->blog_posts_count }}</span>
                </a>
                <a href="{{ route('templates.index', $site) }}" class="inline-flex items-center gap-2 pb-2 text-sm text-zinc-400 transition hover:text-zinc-200">
                    Templates
                    <span class="rounded bg-zinc-700/40 px-1.5 py-0.5 text-[10px] text-zinc-400">{{ $site->content_templates_count }}</span>
                </a>
                <a href="{{ route('sites.files', $site) }}" class="inline-flex items-center gap-2 pb-2 text-sm text-zinc-400 transition hover:text-zinc-200">
                    Files
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-800 text-left text-[11px] uppercase tracking-[0.12em] text-zinc-500">
                            <th class="py-2 pr-3 font-medium">Page</th>
                            <th class="px-3 py-2 font-medium">URL</th>
                            <th class="px-3 py-2 font-medium">SEO</th>
                            <th class="px-3 py-2 font-medium">Visitors</th>
                            <th class="px-3 py-2 font-medium">Status</th>
                            <th class="py-2 pl-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/80">
                        @forelse ($pages as $page)
                            @php
                                $score = (int) ($page->seo_score ?? 0);
                                $scoreClasses = match (true) {
                                    $score >= 90 => 'bg-emerald-500/20 text-emerald-300',
                                    $score >= 80 => 'bg-amber-500/20 text-amber-300',
                                    default => 'bg-red-500/20 text-red-300',
                                };
                            @endphp
                            <tr class="transition hover:bg-zinc-800/35">
                                <td class="py-2.5 pr-3">
                                    <div class="font-medium text-zinc-100">{{ $page->title ?: \Illuminate\Support\Str::afterLast((string) $page->file_path, '/') }}</div>
                                </td>
                                <td class="px-3 py-2.5 font-mono text-xs text-zinc-400">{{ $page->url_path ?: '/' }}</td>
                                <td class="px-3 py-2.5">
                                    <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-semibold {{ $scoreClasses }}">{{ $score }}/100</span>
                                </td>
                                <td class="px-3 py-2.5 font-mono text-xs tabular-nums text-zinc-300">{{ number_format((int) ($page->visitors_30d ?? 0)) }}</td>
                                <td class="px-3 py-2.5">
                                    @if ($page->is_published)
                                        <span class="inline-flex rounded-md bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-300">Published</span>
                                    @else
                                        <span class="inline-flex rounded-md bg-amber-500/15 px-2 py-0.5 text-xs font-medium text-amber-300">Draft</span>
                                    @endif
                                </td>
                                <td class="py-2.5 pl-3">
                                    <div class="flex justify-end gap-1">
                                        <flux:button href="{{ route('seo.meta', ['site' => $site, 'page' => $page]) }}" size="xs" variant="ghost">SEO</flux:button>
                                        <flux:button href="{{ route('editor', ['site' => $site, 'page' => $page]) }}" size="xs" variant="subtle">Edit</flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-sm text-zinc-500">No pages discovered yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div id="deploy-controls" class="rounded-xl border border-zinc-800/90 bg-zinc-900/85 p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-100">Deploy controls</h2>
                    <p class="text-sm text-zinc-500">Deploy, inspect logs, and rollback previous snapshots.</p>
                </div>
            </div>
            @livewire('sites.deploy-controls', ['siteId' => $site->id], key('deploy-controls-'.$site->id))
        </div>
    </div>

    @if ($shouldAutoRefresh)
        <script>
            setTimeout(() => window.location.reload(), 5000);
        </script>
    @endif
</x-layouts.app>
