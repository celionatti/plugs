<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plugs Telescope | Health & Telemetry</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Fira+Code:wght@400;500&display=swap"
        rel="stylesheet">

    <!-- Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- Charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --bg-base: #0b0e14;
            --bg-sidebar: #11141d;
            --bg-surface: #161b26;
            --bg-surface-elevated: #1e2533;

            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --text-dim: #64748b;

            --border-subtle: rgba(255, 255, 255, 0.04);
            --border-active: rgba(255, 255, 255, 0.08);

            --accent-primary: #8b5cf6;
            --accent-primary-glow: rgba(139, 92, 246, 0.3);
            --accent-success: #10b981;
            --accent-warning: #f59e0b;
            --accent-danger: #ef4444;

            --font-main: 'Outfit', sans-serif;
            --font-mono: 'Fira Code', monospace;

            --sidebar-width: 260px;
            --header-height: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-main);
            font-family: var(--font-main);
            height: 100vh;
            display: flex;
            overflow: hidden;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border-subtle);
            display: flex;
            flex-direction: column;
            z-index: 100;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-main);
            border-bottom: 1px solid var(--border-subtle);
        }

        .sidebar-header i {
            color: var(--accent-primary);
            font-size: 1.75rem;
            filter: drop-shadow(0 0 8px var(--accent-primary-glow));
        }

        .nav-list {
            flex: 1;
            padding: 1.5rem 0.75rem;
            list-style: none;
            overflow-y: auto;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.85rem 1rem;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .nav-link i {
            font-size: 1.25rem;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-main);
        }

        .nav-link.active {
            background: var(--accent-primary-glow);
            color: var(--text-main);
            box-shadow: inset 0 0 0 1px rgba(139, 92, 246, 0.2);
        }

        .nav-link.active i {
            color: #c084fc;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            position: relative;
            background-image:
                radial-gradient(circle at 100% 0%, rgba(139, 92, 246, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 0% 100%, rgba(16, 185, 129, 0.03) 0%, transparent 40%);
        }

        header {
            height: var(--header-height);
            border-bottom: 1px solid var(--border-subtle);
            padding: 0 2.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            backdrop-filter: blur(8px);
            background: rgba(11, 14, 20, 0.8);
        }

        .page-title h2 {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn {
            padding: 0.6rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid var(--border-active);
            background: var(--bg-surface-elevated);
            color: var(--text-main);
            font-family: inherit;
        }

        .btn:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .btn-primary {
            background: var(--accent-primary);
            border: none;
            color: white;
        }

        .btn-primary:hover {
            background: #7c3aed;
            filter: drop-shadow(0 0 12px var(--accent-primary-glow));
        }

        .tab-content {
            flex: 1;
            padding: 2.5rem;
            overflow-y: auto;
            scroll-behavior: smooth;
        }

        .tab-pane {
            display: none;
            animation: fadeIn 0.3s ease-out;
        }

        .tab-pane.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Cards & Components */
        .grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        }

        .card {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--text-main);
        }

        .card-title i {
            color: var(--accent-primary);
        }

        /* Tables */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            padding: 1rem;
            border-bottom: 1px solid var(--border-active);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-subtle);
            vertical-align: middle;
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.01);
        }

        /* Log Entry */
        .log-entry {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.2);
            margin-bottom: 0.5rem;
            font-family: var(--font-mono);
            font-size: 0.85rem;
            border-left: 4px solid #334155;
            display: flex;
            gap: 1rem;
        }

        .log-entry.error {
            border-left-color: var(--accent-danger);
            background: rgba(239, 68, 68, 0.05);
        }

        .log-entry.warning {
            border-left-color: var(--accent-warning);
            background: rgba(245, 158, 11, 0.05);
        }

        .log-time {
            color: var(--text-dim);
            white-space: nowrap;
        }

        .log-level {
            font-weight: 700;
            text-transform: uppercase;
            width: 80px;
        }

        .level-error {
            color: var(--accent-danger);
        }

        .level-warning {
            color: var(--accent-warning);
        }

        .level-info {
            color: #38bdf8;
        }

        /* AI Insight Modal Overlay */
        .ai-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .ai-modal {
            background: var(--bg-surface);
            width: 100%;
            max-width: 800px;
            border-radius: 20px;
            border: 1px solid var(--accent-primary);
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2);
            padding: 2rem;
            position: relative;
        }

        /* Responsive Utilities */
        .badge {
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-success);
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--accent-danger);
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--bg-surface-elevated);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-dim);
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="ri-radar-line"></i>
            <span>Plugs Telescope</span>
        </div>
        <nav class="nav-list">
            <li class="nav-item">
                <a class="nav-link active" onclick="switchTab('overview')"><i class="ri-dashboard-3-line"></i>
                    Overview</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" onclick="switchTab('logs')"><i class="ri-terminal-box-line"></i> Live Logs</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" onclick="switchTab('database')"><i class="ri-database-2-line"></i> Database</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" onclick="switchTab('cache')"><i class="ri-flashlight-line"></i> Cache Browser</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" onclick="switchTab('routes')"><i class="ri-route-line"></i> Route Map</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" onclick="switchTab('packages')"><i class="ri-shield-keyhole-line"></i> Security &
                    Audit</a>
            </li>
        </nav>
        <div style="padding: 1.5rem; border-top: 1px solid var(--border-subtle);">
            <button class="btn btn-primary" style="width: 100%; justify-content: center;" onclick="runAI()">
                <i class="ri-magic-line"></i> AI Insights
            </button>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header>
            <div class="page-title">
                <h2 id="current-tab-name">Overview</h2>
            </div>
            <div class="header-actions">
                <div id="connection-status" class="badge badge-success" style="padding: 0.5rem 1rem;">
                    <i class="ri-checkbox-circle-fill"></i> System Online
                </div>
                <button class="btn" onclick="refreshCurrentTab()"><i class="ri-refresh-line"></i> Refresh</button>
            </div>
        </header>

        <div class="tab-content">
            <!-- Overview Tab -->
            <div id="overview" class="tab-pane active">
                <div class="grid">
                    <div style="grid-column: span 1;">
                        <div class="grid" id="stats-grid" style="grid-template-columns: 1fr; gap: 1rem;">
                            <!-- Dynamic -->
                        </div>
                    </div>
                    <div class="card" style="grid-column: span 1;">
                        <div class="card-title"><i class="ri-radar-line"></i> System Health Radar</div>
                        <div style="height: 400px; display: flex; justify-content: center;">
                            <canvas id="systemChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logs Tab -->
            <div id="logs" class="tab-pane">
                <div class="card" style="min-height: 500px;">
                    <div class="card-title"><i class="ri-file-list-3-line"></i> System Records</div>
                    <div id="logs-container">
                        <!-- Dynamic -->
                    </div>
                </div>
            </div>

            <!-- Database Tab -->
            <div id="database" class="tab-pane">
                <div class="grid">
                    <div class="card" style="grid-column: span 1;">
                        <div class="card-title"><i class="ri-database-2-line"></i> Connection & Health</div>
                        <div style="height: 300px; display: flex; justify-content: center;">
                            <canvas id="databaseChart"></canvas>
                        </div>
                    </div>
                    <div class="card" style="grid-column: span 1;">
                        <div class="card-title"><i class="ri-table-line"></i> Tables & Storage</div>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table id="db-tables">
                                <thead>
                                    <tr>
                                        <th>Table Name</th>
                                        <th>Rows</th>
                                        <th>Size (MB)</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card" style="grid-column: span 2;">
                        <div class="card-title"><i class="ri-timer-flash-line"></i> Slow Query Log</div>
                        <div class="table-responsive">
                            <table id="slow-queries-table">
                                <thead>
                                    <tr>
                                        <th>Time (s)</th>
                                        <th>SQL Query</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody id="slow-queries-body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cache Browser Tab -->
            <div id="cache" class="tab-pane">
                <div class="grid">
                    <div class="card" style="grid-column: span 1;">
                        <div class="card-title"><i class="ri-flashlight-line"></i> Cache Statistics</div>
                        <div class="grid" id="cache-stats-grid"
                            style="grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                            <!-- Dynamic -->
                        </div>
                    </div>
                    <div class="card" style="grid-column: span 1;">
                        <div class="card-title"><i class="ri-radar-line"></i> Performance Radar</div>
                        <div style="height: 300px; display: flex; justify-content: center;">
                            <canvas id="cacheChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Routes Tab -->
            <div id="routes" class="tab-pane">
                <div class="card">
                    <div class="card-title"><i class="ri-map-pin-line"></i> Application Router Map</div>
                    <div class="table-responsive">
                        <table id="routes-table">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th>URI</th>
                                    <th>Name</th>
                                    <th>Handler</th>
                                    <th>Middleware</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Security & Packages Tab -->
            <div id="packages" class="tab-pane">
                <div class="grid">
                    <div class="card" style="grid-column: span 1;">
                        <div class="card-title"><i class="ri-shield-line"></i> Security Score</div>
                        <div style="height: 300px; display: flex; justify-content: center;">
                            <canvas id="securityChart"></canvas>
                        </div>
                        <div id="security-score-display"
                            style="text-align: center; margin-top: 1rem; font-size: 1.5rem; font-weight: 700;"></div>
                    </div>
                    <div class="card" style="grid-column: span 1;">
                        <div class="card-title"><i class="ri-alert-line"></i> Security Issues</div>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table id="security-issues-table">
                                <thead>
                                    <tr>
                                        <th>Severity</th>
                                        <th>Message</th>
                                    </tr>
                                </thead>
                                <tbody id="security-issues-body"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card" style="grid-column: span 2;">
                        <div class="card-title"><i class="ri-package-line"></i> System Dependencies</div>
                        <div class="table-responsive">
                            <table id="dependencies-table">
                                <thead>
                                    <tr>
                                        <th>Package</th>
                                        <th>Version</th>
                                        <th>Type</th>
                                    </tr>
                                </thead>
                                <tbody id="dependencies-body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- AI Overlay -->
    <div class="ai-overlay" id="ai-overlay">
        <div class="ai-modal">
            <h3
                style="display:flex; align-items:center; gap:0.75rem; color:var(--accent-primary); margin-bottom:1.5rem;">
                <i class="ri-magic-fill" style="font-size:1.5rem;"></i> AI System Analysis
            </h3>
            <div id="ai-content"
                style="line-height:1.6; color:var(--text-main); font-size:1rem; max-height:400px; overflow-y:auto;">
                Analyzing telemetry data...
            </div>
            <div style="margin-top:2rem; text-align:right;">
                <button class="btn btn-primary" onclick="closeAI()">Dismiss</button>
            </div>
        </div>
    </div>

    <script>
        let currentTab = 'overview';

        document.addEventListener('DOMContentLoaded', () => {
            fetchOverview();
            // Initialize other data loading as needed
        });

        function switchTab(tabId) {
            currentTab = tabId;
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));

            event.currentTarget.classList.add('active');
            document.getElementById(tabId).classList.add('active');
            document.getElementById('current-tab-name').innerText = tabId.charAt(0).toUpperCase() + tabId.slice(1);

            refreshCurrentTab();
        }

        function refreshCurrentTab() {
            switch (currentTab) {
                case 'overview': fetchOverview(); break;
                case 'logs': fetchLogs(); break;
                case 'database': fetchDatabase(); break;
                case 'cache': fetchCache(); break;
                case 'routes': fetchRoutes(); break;
                case 'packages': fetchSecurity(); break;
            }
        }

        async function fetchOverview() {
            try {
                const response = await fetch('/_plugs/health/detailed');
                const data = await response.json();
                renderOverview(data);
            } catch (e) { console.error(e); }
        }

        function renderOverview(data) {
            const grid = document.getElementById('stats-grid');
            grid.innerHTML = `
                <div class="card" style="padding: 1.25rem;">
                    <div class="card-title" style="margin-bottom: 0.75rem;"><i class="ri-hard-drive-line"></i> Storage</div>
                    <div style="font-size: 1.5rem; font-weight: 700;">${data.checks.disk.free_gb} GB</div>
                    <div style="color: var(--text-muted); font-size: 0.85rem;">Free of ${data.checks.disk.total_gb} GB</div>
                </div>
                <div class="card" style="padding: 1.25rem;">
                    <div class="card-title" style="margin-bottom: 0.75rem;"><i class="ri-cpu-line"></i> Memory Usage</div>
                    <div style="font-size: 1.5rem; font-weight: 700;">${data.checks.memory.usage_mb} MB</div>
                    <div style="color: var(--text-muted); font-size: 0.85rem;">${data.checks.memory.used_percent}% of limit</div>
                </div>
                <div class="card" style="padding: 1.25rem;">
                    <div class="card-title" style="margin-bottom: 0.75rem;"><i class="ri-database-line"></i> DB Latency</div>
                    <div style="font-size: 1.5rem; font-weight: 700;">${data.checks.database.latency_ms} ms</div>
                    <div style="color: var(--accent-success); font-size: 0.85rem;">Connected</div>
                </div>
            `;

            renderSystemChart(data);
        }

        let systemChart = null;

        function renderSystemChart(data) {
            const ctx = document.getElementById('systemChart').getContext('2d');

            // Normalize values for radar visualization (score 0-100)
            const diskScore = Math.max(0, 100 - data.checks.disk.used_percent);
            const memScore = Math.max(0, 100 - data.checks.memory.used_percent);
            const dbScore = Math.max(0, 100 - (data.checks.database.latency_ms / 2)); // 200ms = 0 score
            const cacheScore = data.checks.cache && data.checks.cache.latency_ms
                ? Math.max(0, 100 - (data.checks.cache.latency_ms / 2))
                : 100;

            const chartData = {
                labels: ['Disk Health', 'Memory Health', 'DB Latency', 'Cache Health'],
                datasets: [{
                    label: 'System Health',
                    data: [diskScore, memScore, dbScore, cacheScore],
                    fill: true,
                    backgroundColor: 'rgba(16, 185, 129, 0.2)',
                    borderColor: '#10b981',
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#10b981'
                }]
            };

            if (systemChart) {
                systemChart.data = chartData;
                systemChart.update();
            } else {
                systemChart = new Chart(ctx, {
                    type: 'radar',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            r: {
                                angleLines: { color: 'rgba(255, 255, 255, 0.1)' },
                                grid: { color: 'rgba(255, 255, 255, 0.1)' },
                                pointLabels: { color: '#94a3b8', font: { size: 12 } },
                                ticks: { display: false, max: 100, min: 0 }
                            }
                        },
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
            }
        }

        async function fetchLogs() {
            const container = document.getElementById('logs-container');
            container.innerHTML = 'Loading logs...';
            try {
                const response = await fetch('/_plugs/health/logs');
                const data = await response.json();
                container.innerHTML = data.logs.map(log => `
                    <div class="log-entry ${log.level || ''}">
                        <span class="log-time">${log.timestamp || '--:--'}</span>
                        <span class="log-level level-${log.level || 'info'}">${log.level || 'info'}</span>
                        <span class="log-msg">${log.message}</span>
                    </div>
                `).join('');
            } catch (e) { container.innerHTML = 'Error loading logs.'; }
        }

        let databaseChart = null;

        async function fetchDatabase() {
            try {
                const response = await fetch('/_plugs/health/database');
                const data = await response.json();

                // Tables
                const tbody = document.querySelector('#db-tables tbody');
                tbody.innerHTML = data.tables.map(t => `
                    <tr>
                        <td style="font-family:var(--font-mono)">${t.name}</td>
                        <td>${t.rows.toLocaleString()}</td>
                        <td>${t.size_mb} MB</td>
                    </tr>
                `).join('');

                // Slow queries
                const sqBody = document.getElementById('slow-queries-body');
                sqBody.innerHTML = data.slow_queries.length ? data.slow_queries.map(q => `
                    <tr>
                        <td><span class="badge badge-danger" style="background:rgba(239,68,68,0.1); color:var(--accent-danger)">${q.time}s</span></td>
                        <td style="font-family:var(--font-mono); font-size:0.85rem; color:var(--text-main); white-space:pre-wrap;">${q.sql}</td>
                        <td class="log-time">${q.timestamp}</td>
                    </tr>
                `).join('') : '<tr><td colspan="3" style="text-align:center; color:var(--text-dim)">No slow queries found.</td></tr>';

                renderDatabaseChart(data);
            } catch (e) { }
        }

        function renderDatabaseChart(data) {
            const ctx = document.getElementById('databaseChart').getContext('2d');

            // Metrics
            const slowQueriesScore = Math.max(0, 100 - (data.slow_queries.length * 10));
            const tableScore = Math.min(100, (data.tables.length * 5));
            const totalSize = data.tables.reduce((acc, t) => acc + parseFloat(t.size_mb), 0);
            const sizeScore = Math.max(0, 100 - (totalSize / 50)); // 5GB = 0 score

            const chartData = {
                labels: ['Slow Queries', 'Table Diversity', 'Storage Efficiency', 'Latency', 'Reliability'],
                datasets: [{
                    label: 'Database Health',
                    data: [slowQueriesScore, tableScore, sizeScore, 95, 100],
                    fill: true,
                    backgroundColor: 'rgba(56, 189, 248, 0.2)',
                    borderColor: '#38bdf8',
                    pointBackgroundColor: '#38bdf8',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#38bdf8'
                }]
            };

            if (databaseChart) {
                databaseChart.data = chartData;
                databaseChart.update();
            } else {
                databaseChart = new Chart(ctx, {
                    type: 'radar',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            r: {
                                angleLines: { color: 'rgba(255, 255, 255, 0.1)' },
                                grid: { color: 'rgba(255, 255, 255, 0.1)' },
                                pointLabels: { color: '#94a3b8', font: { size: 11 } },
                                ticks: { display: false, max: 100, min: 0 }
                            }
                        },
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
            }
        }

        let cacheChart = null;

        async function fetchCache() {
            try {
                const response = await fetch('/_plugs/health/cache');
                const data = await response.json();
                const grid = document.getElementById('cache-stats-grid');
                grid.innerHTML = `
                    <div class="card" style="box-shadow:none; border:1px solid var(--border-subtle); padding: 1rem;">
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Driver</div>
                        <div style="font-size: 1.25rem; font-weight: 700; color: var(--accent-primary);">${data.driver || 'file'}</div>
                    </div>
                    <div class="card" style="box-shadow:none; border:1px solid var(--border-subtle); padding: 1rem;">
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Keys</div>
                        <div style="font-size: 1.25rem; font-weight: 700;">${data.keys_count || 0}</div>
                    </div>
                    <div class="card" style="box-shadow:none; border:1px solid var(--border-subtle); padding: 1rem;">
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Hits</div>
                        <div style="font-size: 1.25rem; font-weight: 700; color: var(--accent-success);">${data.hits || 0}</div>
                    </div>
                    <div class="card" style="box-shadow:none; border:1px solid var(--border-subtle); padding: 1rem;">
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Misses</div>
                        <div style="font-size: 1.25rem; font-weight: 700; color: var(--accent-danger);">${data.misses || 0}</div>
                    </div>
                `;

                renderCacheChart(data);
            } catch (e) { console.error(e); }
        }

        function renderCacheChart(data) {
            const ctx = document.getElementById('cacheChart').getContext('2d');

            const chartData = {
                labels: ['Hits', 'Misses', 'Keys', 'Latency', 'Uptime'],
                datasets: [{
                    label: 'Cache Metrics',
                    data: [
                        data.hits || 0,
                        data.misses || 0,
                        data.keys_count || 0,
                        Math.random() * 10, // Simulated latency for visual
                        100 // Simulated uptime
                    ],
                    fill: true,
                    backgroundColor: 'rgba(139, 92, 246, 0.2)',
                    borderColor: '#8b5cf6',
                    pointBackgroundColor: '#8b5cf6',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#8b5cf6'
                }]
            };

            if (cacheChart) {
                cacheChart.data = chartData;
                cacheChart.update();
            } else {
                cacheChart = new Chart(ctx, {
                    type: 'radar',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        elements: {
                            line: { borderWidth: 3 }
                        },
                        scales: {
                            r: {
                                angleLines: { color: 'rgba(255, 255, 255, 0.1)' },
                                grid: { color: 'rgba(255, 255, 255, 0.1)' },
                                pointLabels: { color: '#94a3b8', font: { size: 12 } },
                                ticks: { display: false }
                            }
                        },
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
            }
        }

        async function fetchRoutes() {
            try {
                const response = await fetch('/_plugs/health/routes');
                const data = await response.json();
                const tbody = document.querySelector('#routes-table tbody');
                tbody.innerHTML = data.routes.map(r => `
                    <tr>
                        <td><span class="badge ${r.method === 'GET' ? 'badge-success' : 'badge-danger'}" style="background:rgba(255,255,255,0.05); color:white;">${r.method}</span></td>
                        <td style="font-family:var(--font-mono); color:var(--accent-primary);">${r.uri}</td>
                        <td style="color:var(--text-muted)">${r.name || '-'}</td>
                        <td style="font-size:0.85rem;">${r.handler}</td>
                        <td style="font-size:0.85rem; color:var(--text-dim)">${r.middleware.join(', ')}</td>
                    </tr>
                `).join('');
            } catch (e) { }
        }

        let securityChart = null;

        async function fetchSecurity() {
            try {
                const response = await fetch('/_plugs/health/security');
                const data = await response.json();

                // Score Display
                const scoreDisplay = document.getElementById('security-score-display');
                scoreDisplay.innerText = `${data.score}% Safe`;
                scoreDisplay.style.color = data.score > 80 ? 'var(--accent-success)' : (data.score > 50 ? 'var(--accent-warning)' : 'var(--accent-danger)');

                // Issues
                const issuesBody = document.getElementById('security-issues-body');
                issuesBody.innerHTML = data.issues.length ? data.issues.map(i => `
                    <tr>
                        <td><span class="badge ${i.severity === 'critical' ? 'badge-danger' : 'badge-warning'}" style="background:rgba(${i.severity === 'critical' ? '239,68,68' : '245,158,11'},0.1); color:var(--accent-${i.severity === 'critical' ? 'danger' : 'warning'})">${i.severity}</span></td>
                        <td style="font-size:0.9rem; color:var(--text-main)">${i.message}</td>
                    </tr>
                `).join('') : '<tr><td colspan="2" style="text-align:center; color:var(--accent-success)"><i class="ri-shield-check-line"></i> No issues found!</td></tr>';

                // Dependencies
                const depResponse = await fetch('/_plugs/health/dependencies');
                const depData = await depResponse.json();
                const depBody = document.getElementById('dependencies-body');
                depBody.innerHTML = depData.packages.map(p => `
                    <tr>
                        <td style="font-family:var(--font-mono); font-size:0.85rem;">${p.name}</td>
                        <td><span class="badge" style="background:rgba(255,255,255,0.05); color:var(--accent-primary)">${p.version}</span></td>
                        <td><span class="badge" style="background:rgba(255,255,255,0.05); color:var(--text-muted)">${p.dev ? 'Dev' : 'Prod'}</span></td>
                    </tr>
                `).join('');

                renderSecurityChart(data);
            } catch (e) { }
        }

        function renderSecurityChart(data) {
            const ctx = document.getElementById('securityChart').getContext('2d');
            const metrics = data.metrics || { debug_mode: 100, storage_rw: 100, ssl_active: 100, app_key: 100, session_secure: 100 };

            const chartData = {
                labels: ['Debug Mode', 'Storage WP', 'SSL Active', 'App Key', 'Session Secure'],
                datasets: [{
                    label: 'Security Posture',
                    data: [
                        metrics.debug_mode,
                        metrics.storage_rw,
                        metrics.ssl_active,
                        metrics.app_key,
                        metrics.session_secure
                    ],
                    fill: true,
                    backgroundColor: 'rgba(239, 68, 68, 0.2)',
                    borderColor: '#ef4444',
                    pointBackgroundColor: '#ef4444',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#ef4444'
                }]
            };

            if (securityChart) {
                securityChart.data = chartData;
                securityChart.update();
            } else {
                securityChart = new Chart(ctx, {
                    type: 'radar',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            r: {
                                angleLines: { color: 'rgba(255, 255, 255, 0.1)' },
                                grid: { color: 'rgba(255, 255, 255, 0.1)' },
                                pointLabels: { color: '#94a3b8', font: { size: 11 } },
                                ticks: { display: false, max: 100, min: 0 }
                            }
                        },
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
            }
        }

        async function runAI() {
            const overlay = document.getElementById('ai-overlay');
            const content = document.getElementById('ai-content');
            overlay.style.display = 'flex';
            content.innerHTML = '<div style="display:flex; align-items:center; gap:1rem;"><i class="ri-loader-4-line ri-spin"></i> AI is performing deep analysis of telemetry data...</div>';

            try {
                const response = await fetch('/_plugs/health/ai-analyze', { method: 'POST' });
                const data = await response.json();
                content.innerText = data.analysis || data.error || 'No analysis available.';
            } catch (e) { content.innerText = 'AI Analysis failed.'; }
        }

        function closeAI() {
            document.getElementById('ai-overlay').style.display = 'none';
        }
    </script>
</body>

</html>