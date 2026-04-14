<?php

namespace App\Livewire\Email;

use App\Models\FormSubmission;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class FormInbox extends Component
{
    use WithPagination;

    public ?string $siteId = null;

    public string $filter = 'unread'; // all|unread|spam

    private ?array $visibleSiteIdsCache = null;

    protected function visibleSiteIds(): array
    {
        if ($this->visibleSiteIdsCache === null) {
            $this->visibleSiteIdsCache = SiteAccess::query()->pluck('id')->all();
        }

        return $this->visibleSiteIdsCache;
    }

    protected function ensureVisibleSiteSelection(): void
    {
        if ($this->siteId && ! in_array($this->siteId, $this->visibleSiteIds(), true)) {
            abort(404);
        }
    }

    public function markRead(string $id): void
    {
        FormSubmission::query()
            ->whereKey($id)
            ->whereIn('site_id', $this->visibleSiteIds())
            ->update(['is_read' => true]);
    }

    public function markSpam(string $id): void
    {
        FormSubmission::query()
            ->whereKey($id)
            ->whereIn('site_id', $this->visibleSiteIds())
            ->update(['is_spam' => true, 'is_read' => true]);
    }

    public function delete(string $id): void
    {
        FormSubmission::query()
            ->whereKey($id)
            ->whereIn('site_id', $this->visibleSiteIds())
            ->delete();
    }

    public function render(): View
    {
        $this->ensureVisibleSiteSelection();

        $sites = SiteAccess::query()->where('is_active', true)->orderBy('name')->get();

        $query = FormSubmission::query()
            ->with('site')
            ->whereIn('site_id', $this->visibleSiteIds())
            ->when($this->siteId, fn ($q) => $q->where('site_id', $this->siteId));

        $query = match ($this->filter) {
            'unread' => $query->where('is_read', false)->where('is_spam', false),
            'spam' => $query->where('is_spam', true),
            default => $query->where('is_spam', false),
        };

        $submissions = $query->latest('created_at')->paginate(20);

        $counts = [
            'all' => FormSubmission::query()
                ->whereIn('site_id', $this->visibleSiteIds())
                ->when($this->siteId, fn ($q) => $q->where('site_id', $this->siteId))
                ->where('is_spam', false)
                ->count(),
            'unread' => FormSubmission::query()
                ->whereIn('site_id', $this->visibleSiteIds())
                ->when($this->siteId, fn ($q) => $q->where('site_id', $this->siteId))
                ->where('is_read', false)
                ->where('is_spam', false)
                ->count(),
            'spam' => FormSubmission::query()
                ->whereIn('site_id', $this->visibleSiteIds())
                ->when($this->siteId, fn ($q) => $q->where('site_id', $this->siteId))
                ->where('is_spam', true)
                ->count(),
        ];

        return view('livewire.email.form-inbox', [
            'submissions' => $submissions,
            'sites' => $sites,
            'counts' => $counts,
        ]);
    }
}
