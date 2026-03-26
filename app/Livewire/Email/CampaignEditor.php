<?php

namespace App\Livewire\Email;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\Site;
use Livewire\Component;

class CampaignEditor extends Component
{
    public ?string $siteId = null;
    public ?string $campaignId = null;

    public string $subject = '';
    public string $bodyHtml = '';
    public string $status = 'draft';
    public ?string $scheduledAt = null;

    public bool $showEditor = false;

    public function mount(): void
    {
        if ($this->campaignId) {
            $this->loadCampaign($this->campaignId);
        }
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showEditor = true;
    }

    public function edit(string $id): void
    {
        $this->loadCampaign($id);
        $this->showEditor = true;
    }

    public function save(): void
    {
        $this->validate([
            'siteId'  => 'required',
            'subject' => 'required|string|max:255',
            'bodyHtml' => 'required|string',
            'status'  => 'required|in:draft,scheduled',
        ]);

        $data = [
            'site_id'      => $this->siteId,
            'subject'      => $this->subject,
            'body_html'    => $this->bodyHtml,
            'status'       => $this->status,
            'scheduled_at' => $this->status === 'scheduled' ? $this->scheduledAt : null,
        ];

        if ($this->campaignId) {
            NewsletterCampaign::findOrFail($this->campaignId)->update($data);
        } else {
            $campaign = NewsletterCampaign::create($data);
            $this->campaignId = $campaign->id;
        }

        session()->flash('success', 'Campaign saved.');
    }

    public function sendNow(string $id): void
    {
        $campaign = NewsletterCampaign::findOrFail($id);

        $subscriberCount = NewsletterSubscriber::where('site_id', $campaign->site_id)
            ->where('status', 'active')
            ->count();

        $campaign->update([
            'status'  => 'sending',
            'stats'   => ['queued' => $subscriberCount, 'sent' => 0, 'opened' => 0, 'clicked' => 0, 'bounced' => 0],
        ]);

        // The SendCampaigns artisan command picks this up
        session()->flash('success', "Sending to {$subscriberCount} subscribers...");
    }

    public function delete(string $id): void
    {
        NewsletterCampaign::findOrFail($id)->delete();
        session()->flash('success', 'Campaign deleted.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render()
    {
        $sites = Site::where('is_active', true)->orderBy('name')->get();

        $campaigns = NewsletterCampaign::query()
            ->with('site')
            ->when($this->siteId, fn ($q) => $q->where('site_id', $this->siteId))
            ->latest()
            ->limit(20)
            ->get();

        return view('livewire.email.campaign-editor', [
            'sites'     => $sites,
            'campaigns' => $campaigns,
        ]);
    }

    private function loadCampaign(string $id): void
    {
        $campaign = NewsletterCampaign::findOrFail($id);
        $this->campaignId = $id;
        $this->siteId = $campaign->site_id;
        $this->subject = $campaign->subject;
        $this->bodyHtml = $campaign->body_html;
        $this->status = $campaign->status === 'sent' ? 'draft' : $campaign->status;
        $this->scheduledAt = $campaign->scheduled_at?->format('Y-m-d\TH:i');
    }

    private function resetForm(): void
    {
        $this->campaignId = null;
        $this->subject = '';
        $this->bodyHtml = '';
        $this->status = 'draft';
        $this->scheduledAt = null;
        $this->showEditor = false;
    }
}
