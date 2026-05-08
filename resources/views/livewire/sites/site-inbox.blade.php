<div class="space-y-5">
    <div class="flex items-center justify-between">
        <span class="text-sm text-zinc-400">{{ $filterCounts['all'] }} {{ Str::plural('message', $filterCounts['all']) }}</span>
        <flux:button type="button" wire:click="openComposer" variant="primary" icon="pencil-square">Compose</flux:button>
    </div>

    <x-ui.tabs>
        @foreach (['inbox' => 'Inbox', 'sent' => 'Sent', 'archived' => 'Archived', 'all' => 'All'] as $key => $label)
            <button type="button" wire:click="setFilter('{{ $key }}')" @class(['pk-ui-tab', 'is-active' => $filter === $key])>
                {{ $label }}
                <span class="font-mono text-[11px] text-zinc-500">{{ $filterCounts[$key] }}</span>
            </button>
        @endforeach
    </x-ui.tabs>

    <div class="grid items-stretch gap-4 lg:grid-cols-[minmax(320px,420px)_1fr]">
        {{-- Thread list --}}
        <div class="thread-list min-h-[420px]">
            @forelse ($messages as $message)
                <button
                    type="button"
                    wire:click="selectMessage('{{ $message->id }}')"
                    wire:key="msg-{{ $message->id }}"
                    @class([
                        'grid w-full grid-cols-[auto_1fr_auto] gap-3 border-b border-zinc-800/80 px-4 py-3 text-left transition last:border-b-0 hover:bg-zinc-800/30',
                        'bg-zinc-800/30' => $selectedId === $message->id,
                    ])
                >
                    <span class="mt-2 flex w-2 justify-center">
                        @if ($message->direction === 'inbound' && ! $message->is_read)
                            <span class="size-2 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.45)]"></span>
                        @endif
                    </span>
                    <span class="min-w-0">
                        <span @class(['block truncate text-sm', 'font-semibold text-zinc-100' => $message->direction === 'inbound' && ! $message->is_read, 'font-medium text-zinc-400' => $message->direction === 'outbound' || $message->is_read])>
                            {{ $message->listSenderLabel() }}
                        </span>
                        <span class="mt-0.5 block truncate text-sm text-zinc-300">{{ $message->subject }}</span>
                        <span class="mt-1 line-clamp-2 text-xs leading-relaxed text-zinc-500">{{ $message->previewText() }}</span>
                    </span>
                    <span class="text-right">
                        <time class="block font-mono text-[11px] text-zinc-500" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('M j') }}</time>
                        @if ($message->direction === 'outbound')
                            <x-ui.badge variant="info" class="mt-1">Sent</x-ui.badge>
                        @endif
                    </span>
                </button>
            @empty
                <x-ui.empty icon="inbox" title="No {{ $filter === 'all' ? '' : $filter }} messages">
                    <x-ui.button type="button" wire:click="openComposer" variant="secondary" size="sm">Compose a message</x-ui.button>
                </x-ui.empty>
            @endforelse
        </div>

        {{-- Message detail --}}
        <x-ui.card class="min-h-[420px]">
            @if ($selected)
                <x-ui.card-header>
                    <div class="min-w-0">
                        <x-ui.card-title class="truncate">{{ $selected->subject }}</x-ui.card-title>
                        <x-ui.card-description>{{ $threadMessages->count() }} {{ Str::plural('message', $threadMessages->count()) }} in thread</x-ui.card-description>
                    </div>
                    <x-ui.button-group>
                        <flux:button type="button" wire:click="replyTo('{{ $selected->id }}')" size="sm" variant="outline" icon="arrow-uturn-left">Reply</flux:button>
                        <flux:button type="button" wire:click="archiveMessage('{{ $selected->id }}')" size="sm" variant="ghost">
                            {{ $selected->is_archived ? 'Unarchive' : 'Archive' }}
                        </flux:button>
                    </x-ui.button-group>
                </x-ui.card-header>

                <div class="space-y-3">
                    @foreach ($threadMessages as $threadMessage)
                        <article wire:key="thread-{{ $threadMessage->id }}" @class([
                            'overflow-hidden rounded-lg border',
                            'border-emerald-400/30 bg-emerald-400/[0.03]' => $threadMessage->id === $selected->id,
                            'border-zinc-800 bg-zinc-950/30' => $threadMessage->id !== $selected->id,
                        ])>
                            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-zinc-800/80 px-4 py-3">
                                <div>
                                    <p class="text-sm font-medium">{{ $threadMessage->direction === 'outbound' ? 'You' : ($threadMessage->from_name ?: $threadMessage->from_email ?: 'Unknown') }}</p>
                                    <p class="mt-0.5 text-xs text-zinc-500">
                                        @if ($threadMessage->direction === 'outbound') To {{ $threadMessage->to_email ?: 'client' }}
                                        @else From {{ $threadMessage->from_email ?: 'unknown sender' }} @endif
                                    </p>
                                </div>
                                <time class="font-mono text-[11px] text-zinc-500" datetime="{{ $threadMessage->created_at->toIso8601String() }}">{{ $threadMessage->created_at->format('M j, Y H:i') }}</time>
                            </div>
                            <div class="whitespace-pre-wrap px-4 py-4 text-sm leading-6 text-zinc-300">{{ $threadMessage->body }}</div>
                        </article>
                    @endforeach
                </div>
            @else
                <x-ui.empty icon="envelope-open" title="Select a message" description="Thread details, replies, and archive actions appear here." class="min-h-[360px]" />
            @endif
        </x-ui.card>
    </div>

    {{-- API endpoint info --}}
    <x-ui.card>
        <x-ui.card-header>
            <x-ui.card-title>Inbound email API</x-ui.card-title>
        </x-ui.card-header>
        <p class="text-xs text-zinc-500">POST JSON to ingest replies or forwarded mail. Optional Bearer token: <code class="rounded bg-zinc-800 px-1 py-0.5 font-mono text-[10px] text-emerald-400">INBOX_INBOUND_SECRET</code></p>
        <code class="mt-2 block break-all rounded-lg bg-zinc-950 px-3 py-2 font-mono text-[11px] text-emerald-400/80">POST {{ url('/api/inbox') }}/{{ $site->slug }}</code>
    </x-ui.card>

    @if ($composerOpen)
        <flux:modal name="inbox-composer" class="max-w-lg !bg-zinc-900" :show="true">
            <div class="space-y-4">
                <div>
                    <p class="pk-page-title">{{ $replyingToId ? 'Reply' : 'New message' }}</p>
                    <p class="pk-page-sub mt-1">Sends with your configured mailer and stores the message in this thread.</p>
                </div>
                <div class="space-y-3">
                    <flux:field>
                        <flux:label>To</flux:label>
                        <flux:input type="email" wire:model="composeTo" placeholder="client@example.com" class="font-mono text-sm" />
                        <flux:error name="composeTo" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Subject</flux:label>
                        <flux:input wire:model="composeSubject" placeholder="Subject" />
                        <flux:error name="composeSubject" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Message</flux:label>
                        <flux:textarea wire:model="composeBody" rows="9" placeholder="Write your message..." class="min-h-[220px] font-sans text-sm" />
                        <flux:error name="composeBody" />
                    </flux:field>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <flux:button type="button" wire:click="closeComposer" variant="ghost">Cancel</flux:button>
                    <flux:button type="button" wire:click="sendMessage" variant="primary">Send</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
