<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Reminders</flux:heading>
            <flux:subheading>{{ $site->name }} — follow-ups and deadlines.</flux:subheading>
        </div>
        <flux:text class="text-zinc-400">
            <span class="font-semibold text-zinc-100">{{ $openCount }}</span> open
        </flux:text>
    </div>

    <flux:card>
        <flux:heading size="lg" class="mb-4">Add reminder</flux:heading>
        <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
            <flux:field class="sm:col-span-2">
                <flux:label>Title</flux:label>
                <flux:input wire:model="form_title" placeholder="e.g. Renew SSL" />
                <flux:error name="form_title" />
            </flux:field>
            <flux:field>
                <flux:label>Due date</flux:label>
                <flux:input type="date" wire:model="form_due_date" />
                <flux:error name="form_due_date" />
            </flux:field>
            <flux:field class="sm:col-span-2">
                <flux:label>Notes</flux:label>
                <flux:textarea wire:model="form_notes" rows="2" placeholder="Optional" />
                <flux:error name="form_notes" />
            </flux:field>
            <div class="sm:col-span-2">
                <flux:button type="submit" variant="primary" icon="plus" class="!bg-emerald-500 hover:!bg-emerald-400 !text-zinc-950 dark:!text-zinc-950">
                    Add reminder
                </flux:button>
            </div>
        </form>
    </flux:card>

    <flux:card>
        <flux:heading size="lg" class="mb-4">List</flux:heading>
        <div class="space-y-2">
            @forelse ($reminders as $reminder)
                <div class="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-zinc-800/80 bg-zinc-950/40 px-3 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-zinc-100 {{ $reminder->is_done ? 'line-through text-zinc-500' : '' }}">{{ $reminder->title }}</p>
                        @if ($reminder->due_date)
                            <p class="mt-0.5 text-xs text-zinc-500">Due {{ $reminder->due_date->format('M j, Y') }}</p>
                        @endif
                        @if ($reminder->notes)
                            <p class="mt-1 text-sm text-zinc-400">{{ \Illuminate\Support\Str::limit($reminder->notes, 200) }}</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <flux:button type="button" wire:click="toggleDone('{{ $reminder->id }}')" size="xs" variant="subtle">
                            {{ $reminder->is_done ? 'Reopen' : 'Done' }}
                        </flux:button>
                        <flux:button type="button" wire:click="delete('{{ $reminder->id }}')" wire:confirm="Delete this reminder?" size="xs" variant="ghost" class="text-red-400">
                            Delete
                        </flux:button>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-zinc-500">No reminders yet.</p>
            @endforelse
        </div>
        <div class="mt-4">
            {{ $reminders->links() }}
        </div>
    </flux:card>
</div>
