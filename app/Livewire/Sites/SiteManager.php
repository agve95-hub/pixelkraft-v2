<?php

namespace App\Livewire\Sites;

use App\Models\Site;
use Illuminate\Support\Str;
use Livewire\Component;

class SiteManager extends Component
{
    public string $name = '';
    public string $repoUrl = '';
    public string $branch = 'main';

    protected $rules = [
        'name'    => 'required|string|max:255',
        'repoUrl' => 'required|url|regex:/github\.com/',
        'branch'  => 'required|string|max:100',
    ];

    public function create(): void
    {
        $this->validate();

        $site = Site::create([
            'name'     => $this->name,
            'slug'     => Str::slug($this->name),
            'repo_url' => $this->repoUrl,
            'branch'   => $this->branch,
        ]);

        session()->flash('success', "Site '{$site->name}' created. Cloning repository...");

        $this->redirect(route('sites.show', $site), navigate: true);
    }

    public function render()
    {
        return view('livewire.sites.site-manager');
    }
}
