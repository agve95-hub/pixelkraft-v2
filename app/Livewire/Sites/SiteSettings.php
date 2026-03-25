<?php

namespace App\Livewire\Sites;

use App\Models\Site;
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

    public function mount(): void
    {
        $site = Site::findOrFail($this->siteId);
        $this->name = $site->name;
        $this->domain = $site->domain ?? '';
        $this->buildCommand = $site->build_command ?? '';
        $this->buildOutputDir = $site->build_output_dir ?? '';
        $this->branch = $site->branch;
        $this->projectType = $site->project_type;
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
        ]);

        $site = Site::findOrFail($this->siteId);
        $site->update([
            'name'             => $this->name,
            'domain'           => $this->domain ?: null,
            'build_command'    => $this->buildCommand ?: null,
            'build_output_dir' => $this->buildOutputDir ?: null,
            'branch'           => $this->branch,
            'project_type'     => $this->projectType,
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
        return view('livewire.sites.site-settings');
    }
}
