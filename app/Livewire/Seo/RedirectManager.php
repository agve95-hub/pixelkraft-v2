<?php

namespace App\Livewire\Seo;

use App\Models\Redirect;
use App\Models\Site;
use App\Services\NginxConfigService;
use Livewire\Component;

class RedirectManager extends Component
{
    public string $siteId;

    public string $fromPath = '';
    public string $toPath = '';
    public int $statusCode = 301;
    public ?string $editingId = null;

    public function save(): void
    {
        $this->validate([
            'fromPath'   => 'required|string|max:500|starts_with:/',
            'toPath'     => 'required|string|max:500',
            'statusCode' => 'required|in:301,302',
        ]);

        if ($this->editingId) {
            $redirect = Redirect::findOrFail($this->editingId);
            $redirect->update([
                'from_path'   => $this->fromPath,
                'to_path'     => $this->toPath,
                'status_code' => $this->statusCode,
            ]);
        } else {
            Redirect::create([
                'site_id'     => $this->siteId,
                'from_path'   => $this->fromPath,
                'to_path'     => $this->toPath,
                'status_code' => $this->statusCode,
            ]);
        }

        $this->regenerateNginx();
        $this->resetForm();
        session()->flash('success', 'Redirect saved.');
    }

    public function edit(string $id): void
    {
        $redirect = Redirect::findOrFail($id);
        $this->editingId = $id;
        $this->fromPath = $redirect->from_path;
        $this->toPath = $redirect->to_path;
        $this->statusCode = $redirect->status_code;
    }

    public function toggle(string $id): void
    {
        $redirect = Redirect::findOrFail($id);
        $redirect->update(['is_active' => ! $redirect->is_active]);
        $this->regenerateNginx();
    }

    public function delete(string $id): void
    {
        Redirect::findOrFail($id)->delete();
        $this->regenerateNginx();
        session()->flash('success', 'Redirect deleted.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render()
    {
        $redirects = Redirect::where('site_id', $this->siteId)
            ->orderBy('from_path')
            ->get();

        return view('livewire.seo.redirect-manager', [
            'redirects' => $redirects,
        ]);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->fromPath = '';
        $this->toPath = '';
        $this->statusCode = 301;
    }

    private function regenerateNginx(): void
    {
        try {
            $site = Site::findOrFail($this->siteId);
            if ($site->nginx_conf_path) {
                app(NginxConfigService::class)->generateConfig($site);
                app(NginxConfigService::class)->reloadNginx();
            }
        } catch (\Throwable $e) {
            // Non-fatal — nginx will pick up changes on next deploy
        }
    }
}
