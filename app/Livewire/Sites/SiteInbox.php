<?php

namespace App\Livewire\Sites;

use App\Models\SiteInboxMessage;
use App\Support\SiteAccess;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;

class SiteInbox extends Component
{
    public string $siteId;

    public ?string $selectedId = null;

    public bool $composerOpen = false;

    public string $filter = 'inbox';

    public ?string $replyingToId = null;

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
        $this->replyingToId = null;
        $this->composeSubject = '';
        $this->composeBody = '';
        $this->composerOpen = true;
    }

    public function closeComposer(): void
    {
        $this->composerOpen = false;
        $this->replyingToId = null;
    }

    public function setFilter(string $filter): void
    {
        if (! in_array($filter, ['inbox', 'sent', 'archived', 'all'], true)) {
            return;
        }

        $this->filter = $filter;
        $this->selectedId = null;
    }

    public function replyTo(string $id): void
    {
        $site = SiteAccess::findOrFail($this->siteId);
        $message = SiteInboxMessage::query()
            ->where('site_id', $site->id)
            ->whereKey($id)
            ->firstOrFail();

        $this->replyingToId = $message->id;
        $this->composeTo = $message->direction === 'outbound'
            ? (string) $message->to_email
            : (string) $message->from_email;
        $this->composeSubject = Str::startsWith((string) $message->subject, 'Re:')
            ? (string) $message->subject
            : 'Re: '.(string) $message->subject;
        $this->composeBody = "\n\n> ".str_replace("\n", "\n> ", (string) $message->body);
        $this->composerOpen = true;
    }

    public function archiveMessage(string $id): void
    {
        $site = SiteAccess::findOrFail($this->siteId);
        $message = SiteInboxMessage::query()
            ->where('site_id', $site->id)
            ->whereKey($id)
            ->firstOrFail();

        $message->update(['is_archived' => ! $message->is_archived]);

        if ($this->filter !== 'all') {
            $this->selectedId = null;
        }
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
        $this->replyingToId = null;

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

        $baseQuery = SiteInboxMessage::where('site_id', $site->id);

        match ($this->filter) {
            'sent' => $baseQuery->where('direction', 'outbound')->where('is_archived', false),
            'archived' => $baseQuery->where('is_archived', true),
            'all' => $baseQuery,
            default => $baseQuery->where('direction', 'inbound')->where('is_archived', false),
        };

        $messages = $baseQuery->latest('created_at')->limit(100)->get();

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

        $threadMessages = collect();
        if ($selected) {
            $threadKey = $this->threadKey($selected);
            $threadMessages = SiteInboxMessage::query()
                ->where('site_id', $site->id)
                ->orderBy('created_at')
                ->get()
                ->filter(fn (SiteInboxMessage $message) => $this->threadKey($message) === $threadKey)
                ->values();
        }

        return view('livewire.sites.site-inbox', [
            'site' => $site,
            'messages' => $messages,
            'selected' => $selected,
            'threadMessages' => $threadMessages,
            'filterCounts' => [
                'inbox' => SiteInboxMessage::where('site_id', $site->id)->where('direction', 'inbound')->where('is_archived', false)->count(),
                'sent' => SiteInboxMessage::where('site_id', $site->id)->where('direction', 'outbound')->where('is_archived', false)->count(),
                'archived' => SiteInboxMessage::where('site_id', $site->id)->where('is_archived', true)->count(),
                'all' => SiteInboxMessage::where('site_id', $site->id)->count(),
            ],
        ]);
    }

    private function threadKey(SiteInboxMessage $message): string
    {
        $subject = Str::lower((string) $message->subject);
        $subject = preg_replace('/^(re|fw|fwd):\s*/i', '', $subject) ?: $subject;

        return trim($subject);
    }
}
