@php
    $workflowLabel = ($supportProfile['editor_workflow'] ?? null) === 'visual_html' ? 'Visual HTML' : 'Code first';
    $modeSource = ($supportProfile['deployment_mode_source'] ?? 'configured') === 'inferred' ? 'Auto' : 'Configured';
@endphp

<div class="space-y-5">
    @if (session()->has('success'))
        <x-ui.alert variant="success" icon="check-circle">{{ session('success') }}</x-ui.alert>
    @endif

    {{-- Integration warnings (GA4 credentials missing, Cloudflare mismatch, etc.) --}}
    @foreach ($integrationWarnings as $warning)
        <x-ui.alert variant="warning" icon="exclamation-triangle">{{ $warning }}</x-ui.alert>
    @endforeach

    {{-- Support mode — collapsed by default; useful for developers debugging deploy mode inference --}}
    <details class="group">
        <summary class="flex cursor-pointer items-center gap-2 text-sm text-zinc-500 hover:text-zinc-300 select-none list-none">
            <flux:icon name="chevron-right" class="size-3.5 transition group-open:rotate-90" />
            Deployment mode:
            <x-ui.badge variant="info" class="text-[10px]">{{ strtoupper((string) ($supportProfile['deployment_mode'] ?? 'static')) }}</x-ui.badge>
            <x-ui.badge variant="{{ $modeSource === 'Auto' ? 'warning' : 'success' }}" class="text-[10px]">{{ strtoupper($modeSource) }}</x-ui.badge>
            <x-ui.badge variant="success" class="text-[10px]">{{ strtoupper($workflowLabel) }}</x-ui.badge>
        </summary>
        <div class="mt-2 rounded-lg border border-zinc-800 bg-zinc-900/50 px-4 py-3 text-sm text-zinc-400">
            {{ $supportProfile['summary'] ?? '' }}
            @if ($supportProfile['detail'] ?? '')
                <p class="mt-1 text-zinc-500">{{ $supportProfile['detail'] }}</p>
            @endif
        </div>
    </details>

    {{-- Stats strip --}}
    <div class="stats stats-4">
        <div class="stat">
            <p class="stat-label">Release</p>
            <p class="stat-val-sm">{{ $currentRelease?->status ? ucfirst($currentRelease->status) : 'None' }}</p>
            <p class="stat-note">{{ $currentRelease?->activated_at?->diffForHumans() ?? 'No active release yet' }}</p>
        </div>
        <div class="stat">
            <p class="stat-label">Runtime</p>
            <p class="stat-val-sm">{{ $productionTarget?->runtime_type ?: $deploymentMode }}</p>
            <p class="stat-note">{{ $productionTarget?->release_strategy ?: $releaseStrategy }}</p>
        </div>
        <div class="stat">
            <p class="stat-label">Tracking</p>
            <p class="stat-val-sm">{{ $trackingInstallation?->is_active ? 'Active' : 'Paused' }}</p>
            <p class="stat-note">{{ $trackingInstallation?->script_route ?: 'Installer pending' }}</p>
        </div>
        <div class="stat">
            <p class="stat-label">Webhooks</p>
            <p class="stat-val-sm">{{ $recentWebhookDeliveries->count() }}</p>
            <p class="stat-note">Recent GitHub deliveries</p>
        </div>
    </div>

    <form wire:submit="save" class="space-y-5">
        {{-- General --}}
        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title><x-icons.settings /> General</x-ui.card-title>
            </x-ui.card-header>
            <x-ui.card-content>
                <flux:field>
                    <flux:label>Site name</flux:label>
                    <flux:input wire:model="name" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Domain</flux:label>
                    <flux:input wire:model="domain" placeholder="example.com" class="font-mono" />
                    <flux:error name="domain" />
                </flux:field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Branch</flux:label>
                        <flux:input wire:model="branch" class="font-mono" />
                        <flux:error name="branch" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Project type</flux:label>
                        <flux:select wire:model.live="projectType">
                            @foreach (config('platform.project_types') as $type)
                                @php $label = (new \App\Models\Site(['project_type' => $type]))->project_type_label @endphp
                                <flux:select.option value="{{ $type }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="projectType" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Deployment mode</flux:label>
                    <flux:select wire:model="deploymentMode">
                        @foreach ($deploymentOptions as $mode)
                            <flux:select.option value="{{ $mode }}">{{ $mode }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description><code>static</code> publishes build artifacts for Nginx. <code>runtime</code> keeps a local app process alive behind Nginx.</flux:description>
                    <flux:error name="deploymentMode" />
                </flux:field>

                <label class="option-label-sm">
                    <flux:checkbox wire:model="deployOnWebhook" />
                    <div>
                        <p class="text-sm font-medium">Auto deploy on GitHub push webhook</p>
                        <p class="text-xs text-zinc-500">When enabled, webhook sync also triggers a deploy after pull and parse succeed.</p>
                    </div>
                </label>

                <flux:field>
                    <flux:label>GitHub webhook secret <span class="font-normal text-zinc-500">(optional)</span></flux:label>
                    <flux:input type="password" wire:model="webhookSecret" placeholder="{{ $site->webhook_secret ? 'Leave blank to keep current' : 'Leave blank to use global' }}" class="font-mono" autocomplete="new-password" />
                    <flux:description>Stored encrypted. Overrides the global <code>GITHUB_WEBHOOK_SECRET</code> for this site.</flux:description>
                    <flux:error name="webhookSecret" />
                </flux:field>

                <flux:field>
                    <flux:label>Inbound inbox Bearer <span class="font-normal text-zinc-500">(optional)</span></flux:label>
                    <flux:input type="password" wire:model="inboxInboundSecret" placeholder="{{ $hasInboxInboundSecret ? 'Leave blank to keep current' : 'min. 32 characters' }}" class="font-mono" autocomplete="new-password" />
                    <flux:description>For <code>POST /api/inbox/{{ $site->slug }}</code>. Overrides <code>INBOX_INBOUND_SECRET</code> for this site.</flux:description>
                    <flux:error name="inboxInboundSecret" />
                    @if ($hasInboxInboundSecret)
                        <label class="mt-2 flex items-center gap-2 text-sm">
                            <flux:checkbox wire:model="clearInboxInboundSecret" />
                            Remove per-site inbox secret (fall back to global)
                        </label>
                    @endif
                </flux:field>
            </x-ui.card-content>
        </x-ui.card>

        {{-- Build & infrastructure --}}
        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title><x-icons.zap /> Build &amp; infrastructure</x-ui.card-title>
            </x-ui.card-header>
            <x-ui.card-content>
                <flux:field>
                    <flux:label>Build command</flux:label>
                    <flux:input wire:model="buildCommand" placeholder="npm run build" class="font-mono" />
                    <flux:description>Leave empty for static HTML sites with no build step.</flux:description>
                    <flux:error name="buildCommand" />
                </flux:field>

                <flux:field>
                    <flux:label>Build output directory</flux:label>
                    <flux:input wire:model="buildOutputDir" placeholder="dist" class="font-mono" />
                    <flux:description>Relative to repo root. Common: dist, build, public, _site, out.</flux:description>
                    <flux:error name="buildOutputDir" />
                </flux:field>

                <flux:field>
                    <flux:label>Deploy path</flux:label>
                    <flux:input wire:model="deployPath" placeholder="/var/www/site" class="font-mono" />
                    <flux:description>Absolute path on the server where Nginx serves the built site.</flux:description>
                    <flux:error name="deployPath" />
                </flux:field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Health check URL</flux:label>
                        <flux:input wire:model="healthCheckUrl" placeholder="https://example.com/" class="font-mono" />
                        <flux:description>Verified after activation to confirm the release is live.</flux:description>
                        <flux:error name="healthCheckUrl" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Release strategy</flux:label>
                        <flux:select wire:model="releaseStrategy">
                            <flux:select.option value="symlink">symlink</flux:select.option>
                            <flux:select.option value="replace">replace</flux:select.option>
                            <flux:select.option value="runtime">runtime</flux:select.option>
                        </flux:select>
                        <flux:error name="releaseStrategy" />
                    </flux:field>
                </div>
            </x-ui.card-content>
        </x-ui.card>

        {{-- Tracking --}}
        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title><x-icons.chart /> Tracking &amp; analytics</x-ui.card-title>
                <x-ui.button type="button" wire:click="syncAnalyticsNow" variant="outline" size="sm">Sync analytics now</x-ui.button>
            </x-ui.card-header>
            <x-ui.card-content>
                <label class="option-label-sm">
                    <flux:checkbox wire:model="trackingEnabled" />
                    <div>
                        <p class="text-sm font-medium">Enable first-party platform tracker</p>
                        <p class="text-xs text-zinc-500">Inject the tracker into deploy artifacts and collect page views, CTA clicks, and form events.</p>
                    </div>
                </label>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>GA4 measurement ID</flux:label>
                        <flux:input wire:model="gaPropertyId" placeholder="G-XXXXXXXXXX" class="font-mono" />
                        <flux:error name="gaPropertyId" />
                    </flux:field>
                    <flux:field>
                        <flux:label>GTM container ID</flux:label>
                        <flux:input wire:model="gtmId" placeholder="GTM-XXXXXXX" class="font-mono" />
                        <flux:error name="gtmId" />
                    </flux:field>
                </div>

                <label class="option-label-sm">
                    <flux:checkbox wire:model="trackingConsentMode" />
                    <div>
                        <p class="text-sm font-medium">Consent-aware tracking mode</p>
                        <p class="text-xs text-zinc-500">Persist consent intent in the installation config so future banner logic can honor it.</p>
                    </div>
                </label>
            </x-ui.card-content>
        </x-ui.card>

        <div>
            <x-ui.button type="submit">Save settings</x-ui.button>
        </div>
    </form>

    {{-- Danger zone — standalone, clearly separated from the form --}}
    <x-ui.card class="border-red-500/20">
        <x-ui.card-header>
            <x-ui.card-title class="text-red-400"><x-icons.alert /> Danger zone</x-ui.card-title>
        </x-ui.card-header>
        <x-ui.card-description>Permanently delete this site and all associated data including pages, deploys, invoices, and subscribers.</x-ui.card-description>
        <div class="mt-4" x-data="{ confirm: false }">
            <x-ui.button type="button" x-show="!confirm" x-on:click="confirm = true" variant="destructive" size="sm">Delete site</x-ui.button>
            <div x-show="confirm" x-cloak class="flex flex-wrap items-center gap-3">
                <p class="text-xs text-red-400">Are you sure? This cannot be undone.</p>
                <x-ui.button type="button" wire:click="deleteSite" variant="destructive" size="sm">Yes, delete</x-ui.button>
                <x-ui.button type="button" x-on:click="confirm = false" variant="outline" size="sm">Cancel</x-ui.button>
            </div>
        </div>
    </x-ui.card>

    {{-- Activity audit — read-only, below the fold --}}
    <div>
        <p class="mb-3 text-xs font-medium uppercase tracking-wider text-zinc-500">Activity</p>
        <div class="grid gap-4 lg:grid-cols-3">
            <x-ui.card>
                <x-ui.card-header>
                    <x-ui.card-title><x-icons.report /> Git operations</x-ui.card-title>
                </x-ui.card-header>
                @forelse ($recentGitOperations as $operation)
                    <div class="activity-item">
                        <span class="activity-dot activity-dot-success"></span>
                        <div class="flex-1 min-w-0">
                            <p class="activity-text">{{ ucfirst($operation->operation) }} · {{ ucfirst($operation->status) }}</p>
                            <p class="activity-time">{{ $operation->working_branch ?: $operation->branch ?: $branch }}</p>
                        </div>
                        <span class="activity-time">{{ $operation->started_at?->diffForHumans() ?? 'recently' }}</span>
                    </div>
                @empty
                    <x-ui.empty icon="arrow-path" title="No Git operations yet" />
                @endforelse
            </x-ui.card>

            <x-ui.card>
                <x-ui.card-header>
                    <x-ui.card-title><x-icons.globe /> Webhooks</x-ui.card-title>
                </x-ui.card-header>
                @forelse ($recentWebhookDeliveries as $delivery)
                    <div class="activity-item">
                        <span class="activity-dot activity-dot-info"></span>
                        <div class="flex-1 min-w-0">
                            <p class="activity-text">{{ $delivery->event ?: 'push' }} · {{ $delivery->status ?: 'received' }}</p>
                            <p class="activity-time">{{ $delivery->repository ?: 'GitHub delivery' }}</p>
                        </div>
                        <span class="activity-time">{{ $delivery->received_at?->diffForHumans() ?? 'recently' }}</span>
                    </div>
                @empty
                    <x-ui.empty icon="globe-alt" title="No webhook deliveries yet" />
                @endforelse
            </x-ui.card>

            <x-ui.card>
                <x-ui.card-header>
                    <x-ui.card-title><x-icons.file /> Releases</x-ui.card-title>
                </x-ui.card-header>
                @forelse ($recentReleases as $release)
                    <div class="issue-item">
                        <span class="issue-icon {{ $release->is_current ? 'issue-icon-green' : 'issue-icon-blue' }}">
                            @if ($release->is_current) <x-icons.check /> @else <x-icons.clock /> @endif
                        </span>
                        <div>
                            <p class="issue-text">{{ ucfirst($release->status) }} · {{ $release->source_commit_sha ? \Illuminate\Support\Str::limit($release->source_commit_sha, 10, '') : '—' }}</p>
                            <p class="issue-meta">{{ $release->activated_at?->diffForHumans() ?? 'pending' }}</p>
                        </div>
                    </div>
                @empty
                    <x-ui.empty icon="clock" title="No releases yet" />
                @endforelse
            </x-ui.card>
        </div>
    </div>
</div>
