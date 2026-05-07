<div class="max-w-6xl space-y-6">
    <div class="pk-page-head">
        <div>
            <a href="{{ route('sites.show', $site) }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm text-zinc-500 transition hover:text-zinc-300">
                <span aria-hidden="true">&larr;</span>
                {{ $site->name }}
            </a>
            <h1 class="pk-page-title">Inbox</h1>
            <p class="pk-page-sub">{{ $site->clientDisplayName() }} &middot; {{ $filterCounts['all'] }} {{ Str::plural('message', $filterCounts['all']) }}</p>
        </div>
        <flux:button type="button" wire:click="openComposer" variant="primary" icon="pencil-square" class="!bg-emerald-400 hover:!bg-emerald-300 !text-zinc-950 dark:!text-zinc-950">
            Compose
        </flux:button>
    </div>

    <div class="tab-bar mb-1">
        @foreach (['inbox' => 'Inbox', 'sent' => 'Sent', 'archived' => 'Archived', 'all' => 'All'] as $key => $label)
            <button type="button" wire:click="setFilter('{{ $key }}')" @class(['tab', 'active' => $filter === $key])>
                {{ $label }}
                <span class="font-mono text-[11px] text-zinc-500">{{ $filterCounts[$key] }}</span>
            </button>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(320px,420px)_1fr]">
        <div class="thread-list">
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
                        <span @class([
                            'block truncate text-sm',
                            'font-semibold text-zinc-100' => $message->direction === 'inbound' && ! $message->is_read,
                            'font-medium text-zinc-400' => $message->direction === 'outbound' || $message->is_read,
                        ])>
                            {{ $message->listSenderLabel() }}
                        </span>
                        <span class="mt-0.5 block truncate text-sm text-zinc-300">{{ $message->subject }}</span>
                        <span class="mt-1 line-clamp-2 text-xs leading-relaxed text-zinc-500">{{ $message->previewText() }}</span>
                    </span>
                    <span class="text-right">
                        <time class="block font-mono text-[11px] text-zinc-500" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('M j') }}</time>
                        @if ($message->direction === 'outbound')
                            <span class="mt-1 inline-flex rounded bg-blue-500/10 px-1.5 py-0.5 text-[10px] font-medium text-blue-400">Sent</span>
                        @endif
                    </span>
                </button>
            @empty
                <div class="empty">
                    <div class="empty-icon"><flux:icon name="inbox" class="size-4" /></div>
                    <p>No {{ $filter === 'all' ? '' : $filter }} messages</p>
                    <button type="button" wire:click="openComposer" class="text-sm font-medium text-emerald-400 hover:text-emerald-300">Compose a message</button>
                </div>
            @endforelse
        </div>

        <section class="dash-card min-h-[420px]">
            @if ($selected)
                <div class="dash-card-head">
                    <div class="min-w-0">
                        <p class="dash-card-title truncate">{{ $selected->subject }}</p>
                        <p class="mt-1 text-xs text-zinc-500">
                            {{ $threadMessages->count() }} {{ Str::plural('message', $threadMessages->count()) }} in thread
                        </p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <flux:button type="button" wire:click="replyTo('{{ $selected->id }}')" size="sm" variant="subtle" icon="arrow-uturn-left">Reply</flux:button>
                        <flux:button type="button" wire:click="archiveMessage('{{ $selected->id }}')" size="sm" variant="subtle">
                            {{ $selected->is_archived ? 'Unarchive' : 'Archive' }}
                        </flux:button>
                    </div>
                </div>

                <div class="space-y-3">
                    @foreach ($threadMessages as $threadMessage)
                        <article wire:key="thread-{{ $threadMessage->id }}" @class([
                            'overflow-hidden rounded-lg border',
                            'border-emerald-400/30 bg-emerald-400/[0.03]' => $threadMessage->id === $selected->id,
                            'border-zinc-800 bg-zinc-950/30' => $threadMessage->id !== $selected->id,
                        ])>
                            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-zinc-800/80 px-4 py-3">
                                <div>
                                    <p class="text-sm font-medium text-zinc-100">{{ $threadMessage->direction === 'outbound' ? 'You' : ($threadMessage->from_name ?: $threadMessage->from_email ?: 'Unknown') }}</p>
                                    <p class="mt-0.5 text-xs text-zinc-500">
                                        @if ($threadMessage->direction === 'outbound')
                                            To {{ $threadMessage->to_email ?: 'client' }}
                                        @else
                                            From {{ $threadMessage->from_email ?: 'unknown sender' }}
                                        @endif
                                    </p>
                                </div>
                                <time class="font-mono text-[11px] text-zinc-500" datetime="{{ $threadMessage->created_at->toIso8601String() }}">
                                    {{ $threadMessage->created_at->format('M j, Y H:i') }}
                                </time>
                            </div>
                            <div class="whitespace-pre-wrap px-4 py-4 text-sm leading-6 text-zinc-300">{{ $threadMessage->body }}</div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="empty min-h-[360px]">
                    <div class="empty-icon"><flux:icon name="envelope-open" class="size-4" /></div>
                    <p>Select a message</p>
                    <span class="text-xs">Thread details, replies, and archive actions appear here.</span>
                </div>
            @endif
        </section>
    </div>

    <div class="dash-card">
        <div class="dash-card-head">
            <h2 class="dash-card-title">Inbound email API</h2>
        </div>
        <p class="text-xs text-zinc-500">POST JSON to ingest replies or forwarded mail. Optional Bearer token: <code class="rounded bg-zinc-800 px-1 py-0.5 font-mono text-[10px] text-emerald-400">INBOX_INBOUND_SECRET</code></p>
        <code class="mt-2 block break-all rounded-lg bg-zinc-950 px-3 py-2 font-mono text-[11px] text-emerald-400/80">
            POST {{ url('/api/inbox') }}/{{ $site->slug }}
        </code>
    </div>

    @if ($composerOpen)
        <flux:modal name="inbox-composer" class="max-w-lg !bg-zinc-900" :show="true">
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg" class="text-zinc-100">{{ $replyingToId ? 'Reply' : 'New message' }}</flux:heading>
                    <flux:text class="mt-1 text-zinc-500">Sends with your configured mailer and stores the message in this thread.</flux:text>
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
                    <flux:button type="button" wire:click="closeComposer" variant="subtle">Cancel</flux:button>
                    <flux:button type="button" wire:click="sendMessage" variant="primary">Send</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
