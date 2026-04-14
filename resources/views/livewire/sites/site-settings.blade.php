@php
    $workflowLabel = ($supportProfile['editor_workflow'] ?? null) === 'visual_html' ? 'Visual HTML' : 'Code first';
    $modeSource = ($supportProfile['deployment_mode_source'] ?? 'configured') === 'inferred' ? 'Auto' : 'Configured';
@endphp

<div style="max-width:980px;display:grid;gap:20px">
    @if (session()->has('success'))
        <div class="dash-card" style="border-color:rgba(16,185,129,0.25);background:rgba(16,185,129,0.08)">
            <div style="font-size:13px;color:#d1fae5">{{ session('success') }}</div>
        </div>
    @endif

    <div class="dash-card">
        <div class="dash-card-head">
            <div class="dash-card-title">
                <x-icons.shield />
                <span>Current support mode</span>
            </div>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px">
            <x-pill color="blue" :dot="false" style="font-size:10px">{{ strtoupper((string) ($supportProfile['deployment_mode'] ?? 'static')) }}</x-pill>
            <x-pill :color="$modeSource === 'Auto' ? 'yellow' : 'green'" :dot="false" style="font-size:10px">{{ strtoupper($modeSource) }}</x-pill>
            <x-pill color="green" :dot="false" style="font-size:10px">{{ strtoupper($workflowLabel) }}</x-pill>
        </div>

        <div style="display:grid;gap:10px;font-size:13px;color:rgba(255,255,255,0.76)">
            <div>{{ $supportProfile['summary'] ?? 'No support profile available.' }}</div>
            <div style="color:var(--zinc-500)">{{ $supportProfile['detail'] ?? '' }}</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:rgba(255,255,255,0.1);border-radius:12px;overflow:hidden">
        <div class="stat">
            <div class="stat-label">Release</div>
            <div class="stat-val-sm">{{ $currentRelease?->status ? ucfirst($currentRelease->status) : 'None' }}</div>
            <div class="stat-note">{{ $currentRelease?->activated_at?->diffForHumans() ?? 'No active release yet' }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Runtime</div>
            <div class="stat-val-sm">{{ $productionTarget?->runtime_type ?: $deploymentMode }}</div>
            <div class="stat-note">{{ $productionTarget?->release_strategy ?: $releaseStrategy }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Tracking</div>
            <div class="stat-val-sm">{{ $trackingInstallation?->is_active ? 'Active' : 'Paused' }}</div>
            <div class="stat-note">{{ $trackingInstallation?->script_route ?: 'Installer pending' }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Webhooks</div>
            <div class="stat-val-sm">{{ $recentWebhookDeliveries->count() }}</div>
            <div class="stat-note">Recent GitHub deliveries</div>
        </div>
    </div>

    <form wire:submit="save" style="display:grid;gap:20px">
        <div class="dash-card">
            <div class="dash-card-head">
                <div class="dash-card-title">
                    <x-icons.settings />
                    <span>General</span>
                </div>
            </div>

            <div class="form-field">
                <label class="form-label" for="site-name">Site name</label>
                <input id="site-name" wire:model="name" class="form-input" />
                @error('name')
                    <div class="form-hint" style="color:var(--red)">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-field">
                <label class="form-label" for="site-domain">Domain</label>
                <input id="site-domain" wire:model="domain" class="form-input" placeholder="example.com" style="font-family:var(--mono);font-size:13px" />
                @error('domain')
                    <div class="form-hint" style="color:var(--red)">{{ $message }}</div>
                @enderror
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-field">
                    <label class="form-label" for="site-branch">Branch</label>
                    <input id="site-branch" wire:model="branch" class="form-input" style="font-family:var(--mono);font-size:13px" />
                    @error('branch')
                        <div class="form-hint" style="color:var(--red)">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-field">
                    <label class="form-label" for="project-type">Project type</label>
                    <select id="project-type" wire:model.live="projectType" class="form-input" style="cursor:pointer">
                        @foreach (config('pixelkraft.project_types') as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('projectType')
                        <div class="form-hint" style="color:var(--red)">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-field">
                <label class="form-label" for="deployment-mode">Deployment mode</label>
                <select id="deployment-mode" wire:model="deploymentMode" class="form-input" style="cursor:pointer">
                    @foreach ($deploymentOptions as $mode)
                        <option value="{{ $mode }}">{{ $mode }}</option>
                    @endforeach
                </select>
                <div class="form-hint">
                    `static` publishes build artifacts for Nginx to serve directly. `runtime` keeps a local app process alive behind Nginx.
                </div>
                @error('deploymentMode')
                    <div class="form-hint" style="color:var(--red)">{{ $message }}</div>
                @enderror
            </div>

            <label style="display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border:1px solid rgba(255,255,255,0.08);border-radius:10px;cursor:pointer;transition:background 0.08s" onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background=''">
                <input type="checkbox" wire:model="deployOnWebhook" style="margin-top:3px;accent-color:var(--accent)" />
                <div>
                    <div style="font-weight:500;font-size:14px;color:rgba(255,255,255,0.8);margin-bottom:2px">Auto deploy on GitHub push webhook</div>
                    <div style="font-size:12px;color:var(--zinc-500)">When enabled, webhook sync also triggers a deploy after pull and parse succeed.</div>
                </div>
            </label>

            <div class="form-field" style="margin-top:4px">
                <label class="form-label" for="per-site-webhook-secret">GitHub webhook secret (optional)</label>
                <input id="per-site-webhook-secret" type="password" wire:model="webhookSecret" class="form-input" placeholder="{{ $site->webhook_secret ? 'Leave blank to keep current secret' : 'Leave blank to use global secret' }}" autocomplete="new-password" style="font-family:var(--mono);font-size:13px" />
                <div class="form-hint">Stored encrypted. When set, GitHub must sign with this secret instead of the global <code style="font-size:11px">GITHUB_WEBHOOK_SECRET</code>.</div>
                @error('webhookSecret')
                    <div class="form-hint" style="color:var(--red)">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-field">
                <label class="form-label" for="inbox-inbound-secret">Inbound inbox Bearer (optional)</label>
                <input id="inbox-inbound-secret" type="password" wire:model="inboxInboundSecret" class="form-input" placeholder="{{ $hasInboxInboundSecret ? 'Leave blank to keep current token' : 'min. 32 characters' }}" autocomplete="new-password" style="font-family:var(--mono);font-size:13px" />
                <div class="form-hint">For <code style="font-size:11px">POST /api/inbox/{{ $site->slug }}</code>. When set, this token is used instead of <code style="font-size:11px">INBOX_INBOUND_SECRET</code> for this site only (32+ chars).</div>
                @error('inboxInboundSecret')
                    <div class="form-hint" style="color:var(--red)">{{ $message }}</div>
                @enderror
                @if ($hasInboxInboundSecret)
                    <label style="display:flex;align-items:center;gap:8px;margin-top:10px;font-size:13px;color:rgba(255,255,255,0.75);cursor:pointer">
                        <input type="checkbox" wire:model="clearInboxInboundSecret" style="accent-color:var(--accent)" />
                        <span>Remove per-site inbox secret (fall back to global)</span>
                    </label>
                @endif
            </div>
        </div>

        <div class="dash-card">
            <div class="dash-card-head">
                <div class="dash-card-title">
                    <x-icons.zap />
                    <span>Build &amp; infrastructure</span>
                </div>
            </div>

            <div class="form-field">
                <label class="form-label" for="build-command">Build command</label>
                <input id="build-command" wire:model="buildCommand" class="form-input" placeholder="npm run build" style="font-family:var(--mono);font-size:13px" />
                <div class="form-hint">Leave empty for static HTML sites with no build step.</div>
                @error('buildCommand')
                    <div class="form-hint" style="color:var(--red)">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-field">
                <label class="form-label" for="build-output-dir">Build output directory</label>
                <input id="build-output-dir" wire:model="buildOutputDir" class="form-input" placeholder="dist" style="font-family:var(--mono);font-size:13px" />
                <div class="form-hint">Relative to repo root. Common values: dist, build, public, _site, out.</div>
                @error('buildOutputDir')
                    <div class="form-hint" style="color:var(--red)">{{ $message }}</div>
                @enderror
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-field">
                    <label class="form-label" for="deploy-path">Deploy path</label>
                    <input id="deploy-path" wire:model="deployPath" class="form-input" placeholder="/var/www/site" style="font-family:var(--mono);font-size:13px" />
                    @error('deployPath')
                        <div class="form-hint" style="color:var(--red)">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-field">
                    <label class="form-label" for="ssh-host">SSH / target host</label>
                    <input id="ssh-host" wire:model="sshHost" class="form-input" placeholder="server.example.com" style="font-family:var(--mono);font-size:13px" />
                    @error('sshHost')
                        <div class="form-hint" style="color:var(--red)">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-field">
                    <label class="form-label" for="health-check-url">Health check URL</label>
                    <input id="health-check-url" wire:model="healthCheckUrl" class="form-input" placeholder="https://example.com/" style="font-family:var(--mono);font-size:13px" />
                    <div class="form-hint">Used after activation to verify the release went live.</div>
                    @error('healthCheckUrl')
                        <div class="form-hint" style="color:var(--red)">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-field">
                    <label class="form-label" for="release-strategy">Release strategy</label>
                    <select id="release-strategy" wire:model="releaseStrategy" class="form-input" style="cursor:pointer">
                        <option value="symlink">symlink</option>
                        <option value="replace">replace</option>
                        <option value="runtime">runtime</option>
                    </select>
                    @error('releaseStrategy')
                        <div class="form-hint" style="color:var(--red)">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="dash-card">
            <div class="dash-card-head">
                <div class="dash-card-title">
                    <x-icons.chart />
                    <span>Tracking &amp; analytics</span>
                </div>
                <button type="button" wire:click="syncAnalyticsNow" class="btn btn-sm">Sync analytics now</button>
            </div>

            <label style="display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border:1px solid rgba(255,255,255,0.08);border-radius:10px;cursor:pointer;transition:background 0.08s;margin-bottom:16px" onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background=''">
                <input type="checkbox" wire:model="trackingEnabled" style="margin-top:3px;accent-color:var(--accent)" />
                <div>
                    <div style="font-weight:500;font-size:14px;color:rgba(255,255,255,0.8);margin-bottom:2px">Enable first-party pixelkraft tracker</div>
                    <div style="font-size:12px;color:var(--zinc-500)">Inject the tracker into deploy artifacts and collect page views, CTA clicks, and form events.</div>
                </div>
            </label>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-field">
                    <label class="form-label" for="ga-property-id">GA4 measurement ID</label>
                    <input id="ga-property-id" wire:model="gaPropertyId" class="form-input" placeholder="G-XXXXXXXXXX" style="font-family:var(--mono);font-size:13px" />
                    @error('gaPropertyId')
                        <div class="form-hint" style="color:var(--red)">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-field">
                    <label class="form-label" for="gtm-id">GTM container ID</label>
                    <input id="gtm-id" wire:model="gtmId" class="form-input" placeholder="GTM-XXXXXXX" style="font-family:var(--mono);font-size:13px" />
                    @error('gtmId')
                        <div class="form-hint" style="color:var(--red)">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <label style="display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border:1px solid rgba(255,255,255,0.08);border-radius:10px;cursor:pointer;transition:background 0.08s" onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background=''">
                <input type="checkbox" wire:model="trackingConsentMode" style="margin-top:3px;accent-color:var(--accent)" />
                <div>
                    <div style="font-weight:500;font-size:14px;color:rgba(255,255,255,0.8);margin-bottom:2px">Consent-aware tracking mode</div>
                    <div style="font-size:12px;color:var(--zinc-500)">Persist consent-mode intent in the installation config so future banner logic can honor it.</div>
                </div>
            </label>
        </div>

        <div style="display:flex;gap:12px">
            <button type="submit" class="btn btn-accent" style="padding:10px 24px;font-size:14px">Save settings</button>
        </div>
    </form>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="dash-card">
            <div class="dash-card-head">
                <div class="dash-card-title">
                    <x-icons.report />
                    <span>Recent Git operations</span>
                </div>
            </div>

            <div class="thread-list">
                @forelse ($recentGitOperations as $operation)
                    <div class="thread" style="cursor:default">
                        <div>
                            <div class="thread-from">{{ ucfirst($operation->operation) }} · {{ ucfirst($operation->status) }}</div>
                            <div class="thread-preview">{{ $operation->working_branch ?: $operation->branch ?: $branch }}</div>
                        </div>
                        <div class="thread-time">{{ $operation->started_at?->diffForHumans() ?? 'recently' }}</div>
                    </div>
                @empty
                    <div class="empty">
                        <div class="empty-icon"><x-icons.zap /></div>
                        No Git operations recorded yet
                    </div>
                @endforelse
            </div>
        </div>

        <div class="dash-card">
            <div class="dash-card-head">
                <div class="dash-card-title">
                    <x-icons.globe />
                    <span>Recent webhooks</span>
                </div>
            </div>

            <div class="thread-list">
                @forelse ($recentWebhookDeliveries as $delivery)
                    <div class="thread" style="cursor:default">
                        <div>
                            <div class="thread-from">{{ $delivery->event ?: 'push' }} · {{ $delivery->status ?: 'received' }}</div>
                            <div class="thread-preview">{{ $delivery->repository ?: 'GitHub delivery' }}</div>
                        </div>
                        <div class="thread-time">{{ $delivery->received_at?->diffForHumans() ?? 'recently' }}</div>
                    </div>
                @empty
                    <div class="empty">
                        <div class="empty-icon"><x-icons.globe /></div>
                        No webhook deliveries recorded yet
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="dash-card">
            <div class="dash-card-head">
                <div class="dash-card-title">
                    <x-icons.file />
                    <span>Release history</span>
                </div>
            </div>

            @forelse ($recentReleases as $release)
                <div class="issue-item">
                    <div class="issue-icon issue-icon-{{ $release->is_current ? 'green' : 'blue' }}">
                        @if ($release->is_current)
                            <x-icons.check />
                        @else
                            <x-icons.clock />
                        @endif
                    </div>
                    <div>
                        <div class="issue-text">{{ ucfirst($release->status) }} · {{ $release->source_commit_sha ? \Illuminate\Support\Str::limit($release->source_commit_sha, 12, '') : 'pending commit' }}</div>
                        <div class="issue-meta">{{ $release->activated_at?->diffForHumans() ?? 'waiting for activation' }} · {{ $release->tracking_version ?: 'tracking pending' }}</div>
                    </div>
                </div>
            @empty
                <div class="empty">
                    <div class="empty-icon"><x-icons.file /></div>
                    No releases recorded yet
                </div>
            @endforelse
        </div>

        <div class="dash-card" style="border-color:rgba(239,68,68,0.22)">
            <div class="dash-card-head">
                <div class="dash-card-title" style="color:var(--red)">
                    <x-icons.alert />
                    <span>Danger zone</span>
                </div>
            </div>

            <div style="font-size:13px;color:var(--zinc-500);margin-bottom:16px">
                Permanently delete this site and all associated data.
            </div>

            <div x-data="{ confirm: false }" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <button type="button" x-show="!confirm" x-on:click="confirm = true" class="btn btn-sm" style="color:var(--red);border-color:rgba(239,68,68,0.25)">Delete site</button>
                <div x-show="confirm" x-cloak style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                    <span style="font-size:12px;color:var(--red)">Are you sure? This cannot be undone.</span>
                    <button type="button" wire:click="deleteSite" class="btn btn-sm" style="background:rgba(239,68,68,0.16);border-color:rgba(239,68,68,0.24);color:var(--red)">Yes, delete</button>
                    <button type="button" x-on:click="confirm = false" class="btn btn-sm">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>
