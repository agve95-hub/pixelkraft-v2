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

        // Single query with conditional aggregation instead of 3 separate COUNT queries.
        $countRow = FormSubmission::query()
            ->whereIn('site_id', $this->visibleSiteIds())
            ->when($this->siteId, fn ($q) => $q->where('site_id', $this->siteId))
            ->selectRaw('
                SUM(CASE WHEN is_spam = 0 THEN 1 ELSE 0 END) as all_count,
                SUM(CASE WHEN is_read = 0 AND is_spam = 0 THEN 1 ELSE 0 END) as unread_count,
                SUM(CASE WHEN is_spam = 1 THEN 1 ELSE 0 END) as spam_count
            ')
            ->first();
        $counts = [
            'all' => (int) ($countRow->all_count ?? 0),
            'unread' => (int) ($countRow->unread_count ?? 0),
            'spam' => (int) ($countRow->spam_count ?? 0),
        ];

        return view('livewire.email.form-inbox', [
            'submissions' => $submissions,
            'sites' => $sites,
            'counts' => $counts,
        ]);
    }
}
