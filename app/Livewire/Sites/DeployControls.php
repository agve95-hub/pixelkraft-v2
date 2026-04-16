<?php

namespace App\Livewire\Sites;

use App\Jobs\DeploySiteJob;
use App\Jobs\ProvisionSslJob;
use App\Models\DeployLog;
use App\Services\DeployService;
use App\Services\NginxConfigService;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class DeployControls extends Component
{
    public string $siteId;

    public bool $showLogs = false;

    public ?string $viewingLogId = null;

    public function deploy(): void
    {
        $site = SiteAccess::findOrFail($this->siteId);

        if ($site->deploy_status?->isActive()) {
            session()->flash('error', 'A deploy is already in progress. Please wait for it to finish.');

            return;
        }

        DeploySiteJob::dispatch($site, 'manual');

        session()->flash('success', "Deploy started for {$site->name}.");
    }

    public function setupDomain(): void
    {
        $site = SiteAccess::findOrFail($this->siteId);

        if (empty($site->domain) || empty($site->deploy_path)) {
            session()->flash('error', 'Configure domain and deploy path in settings first.');

            return;
        }

        try {
            $nginx = app(NginxConfigService::class);
            $configPath = $nginx->generateConfig($site);

            $site->update(['nginx_conf_path' => $configPath]);

            $nginx->reloadNginx();

            session()->flash('success', "Nginx configured for {$site->domain}. Setting up SSL...");

            // Provision SSL in background
            ProvisionSslJob::dispatch($site);

        } catch (\Throwable $e) {
            Log::error('Domain setup failed', ['site_id' => $this->siteId, 'error' => $e->getMessage()]);
            session()->flash('error', 'Domain setup failed. Check application logs for details.');
        }
    }

    public function rollback(string $logId): void
    {
        $site = SiteAccess::findOrFail($this->siteId);
        $log = DeployLog::query()
            ->where('site_id', $site->id)
            ->findOrFail($logId);

        if (empty($log->snapshot_tag)) {
            session()->flash('error', 'No snapshot available for this deploy.');

            return;
        }

        try {
            $deployer = app(DeployService::class);
            $deployer->rollback($site, $log);

            session()->flash('success', "Rolled back to {$log->snapshot_tag}.");
        } catch (\Throwable $e) {
            Log::error('Rollback failed', ['site_id' => $site->id, 'log_id' => $logId, 'error' => $e->getMessage()]);
            session()->flash('error', 'Rollback failed. Check application logs for details.');
        }
    }

    public function viewLog(string $logId): void
    {
        $this->viewingLogId = $logId;
    }

    public function closeLog(): void
    {
        $this->viewingLogId = null;
    }

    public function render(): View
    {
        $site = SiteAccess::findOrFail($this->siteId);

        $deployLogs = $site->deployLogs()
            ->latest('created_at')
            ->limit(15)
            ->get();

        $viewingLog = $this->viewingLogId
            ? $site->deployLogs()->find($this->viewingLogId)
            : null;
        $currentRelease = $site->currentDeploymentRelease()->first();
        $productionTarget = $site->deploymentTargets()
            ->where('environment', 'production')
            ->first();
        $trackingInstallation = $site->activeTrackingInstallation()->first();

        return view('livewire.sites.deploy-controls', [
            'site' => $site,
            'deployLogs' => $deployLogs,
            'viewingLog' => $viewingLog,
            'currentRelease' => $currentRelease,
            'productionTarget' => $productionTarget,
            'trackingInstallation' => $trackingInstallation,
        ]);
    }
}
