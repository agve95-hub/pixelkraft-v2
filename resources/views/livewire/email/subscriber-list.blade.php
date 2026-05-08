<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <flux:select wire:model.live="siteId" size="sm" class="w-auto">
            <flux:select.option value="">All Sites</flux:select.option>
            @foreach ($sites as $site)
                <flux:select.option value="{{ $site->id }}">{{ $site->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search..." size="sm" class="max-w-xs" />

        <div class="ml-auto flex gap-3 text-xs text-zinc-500">
            <span><span class="font-semibold text-emerald-400">{{ $stats['active'] }}</span> active</span>
            <span><span class="font-semibold text-zinc-400">{{ $stats['unsubscribed'] }}</span> unsub</span>
            <span><span class="font-semibold text-red-400">{{ $stats['bounced'] }}</span> bounced</span>
        </div>
    </div>

    @if ($siteId)
        <x-ui.card>
            <x-ui.card-header><x-ui.card-title>Add subscriber</x-ui.card-title></x-ui.card-header>
            <form wire:submit="addSubscriber" class="flex flex-wrap items-end gap-3">
                <flux:field class="flex-1 min-w-[180px]">
                    <flux:label>Email</flux:label>
                    <flux:input type="email" wire:model="newEmail" placeholder="subscriber@email.com" />
                </flux:field>
                <flux:field class="flex-1 min-w-[120px]">
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="newName" placeholder="Optional" />
                </flux:field>
                <flux:button type="submit" variant="primary" size="sm" class="shrink-0">Add</flux:button>
            </form>
        </x-ui.card>
    @endif

    <x-ui.table>
        <thead>
            <tr>
                <th>Email</th>
                <th class="hidden md:table-cell">Name</th>
                <th class="hidden lg:table-cell">Site</th>
                <th>Status</th>
                <th class="hidden lg:table-cell">Joined</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($subscribers as $sub)
                <tr>
                    <td class="font-mono text-xs">{{ $sub->email }}</td>
                    <td class="hidden md:table-cell text-xs">{{ $sub->name ?? '—' }}</td>
                    <td class="hidden lg:table-cell text-xs text-zinc-500">{{ $sub->site?->name }}</td>
                    <td>
                        @switch($sub->status)
                            @case('active') <x-ui.badge variant="success">Active</x-ui.badge> @break
                            @case('unsubscribed') <x-ui.badge>Unsub</x-ui.badge> @break
                            @case('bounced') <x-ui.badge variant="destructive">Bounced</x-ui.badge> @break
                        @endswitch
                    </td>
                    <td class="hidden lg:table-cell text-xs text-zinc-600">{{ $sub->created_at->format('M j, Y') }}</td>
                    <td>
                        <x-ui.button-group align="end">
                            @if ($sub->status === 'active')
                                <x-ui.button wire:click="unsubscribe('{{ $sub->id }}')" size="xs" variant="ghost" class="text-amber-400">Unsub</x-ui.button>
                            @else
                                <x-ui.button wire:click="resubscribe('{{ $sub->id }}')" size="xs" variant="ghost" class="text-emerald-400">Resub</x-ui.button>
                            @endif
                            <x-ui.button wire:click="delete('{{ $sub->id }}')" wire:confirm="Remove subscriber?" size="xs" variant="ghost" class="text-red-400">Del</x-ui.button>
                        </x-ui.button-group>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <x-ui.empty icon="users" title="No subscribers yet." />
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.table>

    <div>{{ $subscribers->links() }}</div>
</div>
