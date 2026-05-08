<div>
    <div class="dash-card h-full">
        <div class="dash-card-head">
            <p class="dash-card-title">Recorded expenses</p>
            <p class="font-mono text-xs tabular-nums text-zinc-500">&euro;{{ number_format($grandTotal, 2) }}</p>
        </div>

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
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium">Total</p>
                    <p class="expense-amt">&euro;{{ number_format($grandTotal, 2) }}</p>
                </div>
                <div class="mt-2 flex items-center justify-between rounded-md border border-zinc-800/60 px-3 py-2">
                    <p class="text-sm text-zinc-400">{{ now()->format('F Y') }} report</p>
                    <span class="pill pill-red pill-no-dot">Draft</span>
                </div>
            @else
                <div class="empty">
                    <p>No expense entries yet</p>
                    <p class="text-xs text-zinc-600">Record expenses per site to see totals here.</p>
                </div>
            @endif
        </div>
    </div>
</div>
