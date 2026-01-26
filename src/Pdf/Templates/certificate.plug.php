<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Certificate</title>
    <style>
        @page {
            margin: 0;
            size: a4 landscape;
        }

        body {
            font-family: 'Georgia', serif;
            margin: 0;
            padding: 0;
            background-color: #fff;
            color: #2c3e50;
        }

        .outer-border {
            border: 20px solid #f1c40f;
            height: 520px;
            padding: 20px;
            margin: 20px;
        }

        .inner-border {
            border: 5px solid #2c3e50;
            height: 470px;
            padding: 40px;
            text-align: center;
        }

        .certificate-header {
            font-size: 50px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 5px;
            margin-bottom: 20px;
        }

        .participant-name {
            font-size: 45px;
            font-weight: bold;
            border-bottom: 2px solid #2c3e50;
            display: inline-block;
            margin: 20px 0;
            padding: 0 50px;
            color: #2980b9;
        }

        .seal {
            width: 100px;
            height: 100px;
            background-color: #f1c40f;
            border-radius: 50%;
            display: inline-block;
            line-height: 100px;
            font-size: 40px;
            color: white;
        }
    </style>
</head>

<body>
    <div class="outer-border">
        <div class="inner-border">
            <div class="certificate-header">Certificate</div>
            <div style="font-size: 20px; font-style: italic;">OF ACHIEVEMENT</div>
            <div style="font-size: 18px; margin-top:20px;">This is to certify that</div>
            <div class="participant-name">{{ $participant_name ?? 'Celio Natti' }}</div>
            <div style="font-size: 18px; width: 80%; margin: 0 auto;">has successfully completed the Professional PHP
                Web Development Course with outstanding performance.</div>
            <div style="margin-top: 60px;">
                <div class="seal">★</div>
            </div>
        </div>
    </div>
</body>

</html>