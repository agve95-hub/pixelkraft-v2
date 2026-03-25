<?php

namespace App\Livewire\Sites;

use App\Jobs\DeploySiteJob;
use App\Jobs\ProvisionSslJob;
use App\Models\DeployLog;
use App\Models\Site;
use App\Services\DeployService;
use App\Services\NginxConfigService;
use Livewire\Component;

class DeployControls extends Component
{
    public string $siteId;
    public bool $showLogs = false;
    public ?string $viewingLogId = null;

    public function deploy(): void
    {
        $site = Site::findOrFail($this->siteId);

        DeploySiteJob::dispatch($site, 'manual');

        $site->update(['deploy_status' => 'building']);

        session()->flash('success', "Deploy started for {$site->name}.");
    }

    public function setupDomain(): void
    {
        $site = Site::findOrFail($this->siteId);

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
            session()->flash('error', 'Domain setup failed: ' . $e->getMessage());
        }
    }

    public function rollback(string $logId): void
    {
        $site = Site::findOrFail($this->siteId);
        $log = DeployLog::findOrFail($logId);

        if (empty($log->snapshot_tag)) {
            session()->flash('error', 'No snapshot available for this deploy.');
            return;
        }

        try {
            $deployer = app(DeployService::class);
            $deployer->rollback($site, $log->snapshot_tag);

            session()->flash('success', "Rolled back to {$log->snapshot_tag}.");
        } catch (\Throwable $e) {
            session()->flash('error', 'Rollback failed: ' . $e->getMessage());
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

    public function render()
    {
        $site = Site::findOrFail($this->siteId);

        $deployLogs = $site->deployLogs()
            ->latest('created_at')
            ->limit(15)
            ->get();

        $viewingLog = $this->viewingLogId
            ? DeployLog::find($this->viewingLogId)
            : null;

        return view('livewire.sites.deploy-controls', [
            'site'       => $site,
            'deployLogs' => $deployLogs,
            'viewingLog' => $viewingLog,
        ]);
    }
}
