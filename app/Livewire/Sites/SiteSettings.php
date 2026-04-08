<?php

namespace App\Livewire\Sites;

use App\Models\Site;
use App\Services\SiteSupportService;
use App\Services\SiteRuntimeService;
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
    public string $deploymentMode = SiteRuntimeService::MODE_STATIC;
    public bool $deployOnWebhook = false;

    public function mount(): void
    {
        $site = Site::findOrFail($this->siteId);
        $runtime = $this->runtime();
        $this->name = $site->name;
        $this->domain = $site->domain ?? '';
        $this->buildCommand = $site->build_command ?? '';
        $this->buildOutputDir = $site->build_output_dir ?? '';
        $this->branch = $site->branch;
        $this->projectType = $site->project_type;
        $this->deploymentMode = $site->deployment_mode ?: $runtime->deploymentMode($site);
        $this->deployOnWebhook = (bool) $site->deploy_on_webhook;
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
            'name'           => 'required|string|max:255',
            'domain'         => 'nullable|string|max:255',
            'buildCommand'   => 'nullable|string|max:500',
            'buildOutputDir' => 'nullable|string|max:255',
            'branch'         => 'required|string|max:100',
            'projectType'    => 'required|string',
            'deploymentMode' => 'required|in:static,runtime',
            'deployOnWebhook' => 'boolean',
        ]);

        if (
            $this->deploymentMode === SiteRuntimeService::MODE_RUNTIME
            && ! $this->runtime()->supportsRuntimeModeForProjectType($this->projectType)
        ) {
            $this->addError('deploymentMode', 'Runtime deployment is currently only supported for Next.js projects.');
            return;
        }

        $site = Site::findOrFail($this->siteId);
        $site->update([
            'name'             => $this->name,
            'domain'           => $this->domain ?: null,
            'deployment_mode'  => $this->deploymentMode,
            'build_command'    => $this->buildCommand ?: null,
            'build_output_dir' => $this->buildOutputDir ?: null,
            'branch'           => $this->branch,
            'project_type'     => $this->projectType,
            'deploy_on_webhook' => $this->deployOnWebhook,
        ]);

        session()->flash('success', 'Site settings updated.');
    }

    public function deleteSite(): void
    {
        $site = Site::findOrFail($this->siteId);
        $site->delete();

        session()->flash('success', "Site '{$site->name}' deleted.");
        $this->redirect(route('sites.index'), navigate: true);
    }

    public function render()
    {
        $site = Site::findOrFail($this->siteId);

        return view('livewire.sites.site-settings', [
            'supportProfile' => app(SiteSupportService::class)->siteProfile($site),
            'deploymentOptions' => $this->runtime()->supportedDeploymentModesForProjectType($this->projectType),
        ]);
    }

    private function runtime(): SiteRuntimeService
    {
        return app(SiteRuntimeService::class);
    }
}
