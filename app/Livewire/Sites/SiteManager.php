<?php

namespace App\Livewire\Sites;

use App\Jobs\CloneRepoJob;
use App\Models\Site;
use Illuminate\Support\Str;
use Livewire\Component;

class SiteManager extends Component
{
    public string $name = '';
    public string $repoUrl = '';
    public string $branch = 'main';
    public string $githubToken = '';

    protected $rules = [
        'name'        => 'required|string|max:255',
        'repoUrl'     => 'required|url|regex:/github\.com/',
        'branch'      => 'required|string|max:100',
        'githubToken' => 'nullable|string|max:500',
    ];

    public function create(): void
    {
        $this->validate();

        $site = Site::create([
            'name'         => $this->name,
            'slug'         => Str::slug($this->name),
            'repo_url'     => $this->repoUrl,
            'branch'       => $this->branch,
            'github_token' => $this->githubToken ?: null,
        ]);

        // Dispatch clone job to background
        CloneRepoJob::dispatch($site);

        session()->flash('success', "Site '{$site->name}' created. Cloning repository in background...");

        $this->redirect(route('sites.index', ['site' => $site->id]), navigate: true);
    }

    public function render()
    {
        return view('livewire.sites.site-manager');
    }
}
