<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Ticket - {{ $ticket_id ?? 'TKT-001' }}</title>
    <style>
        body {
            font-family: 'Helvetica', sans-serif;
            background-color: #121212;
            color: #fff;
            padding: 50px;
        }

        .ticket {
            display: table;
            width: 700px;
            margin: 0 auto;
            background: #1e1e1e;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
            border: 1px solid #333;
        }

        .left-part {
            display: table-cell;
            width: 70%;
            padding: 40px;
            position: relative;
        }

        .right-part {
            display: table-cell;
            width: 30%;
            background: #252525;
            padding: 40px;
            text-align: center;
            border-left: 2px dashed #444;
            vertical-align: middle;
        }

        .event-title {
            font-size: 28px;
            font-weight: 800;
            color: #f39c12;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        .event-info {
            margin-bottom: 15px;
        }

        .info-label {
            color: #888;
            font-size: 11px;
            text-transform: uppercase;
        }

        .info-value {
            font-size: 16px;
            font-weight: 600;
        }

        .barcode {
            margin-top: 30px;
            height: 40px;
            background: repeating-linear-gradient(90deg, #fff, #fff 2px, #1e1e1e 2px, #1e1e1e 4px);
            width: 100%;
        }

        .seat {
            font-size: 40px;
            font-weight: 900;
            color: #fff;
            margin: 10px 0;
        }
    </style>
</head>

<body>
    <div class="ticket">
        <div class="left-part">
            <div class="event-title">{{ $event_name ?? 'TECH SUMMIT 2024' }}</div>
            <div class="event-info">
                <div class="info-label">Date & Time</div>
                <div class="info-value">{{ $event_date ?? 'Friday, 12 July 2024' }}</div>
            </div>
            <div class="event-info">
                <div class="info-label">Attendee</div>
                <div class="info-value">{{ $attendee_name ?? 'ALEX RIVERA' }}</div>
            </div>
            <div class="barcode"></div>
        </div>
        <div class="right-part">
            <div class="info-label">Seat / Area</div>
            <div class="seat">{{ $seat ?? 'A1' }}</div>
            <div style="font-size: 24px; font-weight: bold; color: #f39c12;">{{ $currency ?? '$' }}{{
                number_format($price ?? 299, 0) }}</div>
        </div>
    </div>
</body>

</html>