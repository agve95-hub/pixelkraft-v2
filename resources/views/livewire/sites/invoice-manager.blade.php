@php
    use App\Models\Invoice;
    $firstCurrency = $invoices->first()?->currency_code ?? 'EUR';
    $symAll = Invoice::currencySymbol($firstCurrency);
    $fmt = function (float $amount, string $sym) {
        $s = trim($sym);

        return (strlen($s) > 1 && ! str_ends_with($s, ' ') ? $s.' ' : $s).number_format($amount, 2, '.', '');
    };
@endphp

<div class="max-w-5xl">
    @if ($screen === 'index')
        <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
            <div>
                <a
                    href="{{ route('sites.show', $site) }}"
                    wire:navigate
                    class="mb-2 inline-flex items-center gap-1 text-sm text-zinc-500 transition hover:text-zinc-300"
                >
                    <span aria-hidden="true">←</span>
                    {{ $site->name }}
                </a>
                <h1 class="text-2xl font-semibold tracking-tight text-zinc-100">Invoices</h1>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ $site->clientDisplayName() }}
                    <span class="text-zinc-600">·</span>
                    {{ $invoices->count() }} {{ Str::plural('invoice', $invoices->count()) }}
                </p>
            </div>
            <button
                type="button"
                wire:click="startCreate"
                class="inline-flex items-center justify-center rounded-lg bg-emerald-500 px-4 py-2 text-sm font-medium text-zinc-950 shadow-sm transition hover:bg-emerald-400"
            >
                + New invoice
            </button>
        </div>

        <div
            class="mb-6 grid grid-cols-2 gap-px overflow-hidden rounded-xl bg-zinc-700/50 sm:grid-cols-4"
            style="box-shadow: 0 1px 2px rgba(0,0,0,0.05)"
        >
            <div class="bg-zinc-900/80 px-4 py-4">
                <div class="text-[11px] font-medium uppercase tracking-wide text-zinc-500">Total invoiced</div>
                <div class="mt-1 font-mono text-lg font-medium text-zinc-100">{{ $fmt($totalAllAmount, $symAll) }}</div>
            </div>
            <div class="bg-zinc-900/80 px-4 py-4">
                <div class="text-[11px] font-medium uppercase tracking-wide text-zinc-500">Paid</div>
                <div class="mt-1 font-mono text-lg font-medium text-emerald-400">{{ $fmt($totalPaidAmount, $symAll) }}</div>
                <div class="mt-0.5 text-xs text-zinc-500">{{ $paidInvoices->count() }} {{ Str::plural('invoice', $paidInvoices->count()) }}</div>
            </div>
            <div class="bg-zinc-900/80 px-4 py-4">
                <div class="text-[11px] font-medium uppercase tracking-wide text-zinc-500">Outstanding</div>
                <div @class(['mt-1 font-mono text-lg font-medium', 'text-amber-400' => $totalUnpaidAmount > 0, 'text-zinc-100' => $totalUnpaidAmount <= 0])>
                    {{ $fmt($totalUnpaidAmount, $symAll) }}
                </div>
                <div class="mt-0.5 text-xs text-zinc-500">{{ $unpaidInvoices->count() }} unpaid</div>
            </div>
            <div class="bg-zinc-900/80 px-4 py-4">
                <div class="text-[11px] font-medium uppercase tracking-wide text-zinc-500">Invoices</div>
                <div class="mt-1 font-mono text-lg font-medium text-zinc-100">{{ $invoices->count() }}</div>
            </div>
        </div>

        @if ($invoices->isEmpty())
            <div
                class="flex flex-col items-center justify-center rounded-xl border border-zinc-700/80 px-4 py-16 text-center text-sm text-zinc-500"
            >
                <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-zinc-800/80 text-zinc-500">
                    <flux:icon name="document-text" class="size-4 opacity-50" />
                </div>
                <p>No invoices yet</p>
                <p class="mt-1 text-xs text-zinc-600">Create your first invoice for {{ $site->clientDisplayName() }}</p>
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-zinc-700/80 shadow-sm">
                <div
                    class="hidden grid-cols-[100px_1fr_auto_auto_auto] gap-4 border-b border-zinc-700/80 px-4 py-2.5 text-xs font-medium text-zinc-400 sm:grid"
                >
                    <span>Invoice</span>
                    <span>Description</span>
                    <span>Date</span>
                    <span>Amount</span>
                    <span>Status</span>
                </div>
                @foreach ($invoices as $inv)
                    @php
                        $sym = $inv->displayCurrencySymbol();
                        $pill = $inv->status === Invoice::STATUS_PAID ? 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/25' : ($inv->isOverdue() ? 'bg-red-500/15 text-red-400 ring-1 ring-red-500/25' : 'bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/25');
                        $first = $inv->items->first()?->description ?? '—';
                    @endphp
                    <button
                        type="button"
                        wire:click="openInvoice('{{ $inv->id }}')"
                        wire:key="inv-row-{{ $inv->id }}"
                        class="grid w-full grid-cols-1 gap-2 border-b border-zinc-800/90 px-4 py-3 text-left text-sm transition last:border-b-0 hover:bg-zinc-800/30 sm:grid-cols-[100px_1fr_auto_auto_auto] sm:items-center sm:gap-4"
                    >
                        <span class="font-mono text-[13px] font-medium text-zinc-100">{{ $inv->number }}</span>
                        <div class="min-w-0 text-left">
                            <div class="truncate text-zinc-300">{{ $first }}</div>
                            <div class="text-[11px] text-zinc-500">
                                {{ $inv->items->count() }} {{ Str::plural('item', $inv->items->count()) }}
                                @if ((float) $inv->discount_percent > 0)
                                    <span class="text-zinc-600">·</span> {{ $inv->discount_percent }}% disc.
                                @endif
                                @if ((float) $inv->tax_rate > 0)
                                    <span class="text-zinc-600">·</span> {{ $inv->tax_rate }}% tax
                                @endif
                            </div>
                        </div>
                        <span class="font-mono text-xs text-zinc-500">{{ $inv->invoice_date->toDateString() }}</span>
                        <span class="font-mono text-sm font-medium text-zinc-100">{{ $fmt((float) $inv->total(), $sym) }}</span>
                        <span class="inline-flex">
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-semibold {{ $pill }}">
                                {{ $inv->displayStatus() }}
                            </span>
                        </span>
                    </button>
                @endforeach
            </div>
        @endif
    @endif

    @if ($screen === 'create')
        @php
            $t = $this->createTotals();
            $sym = Invoice::currencySymbol($form_currency_code);
        @endphp
        <div class="mb-8">
            <button
                type="button"
                wire:click="cancelCreate"
                class="mb-4 inline-flex items-center gap-1 text-sm text-zinc-500 transition hover:text-zinc-300"
            >
                <span aria-hidden="true">←</span>
                Invoices
            </button>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-100">New invoice</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ $site->clientDisplayName() }} · {{ $form_number }}</p>
        </div>

        <div class="max-w-3xl space-y-8">
            @error('form_lines')
                <flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>
            @enderror

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:field>
                    <flux:label>From <span class="font-normal text-zinc-500">(your business)</span></flux:label>
                    <flux:textarea wire:model="form_from_address" rows="4" class="font-sans" />
                </flux:field>
                <flux:field>
                    <flux:label>Bill to <span class="font-normal text-zinc-500">(client)</span></flux:label>
                    <flux:textarea wire:model="form_bill_to" rows="4" class="font-sans" />
                </flux:field>
            </div>

            <div class="h-px bg-zinc-800/80"></div>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <flux:field>
                    <flux:label>Invoice #</flux:label>
                    <flux:input wire:model="form_number" class="font-mono text-sm" />
                    @error('form_number')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>
                <flux:field>
                    <flux:label>Invoice date</flux:label>
                    <flux:input type="date" wire:model.live="form_invoice_date" />
                </flux:field>
                <flux:field>
                    <flux:label>Payment terms</flux:label>
                    <flux:select wire:model.live="form_payment_terms">
                        @foreach ($paymentTermOptions as $opt)
                            <flux:select.option value="{{ $opt['value'] }}">
                                {{ $opt['label'] }}
                                @if ($opt['days'] !== null)
                                    ({{ $opt['days'] }}d)
                                @endif
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>Due date</flux:label>
                    <flux:input type="date" wire:model="form_due_date" />
                </flux:field>
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <flux:field>
                    <flux:label>Currency</flux:label>
                    <flux:select wire:model.live="form_currency_code">
                        @foreach ($currencyOptions as $c)
                            <flux:select.option value="{{ $c['code'] }}">{{ $c['symbol'] }} — {{ $c['name'] }} ({{ $c['code'] }})</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>Tax rate (%)</flux:label>
                    <flux:input wire:model.live="form_tax_rate" type="text" inputmode="decimal" class="font-mono text-sm" />
                    <flux:description>0 for tax-exempt.</flux:description>
                </flux:field>
                <flux:field>
                    <flux:label>Discount (%)</flux:label>
                    <flux:input wire:model.live="form_discount_percent" type="text" inputmode="decimal" class="font-mono text-sm" />
                    <flux:description>Applied before tax.</flux:description>
                </flux:field>
            </div>

            <div class="h-px bg-zinc-800/80"></div>

            <div class="flex items-center justify-between">
                <flux:heading size="sm">Line items</flux:heading>
                <flux:button type="button" size="sm" variant="subtle" wire:click="addLine">+ Add item</flux:button>
            </div>

            <div class="overflow-hidden rounded-xl border border-zinc-700/80">
                <div
                    class="hidden grid-cols-[1fr_72px_100px_90px_32px] gap-3 border-b border-zinc-700/80 px-5 py-2.5 text-[11px] font-medium uppercase tracking-wide text-zinc-400 sm:grid"
                >
                    <span>Description</span>
                    <span class="text-center">Qty</span>
                    <span class="text-right">Rate ({{ trim($sym) }})</span>
                    <span class="text-right">Amount</span>
                    <span></span>
                </div>
                @foreach ($form_lines as $i => $line)
                    @php
                        $lt = ((float) ($line['quantity'] ?? 0)) * ((float) ($line['rate'] ?? 0));
                    @endphp
                    <div
                        wire:key="line-{{ $i }}"
                        class="grid grid-cols-1 gap-3 border-b border-zinc-800/80 px-4 py-3 last:border-b-0 sm:grid-cols-[1fr_72px_100px_90px_32px] sm:items-center sm:px-5"
                    >
                        <flux:input wire:model.live="form_lines.{{ $i }}.description" placeholder="e.g. Monthly website maintenance" />
                        <flux:input
                            wire:model.live="form_lines.{{ $i }}.quantity"
                            class="text-center font-mono text-sm"
                            type="text"
                            inputmode="decimal"
                        />
                        <flux:input
                            wire:model.live="form_lines.{{ $i }}.rate"
                            class="text-right font-mono text-sm"
                            type="text"
                            inputmode="decimal"
                        />
                        <div class="hidden text-right font-mono text-sm font-medium text-zinc-300 sm:block">{{ $fmt($lt, $sym) }}</div>
                        <div class="flex justify-end sm:block">
                            <button
                                type="button"
                                wire:click="removeLine({{ $i }})"
                                @disabled(count($form_lines) <= 1)
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-red-500/10 hover:text-red-400 disabled:opacity-20 disabled:hover:bg-transparent disabled:hover:text-zinc-500"
                                title="Remove item"
                            >
                                ×
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-col items-end gap-2 pr-0 sm:pr-12">
                <div class="flex gap-8 text-sm text-zinc-400">
                    <span class="min-w-[110px] text-right">Subtotal</span>
                    <span class="min-w-[100px] text-right font-mono font-medium text-zinc-100">{{ $fmt($t['subtotal'], $sym) }}</span>
                </div>
                @if ((float) $form_discount_percent > 0)
                    <div class="flex gap-8 text-sm text-emerald-400/90">
                        <span class="min-w-[110px] text-right">Discount ({{ $form_discount_percent }}%)</span>
                        <span class="min-w-[100px] text-right font-mono">−{{ $fmt($t['discount'], $sym) }}</span>
                    </div>
                @endif
                <div class="flex gap-8 text-sm text-zinc-500">
                    <span class="min-w-[110px] text-right">Tax ({{ $form_tax_rate }}%)</span>
                    <span class="min-w-[100px] text-right font-mono">{{ $fmt($t['tax'], $sym) }}</span>
                </div>
                <div class="mt-1 flex gap-8 border-t border-zinc-700/80 pt-3 text-lg font-semibold text-zinc-100">
                    <span class="min-w-[110px] text-right">Total</span>
                    <span class="min-w-[100px] text-right font-mono">{{ $fmt($t['total'], $sym) }}</span>
                </div>
            </div>

            <div class="h-px bg-zinc-800/80"></div>

            <flux:field>
                <flux:label>Payment details <span class="font-normal text-zinc-500">Shown on invoice</span></flux:label>
                <flux:textarea wire:model="form_payment_details" rows="3" class="font-mono text-sm" />
            </flux:field>

            <flux:field>
                <flux:label>Notes <span class="font-normal text-zinc-500">Optional — visible to client</span></flux:label>
                <flux:textarea wire:model="form_notes" rows="3" />
            </flux:field>

            <div class="flex flex-wrap gap-3 pb-12">
                <flux:button type="button" variant="primary" wire:click="saveInvoice">Create invoice</flux:button>
                <flux:button type="button" variant="subtle" wire:click="previewDraft">Preview</flux:button>
                <flux:button type="button" variant="ghost" wire:click="cancelCreate">Cancel</flux:button>
            </div>
        </div>
    @endif

    @if ($screen === 'preview' && $previewData)
        @php
            $sym = Invoice::currencySymbol($previewData['currency_code']);
            $badge = 'bg-amber-100 text-amber-700';
        @endphp
        <div class="mb-8">
            <button
                type="button"
                wire:click="backFromPreview"
                class="mb-4 inline-flex items-center gap-1 text-sm text-zinc-500 transition hover:text-zinc-300"
            >
                <span aria-hidden="true">←</span>
                Back to edit
            </button>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight text-zinc-100">{{ $previewData['number'] }}</h1>
                <span class="inline-flex items-center gap-1 rounded-md px-2.5 py-0.5 text-xs font-semibold {{ $badge }}">Preview</span>
                <div class="ml-auto flex flex-wrap gap-2">
                    <flux:button type="button" size="sm" variant="subtle" onclick="window.pixelkraftPrintInvoice?.()">
                        Print / PDF
                    </flux:button>
                </div>
            </div>
        </div>

        <div id="invoicePrint" class="mx-auto max-w-[800px] rounded-xl bg-white text-zinc-900 shadow-xl ring-1 ring-white/10">
            <div class="p-10">
                <div class="mb-8 flex justify-between gap-6">
                    <div>
                        <h2 class="text-[28px] font-bold text-zinc-900">Invoice</h2>
                        <div class="font-mono text-sm text-zinc-500">{{ $previewData['number'] }}</div>
                    </div>
                    <div class="text-right text-sm leading-relaxed text-zinc-500">
                        {!! nl2br(e($previewData['from_address'] ?? '')) !!}
                    </div>
                </div>

                <div class="mb-8 grid gap-8 sm:grid-cols-2">
                    <div>
                        <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Bill to</div>
                        <div class="whitespace-pre-line text-sm leading-relaxed text-zinc-700">{{ $previewData['bill_to'] ?? '' }}</div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Invoice date</div>
                            <div class="text-sm text-zinc-700">{{ $previewData['invoice_date'] }}</div>
                        </div>
                        <div>
                            <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Due date</div>
                            <div class="text-sm text-zinc-700">{{ $previewData['due_date'] }}</div>
                        </div>
                    </div>
                </div>

                <table class="mb-6 w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="border-b-2 border-zinc-200 pb-2 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Description</th>
                            <th class="border-b-2 border-zinc-200 pb-2 text-right text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Qty</th>
                            <th class="border-b-2 border-zinc-200 pb-2 text-right text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Rate</th>
                            <th class="border-b-2 border-zinc-200 pb-2 text-right text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($previewData['items'] as $item)
                            @php
                                $amt = $item['quantity'] * $item['rate'];
                            @endphp
                            <tr>
                                <td class="border-b border-zinc-100 py-2.5 text-sm text-zinc-800">{{ $item['description'] }}</td>
                                <td class="border-b border-zinc-100 py-2.5 text-right font-mono text-sm text-zinc-700">{{ $item['quantity'] }}</td>
                                <td class="border-b border-zinc-100 py-2.5 text-right font-mono text-sm text-zinc-700">{{ $fmt($item['rate'], $sym) }}</td>
                                <td class="border-b border-zinc-100 py-2.5 text-right font-mono text-sm font-medium text-zinc-800">{{ $fmt($amt, $sym) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mb-6 flex flex-col items-end gap-1.5">
                    <div class="flex gap-8 text-sm text-zinc-500">
                        <span class="min-w-[100px] text-right">Subtotal</span>
                        <span class="min-w-[80px] text-right font-mono font-medium text-zinc-700">{{ $fmt((float) $previewData['subtotal'], $sym) }}</span>
                    </div>
                    @if ((float) $previewData['discount_percent'] > 0)
                        <div class="flex gap-8 text-sm text-emerald-600">
                            <span class="min-w-[100px] text-right">Discount ({{ $previewData['discount_percent'] }}%)</span>
                            <span class="min-w-[80px] text-right font-mono">−{{ $fmt((float) $previewData['discount_amount'], $sym) }}</span>
                        </div>
                    @endif
                    <div class="flex gap-8 text-sm text-zinc-500">
                        <span class="min-w-[100px] text-right">Tax ({{ $previewData['tax_rate'] }}%)</span>
                        <span class="min-w-[80px] text-right font-mono text-zinc-700">{{ $fmt((float) $previewData['tax_amount'], $sym) }}</span>
                    </div>
                    <div class="mt-1 flex gap-8 border-t-2 border-zinc-900 pt-2 text-lg font-semibold text-zinc-900">
                        <span class="min-w-[100px] text-right">Total</span>
                        <span class="min-w-[80px] text-right font-mono">{{ $fmt((float) $previewData['total'], $sym) }}</span>
                    </div>
                </div>

                @if (! empty($previewData['payment_details']))
                    <div class="mb-4">
                        <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Payment details</div>
                        <div class="whitespace-pre-line font-mono text-sm leading-relaxed text-zinc-600">{{ $previewData['payment_details'] }}</div>
                    </div>
                @endif

                @if (! empty($previewData['notes']))
                    <div class="rounded-lg bg-zinc-50 p-4 text-sm leading-relaxed text-zinc-500">
                        <strong class="text-zinc-600">Notes:</strong>
                        {{ $previewData['notes'] }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if ($screen === 'show' && $activeInvoice)
        @php
            $inv = $activeInvoice;
            $sym = $inv->displayCurrencySymbol();
            $badge =
                $inv->status === Invoice::STATUS_PAID
                    ? 'bg-emerald-100 text-emerald-700'
                    : ($inv->isOverdue()
                        ? 'bg-red-100 text-red-700'
                        : 'bg-amber-100 text-amber-700');
        @endphp
        <div class="mb-8">
            <button
                type="button"
                wire:click="backToList"
                class="mb-4 inline-flex items-center gap-1 text-sm text-zinc-500 transition hover:text-zinc-300"
            >
                <span aria-hidden="true">←</span>
                Invoices
            </button>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight text-zinc-100">{{ $inv->number }}</h1>
                <span class="inline-flex items-center gap-1 rounded-md px-2.5 py-0.5 text-xs font-semibold {{ $badge }}">● {{ $inv->displayStatus() }}</span>
                <div class="ml-auto flex flex-wrap gap-2">
                    @if ($inv->status === Invoice::STATUS_UNPAID)
                        <flux:button type="button" size="sm" variant="subtle" wire:click="markPaid" wire:confirm="Mark this invoice as paid?">
                            Mark as paid
                        </flux:button>
                    @endif
                    <flux:button type="button" size="sm" variant="subtle" onclick="window.pixelkraftPrintInvoice?.()">Print / PDF</flux:button>
                    <flux:button type="button" size="sm" variant="subtle" wire:click="duplicate">Duplicate</flux:button>
                </div>
            </div>
        </div>

        <div id="invoicePrint" class="mx-auto max-w-[800px] rounded-xl bg-white text-zinc-900 shadow-xl ring-1 ring-white/10">
            <div class="p-10">
                <div class="mb-8 flex justify-between gap-6">
                    <div>
                        <h2 class="text-[28px] font-bold text-zinc-900">Invoice</h2>
                        <div class="font-mono text-sm text-zinc-500">{{ $inv->number }}</div>
                    </div>
                    <div class="text-right text-sm leading-relaxed text-zinc-500">
                        {!! nl2br(e($inv->from_address ?? '')) !!}
                    </div>
                </div>

                <div class="mb-8 grid gap-8 sm:grid-cols-2">
                    <div>
                        <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Bill to</div>
                        <div class="whitespace-pre-line text-sm leading-relaxed text-zinc-700">{{ $inv->bill_to ?? '' }}</div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Invoice date</div>
                            <div class="text-sm text-zinc-700">{{ $inv->invoice_date->toDateString() }}</div>
                        </div>
                        <div>
                            <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Due date</div>
                            <div class="text-sm text-zinc-700">{{ $inv->due_date->toDateString() }}</div>
                        </div>
                        <div>
                            <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Status</div>
                            <span class="inline-flex rounded-md px-2 py-0.5 text-[11px] font-semibold {{ $badge }}">{{ $inv->displayStatus() }}</span>
                        </div>
                        <div>
                            <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Currency</div>
                            <div class="font-mono text-sm text-zinc-700">{{ trim($sym) }} ({{ $inv->currency_code }})</div>
                        </div>
                    </div>
                </div>

                <table class="mb-6 w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="border-b-2 border-zinc-200 pb-2 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Description</th>
                            <th class="border-b-2 border-zinc-200 pb-2 text-right text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Qty</th>
                            <th class="border-b-2 border-zinc-200 pb-2 text-right text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Rate</th>
                            <th class="border-b-2 border-zinc-200 pb-2 text-right text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($inv->items as $item)
                            @php
                                $amt = (float) $item->quantity * (float) $item->rate;
                            @endphp
                            <tr>
                                <td class="border-b border-zinc-100 py-2.5 text-sm text-zinc-800">{{ $item->description }}</td>
                                <td class="border-b border-zinc-100 py-2.5 text-right font-mono text-sm text-zinc-700">{{ $item->quantity }}</td>
                                <td class="border-b border-zinc-100 py-2.5 text-right font-mono text-sm text-zinc-700">{{ $fmt((float) $item->rate, $sym) }}</td>
                                <td class="border-b border-zinc-100 py-2.5 text-right font-mono text-sm font-medium text-zinc-800">{{ $fmt($amt, $sym) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mb-6 flex flex-col items-end gap-1.5">
                    <div class="flex gap-8 text-sm text-zinc-500">
                        <span class="min-w-[100px] text-right">Subtotal</span>
                        <span class="min-w-[80px] text-right font-mono font-medium text-zinc-700">{{ $fmt((float) $inv->subtotal(), $sym) }}</span>
                    </div>
                    @if ((float) $inv->discount_percent > 0)
                        <div class="flex gap-8 text-sm text-emerald-600">
                            <span class="min-w-[100px] text-right">Discount ({{ $inv->discount_percent }}%)</span>
                            <span class="min-w-[80px] text-right font-mono">−{{ $fmt((float) $inv->discountAmount(), $sym) }}</span>
                        </div>
                    @endif
                    <div class="flex gap-8 text-sm text-zinc-500">
                        <span class="min-w-[100px] text-right">Tax ({{ $inv->tax_rate }}%)</span>
                        <span class="min-w-[80px] text-right font-mono text-zinc-700">{{ $fmt((float) $inv->taxAmount(), $sym) }}</span>
                    </div>
                    <div class="mt-1 flex gap-8 border-t-2 border-zinc-900 pt-2 text-lg font-semibold text-zinc-900">
                        <span class="min-w-[100px] text-right">Total</span>
                        <span class="min-w-[80px] text-right font-mono">{{ $fmt((float) $inv->total(), $sym) }}</span>
                    </div>
                </div>

                @if ($inv->payment_details)
                    <div class="mb-4">
                        <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Payment details</div>
                        <div class="whitespace-pre-line font-mono text-sm leading-relaxed text-zinc-600">{{ $inv->payment_details }}</div>
                    </div>
                @endif

                @if ($inv->notes)
                    <div class="rounded-lg bg-zinc-50 p-4 text-sm leading-relaxed text-zinc-500">
                        <strong class="text-zinc-600">Notes:</strong>
                        {{ $inv->notes }}
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

<script>
    window.pixelkraftPrintInvoice = function () {
        const el = document.getElementById('invoicePrint');
        if (!el) return;
        const w = window.open('', '', 'width=820,height=960');
        if (!w) return;
        w.document.write(
            '<!DOCTYPE html><html><head><title>Invoice</title>' +
                '<link rel="preconnect" href="https://fonts.bunny.net">' +
                '<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />' +
                '<style>' +
                '*{margin:0;padding:0;box-sizing:border-box}' +
                "body{font-family:'Inter',system-ui,sans-serif;padding:40px;color:#18181b;-webkit-print-color-adjust:exact;print-color-adjust:exact}" +
                'h2{font-size:28px;font-weight:700;margin-bottom:4px}' +
                '.inv-p-id{font-family:ui-monospace,monospace;font-size:14px;color:#71717a;margin-bottom:24px}' +
                '.inv-p-grid{display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-bottom:32px}' +
                '.inv-p-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:#a1a1aa;margin-bottom:4px}' +
                '.inv-p-val{font-size:14px;color:#3f3f46;line-height:1.5;white-space:pre-line}' +
                '.inv-p-table{width:100%;border-collapse:collapse;margin-bottom:24px}' +
                '.inv-p-table th{text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;color:#a1a1aa;padding:8px 0;border-bottom:2px solid #e4e4e7}' +
                '.inv-p-table td{padding:10px 0;font-size:14px;color:#3f3f46;border-bottom:1px solid #f4f4f5}' +
                '.text-right{text-align:right}' +
                '.mono{font-family:ui-monospace,monospace;font-size:13px}' +
                '.inv-p-totals{display:flex;flex-direction:column;align-items:flex-end;gap:6px;margin-bottom:24px}' +
                '.inv-p-total-row{display:flex;gap:32px;font-size:14px;color:#71717a}' +
                '.inv-p-total-row .label{min-width:100px;text-align:right}' +
                '.inv-p-total-row .val{font-family:ui-monospace,monospace;font-weight:500;min-width:80px;text-align:right;color:#27272a}' +
                '.inv-p-total-row.grand{font-size:18px;font-weight:600;color:#18181b;border-top:2px solid #18181b;padding-top:8px}' +
                '.inv-p-total-row.grand .val{color:#18181b}' +
                '.inv-p-notes{font-size:13px;color:#71717a;line-height:1.5;padding:16px;background:#fafafa;border-radius:8px}' +
                '@media print{body{padding:20px}}' +
                '</style></head><body>' +
                el.innerHTML +
                '</body></html>'
        );
        w.document.close();
        setTimeout(function () {
            w.print();
        }, 300);
    };
</script>
