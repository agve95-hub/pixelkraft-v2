<x-layouts.app>
    <x-slot:title>{{ $site->name }}</x-slot:title>

    @php
        $deployStatus = $site->deploy_status?->value ?? 'draft';
        $deployStatusLabel = $site->status;
        $deployBadgeVariant = match ($deployStatus) {
            'live' => 'success',
            'building', 'deploying', 'queued' => 'warning',
            'failed' => 'destructive',
            default => 'default',
        };
        $sslStatus = (string) ($site->ssl_status ?? 'pending');
        $sslStatusLabel = match ($sslStatus) {
            'active' => 'Active', 'expired' => 'Expired', 'error' => 'Error', default => 'Pending',
        };
        $sslBadgeVariant = match ($sslStatus) {
            'active' => 'success', 'expired', 'error' => 'destructive', default => 'warning',
        };
        $seoIssueCount = (int) ($seoIssueCount ?? $seoIssues->sum('count'));
        $warningCount  = (int) ($seoWarningCount ?? $seoIssues->where('severity', 'warning')->sum('count'));
        $latestResponseLabel = $latestResponseMs ? $latestResponseMs . 'ms' : '—';
        $p95ResponseLabel    = $p95ResponseMs ? $p95ResponseMs . 'ms' : '—';
        $trendClass = ($visitorsTrendPercent ?? 0) >= 0 ? 'text-emerald-400' : 'text-red-400';
        $trendLabel = is_null($visitorsTrendPercent)
            ? 'No baseline data'
            : (($visitorsTrendPercent >= 0 ? '+' : '') . $visitorsTrendPercent . '% vs last week');
    @endphp

    <div class="space-y-6">
        {{-- Page header --}}
        <div class="ui-page-head">
            <div class="space-y-2">
                <a href="{{ route('sites.index') }}" class="back-link">
                    <flux:icon name="chevron-left" class="size-3.5" /> Sites
                </a>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="ui-page-title">{{ $site->name }}</h1>
                    <x-ui.badge variant="{{ $deployBadgeVariant }}" dot>{{ $deployStatusLabel }}</x-ui.badge>
                    <x-ui.badge>{{ str($site->project_type ?? 'project')->lower() }}</x-ui.badge>
                    @if (filled($site->client_first_name) || filled($site->client_last_name) || filled($site->client_company))
                        <span class="text-sm text-zinc-400">{{ $site->clientDisplayName() }}</span>
                    @endif
                </div>
                @if (filled($site->domain))
                    <a href="https://{{ $site->domain }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-xs font-mono text-zinc-500 transition hover:text-zinc-300">
                        {{ $site->domain }} <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                    </a>
                @endif
            </div>

            <x-ui.button-group>
                <x-ui.button href="{{ route('sites.inbox', $site) }}" variant="outline" size="sm" icon="envelope">
                    Inbox
                    @if (($site->inbox_unread_count ?? 0) > 0)
                        <x-ui.badge variant="success">{{ $site->inbox_unread_count > 99 ? '99+' : $site->inbox_unread_count }}</x-ui.badge>
                    @endif
                </x-ui.button>
                <x-ui.button href="{{ route('sites.invoices', $site) }}" variant="outline" size="sm" icon="document-text">
                    Invoices
                    @if (($site->invoices_unpaid_count ?? 0) > 0)
                        <x-ui.badge variant="warning">{{ $site->invoices_unpaid_count > 99 ? '99+' : $site->invoices_unpaid_count }}</x-ui.badge>
                    @endif
                </x-ui.button>
                <x-ui.button href="#deploy-controls" variant="outline" size="sm" icon="arrow-down">Deploy</x-ui.button>
                <x-ui.button href="{{ route('sites.settings', $site) }}" variant="outline" size="sm" icon="cog-6-tooth">Settings</x-ui.button>
            </x-ui.button-group>
        </div>

        {{-- Stats --}}
        <div class="ui-stat-grid">
            <div class="stat">
                <p class="stat-label">Visitors today</p>
                <p class="stat-val tabular-nums">{{ number_format($visitorsToday) }}</p>
                <p class="stat-note {{ $trendClass }}">{{ $trendLabel }}</p>
            </div>
            <div class="stat">
                <p class="stat-label">Uptime</p>
                <p class="stat-val tabular-nums">{{ $uptimePercent !== null ? $uptimePercent . '%' : '—' }}</p>
                <p class="stat-note">{{ $uptimePercent === 100.0 ? 'No downtime' : 'Rolling 50 checks' }}</p>
            </div>
            <div class="stat">
                <p class="stat-label">SSL</p>
                <div class="mt-2"><x-ui.badge variant="{{ $sslBadgeVariant }}" dot>{{ $sslStatusLabel }}</x-ui.badge></div>
                <p class="stat-note">{{ $sslStatus === 'active' ? 'Certificate valid' : 'Not provisioned' }}</p>
            </div>
            <div class="stat">
                <p class="stat-label">SEO issues</p>
                <p class="stat-val tabular-nums {{ $seoIssueCount > 0 ? 'text-amber-300' : '' }}">{{ $seoIssueCount }}</p>
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

        {{-- Errors + SEO --}}
        <div class="grid gap-4 xl:grid-cols-5">
            <x-ui.card class="xl:col-span-3">
                <x-ui.card-header>
                    <x-ui.card-title>
                        <flux:icon name="x-circle" class="size-4 text-red-400" /> Errors
                    </x-ui.card-title>
                </x-ui.card-header>
                @forelse ($errorItems as $error)
                    <div class="issue-item">
                        <span class="issue-icon issue-icon-red"><flux:icon name="exclamation-circle" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="issue-text">{{ $error->title }}</p>
                            <p class="issue-meta">{{ \Illuminate\Support\Str::limit((string) ($error->body ?? 'Issue reported by system checks.'), 80) }} · {{ $error->created_at?->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <x-ui.empty icon="check-circle" title="No recent error reports" />
                @endforelse
            </x-ui.card>

            <x-ui.card class="xl:col-span-2">
                <x-ui.card-header>
                    <x-ui.card-title>
                        <flux:icon name="magnifying-glass" class="size-4" /> SEO issues
                    </x-ui.card-title>
                </x-ui.card-header>
                @forelse ($seoIssues as $issue)
                    <div class="issue-item">
                        <span @class(['issue-icon', 'issue-icon-red' => $issue['severity'] === 'error', 'issue-icon-yellow' => $issue['severity'] === 'warning', 'issue-icon-blue' => !in_array($issue['severity'], ['error','warning'])])>
                            <flux:icon name="exclamation-triangle" />
                        </span>
                        <div class="min-w-0 flex-1"><p class="issue-text">{{ $issue['message'] }}</p></div>
                        <span class="tag">{{ $issue['count'] }}</span>
                    </div>
                @empty
                    <x-ui.empty icon="check-circle" title="No SEO issues found" />
                @endforelse
            </x-ui.card>
        </div>

        {{-- Pages table --}}
        <x-ui.card padding="flush">
            <x-ui.card-header class="px-[18px] pt-4 pb-0">
                <x-ui.tabs>
                    <x-ui.tab active>
                        Pages <span class="badge-count">{{ $site->pages_count }}</span>
                    </x-ui.tab>
                    <a href="{{ route('blog.index', $site) }}" class="ui-tab">
                        Blog <span class="badge-count">{{ $site->blog_posts_count }}</span>
                    </a>
                    <a href="{{ route('templates.index', $site) }}" class="ui-tab">
                        Templates <span class="badge-count">{{ $site->content_templates_count }}</span>
                    </a>
                    <a href="{{ route('sites.files', $site) }}" class="ui-tab">Files</a>
                </x-ui.tabs>
            </x-ui.card-header>

            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th class="pl-[18px]">Page</th>
                            <th>URL</th>
                            <th>SEO</th>
                            <th>Visitors</th>
                            <th>Status</th>
                            <th class="pr-[18px]"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pages as $page)
                            @php
                                $score = (int) ($page->seo_score ?? 0);
                                $scoreVariant = match (true) { $score >= 90 => 'success', $score >= 80 => 'warning', default => 'destructive' };
                            @endphp
                            <tr class="clickable">
                                <td class="pl-[18px] font-medium text-zinc-100">{{ $page->title ?: \Illuminate\Support\Str::afterLast((string) $page->file_path, '/') }}</td>
                                <td class="font-mono text-xs">{{ $page->url_path ?: '/' }}</td>
                                <td><x-ui.badge variant="{{ $scoreVariant }}">{{ $score }}/100</x-ui.badge></td>
                                <td class="font-mono tabular-nums">{{ number_format((int) ($page->visitors_30d ?? 0)) }}</td>
                                <td>
                                    @if ($page->is_published)
                                        <x-ui.badge variant="success">Published</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="warning">Draft</x-ui.badge>
                                    @endif
                                </td>
                                <td class="pr-[18px]">
                                    <x-ui.button-group align="end">
                                        <x-ui.button href="{{ route('seo.meta', ['site' => $site, 'page' => $page]) }}" size="xs" variant="ghost">SEO</x-ui.button>
                                        <x-ui.button href="{{ route('editor', ['site' => $site, 'page' => $page]) }}" size="xs" variant="outline">Edit</x-ui.button>
                                    </x-ui.button-group>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-ui.empty icon="document-duplicate" title="No pages discovered yet" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        {{-- Deploy controls --}}
        <x-ui.card id="deploy-controls">
            <x-ui.card-header>
                <div>
                    <x-ui.card-title>Deploy controls</x-ui.card-title>
                    <x-ui.card-description>Deploy, inspect logs, and rollback previous snapshots.</x-ui.card-description>
                </div>
            </x-ui.card-header>
            @livewire('sites.deploy-controls', ['siteId' => $site->id], key('deploy-controls-'.$site->id))
        </x-ui.card>
    </div>
</x-layouts.app>
