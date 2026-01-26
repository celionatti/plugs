<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>CV - {{ $name ?? 'Your Name' }}</title>
    <style>
        body {
            font-family: 'Helvetica', sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
            line-height: 1.5;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 200px;
            background-color: #2c3e50;
            color: white;
            padding: 40px 30px;
        }

        .main-content {
            margin-left: 260px;
            padding: 40px 40px 40px 0;
        }

        .name {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            text-transform: uppercase;
        }

        .section-header {
            border-bottom: 2px solid #2c3e50;
            color: #2c3e50;
            font-size: 18px;
            font-weight: bold;
            margin: 30px 0 15px;
            text-transform: uppercase;
        }

        .exp-company {
            color: #2980b9;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div style="font-weight: bold; text-transform: uppercase; margin-bottom: 10px; color: #bdc3c7;">Contact</div>
        <div style="font-size: 12px; margin-bottom: 5px;">📞 {{ $phone ?? '+123 456 7890' }}</div>
        <div style="font-size: 12px;">📧 {{ $email ?? 'hello@example.com' }}</div>
    </div>
    <div class="main-content">
        <div class="name">{{ $name ?? 'Celio Natti' }}</div>
        <div style="font-size: 18px; color: #7f8c8d;">{{ $job_title ?? 'Senior Fullstack Developer' }}</div>
        <div class="section-header">Profile</div>
        <div style="font-size: 14px;">{{ $profile ?? 'Passionate software architect focused on building high-performance
            PHP frameworks.' }}</div>
        <div class="section-header">Experience</div>
        <div class="exp-company">Plugs Labs</div>
        <div style="font-size: 13px;">Leading the development of the Plugs framework and scalable web architectures.
        </div>
    </div>
</body>

</html>