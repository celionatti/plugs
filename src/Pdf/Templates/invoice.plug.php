<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Invoice - {{ $invoice_number ?? 'INV-001' }}</title>
    <style>
        @page {
            margin: 0;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #333;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }

        .container {
            padding: 40px;
        }

        .header {
            background-color: #0d6efd;
            color: white;
            padding: 40px;
            display: table;
            width: 100%;
            box-sizing: border-box;
        }

        .header-left {
            display: table-cell;
            vertical-align: middle;
        }

        .header-right {
            display: table-cell;
            text-align: right;
            vertical-align: middle;
        }

        .logo {
            font-size: 32px;
            font-weight: bold;
            letter-spacing: -1px;
        }

        .invoice-title {
            font-size: 24px;
            text-transform: uppercase;
            margin: 0;
        }

        .details {
            margin: 40px 0;
            width: 100%;
        }

        .details td {
            vertical-align: top;
            width: 50%;
        }

        .section-title {
            color: #0d6efd;
            text-transform: uppercase;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 10px;
            border-bottom: 2px solid #eee;
            padding-bottom: 5px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .items-table th {
            background-color: #f8f9fa;
            text-align: left;
            padding: 12px;
            border-bottom: 2px solid #dee2e6;
            color: #666;
            text-transform: uppercase;
            font-size: 11px;
        }

        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .summary {
            float: right;
            width: 250px;
            margin-top: 30px;
        }

        .summary-row {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .summary-row span:last-child {
            float: right;
            font-weight: bold;
        }

        .total-row {
            background-color: #f8f9fa;
            padding: 15px;
            margin-top: 10px;
            border-radius: 4px;
        }

        .total-row span:last-child {
            font-size: 18px;
            color: #0d6efd;
        }

        .footer {
            position: fixed;
            bottom: 40px;
            left: 40px;
            right: 40px;
            text-align: center;
            color: #999;
            font-size: 11px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .status-stamp {
            position: absolute;
            top: 200px;
            right: 40px;
            border: 4px solid #198754;
            color: #198754;
            padding: 10px 20px;
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
            transform: rotate(-15deg);
            opacity: 0.3;
            border-radius: 10px;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="header-left">
            <div class="logo">{{ $company_name ?? 'PLUGS FRAMEWORK' }}</div>
            <div style="font-size: 13px; opacity: 0.8;">{{ $company_address ?? '123 Tech Street, Digital City' }}</div>
        </div>
        <div class="header-right">
            <h1 class="invoice-title">Invoice</h1>
            <div style="font-size: 14px; margin-top: 5px;">#{{ $invoice_number ?? 'INV-2024-001' }}</div>
            <div style="font-size: 14px;">Date: {{ $date ?? date('d M Y') }}</div>
        </div>
    </div>
    @if(($status ?? '') == 'paid') <div class="status-stamp">Paid</div> @endif
    <div class="container">
        <table class="details">
            <tr>
                <td>
                    <div class="section-title">Bill To</div>
                    <div style="font-weight: bold; font-size: 16px;">{{ $customer_name ?? 'John Doe' }}</div>
                    <div style="color: #666;">{{ $customer_address ?? '456 Client Road' }}<br>{{ $customer_email ??
                        'john@example.com' }}</div>
                </td>
                <td style="text-align: right;">
                    <div class="section-title">Payment Info</div>
                    <div style="color: #666;">Method: {{ $payment_method ?? 'Direct Bank Transfer' }}<br>Due Date: {{
                        $due_date ?? date('d M Y', strtotime('+14 days')) }}</div>
                </td>
            </tr>
        </table>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="text-align: center; width: 60px;">Qty</th>
                    <th style="text-align: right; width: 100px;">Unit Price</th>
                    <th style="text-align: right; width: 100px;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items ?? [] as $item)
                <tr>
                    <td>
                        <div style="font-weight: bold;">{{ $item['name'] }}</div>
                        <div style="font-size: 11px; color: #666;">{{ $item['description'] ?? '' }}</div>
                    </td>
                    <td style="text-align: center;">{{ $item['quantity'] }}</td>
                    <td style="text-align: right;">{{ number_format($item['price'], 2) }}</td>
                    <td style="text-align: right;">{{ number_format($item['quantity'] * $item['price'], 2) }}</td>
                </tr>
                @endforeach
                @if(empty($items))
                <tr>
                    <td>App Development Service</td>
                    <td style="text-align: center;">1</td>
                    <td style="text-align: right;">1,500.00</td>
                    <td style="text-align: right;">1,500.00</td>
                </tr>
                @endif
            </tbody>
        </table>
        <div class="summary">
            <div class="summary-row"><span>Subtotal</span><span>{{ $currency ?? '$' }}{{ number_format($subtotal ??
                    1500, 2) }}</span></div>
            <div class="summary-row"><span>Tax ({{ $tax_rate ?? 0 }}%)</span><span>{{ $currency ?? '$' }}{{
                    number_format($tax ?? 0, 2) }}</span></div>
            <div class="summary-row total-row"><span>Total Amount</span><span>{{ $currency ?? '$' }}{{
                    number_format($total ?? 1500, 2) }}</span></div>
        </div>
    </div>
    <div class="footer">Generated by Plugs Framework PDF Engine</div>
</body>

</html>