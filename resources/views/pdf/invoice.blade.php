<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Arial, sans-serif;
            font-size: 13px;
            color: #18181b;
            background: #fff;
            padding: 48px 56px;
            line-height: 1.5;
        }

        /* Header */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 36px;
        }
        .header-left  { display: table-cell; vertical-align: top; width: 50%; }
        .header-right { display: table-cell; vertical-align: top; width: 50%; text-align: right; }

        h1 { font-size: 28px; font-weight: 700; color: #09090b; letter-spacing: -0.5px; }
        .invoice-number { font-family: monospace; font-size: 13px; color: #71717a; margin-top: 4px; }
        .from-address { font-size: 12px; color: #71717a; white-space: pre-wrap; line-height: 1.6; }

        /* Meta grid */
        .meta {
            display: table;
            width: 100%;
            margin-bottom: 36px;
        }
        .meta-bill  { display: table-cell; vertical-align: top; width: 50%; padding-right: 24px; }
        .meta-dates { display: table-cell; vertical-align: top; width: 50%; }
        .meta-dates-inner { display: table; width: 100%; }
        .meta-date-col { display: table-cell; vertical-align: top; width: 50%; }

        .label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #a1a1aa;
            margin-bottom: 5px;
        }
        .bill-to-text {
            font-size: 13px;
            color: #3f3f46;
            white-space: pre-wrap;
            line-height: 1.6;
        }
        .date-value { font-size: 13px; color: #3f3f46; }

        /* Items table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
        }
        thead th {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #a1a1aa;
            padding-bottom: 8px;
            border-bottom: 2px solid #e4e4e7;
        }
        thead th:first-child { text-align: left; }
        thead th:not(:first-child) { text-align: right; }

        tbody td {
            padding: 10px 0;
            border-bottom: 1px solid #f4f4f5;
            font-size: 13px;
            color: #3f3f46;
            vertical-align: top;
        }
        tbody td:first-child { text-align: left; color: #27272a; }
        tbody td:not(:first-child) { text-align: right; font-family: monospace; }
        tbody td:last-child { font-weight: 600; color: #18181b; }

        /* Totals */
        .totals { width: 100%; margin-bottom: 28px; }
        .totals-row { display: table; width: 100%; }
        .totals-spacer { display: table-cell; width: 60%; }
        .totals-label { display: table-cell; width: 22%; text-align: right; font-size: 13px; color: #71717a; padding: 3px 0; }
        .totals-amount { display: table-cell; width: 18%; text-align: right; font-family: monospace; font-size: 13px; color: #3f3f46; padding: 3px 0; font-weight: 500; }
        .totals-label.discount { color: #16a34a; }
        .totals-amount.discount { color: #16a34a; }
        .totals-total-label { display: table-cell; width: 22%; text-align: right; font-size: 16px; font-weight: 700; color: #09090b; padding-top: 10px; border-top: 2px solid #09090b; }
        .totals-total-amount { display: table-cell; width: 18%; text-align: right; font-family: monospace; font-size: 16px; font-weight: 700; color: #09090b; padding-top: 10px; border-top: 2px solid #09090b; }

        /* Payment & Notes */
        .section-label { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #a1a1aa; margin-bottom: 6px; }
        .payment-details {
            font-family: monospace;
            font-size: 12px;
            color: #52525b;
            white-space: pre-wrap;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .notes {
            background: #fafafa;
            border: 1px solid #f4f4f5;
            border-radius: 8px;
            padding: 14px 16px;
            font-size: 12px;
            color: #71717a;
            line-height: 1.6;
        }
        .notes strong { color: #52525b; }

        .divider { border: none; border-top: 1px solid #f4f4f5; margin: 24px 0; }
    </style>
</head>
<body>

    <div class="header">
        <div class="header-left">
            <h1>Invoice</h1>
            <div class="invoice-number">{{ $invoice->number }}</div>
        </div>
        @if ($invoice->from_address)
            <div class="header-right">
                <div class="from-address">{{ $invoice->from_address }}</div>
            </div>
        @endif
    </div>

    <div class="meta">
        <div class="meta-bill">
            <div class="label">Bill to</div>
            <div class="bill-to-text">{{ $invoice->bill_to ?? '' }}</div>
        </div>
        <div class="meta-dates">
            <div class="meta-dates-inner">
                <div class="meta-date-col">
                    <div class="label">Invoice date</div>
                    <div class="date-value">{{ $invoice->invoice_date->format('M j, Y') }}</div>
                </div>
                <div class="meta-date-col">
                    <div class="label">Due date</div>
                    <div class="date-value">{{ $invoice->due_date ? $invoice->due_date->format('M j, Y') : '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    @php
        $sym = $invoice->displayCurrencySymbol();
        $fmt = function (float $amount, string $s): string {
            $s = trim($s);
            return (strlen($s) > 1 && !str_ends_with($s, ' ') ? $s . ' ' : $s) . number_format($amount, 2, '.', '');
        };
    @endphp

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Qty</th>
                <th>Rate</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                @php $amt = (float) $item->quantity * (float) $item->rate; @endphp
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $fmt((float) $item->rate, $sym) }}</td>
                    <td>{{ $fmt($amt, $sym) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="totals-row">
            <div class="totals-spacer"></div>
            <div class="totals-label">Subtotal</div>
            <div class="totals-amount">{{ $fmt((float) $invoice->subtotal(), $sym) }}</div>
        </div>
        @if ((float) $invoice->discount_percent > 0)
            <div class="totals-row">
                <div class="totals-spacer"></div>
                <div class="totals-label discount">Discount ({{ rtrim(rtrim($invoice->discount_percent, '0'), '.') }}%)</div>
                <div class="totals-amount discount">−{{ $fmt((float) $invoice->discountAmount(), $sym) }}</div>
            </div>
        @endif
        @if ((float) $invoice->tax_rate > 0)
            <div class="totals-row">
                <div class="totals-spacer"></div>
                <div class="totals-label">Tax ({{ rtrim(rtrim($invoice->tax_rate, '0'), '.') }}%)</div>
                <div class="totals-amount">{{ $fmt((float) $invoice->taxAmount(), $sym) }}</div>
            </div>
        @endif
        <div class="totals-row">
            <div class="totals-spacer"></div>
            <div class="totals-total-label">Total</div>
            <div class="totals-total-amount">{{ $fmt((float) $invoice->total(), $sym) }}</div>
        </div>
    </div>

    @if ($invoice->payment_details)
        <div class="section-label">Payment details</div>
        <div class="payment-details">{{ $invoice->payment_details }}</div>
    @endif

    @if ($invoice->notes)
        <div class="notes">
            <strong>Notes:</strong> {{ $invoice->notes }}
        </div>
    @endif

</body>
</html>
