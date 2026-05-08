@php
    $workflowLabel = ($supportProfile['editor_workflow'] ?? null) === 'visual_html' ? 'Visual HTML' : 'Code first';
    $modeSource = ($supportProfile['deployment_mode_source'] ?? 'configured') === 'inferred' ? 'Auto' : 'Configured';
@endphp

<div class="max-w-[980px] space-y-5">
    @if (session()->has('success'))
        <x-ui.alert variant="success" icon="check-circle">{{ session('success') }}</x-ui.alert>
    @endif

    {{-- Support mode summary --}}
    <x-ui.card>
        <x-ui.card-header>
            <x-ui.card-title><x-icons.shield /> Current support mode</x-ui.card-title>
            <div class="flex flex-wrap gap-2">
                <x-ui.badge variant="info">{{ strtoupper((string) ($supportProfile['deployment_mode'] ?? 'static')) }}</x-ui.badge>
                <x-ui.badge variant="{{ $modeSource === 'Auto' ? 'warning' : 'success' }}">{{ strtoupper($modeSource) }}</x-ui.badge>
                <x-ui.badge variant="success">{{ strtoupper($workflowLabel) }}</x-ui.badge>
            </div>
        </x-ui.card-header>
        <div class="space-y-1 text-sm">
            <p>{{ $supportProfile['summary'] ?? 'No support profile available.' }}</p>
            <p class="text-zinc-500">{{ $supportProfile['detail'] ?? '' }}</p>
        </div>
    </x-ui.card>

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
                            @foreach (config('pixelkraft.project_types') as $type)
                                <flux:select.option value="{{ $type }}">{{ $type }}</flux:select.option>
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

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Deploy path</flux:label>
                        <flux:input wire:model="deployPath" placeholder="/var/www/site" class="font-mono" />
                        <flux:error name="deployPath" />
                    </flux:field>
                    <flux:field>
                        <flux:label>SSH / target host</flux:label>
                        <flux:input wire:model="sshHost" placeholder="server.example.com" class="font-mono" />
                        <flux:error name="sshHost" />
                    </flux:field>
                </div>

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
                        <p class="text-sm font-medium">Enable first-party pixelkraft tracker</p>
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

    {{-- Git & webhook history --}}
    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title><x-icons.report /> Recent Git operations</x-ui.card-title>
            </x-ui.card-header>
            @forelse ($recentGitOperations as $operation)
                <div class="activity-item">
                    <span class="activity-dot" style="background:var(--pk-accent)"></span>
                    <div class="flex-1 min-w-0">
                        <p class="activity-text">{{ ucfirst($operation->operation) }} &middot; {{ ucfirst($operation->status) }}</p>
                        <p class="activity-time">{{ $operation->working_branch ?: $operation->branch ?: $branch }}</p>
                    </div>
                    <span class="activity-time">{{ $operation->started_at?->diffForHumans() ?? 'recently' }}</span>
                </div>
            @empty
                <x-ui.empty icon="arrow-path" title="No Git operations recorded yet" />
            @endforelse
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title><x-icons.globe /> Recent webhooks</x-ui.card-title>
            </x-ui.card-header>
            @forelse ($recentWebhookDeliveries as $delivery)
                <div class="activity-item">
                    <span class="activity-dot" style="background:var(--blue)"></span>
                    <div class="flex-1 min-w-0">
                        <p class="activity-text">{{ $delivery->event ?: 'push' }} &middot; {{ $delivery->status ?: 'received' }}</p>
                        <p class="activity-time">{{ $delivery->repository ?: 'GitHub delivery' }}</p>
                    </div>
                    <span class="activity-time">{{ $delivery->received_at?->diffForHumans() ?? 'recently' }}</span>
                </div>
            @empty
                <x-ui.empty icon="globe-alt" title="No webhook deliveries recorded yet" />
            @endforelse
        </x-ui.card>
    </div>

    {{-- Release history + Danger zone --}}
    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title><x-icons.file /> Release history</x-ui.card-title>
            </x-ui.card-header>
            @forelse ($recentReleases as $release)
                <div class="issue-item">
                    <span class="issue-icon {{ $release->is_current ? 'issue-icon-green' : 'issue-icon-blue' }}">
                        @if ($release->is_current) <x-icons.check /> @else <x-icons.clock /> @endif
                    </span>
                    <div>
                        <p class="issue-text">{{ ucfirst($release->status) }} &middot; {{ $release->source_commit_sha ? \Illuminate\Support\Str::limit($release->source_commit_sha, 12, '') : 'pending commit' }}</p>
                        <p class="issue-meta">{{ $release->activated_at?->diffForHumans() ?? 'waiting' }} &middot; {{ $release->tracking_version ?: 'tracking pending' }}</p>
                    </div>
                </div>
            @empty
                <x-ui.empty icon="clock" title="No releases recorded yet" />
            @endforelse
        </x-ui.card>

        <x-ui.card class="border-red-500/20">
            <x-ui.card-header>
                <x-ui.card-title class="text-red-400"><x-icons.alert /> Danger zone</x-ui.card-title>
            </x-ui.card-header>
            <x-ui.card-description>Permanently delete this site and all associated data.</x-ui.card-description>
            <div class="mt-4" x-data="{ confirm: false }">
                <x-ui.button type="button" x-show="!confirm" x-on:click="confirm = true" variant="outline" size="sm" class="border-red-500/30 text-red-400 hover:bg-red-500/10">Delete site</x-ui.button>
                <div x-show="confirm" x-cloak class="flex flex-wrap items-center gap-3">
                    <p class="text-xs text-red-400">Are you sure? This cannot be undone.</p>
                    <x-ui.button type="button" wire:click="deleteSite" variant="destructive" size="sm">Yes, delete</x-ui.button>
                    <x-ui.button type="button" x-on:click="confirm = false" variant="outline" size="sm">Cancel</x-ui.button>
                </div>
            </div>
        </x-ui.card>
    </div>
</div>
