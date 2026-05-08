<div class="space-y-5">
    <div class="flex items-center gap-3 text-sm text-zinc-400">
        @forelse ($totalsByCurrency as $row)
            <span class="font-mono tabular-nums">
                <span class="text-zinc-500">{{ $row->currency }}</span>
                <span class="ml-1 font-semibold text-zinc-100">{{ number_format((float) $row->total, 2) }}</span>
            </span>
        @empty
            <span class="text-zinc-600">No expenses recorded yet</span>
        @endforelse
    </div>

    <x-ui.card>
        <x-ui.card-header>
            <x-ui.card-title>Add expense</x-ui.card-title>
        </x-ui.card-header>
        <form wire:submit="save" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1.5fr]">
            <flux:field>
                <flux:label>Label</flux:label>
                <flux:input wire:model="form_label" placeholder="e.g. Hosting March" />
                <flux:error name="form_label" />
            </flux:field>
            <flux:field>
                <flux:label>Amount</flux:label>
                <flux:input type="number" step="0.01" min="0.01" wire:model="form_amount" placeholder="0.00" />
                <flux:error name="form_amount" />
            </flux:field>
            <flux:field>
                <flux:label>Currency</flux:label>
                <flux:input wire:model="form_currency" maxlength="3" class="uppercase font-mono" placeholder="EUR" />
                <flux:error name="form_currency" />
            </flux:field>
            <flux:field>
                <flux:label>Date</flux:label>
                <flux:input type="date" wire:model="form_expense_date" />
                <flux:error name="form_expense_date" />
            </flux:field>
            <div class="sm:col-span-2 lg:col-span-4">
                <flux:button type="submit" variant="subtle" icon="plus">Save expense</flux:button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card padding="flush">
        <x-ui.card-header class="px-[18px] pt-4 pb-3">
            <x-ui.card-title>History</x-ui.card-title>
        </x-ui.card-header>
        <div class="overflow-x-auto">
            <table class="pk-ui-table">
                <thead>
                    <tr>
                        <th class="pl-[18px]">Date</th>
                        <th>Label</th>
                        <th class="text-right">Amount</th>
                        <th class="pr-[18px] w-24"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td class="pl-[18px] font-mono">{{ $expense->expense_date->format('Y-m-d') }}</td>
                            <td>{{ $expense->label }}</td>
                            <td class="text-right font-mono tabular-nums">{{ $expense->currency }} {{ number_format((float) $expense->amount, 2) }}</td>
                            <td class="pr-[18px] text-right">
                                <x-ui.button type="button" wire:click="delete('{{ $expense->id }}')" wire:confirm="Remove this expense?" size="xs" variant="ghost" class="text-red-400">Delete</x-ui.button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4"><x-ui.empty icon="banknotes" title="No expenses yet." /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-[18px] py-3">{{ $expenses->links() }}</div>
    </x-ui.card>
</div>
