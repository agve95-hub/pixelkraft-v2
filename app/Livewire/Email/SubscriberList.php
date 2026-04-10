<?php

namespace App\Livewire\Email;

use App\Models\NewsletterSubscriber;
use App\Models\Site;
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

    public function addSubscriber(): void
    {
        $this->validate([
            'newEmail' => 'required|email',
            'newName'  => 'nullable|string|max:255',
            'siteId'   => 'required',
        ]);

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
        NewsletterSubscriber::where('id', $id)->update(['status' => 'unsubscribed']);
    }

    public function resubscribe(string $id): void
    {
        NewsletterSubscriber::where('id', $id)->update(['status' => 'active']);
    }

    public function delete(string $id): void
    {
        NewsletterSubscriber::where('id', $id)->delete();
    }

    public function render(): View
    {
        $sites = Site::where('is_active', true)->orderBy('name')->get();

        $query = NewsletterSubscriber::query()
            ->with('site')
            ->when($this->siteId, fn ($q) => $q->where('site_id', $this->siteId))
            ->when($this->search, fn ($q) => $q->where('email', 'like', "%{$this->search}%")
                ->orWhere('name', 'like', "%{$this->search}%"));

        $subscribers = $query->latest()->paginate(25);

        $stats = [
            'active'       => NewsletterSubscriber::when($this->siteId, fn ($q) => $q->where('site_id', $this->siteId))->where('status', 'active')->count(),
            'unsubscribed' => NewsletterSubscriber::when($this->siteId, fn ($q) => $q->where('site_id', $this->siteId))->where('status', 'unsubscribed')->count(),
            'bounced'      => NewsletterSubscriber::when($this->siteId, fn ($q) => $q->where('site_id', $this->siteId))->where('status', 'bounced')->count(),
        ];

        return view('livewire.email.subscriber-list', [
            'subscribers' => $subscribers,
            'sites'       => $sites,
            'stats'       => $stats,
        ]);
    }
}
