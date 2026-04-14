<?php

namespace App\Livewire\Sites;

use App\Jobs\SyncAnalyticsJob;
use App\Services\SiteProvisioningService;
use App\Services\SiteRuntimeService;
use App\Services\SiteSupportService;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SiteSettings extends Component
{
    public string $siteId;

    public string $name = '';

    public string $domain = '';

    public string $buildCommand = '';

    public string $buildOutputDir = '';

    public string $branch = 'main';

    public string $projectType = 'static_html';

    /** Per-site GitHub webhook secret. Stored encrypted. Leave blank to use the global secret. */
    public string $webhookSecret = '';

    /** Per-site Bearer for POST /api/inbox/{slug}. Stored encrypted. Leave blank to keep unchanged. */
    public string $inboxInboundSecret = '';

    public bool $clearInboxInboundSecret = false;

    public string $deploymentMode = SiteRuntimeService::MODE_STATIC;

    public string $deployPath = '';

    public string $sshHost = '';

    public string $healthCheckUrl = '';

    public string $releaseStrategy = 'symlink';

    public bool $deployOnWebhook = false;

    public bool $trackingEnabled = true;

    public bool $trackingConsentMode = false;

    public string $gaPropertyId = '';

    public string $gtmId = '';

    public function mount(): void
    {
        $site = SiteAccess::findOrFail($this->siteId);
        $runtime = $this->runtime();
        $productionTarget = $site->deploymentTargets()
            ->where('environment', 'production')
            ->first();
        $trackingInstallation = $site->trackingInstallations()
            ->where('provider', 'pixelkraft')
            ->latest()
            ->first();

        $this->name = $site->name;
        $this->domain = $site->domain ?? '';
        $this->webhookSecret = ''; // Never pre-fill secrets into the form — write-only.
        $this->buildCommand = $site->build_command ?? '';
        $this->buildOutputDir = $site->build_output_dir ?? '';
        $this->branch = $site->branch;
        $this->projectType = $site->project_type;
        $this->deploymentMode = $site->deployment_mode ?: $runtime->deploymentMode($site);
        $this->deployPath = $productionTarget?->deploy_path ?? ($site->deploy_path ?? '');
        $this->sshHost = $productionTarget?->host ?? ($site->ssh_host ?? '');
        $this->healthCheckUrl = $productionTarget?->health_check_url ?? '';
        $this->releaseStrategy = $productionTarget?->release_strategy ?? 'symlink';
        $this->deployOnWebhook = (bool) $site->deploy_on_webhook;
        $this->trackingEnabled = (bool) ($trackingInstallation?->is_active ?? true);
        $this->trackingConsentMode = (bool) ($trackingInstallation?->consent_mode ?? false);
        $this->gaPropertyId = $trackingInstallation?->measurement_id ?? ($site->ga_property_id ?? '');
        $this->gtmId = $trackingInstallation?->container_id ?? ($site->gtm_id ?? '');
    }

    public function updatedProjectType(string $value): void
    {
        if (! $this->runtime()->supportsRuntimeModeForProjectType($value)) {
            $this->deploymentMode = SiteRuntimeService::MODE_STATIC;
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            // Hostname: letters, digits, dots, hyphens only; no newlines or shell chars.
            'domain' => ['nullable', 'string', 'max:253', 'regex:/^[a-zA-Z0-9*][a-zA-Z0-9.\-*]*$/'],
            // Build command: disallow shell injection metacharacters (; | ` $ < >)
            // and newlines. The && operator is still allowed (commonly used for
            // "build && export" workflows).
            'buildCommand' => ['nullable', 'string', 'max:500', 'not_regex:/[;|`\$<>\r\n]/'],
            'buildOutputDir' => ['nullable', 'string', 'max:255', 'not_regex:/[\r\n]/'],
            // Branch: letters, digits, hyphens, dots, underscores, forward-slashes.
            'branch' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-._\/]*$/'],
            'projectType' => ['required', 'string', 'in:static_html,php_site,react,vue,svelte,astro,hugo,eleventy,nextjs,nuxt,custom'],
            'deploymentMode' => 'required|in:static,runtime',
            // Deploy path must be an absolute filesystem path; no newlines.
            'deployPath' => ['nullable', 'string', 'max:255', 'regex:/^\//', 'not_regex:/[\r\n;{}]/'],
            'sshHost' => ['nullable', 'string', 'max:255', 'not_regex:/[\r\n]/'],
            'healthCheckUrl' => 'nullable|url|max:500',
            'releaseStrategy' => 'required|in:symlink,replace,runtime',
            'deployOnWebhook' => 'boolean',
            'trackingEnabled' => 'boolean',
            'trackingConsentMode' => 'boolean',
            'gaPropertyId' => 'nullable|string|max:255',
            'gtmId' => 'nullable|string|max:255',
            // Leave blank to keep the existing secret, or enter a new one to rotate it.
            'webhookSecret' => ['nullable', 'string', 'min:16', 'max:255', 'not_regex:/[\r\n]/'],
            'inboxInboundSecret' => ['nullable', 'string', 'min:32', 'max:255', 'not_regex:/[\r\n]/'],
            'clearInboxInboundSecret' => 'boolean',
        ]);

        if (
            $this->deploymentMode === SiteRuntimeService::MODE_RUNTIME
            && ! $this->runtime()->supportsRuntimeModeForProjectType($this->projectType)
        ) {
            $this->addError('deploymentMode', 'Runtime deployment is currently only supported for Next.js projects.');

            return;
        }

        $site = SiteAccess::findOrFail($this->siteId);
        $updatePayload = [
            'name' => $this->name,
            'domain' => $this->domain ?: null,
            'deployment_mode' => $this->deploymentMode,
            'build_command' => $this->buildCommand ?: null,
            'build_output_dir' => $this->buildOutputDir ?: null,
            'branch' => $this->branch,
            'project_type' => $this->projectType,
            'deploy_path' => $this->deployPath ?: null,
            'ssh_host' => $this->sshHost ?: null,
            'deploy_on_webhook' => $this->deployOnWebhook,
            'ga_property_id' => $this->gaPropertyId ?: null,
            'gtm_id' => $this->gtmId ?: null,
        ];

        // Only update the webhook secret when the operator explicitly types a new one.
        // An empty submission leaves the existing encrypted value untouched.
        if ($this->webhookSecret !== '') {
            $updatePayload['webhook_secret'] = $this->webhookSecret;
        }

        if ($this->clearInboxInboundSecret) {
            $updatePayload['inbox_inbound_secret'] = null;
        } elseif ($this->inboxInboundSecret !== '') {
            $updatePayload['inbox_inbound_secret'] = $this->inboxInboundSecret;
        }

        $site->update($updatePayload);

        $site->refresh();
        app(SiteProvisioningService::class)->initializeSite($site);

        $runtimeType = $this->runtime()->deploymentMode($site);
        $healthCheckUrl = $this->healthCheckUrl ?: ($this->domain ? 'https://'.$this->domain.'/' : null);

        $site->deploymentTargets()
            ->where('environment', 'production')
            ->update([
                'host' => $this->sshHost ?: $this->domain ?: null,
                'deploy_path' => $this->deployPath ?: null,
                'runtime_type' => $runtimeType,
                'health_check_url' => $healthCheckUrl,
                'release_strategy' => $this->releaseStrategy,
                'is_active' => true,
            ]);

        $site->deploymentTargets()
            ->where('environment', 'staging')
            ->update([
                'host' => $this->sshHost ?: $this->domain ?: null,
                'deploy_path' => $this->deployPath ? rtrim($this->deployPath, '/\\').'-staging' : null,
                'runtime_type' => $runtimeType,
                'release_strategy' => $this->releaseStrategy,
            ]);

        $trackingInstallation = $site->trackingInstallations()->firstOrCreate(
            [
                'provider' => 'pixelkraft',
            ],
            [
                'site_id' => $site->id,
            ],
        );

        $trackingInstallation->update([
            'measurement_id' => $this->gaPropertyId ?: null,
            'container_id' => $this->gtmId ?: null,
            'script_route' => route('tracking.script', ['site' => $site]),
            'collector_path' => route('tracking.collect', ['site' => $site]),
            'consent_mode' => $this->trackingConsentMode,
            'is_active' => $this->trackingEnabled,
        ]);

        $this->inboxInboundSecret = '';
        $this->clearInboxInboundSecret = false;

        session()->flash('success', 'Site settings, deployment targets, and tracking installation updated.');
    }

    public function syncAnalyticsNow(): void
    {
        $site = SiteAccess::findOrFail($this->siteId);
        SyncAnalyticsJob::dispatch($site);

        session()->flash('success', 'Analytics sync queued. Fresh tracker and GA data will appear after the background job completes.');
    }

    public function deleteSite(): void
    {
        $site = SiteAccess::findOrFail($this->siteId);
        $site->delete();

        session()->flash('success', "Site '{$site->name}' deleted.");
        $this->redirect(route('sites.index'), navigate: true);
    }

    public function render(): View
    {
        $site = SiteAccess::findOrFail($this->siteId);

        return view('livewire.sites.site-settings', [
            'site' => $site,
            'supportProfile' => app(SiteSupportService::class)->siteProfile($site),
            'hasInboxInboundSecret' => filled($site->getAttribute('inbox_inbound_secret')),
            'deploymentOptions' => $this->runtime()->supportedDeploymentModesForProjectType($this->projectType),
            'productionTarget' => $site->deploymentTargets()->where('environment', 'production')->first(),
            'currentRelease' => $site->currentDeploymentRelease()->first(),
            'recentReleases' => $site->deploymentReleases()->latest('activated_at')->limit(4)->get(),
            'recentGitOperations' => $site->gitOperations()->latest('started_at')->limit(6)->get(),
            'recentWebhookDeliveries' => $site->webhookDeliveries()->latest('received_at')->limit(6)->get(),
            'trackingInstallation' => $site->trackingInstallations()->where('provider', 'pixelkraft')->latest()->first(),
        ]);
    }

    private function runtime(): SiteRuntimeService
    {
        return app(SiteRuntimeService::class);
    }
}
