<div class="space-y-6">
    <div class="pk-page-head">
        <div>
            <h1 class="pk-page-title">Expenses</h1>
            <p class="pk-page-sub">{{ $site->name }} — recorded costs.</p>
        </div>
        <div class="text-right text-sm">
            @forelse ($totalsByCurrency as $row)
                <p class="font-mono tabular-nums">
                    <span class="text-zinc-500">{{ $row->currency }}</span>
                    <span class="ml-2 font-semibold">{{ number_format((float) $row->total, 2) }}</span>
                </p>
            @empty
                <p class="pk-page-sub">No totals yet</p>
            @endforelse
        </div>
    </div>

    <div class="dash-card">
        <p class="section-title mb-4">Add expense</p>
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
                <button type="submit" class="btn btn-accent">+ Save expense</button>
            </div>
        </form>
    </div>

    <div class="dash-card !p-0">
        <div class="dash-card-head px-[18px] pt-4 pb-3">
            <p class="section-title">History</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
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
                                <flux:button type="button" wire:click="delete('{{ $expense->id }}')" wire:confirm="Remove this expense?" size="xs" variant="ghost" class="text-red-400">Delete</flux:button>
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
        <div class="px-[18px] py-3">{{ $expenses->links() }}</div>
    </div>
</div>
