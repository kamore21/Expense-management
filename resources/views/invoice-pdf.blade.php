<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #111827;
            background: white;
            margin: 32px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 18px;
            margin-bottom: 24px;
        }
        .title {
            font-size: 28px;
            font-weight: bold;
        }
        .meta {
            text-align: right;
            line-height: 1.6;
        }
        .section {
            margin-top: 24px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #f3f4f6;
            padding: 8px 0;
        }
        .total {
            margin-top: 20px;
            font-size: 22px;
            font-weight: bold;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="title">Invoice</div>
            <div style="margin-top: 8px;">{{ $invoice->invoice_number }}</div>
        </div>
        <div class="meta">
            <div><strong>Client:</strong> {{ $invoice->client }}</div>
            <div><strong>Status:</strong> {{ $invoice->status }}</div>
            <div><strong>Due:</strong> <x-regional-date :value="$invoice->due_date" date-only /></div>
            @if ($invoice->paid_at)
                <div><strong>Paid:</strong> <x-regional-date :value="$invoice->paid_at" date-only /></div>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="row"><span>Invoice number</span><span>{{ $invoice->invoice_number }}</span></div>
        <div class="row"><span>Client</span><span>{{ $invoice->client }}</span></div>
        <div class="row"><span>Amount</span><span><x-money :amount="$invoice->amount" :currency="$currencyCode" /></span></div>
        <div class="row"><span>Status</span><span>{{ $invoice->status }}</span></div>
        <div class="row"><span>Due date</span><span><x-regional-date :value="$invoice->due_date" date-only /></span></div>
        @if ($invoice->paid_at)
            <div class="row"><span>Paid date</span><span><x-regional-date :value="$invoice->paid_at" date-only /></span></div>
        @endif
    </div>

    <div class="total">Total: <x-money :amount="$invoice->amount" :currency="$currencyCode" /></div>
</body>
</html>
