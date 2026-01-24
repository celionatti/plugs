<!DOCTYPE html>
<?php /** @var string $childContent */ ?>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plugs Performance Debugger</title>
    <style>
        :root {
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent: #8b5cf6;
            --accent-glow: rgba(139, 92, 246, 0.3);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --border: #334155;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .brand {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #a78bfa, #2dd4bf);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .table th,
        .table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .table th {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.875rem;
            background: rgba(0, 0, 0, 0.2);
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .table tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning);
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger);
        }

        .badge-neutral {
            background: rgba(148, 163, 184, 0.2);
            color: var(--text-secondary);
        }

        .method {
            font-family: monospace;
            font-weight: 700;
        }

        .method-GET {
            color: #60a5fa;
        }

        .method-POST {
            color: #2dd4bf;
        }

        .method-PUT {
            color: #f59e0b;
        }

        .method-DELETE {
            color: #ef4444;
        }

        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: 1px solid transparent;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
            box-shadow: 0 0 10px var(--accent-glow);
        }

        .btn-primary:hover {
            background: #7c3aed;
        }

        .btn-outline {
            background: transparent;
            border-color: var(--border);
            color: var(--text-secondary);
        }

        .btn-outline:hover {
            border-color: var(--text-primary);
            color: var(--text-primary);
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-card);
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent), #2dd4bf);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        code {
            background: rgba(0, 0, 0, 0.3);
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-family: 'Fira Code', monospace;
            font-size: 0.85em;
            color: #e2e8f0;
        }

        .back-link {
            color: var(--text-secondary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .back-link:hover {
            color: var(--text-primary);
        }

        .nav-tabs {
            display: flex;
            border-bottom: 1px solid var(--border);
            padding: 0 1.5rem;
        }

        .nav-item {
            padding: 1rem;
            color: var(--text-secondary);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
        }

        .nav-item.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }

        .tab-content {
            display: none;
            padding: 1.5rem;
        }

        .tab-content.active {
            display: block;
        }

        pre {
            background: #000;
            padding: 1em;
            border-radius: 8px;
            overflow-x: auto;
            color: #a5b4fc;
        }
    </style>
</head>

<body>
    <div class="container">
        <header class="header">
            <div class="brand">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
                Plugs Profiler
            </div>
            <nav>
                <a href="/debug/performance" class="btn btn-outline">Requests</a>
                <a href="/debug/performance/latest" class="btn btn-primary" style="margin-left: 0.5rem;">Latest
                    Request</a>
            </nav>
        </header>

        <?= $childContent ?>

    </div>

    <script>
        function showTab(id) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
            document.getElementById(id).classList.add('active');
            document.querySelector(`[onclick="showTab('${id}')"]`).classList.add('active');
        }
    </script>
</body>

</html>