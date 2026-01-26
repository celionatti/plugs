<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Booking Confirmation - {{ $booking_id ?? 'BK-772' }}</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            color: #1f2937;
            background-color: #f9fafb;
        }

        .header {
            background-color: #111827;
            color: white;
            padding: 40px;
            text-align: center;
        }

        .brand {
            font-size: 24px;
            font-weight: 800;
            color: #10b981;
            margin-bottom: 8px;
        }

        .container {
            max-width: 800px;
            margin: -30px auto 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 40px;
        }

        .itinerary-grid {
            display: table;
            width: 100%;
            margin-bottom: 30px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 30px;
        }

        .itinerary-col {
            display: table-cell;
            width: 50%;
        }

        .label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .value {
            font-size: 18px;
            font-weight: 700;
        }

        .property-card {
            background-color: #f3f4f6;
            padding: 24px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .property-name {
            font-size: 20px;
            font-weight: 800;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="brand">{{ $company_name ?? 'PLUGS STAY' }}</div>
        <div style="font-size: 16px; opacity: 0.9;">Your Reservation is Confirmed</div>
    </div>
    <div class="container">
        <div style="margin-bottom: 30px;">
            <div class="label">Booking ID</div>
            <div class="value">#{{ $booking_id ?? 'BS-2026-9003' }}</div>
        </div>
        <div class="itinerary-grid">
            <div class="itinerary-col">
                <div class="label">Check-In</div>
                <div class="value">{{ $check_in_date ?? 'Sun, 15 Mar 2026' }}</div>
            </div>
            <div class="itinerary-col" style="padding-left: 20px; border-left: 1px solid #e5e7eb;">
                <div class="label">Check-Out</div>
                <div class="value">{{ $check_out_date ?? 'Wed, 18 Mar 2026' }}</div>
            </div>
        </div>
        <div class="property-card">
            <div class="label">Property / Location</div>
            <div class="property-name">{{ $property_name ?? 'Ocean Breeze Resort' }}</div>
        </div>
        <div
            style="margin-top: 30px; background-color: #111827; color: #10b981; padding: 20px; border-radius: 8px; text-align: right; font-size: 24px; font-weight: bold;">
            {{ $currency ?? '$' }}{{ number_format($total_price ?? 1250, 2) }}</div>
    </div>
</body>

</html>