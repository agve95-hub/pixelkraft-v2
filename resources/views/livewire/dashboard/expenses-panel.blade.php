<div>
    <x-ui.card class="h-full">
        <x-ui.card-header>
            <x-ui.card-title>Recorded expenses</x-ui.card-title>
            <span class="font-mono text-xs tabular-nums text-zinc-500">&euro;{{ number_format($grandTotal, 2) }}</span>
        </x-ui.card-header>

        <div>
            @foreach ($siteExpenses as $expense)
                <div class="expense-row">
                    <div>
                        <p class="expense-desc">{{ $expense['site']->name }}</p>
                        <p class="expense-cat">{{ $expense['items'] }} {{ $expense['items'] === 1 ? 'entry' : 'entries' }}</p>
                    </div>
                    <p class="expense-amt">&euro;{{ number_format($expense['total'], 2) }}</p>
                </div>
            @endforeach

            @if ($siteExpenses->isNotEmpty())
                <div class="separator"></div>
                <div class="flex items-center justify-between py-1">
                    <p class="text-sm font-medium">Total</p>
                    <p class="expense-amt">&euro;{{ number_format($grandTotal, 2) }}</p>
                </div>
                <div class="mt-2 flex items-center justify-between rounded-md border border-zinc-800/60 px-3 py-2">
                    <p class="text-sm text-zinc-400">{{ now()->format('F Y') }} report</p>
                    <x-ui.badge variant="destructive">Draft</x-ui.badge>
                </div>
            @else
                <x-ui.empty icon="banknotes" title="No expense entries yet" description="Record expenses per site to see totals here." />
            @endif
        </div>
    </x-ui.card>
</div>
