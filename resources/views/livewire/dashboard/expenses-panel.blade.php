<div>
    <div class="h-full rounded-xl border border-zinc-800/80 bg-[#1e1e1e] p-5">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-zinc-100">Recorded expenses</h3>
            <p class="text-xs tabular-nums text-zinc-500">&euro;{{ number_format($grandTotal, 2) }}</p>
        </div>

        <div class="space-y-1.5">
            @foreach ($siteExpenses as $expense)
                <div class="flex items-center justify-between rounded-lg border border-zinc-800/70 px-3 py-2.5">
                    <div>
                        <p class="text-sm font-medium text-zinc-200">{{ $expense['site']->name }}</p>
                        <p class="text-xs text-zinc-500">{{ $expense['items'] }} {{ $expense['items'] === 1 ? 'entry' : 'entries' }}</p>
                    </div>
                    <p class="text-sm font-medium tabular-nums text-zinc-100">&euro;{{ number_format($expense['total'], 2) }}</p>
                </div>
            @endforeach

            @if ($siteExpenses->isNotEmpty())
                <div class="mt-2 border-t border-zinc-800/80 pt-3">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-sm font-medium text-zinc-200">Total</p>
                        <p class="text-sm font-semibold tabular-nums text-zinc-100">&euro;{{ number_format($grandTotal, 2) }}</p>
                    </div>
                    <div class="flex items-center justify-between rounded-lg border border-zinc-800/70 px-3 py-2">
                        <p class="text-sm text-zinc-400">{{ now()->format('F Y') }} report</p>
                        <span class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase bg-red-500/20 text-red-300">Draft</span>
                    </div>
                </div>
            @else
                <div class="py-7 text-center">
                    <p class="text-sm text-zinc-500">No expense entries yet</p>
                    <p class="mt-1 text-xs text-zinc-600">Record expenses per site to see totals here.</p>
                </div>
            @endif
        </div>
    </div>
</div>
