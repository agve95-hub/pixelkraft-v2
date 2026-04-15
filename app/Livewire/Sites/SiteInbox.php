<?php

namespace App\Livewire\Sites;

use App\Models\SiteInboxMessage;
use App\Support\SiteAccess;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class SiteInbox extends Component
{
    public string $siteId;

    public ?string $selectedId = null;

    public bool $composerOpen = false;

    public string $composeTo = '';

    public string $composeSubject = '';

    public string $composeBody = '';

    public function mount(string $siteId): void
    {
        $this->siteId = $siteId;
        $site = SiteAccess::findOrFail($siteId);
        $this->composeTo = (string) ($site->client_email ?? '');
    }

    public function openComposer(): void
    {
        $site = SiteAccess::findOrFail($this->siteId);
        if ($this->composeTo === '') {
            $this->composeTo = (string) ($site->client_email ?? '');
        }
        $this->composerOpen = true;
    }

    public function closeComposer(): void
    {
        $this->composerOpen = false;
    }

    public function sendMessage(): void
    {
        $this->validate([
            'composeTo' => 'required|email',
            'composeSubject' => 'required|string|max:255',
            'composeBody' => 'required|string|max:50000',
        ]);

        $site = SiteAccess::findOrFail($this->siteId);

        $fromAddress = config('mail.from.address');
        $fromName = $site->name.' — '.config('app.name');

        try {
            Mail::raw($this->composeBody, function ($message) use ($fromAddress, $fromName) {
                $message->from($fromAddress, $fromName)
                    ->replyTo(auth()->user()->email, auth()->user()->name)
                    ->to($this->composeTo)
                    ->subject($this->composeSubject);
            });
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'Could not send email. Check mail configuration (MAIL_*) or application logs.');

            return;
        }

        SiteInboxMessage::create([
            'site_id' => $site->id,
            'user_id' => auth()->id(),
            'direction' => 'outbound',
            'from_email' => auth()->user()->email,
            'from_name' => auth()->user()->name,
            'to_email' => $this->composeTo,
            'subject' => $this->composeSubject,
            'body' => $this->composeBody,
            'is_read' => true,
            'source' => 'dashboard',
        ]);

        $this->reset('composeSubject', 'composeBody');
        $this->composerOpen = false;

        session()->flash('success', 'Message sent.');
    }

    public function selectMessage(string $id): void
    {
        $site = SiteAccess::findOrFail($this->siteId);

        if ($this->selectedId === $id) {
            $this->selectedId = null;

            return;
        }

        $this->selectedId = $id;

        $msg = SiteInboxMessage::query()
            ->where('site_id', $site->id)
            ->whereKey($id)
            ->first();

        if ($msg && $msg->direction === 'inbound' && ! $msg->is_read) {
            $msg->update(['is_read' => true]);
        }
    }

    public function render()
    {
        $site = SiteAccess::findOrFail($this->siteId);

        $messages = SiteInboxMessage::where('site_id', $site->id)
            ->latest('created_at')
            ->limit(100)
            ->get();

        // If the selected message was not loaded in the capped result set (e.g.
        // it's older than the 100-message window), fetch it individually so the
        // thread view still works correctly.
        $selected = null;
        if ($this->selectedId) {
            $selected = $messages->firstWhere('id', $this->selectedId)
                ?? SiteInboxMessage::query()
                    ->where('site_id', $site->id)
                    ->whereKey($this->selectedId)
                    ->first();
        }

        return view('livewire.sites.site-inbox', [
            'site' => $site,
            'messages' => $messages,
            'selected' => $selected,
        ]);
    }
}
