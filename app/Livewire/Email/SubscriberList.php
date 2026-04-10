<?php

namespace App\Livewire\Email;

use App\Models\NewsletterSubscriber;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class SubscriberList extends Component
{
    use WithPagination;

    public ?string $siteId = null;
    public string $search = '';

    // Add form
    public string $newEmail = '';
    public string $newName = '';

    private ?array $visibleSiteIdsCache = null;

    protected function visibleSiteIds(): array
    {
        if ($this->visibleSiteIdsCache === null) {
            $this->visibleSiteIdsCache = SiteAccess::query()->pluck('id')->all();
        }

        return $this->visibleSiteIdsCache;
    }

    public function addSubscriber(): void
    {
        $this->validate([
            'newEmail' => 'required|email',
            'newName'  => 'nullable|string|max:255',
            'siteId'   => 'required',
        ]);

        abort_unless(in_array($this->siteId, $this->visibleSiteIds(), true), 404);

        NewsletterSubscriber::updateOrCreate(
            ['site_id' => $this->siteId, 'email' => $this->newEmail],
            ['name' => $this->newName ?: null, 'status' => 'active']
        );

        $this->newEmail = '';
        $this->newName = '';
        session()->flash('success', 'Subscriber added.');
    }

    public function unsubscribe(string $id): void
    {
        NewsletterSubscriber::query()
            ->whereKey($id)
            ->whereIn('site_id', $this->visibleSiteIds())
            ->update(['status' => 'unsubscribed']);
    }

    public function resubscribe(string $id): void
    {
        NewsletterSubscriber::query()
            ->whereKey($id)
            ->whereIn('site_id', $this->visibleSiteIds())
            ->update(['status' => 'active']);
    }

    public function delete(string $id): void
    {
        NewsletterSubscriber::query()
            ->whereKey($id)
            ->whereIn('site_id', $this->visibleSiteIds())
            ->delete();
    }

    public function render(): View
    {
        if ($this->siteId && ! in_array($this->siteId, $this->visibleSiteIds(), true)) {
            abort(404);
        }

        $sites = SiteAccess::query()->where('is_active', true)->orderBy('name')->get();

        $query = NewsletterSubscriber::query()
            ->with('site')
            ->whereIn('site_id', $this->visibleSiteIds())
            ->when($this->siteId, fn ($q) => $q->where('site_id', $this->siteId))
            ->when($this->search, function ($q) {
                $q->where(function ($search): void {
                    $search
                        ->where('email', 'like', "%{$this->search}%")
                        ->orWhere('name', 'like', "%{$this->search}%");
                });
            });

        $subscribers = $query->latest()->paginate(25);

        $stats = [
            'active'       => NewsletterSubscriber::query()
                ->whereIn('site_id', $this->visibleSiteIds())
                ->when($this->siteId, fn ($q) => $q->where('site_id', $this->siteId))
                ->where('status', 'active')
                ->count(),
            'unsubscribed' => NewsletterSubscriber::query()
                ->whereIn('site_id', $this->visibleSiteIds())
                ->when($this->siteId, fn ($q) => $q->where('site_id', $this->siteId))
                ->where('status', 'unsubscribed')
                ->count(),
            'bounced'      => NewsletterSubscriber::query()
                ->whereIn('site_id', $this->visibleSiteIds())
                ->when($this->siteId, fn ($q) => $q->where('site_id', $this->siteId))
                ->where('status', 'bounced')
                ->count(),
        ];

        return view('livewire.email.subscriber-list', [
            'subscribers' => $subscribers,
            'sites'       => $sites,
            'stats'       => $stats,
        ]);
    }
}
