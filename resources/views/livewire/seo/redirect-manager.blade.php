<div class="space-y-4">
    {{-- Add/Edit form --}}
    <div class="card">
        <h3 class="text-sm font-semibold text-zinc-200 mb-4">{{ $editingId ? 'Edit Redirect' : 'Add Redirect' }}</h3>
        <form wire:submit="save" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[150px]">
                <label class="flux-label">From</label>
                <input type="text" wire:model="fromPath" class="flux-input text-sm mono" placeholder="/old-page">
                @error('fromPath') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div class="flex-1 min-w-[150px]">
                <label class="flux-label">To</label>
                <input type="text" wire:model="toPath" class="flux-input text-sm mono" placeholder="/new-page or https://...">
                @error('toPath') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div class="w-24">
                <label class="flux-label">Type</label>
                <select wire:model="statusCode" class="flux-input text-sm">
                    <option value="301">301</option>
                    <option value="302">302</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flux-btn-primary text-xs">{{ $editingId ? 'Update' : 'Add' }}</button>
                @if ($editingId)
                    <button type="button" wire:click="cancel" class="flux-btn-ghost text-xs">Cancel</button>
                @endif
            </div>
        </form>
    </div>

    {{-- Redirect list --}}
    @if ($redirects->isNotEmpty())
        <div class="flux-card overflow-hidden !p-0">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-zinc-800">
                        <th class="flux-table-header px-4 py-2">From</th>
                        <th class="flux-table-header px-4 py-2">To</th>
                        <th class="flux-table-header px-4 py-2">Type</th>
                        <th class="flux-table-header px-4 py-2">Status</th>
                        <th class="flux-table-header px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($redirects as $redirect)
                        <tr class="hover:bg-zinc-800/30 transition">
                            <td class="flux-table-cell mono text-xs">{{ $redirect->from_path }}</td>
                            <td class="flux-table-cell mono text-xs">{{ Str::limit($redirect->to_path, 40) }}</td>
                            <td class="flux-table-cell"><span class="flux-badge-purple !text-[10px]">{{ $redirect->status_code }}</span></td>
                            <td class="flux-table-cell">
                                <button wire:click="toggle('{{ $redirect->id }}')" class="cursor-pointer">
                                    @if ($redirect->is_active)
                                        <span class="flux-badge-green !text-[10px]">Active</span>
                                    @else
                                        <span class="flux-badge bg-zinc-500/10 text-zinc-500 !text-[10px]">Disabled</span>
                                    @endif
                                </button>
                            </td>
                            <td class="flux-table-cell text-right">
                                <button wire:click="edit('{{ $redirect->id }}')" class="text-xs text-zinc-500 hover:text-zinc-300 mr-2">Edit</button>
                                <button wire:click="delete('{{ $redirect->id }}')" wire:confirm="Delete this redirect?" class="text-xs text-red-400 hover:text-red-300">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
