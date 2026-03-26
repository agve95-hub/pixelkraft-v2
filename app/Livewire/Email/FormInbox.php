<?php

namespace App\Livewire\Email;

use App\Models\FormSubmission;
use App\Models\Site;
use Livewire\Component;
use Livewire\WithPagination;

class FormInbox extends Component
{
    use WithPagination;

    public ?string $siteId = null;
    public string $filter = 'unread'; // all|unread|spam

    public function markRead(string $id): void
    {
        FormSubmission::where('id', $id)->update(['is_read' => true]);
    }

    public function markSpam(string $id): void
    {
        FormSubmission::where('id', $id)->update(['is_spam' => true, 'is_read' => true]);
    }

    public function delete(string $id): void
    {
        FormSubmission::where('id', $id)->delete();
    }

    public function render()
    {
        $sites = Site::where('is_active', true)->orderBy('name')->get();

        $query = FormSubmission::query()
            ->with('site')
            ->when($this->siteId, fn ($q) => $q->where('site_id', $this->siteId));

        $query = match ($this->filter) {
            'unread' => $query->where('is_read', false)->where('is_spam', false),
            'spam'   => $query->where('is_spam', true),
            default  => $query->where('is_spam', false),
        };

        $submissions = $query->latest('created_at')->paginate(20);

        $counts = [
            'all'    => FormSubmission::when($this->siteId, fn ($q) => $q->where('site_id', $this->siteId))->where('is_spam', false)->count(),
            'unread' => FormSubmission::when($this->siteId, fn ($q) => $q->where('site_id', $this->siteId))->where('is_read', false)->where('is_spam', false)->count(),
            'spam'   => FormSubmission::when($this->siteId, fn ($q) => $q->where('site_id', $this->siteId))->where('is_spam', true)->count(),
        ];

        return view('livewire.email.form-inbox', [
            'submissions' => $submissions,
            'sites'       => $sites,
            'counts'      => $counts,
        ]);
    }
}
