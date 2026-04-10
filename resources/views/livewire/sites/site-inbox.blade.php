<div class="max-w-3xl">
    <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a
                href="{{ route('sites.show', $site) }}"
                wire:navigate
                class="mb-2 inline-flex items-center gap-1 text-sm text-zinc-500 transition hover:text-zinc-300"
            >
                <span aria-hidden="true">←</span>
                {{ $site->name }}
            </a>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-100">Inbox</h1>
            <p class="mt-1 text-sm text-zinc-500">
                {{ $site->clientDisplayName() }}
                <span class="text-zinc-600">·</span>
                {{ $messages->count() }} {{ Str::plural('message', $messages->count()) }}
            </p>
        </div>
        <button
            type="button"
            wire:click="openComposer"
            class="inline-flex items-center justify-center rounded-lg border border-zinc-600 bg-transparent px-4 py-2 text-sm font-medium text-zinc-100 shadow-sm transition hover:border-zinc-500 hover:bg-zinc-800/80"
        >
            Compose
        </button>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-700/80 bg-zinc-900/40">
        @forelse ($messages as $message)
            <div
                wire:click="selectMessage('{{ $message->id }}')"
                wire:key="msg-{{ $message->id }}"
                role="button"
                tabindex="0"
                @keydown.enter="$wire.selectMessage('{{ $message->id }}')"
                @class([
                    'block w-full cursor-pointer border-b border-zinc-800/90 px-4 py-4 text-left transition last:border-b-0 hover:bg-zinc-800/30',
                    'bg-zinc-800/20' => $selectedId === $message->id,
                ])
            >
                <div class="flex items-start gap-3">
                    <div class="mt-1.5 w-2 shrink-0 flex justify-center">
                        @if ($message->direction === 'inbound' && ! $message->is_read)
                            <span class="h-2 w-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.45)]" title="Unread"></span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between gap-3">
                            <span
                                @class([
                                    'truncate text-sm',
                                    'font-semibold text-zinc-100' => $message->direction === 'inbound' && ! $message->is_read,
                                    'font-medium text-zinc-400' => $message->direction === 'outbound' || $message->is_read,
                                ])
                            >
                                {{ $message->listSenderLabel() }}
                            </span>
                            <time class="shrink-0 text-xs text-zinc-500" datetime="{{ $message->created_at->toIso8601String() }}">
                                {{ $message->created_at->format('M j') }}
                            </time>
                        </div>
                        <p
                            @class([
                                'mt-0.5 truncate text-sm',
                                'text-zinc-200' => $message->direction === 'inbound' && ! $message->is_read,
                                'text-zinc-500' => $message->direction === 'outbound' || $message->is_read,
                            ])
                        >
                            {{ $message->subject }}
                        </p>
                        <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-zinc-500">
                            {{ $message->previewText() }}
                        </p>
                    </div>
                </div>

                @if ($selectedId === $message->id)
                    <div class="mt-4 border-t border-zinc-800/80 pt-4 pl-5" wire:click.stop>
                        <dl class="space-y-1 text-xs text-zinc-500">
                            @if ($message->direction === 'inbound')
                                <div><span class="text-zinc-600">From</span> {{ $message->from_name ?: '—' }} &lt;{{ $message->from_email ?: '—' }}&gt;</div>
                            @else
                                <div><span class="text-zinc-600">To</span> {{ $message->to_email }}</div>
                            @endif
                            <div><span class="text-zinc-600">Subject</span> {{ $message->subject }}</div>
                        </dl>
                        <div class="mt-3 rounded-lg bg-zinc-950/60 p-3 text-sm text-zinc-300 whitespace-pre-wrap">{{ $message->body }}</div>
                    </div>
                @endif
            </div>
        @empty
            <div class="px-6 py-16 text-center">
                <p class="text-sm text-zinc-500">No messages yet.</p>
                <p class="mt-2 text-xs text-zinc-600">Contact form submissions appear here. You can also forward mail via the inbound endpoint below.</p>
                <button
                    type="button"
                    wire:click="openComposer"
                    class="mt-4 text-sm font-medium text-teal-400/90 hover:text-teal-300"
                >
                    Compose a message
                </button>
            </div>
        @endforelse
    </div>

    <div class="mt-8 rounded-xl border border-zinc-700/60 bg-zinc-900/30 p-4">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-zinc-400">Inbound email API</h2>
        <p class="mt-2 text-xs text-zinc-500">POST JSON to ingest replies or forwarded mail (optional Bearer <code class="rounded bg-zinc-800 px-1 py-0.5 font-mono text-[10px] text-teal-400/90">INBOX_INBOUND_SECRET</code>):</p>
        <code class="mt-2 block break-all rounded-lg bg-zinc-950 px-3 py-2 font-mono text-[11px] text-teal-400/80">
            POST {{ url('/api/inbox') }}/{{ $site->slug }}
        </code>
        <p class="mt-2 text-[10px] leading-relaxed text-zinc-600">
            Body: <span class="font-mono text-zinc-500">subject</span> (required),
            <span class="font-mono text-zinc-500">body</span> (required),
            <span class="font-mono text-zinc-500">from_email</span>,
            <span class="font-mono text-zinc-500">from_name</span>,
            <span class="font-mono text-zinc-500">to_email</span>
        </p>
    </div>

    @if ($composerOpen)
        <flux:modal name="inbox-composer" class="max-w-lg !bg-zinc-900" :show="true">
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg" class="text-zinc-100">New message</flux:heading>
                    <flux:text class="mt-1 text-zinc-500">Sends with your configured mailer (e.g. SMTP or Resend).</flux:text>
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
                        <flux:textarea wire:model="composeBody" rows="8" placeholder="Write your message…" class="min-h-[180px] font-sans text-sm" />
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
