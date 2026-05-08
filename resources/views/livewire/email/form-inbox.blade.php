<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <flux:select wire:model.live="siteId" size="sm" class="w-auto">
            <flux:select.option value="">All Sites</flux:select.option>
            @foreach ($sites as $site)
                <flux:select.option value="{{ $site->id }}">{{ $site->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <x-ui.tabs>
            @foreach (['unread', 'all', 'spam'] as $tab)
                <button wire:click="$set('filter', '{{ $tab }}')"
                    @class(['pk-ui-tab', 'is-active' => $filter === $tab])>
                    {{ ucfirst($tab) }}
                    <span class="font-mono text-[10px] opacity-60">{{ $counts[$tab] }}</span>
                </button>
            @endforeach
        </x-ui.tabs>
    </div>

    <div class="space-y-2">
        @forelse ($submissions as $submission)
            <x-ui.card>
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="mb-1 flex items-center gap-2">
                            <span class="text-xs font-medium">{{ $submission->site?->name ?? 'Unknown site' }}</span>
                            <x-ui.badge variant="info">{{ $submission->form_name }}</x-ui.badge>
                            @if (!$submission->is_read)
                                <span class="inline-block h-1.5 w-1.5 rounded-full bg-violet-400"></span>
                            @endif
                        </div>
                        <div class="mt-2 space-y-1">
                            @foreach ($submission->data as $key => $value)
                                @if (!str_starts_with($key, '_'))
                                    <div class="flex gap-2 text-xs">
                                        <span class="min-w-[80px] font-medium text-zinc-600">{{ ucfirst($key) }}:</span>
                                        <span class="break-all text-zinc-300">{{ is_string($value) ? Str::limit($value, 200) : json_encode($value) }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <p class="mt-2 font-mono text-[10px] text-zinc-600">
                            {{ $submission->created_at->diffForHumans() }} &middot; {{ $submission->ip_address }}
                        </p>
                    </div>

                    <x-ui.button-group>
                        @if (!$submission->is_read)
                            <x-ui.button wire:click="markRead('{{ $submission->id }}')" size="xs" variant="ghost" icon="check" />
                        @endif
                        <x-ui.button wire:click="markSpam('{{ $submission->id }}')" size="xs" variant="ghost" icon="exclamation-triangle" class="text-amber-400" />
                        <x-ui.button wire:click="delete('{{ $submission->id }}')" wire:confirm="Delete this submission?" size="xs" variant="ghost" icon="trash" class="text-red-400" />
                    </x-ui.button-group>
                </div>
            </x-ui.card>
        @empty
            <x-ui.empty icon="inbox" title="{{ $filter === 'spam' ? 'No spam submissions.' : 'No form submissions yet.' }}"
                description="{{ $filter !== 'spam' ? 'Add the form endpoint to your sites to start receiving submissions.' : '' }}" />
        @endforelse
    </div>

    <div>{{ $submissions->links() }}</div>

    <x-ui.card>
        <x-ui.card-header><x-ui.card-title>Integration</x-ui.card-title></x-ui.card-header>
        <p class="text-xs text-zinc-500">Point your contact forms to this endpoint:</p>
        <code class="mt-2 block break-all rounded bg-zinc-800 px-3 py-2 font-mono text-xs text-violet-400">
            POST {{ url('/api/forms') }}/{site-slug}
        </code>
        <p class="mt-2 text-[10px] text-zinc-600">Accepts any JSON body. Rate limited to 10 submissions/minute per IP.</p>
    </x-ui.card>
</div>
