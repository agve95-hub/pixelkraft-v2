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

    public string $screen = 'index';

    public ?string $activeReportId = null;

    public string $form_title = '';

    public string $form_report_date = '';

    public string $form_summary = '';

    public string $form_status = 'draft';

    /** @var array<int, array{type: string, title: string, items: array<int, string>}> */
    public array $form_sections = [];

    /** @var array<int, string> */
    public array $form_next_steps = [];

    public function mount(string $siteId): void
    {
        $this->siteId = $siteId;
        SiteAccess::findOrFail($this->siteId);
        $this->resetCreateForm();
    }

    public function startCreate(): void
    {
        $this->resetCreateForm();
        $this->screen = 'create';
        $this->activeReportId = null;
    }

    public function cancelCreate(): void
    {
        $this->screen = 'index';
        $this->activeReportId = null;
        $this->resetCreateForm();
    }

    public function openReport(string $id): void
    {
        $site = SiteAccess::findOrFail($this->siteId);

        $report = Report::query()
            ->where('site_id', $site->id)
            ->whereKey($id)
            ->firstOrFail();

        $this->activeReportId = $report->id;
        $this->screen = 'show';
    }

    public function backToList(): void
    {
        $this->screen = 'index';
        $this->activeReportId = null;
    }

    public function resetCreateForm(): void
    {
        $this->form_title = now()->format('F Y').' report';
        $this->form_report_date = now()->toDateString();
        $this->form_summary = '';
        $this->form_status = 'draft';
        $this->form_sections = [
            ['type' => 'development', 'title' => 'Development', 'items' => ['']],
            ['type' => 'deployment', 'title' => 'Deployment & Infrastructure', 'items' => ['']],
            ['type' => 'seo', 'title' => 'SEO', 'items' => ['']],
        ];
        $this->form_next_steps = [''];
    }

    public function addSection(): void
    {
        $this->form_sections[] = ['type' => 'general', 'title' => 'New section', 'items' => ['']];
    }

    public function removeSection(int $index): void
    {
        if (count($this->form_sections) <= 1) {
            return;
        }

        unset($this->form_sections[$index]);
        $this->form_sections = array_values($this->form_sections);
    }

    public function addSectionItem(int $sectionIndex): void
    {
        if (! isset($this->form_sections[$sectionIndex])) {
            return;
        }

        $this->form_sections[$sectionIndex]['items'][] = '';
    }

    public function removeSectionItem(int $sectionIndex, int $itemIndex): void
    {
        if (! isset($this->form_sections[$sectionIndex]['items'][$itemIndex])) {
            return;
        }

        if (count($this->form_sections[$sectionIndex]['items']) <= 1) {
            $this->form_sections[$sectionIndex]['items'][0] = '';

            return;
        }

        unset($this->form_sections[$sectionIndex]['items'][$itemIndex]);
        $this->form_sections[$sectionIndex]['items'] = array_values($this->form_sections[$sectionIndex]['items']);
    }

    public function addNextStep(): void
    {
        $this->form_next_steps[] = '';
    }

    public function removeNextStep(int $index): void
    {
        if (count($this->form_next_steps) <= 1) {
            $this->form_next_steps[0] = '';

            return;
        }

        unset($this->form_next_steps[$index]);
        $this->form_next_steps = array_values($this->form_next_steps);
    }

    public function save(): void
    {
        $site = SiteAccess::findOrFail($this->siteId);

        $validated = $this->validate([
            'form_title' => 'required|string|max:255',
            'form_report_date' => 'required|date',
            'form_summary' => 'nullable|string|max:20000',
            'form_status' => 'required|string|in:draft,sent',
            'form_sections' => 'array|max:12',
            'form_sections.*.type' => 'nullable|string|max:64',
            'form_sections.*.title' => 'nullable|string|max:255',
            'form_sections.*.items' => 'array|max:20',
            'form_sections.*.items.*' => 'nullable|string|max:1000',
            'form_next_steps' => 'array|max:20',
            'form_next_steps.*' => 'nullable|string|max:1000',
        ]);

        $sections = collect($validated['form_sections'] ?? [])
            ->map(function (array $section): ?array {
                $items = collect($section['items'] ?? [])
                    ->map(fn ($item) => trim((string) $item))
                    ->filter()
                    ->values()
                    ->all();

                $title = trim((string) ($section['title'] ?? ''));

                if ($title === '' && $items === []) {
                    return null;
                }

                return [
                    'type' => trim((string) ($section['type'] ?? 'general')) ?: 'general',
                    'title' => $title !== '' ? $title : 'Section',
                    'items' => $items,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $nextSteps = collect($validated['form_next_steps'] ?? [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();

        Report::create([
            'site_id' => $site->id,
            'title' => $validated['form_title'],
            'report_date' => $validated['form_report_date'],
            'summary' => $validated['form_summary'] ?: null,
            'meta' => [
                'status' => $validated['form_status'],
                'sections' => $sections,
                'next_steps' => $nextSteps,
            ],
        ]);

        $this->screen = 'index';
        $this->resetCreateForm();
        session()->flash('success', 'Report saved.');
        $this->resetPage();
    }

    public function markSent(string $id): void
    {
        $site = SiteAccess::findOrFail($this->siteId);

        $report = Report::query()
            ->whereKey($id)
            ->where('site_id', $site->id)
            ->firstOrFail();

        $meta = $report->meta ?? [];
        $meta['status'] = 'sent';
        $meta['sent_at'] = now()->toIso8601String();
        $report->update(['meta' => $meta]);

        session()->flash('success', 'Report marked as sent.');
    }

    public function delete(string $id): void
    {
        $site = SiteAccess::findOrFail($this->siteId);

        Report::query()
            ->whereKey($id)
            ->where('site_id', $site->id)
            ->delete();

        if ($this->activeReportId === $id) {
            $this->backToList();
        }

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

        $activeReport = $this->activeReportId
            ? Report::query()
                ->where('site_id', $site->id)
                ->whereKey($this->activeReportId)
                ->first()
            : null;

        return view('livewire.sites.report-manager', [
            'site' => $site,
            'reports' => $reports,
            'activeReport' => $activeReport,
        ]);
    }
}
