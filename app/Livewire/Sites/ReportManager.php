<?php

namespace App\Livewire\Sites;

use App\Models\Report;
use App\Support\SiteAccess;
use Livewire\Component;
use Livewire\WithPagination;

class ReportManager extends Component
{
    use WithPagination;

    public string $siteId;

    public string $form_title = '';

    public string $form_report_date = '';

    public string $form_summary = '';

    public function mount(string $siteId): void
    {
        $this->siteId = $siteId;
        SiteAccess::findOrFail($this->siteId);
        $this->form_report_date = now()->toDateString();
    }

    public function save(): void
    {
        $site = SiteAccess::findOrFail($this->siteId);

        $validated = $this->validate([
            'form_title' => 'required|string|max:255',
            'form_report_date' => 'required|date',
            'form_summary' => 'nullable|string|max:20000',
        ]);

        Report::create([
            'site_id' => $site->id,
            'title' => $validated['form_title'],
            'report_date' => $validated['form_report_date'],
            'summary' => $validated['form_summary'] ?: null,
        ]);

        $this->reset(['form_title', 'form_summary']);
        $this->form_report_date = now()->toDateString();
        session()->flash('success', 'Report saved.');
        $this->resetPage();
    }

    public function delete(string $id): void
    {
        $site = SiteAccess::findOrFail($this->siteId);

        Report::query()
            ->whereKey($id)
            ->where('site_id', $site->id)
            ->delete();

        session()->flash('success', 'Report removed.');
        $this->resetPage();
    }

    public function render()
    {
        $site = SiteAccess::findOrFail($this->siteId);

        $reports = Report::query()
            ->where('site_id', $site->id)
            ->orderByDesc('report_date')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('livewire.sites.report-manager', [
            'site' => $site,
            'reports' => $reports,
        ]);
    }
}
