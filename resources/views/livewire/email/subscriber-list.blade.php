<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <select wire:model.live="siteId" class="input-field text-sm w-auto">
            <option value="">All Sites</option>
            @foreach ($sites as $site)
                <option value="{{ $site->id }}">{{ $site->name }}</option>
            @endforeach
        </select>

        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search..." class="input-field text-sm max-w-xs">

        <div class="flex gap-3 ml-auto text-xs text-zinc-500">
            <span><span class="text-emerald-400 font-semibold">{{ $stats['active'] }}</span> active</span>
            <span><span class="text-zinc-400 font-semibold">{{ $stats['unsubscribed'] }}</span> unsub</span>
            <span><span class="text-red-400 font-semibold">{{ $stats['bounced'] }}</span> bounced</span>
        </div>
    </div>

    {{-- Add subscriber --}}
    @if ($siteId)
        <form wire:submit="addSubscriber" class="card !p-4 flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[180px]">
                <label class="input-label">Email</label>
                <input type="email" wire:model="newEmail" class="input-field text-sm" placeholder="subscriber@email.com">
            </div>
            <div class="flex-1 min-w-[120px]">
                <label class="input-label">Name</label>
                <input type="text" wire:model="newName" class="input-field text-sm" placeholder="Optional">
            </div>
            <button type="submit" class="btn-primary text-xs">Add</button>
        </form>
    @endif

    {{-- Subscriber list --}}
    <div class="card overflow-hidden !p-0">
        <table class="w-full">
            <thead>
                <tr class="border-b border-zinc-800">
                    <th class="table-header px-4 py-2">Email</th>
                    <th class="table-header px-4 py-2 hidden md:table-cell">Name</th>
                    <th class="table-header px-4 py-2 hidden lg:table-cell">Site</th>
                    <th class="table-header px-4 py-2">Status</th>
                    <th class="table-header px-4 py-2 hidden lg:table-cell">Joined</th>
                    <th class="table-header px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subscribers as $sub)
                    <tr class="hover:bg-zinc-800/30 transition">
                        <td class="table-cell mono text-xs">{{ $sub->email }}</td>
                        <td class="table-cell hidden md:table-cell text-xs">{{ $sub->name ?? '—' }}</td>
                        <td class="table-cell hidden lg:table-cell text-xs text-zinc-500">{{ $sub->site?->name }}</td>
                        <td class="table-cell">
                            @switch($sub->status)
                                @case('active') <span class="badge-green !text-[10px]">Active</span> @break
                                @case('unsubscribed') <span class="badge bg-zinc-500/10 text-zinc-500 !text-[10px]">Unsub</span> @break
                                @case('bounced') <span class="badge-red !text-[10px]">Bounced</span> @break
                            @endswitch
                        </td>
                        <td class="table-cell hidden lg:table-cell text-xs text-zinc-600">{{ $sub->created_at->format('M j, Y') }}</td>
                        <td class="table-cell text-right">
                            @if ($sub->status === 'active')
                                <button wire:click="unsubscribe('{{ $sub->id }}')" class="text-[10px] text-zinc-500 hover:text-amber-400 mr-1">Unsub</button>
                            @else
                                <button wire:click="resubscribe('{{ $sub->id }}')" class="text-[10px] text-zinc-500 hover:text-emerald-400 mr-1">Resub</button>
                            @endif
                            <button wire:click="delete('{{ $sub->id }}')" wire:confirm="Remove subscriber?" class="text-[10px] text-zinc-500 hover:text-red-400">Del</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-zinc-500">No subscribers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $subscribers->links() }}</div>
</div>
