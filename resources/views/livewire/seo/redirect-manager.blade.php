<div class="space-y-4">
    <x-ui.card>
        <x-ui.card-header>
            <x-ui.card-title>{{ $editingId ? 'Edit Redirect' : 'Add Redirect' }}</x-ui.card-title>
        </x-ui.card-header>
        <form wire:submit="save" class="flex flex-wrap items-end gap-3">
            <flux:field class="flex-1 min-w-[150px]">
                <flux:label>From</flux:label>
                <flux:input wire:model="fromPath" placeholder="/old-page" class="font-mono" />
                <flux:error name="fromPath" />
            </flux:field>
            <flux:field class="flex-1 min-w-[150px]">
                <flux:label>To</flux:label>
                <flux:input wire:model="toPath" placeholder="/new-page or https://..." class="font-mono" />
                <flux:error name="toPath" />
            </flux:field>
            <flux:field class="w-24">
                <flux:label>Type</flux:label>
                <flux:select wire:model="statusCode">
                    <flux:select.option value="301">301</flux:select.option>
                    <flux:select.option value="302">302</flux:select.option>
                </flux:select>
            </flux:field>
            <div class="flex gap-2">
                <flux:button type="submit" variant="primary" size="sm">{{ $editingId ? 'Update' : 'Add' }}</flux:button>
                @if ($editingId)
                    <flux:button type="button" wire:click="cancel" variant="ghost" size="sm">Cancel</flux:button>
                @endif
            </div>
        </form>
    </x-ui.card>

    @if ($redirects->isNotEmpty())
        <x-ui.table>
            <thead>
                <tr>
                    <th>From</th>
                    <th>To</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($redirects as $redirect)
                    <tr class="clickable">
                        <td class="font-mono text-xs">{{ $redirect->from_path }}</td>
                        <td class="font-mono text-xs">{{ Str::limit($redirect->to_path, 40) }}</td>
                        <td><x-ui.badge>{{ $redirect->status_code }}</x-ui.badge></td>
                        <td>
                            <button wire:click="toggle('{{ $redirect->id }}')">
                                @if ($redirect->is_active)
                                    <x-ui.badge variant="success">Active</x-ui.badge>
                                @else
                                    <x-ui.badge>Disabled</x-ui.badge>
                                @endif
                            </button>
                        </td>
                        <td>
                            <x-ui.button-group align="end">
                                <x-ui.button wire:click="edit('{{ $redirect->id }}')" size="xs" variant="ghost">Edit</x-ui.button>
                                <x-ui.button wire:click="delete('{{ $redirect->id }}')" wire:confirm="Delete this redirect?" size="xs" variant="ghost" class="text-red-400">Delete</x-ui.button>
                            </x-ui.button-group>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
    @endif
</div>
