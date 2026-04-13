<?php

namespace App\Livewire\Sites;

use App\Models\Reminder;
use App\Support\SiteAccess;
use Livewire\Component;
use Livewire\WithPagination;

class ReminderManager extends Component
{
    use WithPagination;

    public string $siteId;

    public string $form_title = '';

    public ?string $form_due_date = null;

    public string $form_notes = '';

    public function mount(string $siteId): void
    {
        $this->siteId = $siteId;
        SiteAccess::findOrFail($this->siteId);
    }

    public function save(): void
    {
        $site = SiteAccess::findOrFail($this->siteId);

        $validated = $this->validate([
            'form_title' => 'required|string|max:255',
            'form_due_date' => 'nullable|date',
            'form_notes' => 'nullable|string|max:5000',
        ]);

        Reminder::create([
            'site_id' => $site->id,
            'title' => $validated['form_title'],
            'due_date' => $validated['form_due_date'] ?: null,
            'notes' => $validated['form_notes'] ?: null,
            'is_done' => false,
        ]);

        $this->reset(['form_title', 'form_due_date', 'form_notes']);
        session()->flash('success', 'Reminder added.');
        $this->resetPage();
    }

    public function toggleDone(string $id): void
    {
        $site = SiteAccess::findOrFail($this->siteId);

        $reminder = Reminder::query()
            ->whereKey($id)
            ->where('site_id', $site->id)
            ->firstOrFail();

        $reminder->update(['is_done' => ! $reminder->is_done]);
    }

    public function delete(string $id): void
    {
        $site = SiteAccess::findOrFail($this->siteId);

        Reminder::query()
            ->whereKey($id)
            ->where('site_id', $site->id)
            ->delete();

        session()->flash('success', 'Reminder removed.');
        $this->resetPage();
    }

    public function render()
    {
        $site = SiteAccess::findOrFail($this->siteId);

        $reminders = Reminder::query()
            ->where('site_id', $site->id)
            ->orderBy('is_done')
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->paginate(20);

        $openCount = Reminder::query()
            ->where('site_id', $site->id)
            ->where('is_done', false)
            ->count();

        return view('livewire.sites.reminder-manager', [
            'site' => $site,
            'reminders' => $reminders,
            'openCount' => $openCount,
        ]);
    }
}
