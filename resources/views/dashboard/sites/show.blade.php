<x-layouts.app>
    <x-slot:title>{{ $site->name }}</x-slot:title>

    @php
        $deployStatus = $site->deploy_status?->value ?? 'draft';
        $deployStatusLabel = $site->status; // computed attribute on Site model
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

        $seoIssueCount = (int) ($seoIssueCount ?? $seoIssues->sum('count'));
        $warningCount = (int) ($seoWarningCount ?? $seoIssues->where('severity', 'warning')->sum('count'));
        $latestResponseLabel = $latestResponseMs ? $latestResponseMs . 'ms' : '—';
        $p95ResponseLabel = $p95ResponseMs ? $p95ResponseMs . 'ms' : '—';

        $trendClass = ($visitorsTrendPercent ?? 0) >= 0 ? 'text-emerald-400' : 'text-red-400';
        $trendLabel = is_null($visitorsTrendPercent)
            ? 'No baseline data'
            : (($visitorsTrendPercent >= 0 ? '+' : '') . $visitorsTrendPercent . '% vs last week');
    @endphp

    <div class="space-y-7">
        <div class="pk-page-head">
            <div class="space-y-2">
                <a href="{{ route('sites.index') }}" class="inline-flex items-center gap-1 text-xs text-zinc-500 transition hover:text-zinc-300">
                    <flux:icon name="chevron-left" class="size-3.5" />
                    Sites
                </a>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="pk-page-title">{{ $site->name }}</h1>
                    <span class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium {{ $deployStatusClasses }}">
                        <span class="size-1.5 rounded-full bg-current"></span>{{ $deployStatusLabel }}
                    </span>
                    <span class="inline-flex items-center rounded-md bg-zinc-700/40 px-2 py-0.5 text-xs font-semibold text-zinc-300">{{ str($site->project_type ?? 'project')->lower() }}</span>
                    @if (filled($site->client_first_name) || filled($site->client_last_name) || filled($site->client_company))
                        <span class="text-sm text-zinc-400">{{ $site->clientDisplayName() }}</span>
                    @endif
                </div>
                @if (filled($site->domain))
                    <a href="https://{{ $site->domain }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-xs font-mono text-zinc-500 transition hover:text-zinc-300">
                        {{ $site->domain }}
                        <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                    </a>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button href="{{ route('sites.inbox', $site) }}" variant="subtle" size="sm" icon="envelope" aria-label="Inbox{{ ($site->inbox_unread_count ?? 0) > 0 ? ' (' . $site->inbox_unread_count . ' unread)' : '' }}">
                    Inbox
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
                <flux:button href="#deploy-controls" variant="subtle" size="sm" icon="arrow-down">
                    Deploy controls
                </flux:button>
                <flux:button href="{{ route('sites.settings', $site) }}" variant="subtle" icon="cog-6-tooth" size="sm">Settings</flux:button>
            </div>
        </div>

        <div class="pk-stat-grid">
            <div class="stat">
                <p class="stat-label">Visitors today</p>
                <p class="stat-val tabular-nums">{{ number_format($visitorsToday) }}</p>
                <p class="stat-note {{ $trendClass }}">{{ $trendLabel }}</p>
            </div>

            <div class="stat">
                <p class="stat-label">Uptime</p>
                <p class="mt-1 font-mono text-3xl font-semibold text-zinc-100">{{ $uptimePercent !== null ? $uptimePercent . '%' : '—' }}</p>
                <p class="stat-note">{{ $uptimePercent === 100.0 ? 'No downtime' : 'Rolling 50 checks' }}</p>
            </div>

            <div class="stat">
                <p class="stat-label">SSL</p>
                <div class="mt-2">
                    <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium {{ $sslClasses }}">{{ $sslStatusLabel }}</span>
                </div>
                <p class="stat-note">{{ $sslStatus === 'active' ? 'Certificate valid' : 'Not provisioned' }}</p>
            </div>

            <div class="stat">
                <p class="stat-label">SEO issues</p>
                <p class="stat-val tabular-nums text-amber-300">{{ $seoIssueCount }}</p>
                <p class="stat-note">{{ $warningCount }} warning{{ $warningCount === 1 ? '' : 's' }}</p>
            </div>

            <div class="stat">
                <p class="stat-label">Errors</p>
                <p class="stat-val tabular-nums {{ $errorCount > 0 ? 'text-red-300' : '' }}">{{ $errorCount }}</p>
                <p class="stat-note">{{ $errorCount > 0 ? 'Needs attention' : 'No active errors' }}</p>
            </div>

            <div class="stat">
                <p class="stat-label">Response</p>
                <p class="stat-val tabular-nums">{{ $latestResponseLabel }}</p>
                <p class="stat-note">P95: {{ $p95ResponseLabel }}</p>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-5">
            <div class="dash-card xl:col-span-3">
                <div class="dash-card-head">
                    <p class="dash-card-title">
                        <flux:icon name="x-circle" class="size-4 text-red-400" />
                        Errors
                    </p>
                </div>
                @forelse ($errorItems as $error)
                    <div class="issue-item">
                        <span class="issue-icon issue-icon-red"><flux:icon name="exclamation-circle" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="issue-text">{{ $error->title }}</p>
                            <p class="issue-meta">
                                {{ \Illuminate\Support\Str::limit((string) ($error->body ?? 'Issue reported by system checks.'), 80) }}
                                · {{ $error->created_at?->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="empty"><p>No recent error reports.</p></div>
                @endforelse
            </div>

            <div class="dash-card xl:col-span-2">
                <div class="dash-card-head">
                    <p class="dash-card-title">
                        <flux:icon name="magnifying-glass" class="size-4" />
                        SEO issues
                    </p>
                </div>
                @forelse ($seoIssues as $issue)
                    <div class="issue-item">
                        <span @class([
                            'issue-icon',
                            'issue-icon-red' => $issue['severity'] === 'error',
                            'issue-icon-yellow' => $issue['severity'] === 'warning',
                            'issue-icon-blue' => !in_array($issue['severity'], ['error', 'warning']),
                        ])><flux:icon name="exclamation-triangle" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="issue-text">{{ $issue['message'] }}</p>
                        </div>
                        <span class="tag">{{ $issue['count'] }}</span>
                    </div>
                @empty
                    <div class="empty"><p>No SEO issues found.</p></div>
                @endforelse
            </div>
        </div>

        <div class="dash-card !p-0">
            <div class="pk-underline-tabs px-2">
                <span class="pk-underline-tab active">
                    Pages <span class="badge-count">{{ $site->pages_count }}</span>
                </span>
                <a href="{{ route('blog.index', $site) }}" class="pk-underline-tab">
                    Blog <span class="badge-count">{{ $site->blog_posts_count }}</span>
                </a>
                <a href="{{ route('templates.index', $site) }}" class="pk-underline-tab">
                    Templates <span class="badge-count">{{ $site->content_templates_count }}</span>
                </a>
                <a href="{{ route('sites.files', $site) }}" class="pk-underline-tab">Files</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="pl-4">Page</th>
                            <th>URL</th>
                            <th>SEO</th>
                            <th>Visitors</th>
                            <th>Status</th>
                            <th class="pr-4"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pages as $page)
                            @php
                                $score = (int) ($page->seo_score ?? 0);
                                $scoreClass = match (true) {
                                    $score >= 90 => 'pill-green',
                                    $score >= 80 => 'pill-yellow',
                                    default => 'pill-red',
                                };
                            @endphp
                            <tr class="clickable">
                                <td class="pl-4">
                                    <span class="font-medium text-zinc-100">{{ $page->title ?: \Illuminate\Support\Str::afterLast((string) $page->file_path, '/') }}</span>
                                </td>
                                <td class="font-mono text-xs">{{ $page->url_path ?: '/' }}</td>
                                <td>
                                    <span class="pill {{ $scoreClass }} pill-no-dot">{{ $score }}/100</span>
                                </td>
                                <td class="font-mono tabular-nums">{{ number_format((int) ($page->visitors_30d ?? 0)) }}</td>
                                <td>
                                    @if ($page->is_published)
                                        <span class="pill pill-green pill-no-dot">Published</span>
                                    @else
                                        <span class="pill pill-yellow pill-no-dot">Draft</span>
                                    @endif
                                </td>
                                <td class="pr-4">
                                    <div class="flex justify-end gap-1">
                                        <flux:button href="{{ route('seo.meta', ['site' => $site, 'page' => $page]) }}" size="xs" variant="ghost">SEO</flux:button>
                                        <flux:button href="{{ route('editor', ['site' => $site, 'page' => $page]) }}" size="xs" variant="subtle">Edit</flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-zinc-500">No pages discovered yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div id="deploy-controls" class="dash-card">
            <div class="dash-card-head mb-3">
                <div>
                    <p class="section-title">Deploy controls</p>
                    <p class="pk-page-sub">Deploy, inspect logs, and rollback previous snapshots.</p>
                </div>
            </div>
            @livewire('sites.deploy-controls', ['siteId' => $site->id], key('deploy-controls-'.$site->id))
        </div>
    </div>

    {{-- Deploy status is polled via wire:poll.5s on the deploy-controls
         component itself. A full-page reload is not needed and would reset
         scroll position and interrupt reading during active deployments. --}}
</x-layouts.app>
