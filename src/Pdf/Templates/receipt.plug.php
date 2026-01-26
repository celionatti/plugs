<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Receipt - {{ $receipt_number ?? 'REC-001' }}</title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            color: #1a1a1a;
            background-color: #f0f2f5;
            padding: 50px;
            font-size: 14px;
        }

        .receipt-card {
            background: white;
            width: 350px;
            margin: 0 auto;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border-top: 5px solid #198754;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .brand {
            font-size: 20px;
            font-weight: bold;
            color: #198754;
            margin-bottom: 5px;
        }

        .divider {
            border-top: 1px dashed #ddd;
            margin: 20px 0;
        }

        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }

        .info-label {
            display: table-cell;
            color: #888;
            font-size: 11px;
            text-transform: uppercase;
        }

        .info-value {
            display: table-cell;
            text-align: right;
            font-weight: bold;
        }

        .amount-section {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .amount-value {
            font-size: 32px;
            font-weight: bold;
            color: #1a1a1a;
        }

        .status {
            color: #198754;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="receipt-card">
        <div class="header">
            <div class="brand">{{ $company_name ?? 'PLUGS PAY' }}</div>
            <div>Official Payment Receipt</div>
        </div>
        <div class="info-row"><span class="info-label">Receipt No:</span><span class="info-value">#{{ $receipt_number ??
                'REC-998271' }}</span></div>
        <div class="info-row"><span class="info-label">Date:</span><span class="info-value">{{ $date ?? date('d M Y,
                H:i') }}</span></div>
        <div class="divider"></div>
        <div class="info-row"><span class="info-label">Payment For:</span><span class="info-value">{{ $description ??
                'Subscription' }}</span></div>
        <div class="amount-section">
            <div class="amount-value">{{ $currency ?? '$' }}{{ number_format($amount ?? 49.99, 2) }}</div>
            <div class="status">✔ Success</div>
        </div>
        <div style="text-align:center; color:#aaa; font-size:11px; margin-top:30px;">Thank you for choosing {{
            $company_name ?? 'Plugs' }}.</div>
    </div>
</body>

</html>