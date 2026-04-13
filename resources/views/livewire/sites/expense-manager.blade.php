<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Expenses</flux:heading>
            <flux:subheading>{{ $site->name }} — recorded costs (dashboard totals use this data).</flux:subheading>
        </div>
        <div class="text-right text-sm text-zinc-400">
            @forelse ($totalsByCurrency as $row)
                <p class="tabular-nums">
                    <span class="font-mono text-zinc-500">{{ $row->currency }}</span>
                    <span class="ml-2 font-semibold text-zinc-100">{{ number_format((float) $row->total, 2) }}</span>
                </p>
            @empty
                <p>No totals yet</p>
            @endforelse
        </div>
    </div>

    <flux:card>
        <flux:heading size="lg" class="mb-4">Add expense</flux:heading>
        <form wire:submit="save" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
                <flux:button type="submit" variant="primary" icon="plus" class="!bg-emerald-500 hover:!bg-emerald-400 !text-zinc-950 dark:!text-zinc-950">
                    Save expense
                </flux:button>
            </div>
        </form>
    </flux:card>

    <flux:card>
        <flux:heading size="lg" class="mb-4">History</flux:heading>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800 text-left text-[11px] font-medium uppercase tracking-wide text-zinc-500">
                        <th class="py-2 pr-4">Date</th>
                        <th class="py-2 pr-4">Label</th>
                        <th class="py-2 pr-4 text-right">Amount</th>
                        <th class="py-2 w-24"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/80">
                    @forelse ($expenses as $expense)
                        <tr>
                            <td class="py-2.5 pr-4 text-zinc-400">{{ $expense->expense_date->format('Y-m-d') }}</td>
                            <td class="py-2.5 pr-4 text-zinc-100">{{ $expense->label }}</td>
                            <td class="py-2.5 pr-4 text-right font-mono tabular-nums text-zinc-200">
                                {{ $expense->currency }} {{ number_format((float) $expense->amount, 2) }}
                            </td>
                            <td class="py-2.5 text-right">
                                <flux:button type="button" wire:click="delete('{{ $expense->id }}')" wire:confirm="Remove this expense?" size="xs" variant="ghost" class="text-red-400">
                                    Delete
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-zinc-500">No expenses yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $expenses->links() }}
        </div>
    </flux:card>
</div>
