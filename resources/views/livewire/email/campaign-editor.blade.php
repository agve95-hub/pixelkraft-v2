<div class="space-y-4">
    <div class="flex items-center justify-between">
        <select wire:model.live="siteId" class="flux-input text-sm w-auto">
            <option value="">All Sites</option>
            @foreach ($sites as $site)
                <option value="{{ $site->id }}">{{ $site->name }}</option>
            @endforeach
        </select>

        @unless ($showEditor)
            <button wire:click="create" class="flux-btn-primary text-sm" @disabled(!$siteId)>New Campaign</button>
        @endunless
    </div>

    @if ($showEditor)
        <div class="flux-card space-y-4">
            <h3 class="text-sm font-semibold text-zinc-200">{{ $campaignId ? 'Edit Campaign' : 'New Campaign' }}</h3>

            <div>
                <label class="flux-label">Subject line</label>
                <input type="text" wire:model="subject" class="flux-input text-sm" placeholder="Your newsletter subject...">
                @error('subject') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="flux-label">Email body (HTML)</label>
                <textarea wire:model="bodyHtml" rows="16" class="flux-input text-sm resize-y mono" spellcheck="false" placeholder="<h1>Newsletter</h1><p>Your content here...</p>"></textarea>
                @error('bodyHtml') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="flux-label">Status</label>
                    <select wire:model.live="status" class="flux-input text-sm">
                        <option value="draft">Draft</option>
                        <option value="scheduled">Scheduled</option>
                    </select>
                </div>

                @if ($status === 'scheduled')
                    <div>
                        <label class="flux-label">Send at</label>
                        <input type="datetime-local" wire:model="scheduledAt" class="flux-input text-sm">
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <button wire:click="save" class="flux-btn-primary text-sm">Save</button>
                <button wire:click="cancel" class="flux-btn-ghost text-sm">Cancel</button>
            </div>
        </div>
    @endif

    {{-- Campaign list --}}
    <div class="space-y-2">
        @forelse ($campaigns as $campaign)
            <div class="flux-card-hover !p-4 flex items-center justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-medium text-zinc-200 truncate">{{ $campaign->subject }}</p>
                        @switch($campaign->status)
                            @case('sent') <span class="flux-badge-green !text-[10px]">Sent</span> @break
                            @case('sending') <span class="flux-badge-amber !text-[10px]">Sending</span> @break
                            @case('scheduled') <span class="flux-badge-blue !text-[10px]">Scheduled</span> @break
                            @default <span class="flux-badge bg-zinc-500/10 text-zinc-500 !text-[10px]">Draft</span>
                        @endswitch
                    </div>
                    <div class="flex items-center gap-3 mt-1">
                        <span class="text-xs text-zinc-500">{{ $campaign->site?->name }}</span>
                        <span class="mono text-[10px] text-zinc-600">{{ $campaign->created_at->diffForHumans() }}</span>
                        @if ($campaign->stats)
                            <span class="mono text-[10px] text-zinc-600">{{ $campaign->stats['sent'] ?? 0 }} sent</span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    @if ($campaign->status === 'draft')
                        <button wire:click="sendNow('{{ $campaign->id }}')" wire:confirm="Send this campaign now?" class="flux-btn-primary text-[10px] !px-2 !py-1">Send Now</button>
                    @endif
                    <button wire:click="edit('{{ $campaign->id }}')" class="flux-btn-ghost text-[10px] !px-2 !py-1">Edit</button>
                    <button wire:click="delete('{{ $campaign->id }}')" wire:confirm="Delete campaign?" class="text-[10px] text-red-400 hover:text-red-300 px-2 py-1">Del</button>
                </div>
            </div>
        @empty
            <div class="flux-card py-8 text-center text-sm text-zinc-500">No campaigns yet.</div>
        @endforelse
    </div>
</div>
