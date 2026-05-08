<div class="space-y-5">
    <div class="pk-page-head">
        <div>
            <h1 class="pk-page-title">Reminders</h1>
            <p class="pk-page-sub">{{ $site->name }} — follow-ups and deadlines.</p>
        </div>
        <span class="text-sm text-zinc-400"><span class="font-semibold text-zinc-100">{{ $openCount }}</span> open</span>
    </div>

    <x-ui.card>
        <x-ui.card-header>
            <x-ui.card-title>Add reminder</x-ui.card-title>
        </x-ui.card-header>
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
                <flux:button type="submit" variant="primary" icon="plus">Add reminder</flux:button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card padding="flush">
        <x-ui.card-header class="px-[18px] pt-4 pb-3">
            <x-ui.card-title>List</x-ui.card-title>
        </x-ui.card-header>
        <div class="reminder-list">
            @forelse ($reminders as $reminder)
                <div class="reminder">
                    <span class="reminder-check {{ $reminder->is_done ? 'done' : '' }}" wire:click="toggleDone('{{ $reminder->id }}')"></span>
                    <div class="min-w-0 flex-1">
                        <p class="reminder-text {{ $reminder->is_done ? 'done-text' : '' }}">{{ $reminder->title }}</p>
                        @if ($reminder->notes)
                            <p class="issue-meta">{{ \Illuminate\Support\Str::limit($reminder->notes, 200) }}</p>
                        @endif
                    </div>
                    @if ($reminder->due_date)
                        <span class="reminder-due {{ $reminder->due_date->isPast() && !$reminder->is_done ? 'overdue' : '' }}">
                            {{ $reminder->due_date->format('M j') }}
                        </span>
                    @endif
                    <x-ui.button type="button" wire:click="delete('{{ $reminder->id }}')" wire:confirm="Delete this reminder?" size="xs" variant="ghost" class="text-red-400">Delete</x-ui.button>
                </div>
            @empty
                <x-ui.empty icon="clock" title="No reminders yet." class="py-8" />
            @endforelse
        </div>
        <div class="px-[18px] py-3">{{ $reminders->links() }}</div>
    </x-ui.card>
</div>
