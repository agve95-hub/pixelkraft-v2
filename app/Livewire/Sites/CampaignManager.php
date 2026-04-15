<?php

namespace App\Livewire\Sites;

use App\Models\Announcement;
use App\Models\Campaign;
use App\Support\SiteAccess;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

/**
 * Manages popup campaigns and top-bar announcements for a site.
 *
 * Both types share the same "active if enabled + within schedule" logic.
 * Changes are reflected by the public /api/sites/{site}/active-campaigns
 * endpoint within its 60-second cache TTL — the save/delete methods
 * flush the cache key so the next request gets fresh data immediately.
 */
class CampaignManager extends Component
{
    public string $siteId;

    public string $tab = 'campaigns'; // campaigns | announcements

    // ── Campaign form ────────────────────────────────────────────────────────

    public ?string $editingCampaignId = null;

    public string $cf_name = '';

    public string $cf_headline = '';

    public string $cf_body = '';

    public string $cf_cta_text = '';

    public string $cf_cta_url = '';

    public string $cf_trigger = 'on_load';

    public string $cf_trigger_delay_ms = '0';

    public string $cf_starts_at = '';

    public string $cf_ends_at = '';

    public string $cf_priority = '0';

    public bool $cf_is_dismissible = true;

    public bool $cf_is_enabled = false;

    public string $cf_locale = 'en';

    // ── Announcement form ────────────────────────────────────────────────────

    public ?string $editingAnnouncementId = null;

    public string $af_message = '';

    public string $af_style = 'info';

    public string $af_cta_text = '';

    public string $af_cta_url = '';

    public string $af_placement = 'top_bar';

    public string $af_starts_at = '';

    public string $af_ends_at = '';

    public string $af_priority = '0';

    public bool $af_is_dismissible = true;

    public bool $af_is_enabled = false;

    public string $af_locale = 'en';

    public function mount(string $siteId): void
    {
        $this->siteId = $siteId;
        SiteAccess::findOrFail($this->siteId);
    }

    // ── Campaign CRUD ────────────────────────────────────────────────────────

    public function editCampaign(string $id): void
    {
        $site = SiteAccess::findOrFail($this->siteId);
        $campaign = Campaign::query()->where('site_id', $site->id)->whereKey($id)->firstOrFail();

        $this->editingCampaignId = $id;
        $this->cf_name = $campaign->name;
        $this->cf_headline = $campaign->headline;
        $this->cf_body = (string) $campaign->body;
        $this->cf_cta_text = (string) $campaign->cta_text;
        $this->cf_cta_url = (string) $campaign->cta_url;
        $this->cf_trigger = $campaign->trigger;
        $this->cf_trigger_delay_ms = (string) $campaign->trigger_delay_ms;
        $this->cf_starts_at = $campaign->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->cf_ends_at = $campaign->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->cf_priority = (string) $campaign->priority;
        $this->cf_is_dismissible = $campaign->is_dismissible;
        $this->cf_is_enabled = $campaign->is_enabled;
        $this->cf_locale = (string) $campaign->locale;
    }

    public function saveCampaign(): void
    {
        $site = SiteAccess::findOrFail($this->siteId);

        $validated = $this->validate([
            'cf_name' => 'required|string|max:255',
            'cf_headline' => 'required|string|max:255',
            'cf_body' => 'nullable|string|max:10000',
            'cf_cta_text' => 'nullable|string|max:100',
            'cf_cta_url' => 'nullable|url|max:2000',
            'cf_trigger' => 'required|in:on_load,on_scroll,on_exit,on_delay',
            'cf_trigger_delay_ms' => 'nullable|integer|min:0|max:60000',
            'cf_starts_at' => 'required|date',
            'cf_ends_at' => 'required|date|after:cf_starts_at',
            'cf_priority' => 'required|integer|min:0|max:255',
            'cf_locale' => 'nullable|string|max:10',
        ]);

        $data = [
            'site_id' => $site->id,
            'name' => $validated['cf_name'],
            'headline' => $validated['cf_headline'],
            'body' => $validated['cf_body'] ?: null,
            'cta_text' => $validated['cf_cta_text'] ?: null,
            'cta_url' => $validated['cf_cta_url'] ?: null,
            'trigger' => $validated['cf_trigger'],
            'trigger_delay_ms' => (int) $validated['cf_trigger_delay_ms'],
            'starts_at' => $validated['cf_starts_at'],
            'ends_at' => $validated['cf_ends_at'],
            'priority' => (int) $validated['cf_priority'],
            'is_dismissible' => $this->cf_is_dismissible,
            'is_enabled' => $this->cf_is_enabled,
            'locale' => $validated['cf_locale'] ?: 'en',
        ];

        if ($this->editingCampaignId) {
            Campaign::query()
                ->where('site_id', $site->id)
                ->whereKey($this->editingCampaignId)
                ->firstOrFail()
                ->update($data);
            session()->flash('success', 'Campaign updated.');
        } else {
            Campaign::create($data);
            session()->flash('success', 'Campaign created.');
        }

        $this->flushCache($site->id);
        $this->resetCampaignForm();
    }

    public function toggleCampaign(string $id): void
    {
        $site = SiteAccess::findOrFail($this->siteId);
        $campaign = Campaign::query()->where('site_id', $site->id)->whereKey($id)->firstOrFail();
        $campaign->update(['is_enabled' => ! $campaign->is_enabled]);
        $this->flushCache($site->id);
    }

    public function deleteCampaign(string $id): void
    {
        $site = SiteAccess::findOrFail($this->siteId);
        Campaign::query()->where('site_id', $site->id)->whereKey($id)->delete();
        $this->flushCache($site->id);
        session()->flash('success', 'Campaign deleted.');
        $this->resetCampaignForm();
    }

    // ── Announcement CRUD ────────────────────────────────────────────────────

    public function editAnnouncement(string $id): void
    {
        $site = SiteAccess::findOrFail($this->siteId);
        $announcement = Announcement::query()->where('site_id', $site->id)->whereKey($id)->firstOrFail();

        $this->editingAnnouncementId = $id;
        $this->af_message = $announcement->message;
        $this->af_style = $announcement->style;
        $this->af_cta_text = (string) $announcement->cta_text;
        $this->af_cta_url = (string) $announcement->cta_url;
        $this->af_placement = $announcement->placement;
        $this->af_starts_at = $announcement->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->af_ends_at = $announcement->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->af_priority = (string) $announcement->priority;
        $this->af_is_dismissible = $announcement->is_dismissible;
        $this->af_is_enabled = $announcement->is_enabled;
        $this->af_locale = (string) $announcement->locale;
    }

    public function saveAnnouncement(): void
    {
        $site = SiteAccess::findOrFail($this->siteId);

        $validated = $this->validate([
            'af_message' => 'required|string|max:500',
            'af_style' => 'required|in:info,warning,error,success,promo',
            'af_cta_text' => 'nullable|string|max:100',
            'af_cta_url' => 'nullable|url|max:2000',
            'af_placement' => 'required|in:top_bar,inline,floating',
            'af_starts_at' => 'required|date',
            'af_ends_at' => 'required|date|after:af_starts_at',
            'af_priority' => 'required|integer|min:0|max:255',
            'af_locale' => 'nullable|string|max:10',
        ]);

        $data = [
            'site_id' => $site->id,
            'message' => $validated['af_message'],
            'style' => $validated['af_style'],
            'cta_text' => $validated['af_cta_text'] ?: null,
            'cta_url' => $validated['af_cta_url'] ?: null,
            'placement' => $validated['af_placement'],
            'starts_at' => $validated['af_starts_at'],
            'ends_at' => $validated['af_ends_at'],
            'priority' => (int) $validated['af_priority'],
            'is_dismissible' => $this->af_is_dismissible,
            'is_enabled' => $this->af_is_enabled,
            'locale' => $validated['af_locale'] ?: 'en',
        ];

        if ($this->editingAnnouncementId) {
            Announcement::query()
                ->where('site_id', $site->id)
                ->whereKey($this->editingAnnouncementId)
                ->firstOrFail()
                ->update($data);
            session()->flash('success', 'Announcement updated.');
        } else {
            Announcement::create($data);
            session()->flash('success', 'Announcement created.');
        }

        $this->flushCache($site->id);
        $this->resetAnnouncementForm();
    }

    public function toggleAnnouncement(string $id): void
    {
        $site = SiteAccess::findOrFail($this->siteId);
        $announcement = Announcement::query()->where('site_id', $site->id)->whereKey($id)->firstOrFail();
        $announcement->update(['is_enabled' => ! $announcement->is_enabled]);
        $this->flushCache($site->id);
    }

    public function deleteAnnouncement(string $id): void
    {
        $site = SiteAccess::findOrFail($this->siteId);
        Announcement::query()->where('site_id', $site->id)->whereKey($id)->delete();
        $this->flushCache($site->id);
        session()->flash('success', 'Announcement deleted.');
        $this->resetAnnouncementForm();
    }

    public function render()
    {
        $site = SiteAccess::findOrFail($this->siteId);

        $campaigns = Campaign::query()
            ->where('site_id', $site->id)
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->get();

        $announcements = Announcement::query()
            ->where('site_id', $site->id)
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.sites.campaign-manager', [
            'site' => $site,
            'campaigns' => $campaigns,
            'announcements' => $announcements,
        ]);
    }

    private function resetCampaignForm(): void
    {
        $this->editingCampaignId = null;
        $this->reset(['cf_name', 'cf_headline', 'cf_body', 'cf_cta_text', 'cf_cta_url',
            'cf_trigger', 'cf_trigger_delay_ms', 'cf_starts_at', 'cf_ends_at',
            'cf_priority', 'cf_is_dismissible', 'cf_is_enabled', 'cf_locale']);
        $this->cf_trigger = 'on_load';
        $this->cf_trigger_delay_ms = '0';
        $this->cf_priority = '0';
        $this->cf_is_dismissible = true;
        $this->cf_locale = 'en';
    }

    private function resetAnnouncementForm(): void
    {
        $this->editingAnnouncementId = null;
        $this->reset(['af_message', 'af_style', 'af_cta_text', 'af_cta_url',
            'af_placement', 'af_starts_at', 'af_ends_at', 'af_priority',
            'af_is_dismissible', 'af_is_enabled', 'af_locale']);
        $this->af_style = 'info';
        $this->af_placement = 'top_bar';
        $this->af_priority = '0';
        $this->af_is_dismissible = true;
        $this->af_locale = 'en';
    }

    private function flushCache(string $siteId): void
    {
        Cache::forget("active-campaigns:{$siteId}");
    }
}
