<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <flux:select wire:model.live="siteId" size="sm" class="w-auto">
            <flux:select.option value="">All Sites</flux:select.option>
            @foreach ($sites as $site)
                <flux:select.option value="{{ $site->id }}">{{ $site->name }}</flux:select.option>
            @endforeach
        </flux:select>

        @unless ($showEditor)
            <flux:button wire:click="create" variant="primary" size="sm" icon="plus" :disabled="!$siteId">New Campaign</flux:button>
        @endunless
    </div>

    @if ($showEditor)
        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title>{{ $campaignId ? 'Edit Campaign' : 'New Campaign' }}</x-ui.card-title>
            </x-ui.card-header>
            <x-ui.card-content>
                <flux:field>
                    <flux:label>Subject line</flux:label>
                    <flux:input wire:model="subject" placeholder="Your newsletter subject..." />
                    <flux:error name="subject" />
                </flux:field>
                <flux:field>
                    <flux:label>Email body (HTML)</flux:label>
                    <flux:textarea wire:model="bodyHtml" rows="16" class="font-mono text-sm" spellcheck="false"
                        placeholder="<h1>Newsletter</h1><p>Your content here...</p>" />
                    <flux:error name="bodyHtml" />
                </flux:field>
                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Status</flux:label>
                        <flux:select wire:model.live="status">
                            <flux:select.option value="draft">Draft</flux:select.option>
                            <flux:select.option value="scheduled">Scheduled</flux:select.option>
                        </flux:select>
                    </flux:field>
                    @if ($status === 'scheduled')
                        <flux:field>
                            <flux:label>Send at</flux:label>
                            <flux:input type="datetime-local" wire:model="scheduledAt" />
                        </flux:field>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <flux:button wire:click="save" variant="primary">Save</flux:button>
                    <flux:button wire:click="cancel" variant="ghost">Cancel</flux:button>
                </div>
            </x-ui.card-content>
        </x-ui.card>
    @endif

    <div class="space-y-2">
        @forelse ($campaigns as $campaign)
            <x-ui.card>
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="truncate text-sm font-medium">{{ $campaign->subject }}</p>
                            @switch($campaign->status)
                                @case('sent') <x-ui.badge variant="success">Sent</x-ui.badge> @break
                                @case('sending') <x-ui.badge variant="warning">Sending</x-ui.badge> @break
                                @case('scheduled') <x-ui.badge variant="info">Scheduled</x-ui.badge> @break
                                @default <x-ui.badge>Draft</x-ui.badge>
                            @endswitch
                        </div>
                        <div class="mt-1 flex items-center gap-3">
                            <span class="text-xs text-zinc-500">{{ $campaign->site?->name }}</span>
                            <span class="font-mono text-[10px] text-zinc-600">{{ $campaign->created_at->diffForHumans() }}</span>
                            @if ($campaign->stats)
                                <span class="font-mono text-[10px] text-zinc-600">{{ $campaign->stats['sent'] ?? 0 }} sent</span>
                            @endif
                        </div>
                    </div>
                    <x-ui.button-group>
                        @if ($campaign->status === 'draft')
                            <flux:button wire:click="sendNow('{{ $campaign->id }}')" wire:confirm="Send this campaign now?" variant="primary" size="xs">Send Now</flux:button>
                        @endif
                        <x-ui.button wire:click="edit('{{ $campaign->id }}')" size="xs" variant="ghost">Edit</x-ui.button>
                        <x-ui.button wire:click="delete('{{ $campaign->id }}')" wire:confirm="Delete campaign?" size="xs" variant="ghost" class="text-red-400">Delete</x-ui.button>
                    </x-ui.button-group>
                </div>
            </x-ui.card>
        @empty
            <x-ui.empty icon="megaphone" title="No campaigns yet." />
        @endforelse
    </div>
</div>
