<div>
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5 h-full">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <flux:icon name="banknotes" class="size-4 text-zinc-400" />
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Expenses</h3>
            </div>
        </div>

        <div class="space-y-3">
            @foreach ($siteExpenses as $expense)
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $expense['site']->name }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $expense['items'] }} items</p>
                    </div>
                    <p class="text-sm font-medium tabular-nums text-zinc-900 dark:text-zinc-100">&euro;{{ number_format($expense['total'], 2) }}</p>
                </div>
            @endforeach

            @if ($siteExpenses->isNotEmpty())
                <div class="border-t border-zinc-200 dark:border-zinc-700 pt-3 mt-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Total</p>
                        <p class="text-sm font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">&euro;{{ number_format($grandTotal, 2) }}</p>
                    </div>
                </div>

                <div class="pt-2">
                    <p class="text-[10px] uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2">Reports</p>
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ now()->format('F Y') }} Report</p>
                        <span class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase bg-red-500/10 text-red-500">Draft</span>
                    </div>
                </div>
            @else
                <div class="py-6 text-center">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">No expenses to track</p>
                </div>
            @endif
        </div>
    </div>
</div>
