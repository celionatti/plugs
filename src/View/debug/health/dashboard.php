<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plugs Health Dashboard</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        :root {
            /* Futuristic Dark Theme */
            --bg-base: #0f1115;
            --bg-surface: #1a1d24;
            --bg-surface-elevated: #232730;

            --text-main: #f3f4f6;
            --text-muted: #9ca3af;

            --border-subtle: rgba(255, 255, 255, 0.05);
            --border-active: rgba(255, 255, 255, 0.1);

            --accent-primary: #6366f1;
            --accent-success: #10b981;
            --accent-warning: #f59e0b;
            --accent-danger: #ef4444;

            --glass-bg: rgba(26, 29, 36, 0.7);
            --glass-border: rgba(255, 255, 255, 0.08);

            --font-main: 'Outfit', sans-serif;
            --font-mono: 'Fira Code', monospace;
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            background-image:
                radial-gradient(circle at 15% 50%, rgba(99, 102, 241, 0.05) 0%, transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(16, 185, 129, 0.03) 0%, transparent 25%);
        }

        /* Top Navigation */
        .navbar {
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            background: var(--glass-bg);
            border-bottom: 1px solid var(--glass-border);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.25rem;
            font-weight: 600;
            letter-spacing: -0.02em;
        }

        .navbar-brand i {
            color: var(--accent-primary);
            font-size: 1.5rem;
        }

        .status-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-success);
            border: 1px solid rgba(16, 185, 129, 0.2);
            transition: all 0.3s ease;
        }

        .status-badge.unhealthy {
            background: rgba(239, 68, 68, 0.1);
            color: var(--accent-danger);
            border-color: rgba(239, 68, 68, 0.2);
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            box-shadow: 0 0 8px currentColor;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.5;
                transform: scale(1.1);
            }

            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .actions button {
            background: var(--bg-surface-elevated);
            color: var(--text-main);
            border: 1px solid var(--border-active);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-family: inherit;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .actions button:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.2);
        }

        /* Main Layout */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            width: 100%;
            flex: 1;
        }

        .header-section {
            margin-bottom: 2rem;
        }

        .header-section h1 {
            font-size: 1.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }

        .header-section p {
            color: var(--text-muted);
            font-size: 1rem;
        }

        /* Grid Layout */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .col-span-2 {
            grid-column: span 2;
        }

        @media (max-width: 1024px) {
            .col-span-2 {
                grid-column: span 1;
            }
        }

        /* Cards */
        .card {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            border-color: var(--border-active);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            font-size: 1.1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-header h3 i {
            color: var(--accent-primary);
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Metric Item */
        .metric {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-subtle);
        }

        .metric:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .metric-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .metric-value {
            font-weight: 500;
            font-family: var(--font-mono);
            font-size: 0.9rem;
            background: rgba(255, 255, 255, 0.03);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Check Item */
        .check-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 8px;
            margin-bottom: 0.75rem;
            border: 1px solid transparent;
        }

        .check-item:last-child {
            margin-bottom: 0;
        }

        .check-item.ok {
            border-color: rgba(16, 185, 129, 0.1);
        }

        .check-item.warning {
            border-color: rgba(245, 158, 11, 0.1);
        }

        .check-item.error {
            border-color: rgba(239, 68, 68, 0.1);
            background: rgba(239, 68, 68, 0.05);
        }

        .check-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .check-icon.ok {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-success);
        }

        .check-icon.warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--accent-warning);
        }

        .check-icon.error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--accent-danger);
        }

        .check-info {
            flex: 1;
        }

        .check-title {
            font-weight: 500;
            margin-bottom: 0.25rem;
            text-transform: capitalize;
        }

        .check-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-family: var(--font-mono);
        }

        .check-status {
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .check-status.ok {
            color: var(--accent-success);
        }

        .check-status.warning {
            color: var(--accent-warning);
        }

        .check-status.error {
            color: var(--accent-danger);
        }

        /* Progress Bar */
        .progress-bar {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.5s ease;
        }

        .progress-fill.ok {
            background: var(--accent-success);
        }

        .progress-fill.warning {
            background: var(--accent-warning);
        }

        .progress-fill.error {
            background: var(--accent-danger);
        }

        /* Tags for Extensions */
        .tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .tag {
            font-size: 0.8rem;
            font-family: var(--font-mono);
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-muted);
            transition: color 0.2s, border-color 0.2s;
        }

        .tag:hover {
            color: var(--text-main);
            border-color: rgba(255, 255, 255, 0.3);
        }

        /* Loader */
        .loader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--bg-base);
            z-index: 100;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: opacity 0.3s;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top-color: var(--accent-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Error state */
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: none;
        }

        /* Footer */
        footer {
            border-top: 1px solid var(--border-subtle);
            padding: 2rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-top: auto;
        }
    </style>
</head>

<body>

    <!-- Loader -->
    <div class="loader-overlay" id="loader">
        <div class="spinner"></div>
        <p style="color: var(--text-muted); font-family: var(--font-mono); font-size: 0.9rem;">Fetching telemetry
            data...</p>
    </div>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-brand">
            <i class="ri-pulse-line"></i> Plugs Telescope
        </div>
        <div class="actions">
            <div class="status-badge" id="global-status">
                <div class="status-indicator"></div>
                <span id="global-status-text">Checking</span>
            </div>
            <button onclick="fetchHealthData()" title="Refresh Data">
                <i class="ri-refresh-line" id="refresh-icon"></i> Refresh
            </button>
        </div>
    </nav>

    <div class="container">

        <div class="header-section">
            <h1>Health & Telemetry</h1>
            <p>Real-time system health metrics, environment details, and component status.</p>
        </div>

        <div class="error-message" id="error-container">
            <i class="ri-error-warning-line"></i> <span id="error-text">Failed to fetch health data.</span>
        </div>

        <div class="grid">
            <!-- App Environment -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="ri-server-line"></i> Application Environment</h3>
                </div>
                <div class="card-body" id="app-env-body">
                    <!-- Dynamic -->
                </div>
            </div>

            <!-- Server Info -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="ri-terminal-window-line"></i> Server Information</h3>
                </div>
                <div class="card-body" id="server-info-body">
                    <!-- Dynamic -->
                </div>
            </div>

            <!-- Health Checks -->
            <div class="card col-span-2">
                <div class="card-header">
                    <h3><i class="ri-shield-check-line"></i> Component Health Checks</h3>
                </div>
                <div class="card-body" id="health-checks-body"
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1rem;">
                    <!-- Dynamic -->
                </div>
            </div>

            <!-- PHP Extensions -->
            <div class="card col-span-2">
                <div class="card-header">
                    <h3><i class="ri-plug-2-line"></i> Loaded PHP Extensions</h3>
                </div>
                <div class="card-body">
                    <div class="tag-list" id="extensions-list">
                        <!-- Dynamic -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>Plugs Framework &middot; <span id="footer-time"></span></p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            fetchHealthData();

            // Auto refresh every 30 seconds
            setInterval(fetchHealthData, 30000);
        });

        async function fetchHealthData() {
            const refreshIcon = document.getElementById('refresh-icon');
            refreshIcon.classList.add('ri-spin');

            try {
                const response = await fetch('/_plugs/health/detailed');
                if (!response.ok) throw new Error('Network response was not ok');

                const data = await response.json();
                renderDashboard(data);

                document.getElementById('error-container').style.display = 'none';
            } catch (error) {
                console.error('Failed to fetch health data:', error);
                document.getElementById('error-container').style.display = 'block';
                document.getElementById('error-text').innerText = 'Failed to fetch health data. The server might be unreachable.';
            } finally {
                refreshIcon.classList.remove('ri-spin');
                setTimeout(() => {
                    document.getElementById('loader').style.opacity = '0';
                    setTimeout(() => document.getElementById('loader').style.display = 'none', 300);
                }, 500);
            }
        }

        function renderDashboard(data) {
            // Global Status
            const globalStatusObj = document.getElementById('global-status');
            const globalStatusText = document.getElementById('global-status-text');
            globalStatusText.innerText = data.status.charAt(0).toUpperCase() + data.status.slice(1);
            if (data.status === 'healthy') {
                globalStatusObj.className = 'status-badge';
            } else {
                globalStatusObj.className = 'status-badge unhealthy';
            }

            // App Environment
            const envBody = document.getElementById('app-env-body');
            envBody.innerHTML = `
                <div class="metric"><span class="metric-label">Environment</span><span class="metric-value">${data.environment}</span></div>
                <div class="metric"><span class="metric-label">Framework Version</span><span class="metric-value">${data.version}</span></div>
                <div class="metric"><span class="metric-label">App Time</span><span class="metric-value">${new Date(data.timestamp).toLocaleString()}</span></div>
            `;

            // Server Info
            const serverBody = document.getElementById('server-info-body');
            serverBody.innerHTML = `
                <div class="metric"><span class="metric-label">PHP Version</span><span class="metric-value">${data.system?.php_version || 'N/A'}</span></div>
                <div class="metric"><span class="metric-label">Operating System</span><span class="metric-value">${data.system?.os || 'N/A'}</span></div>
                <div class="metric"><span class="metric-label">Server Software</span><span class="metric-value">${data.system?.server_software || 'N/A'}</span></div>
                <div class="metric"><span class="metric-label">Uptime</span><span class="metric-value">${data.system?.uptime || 'N/A'}</span></div>
            `;

            // Checks (Database, Cache, Disk, Memory)
            const checksBody = document.getElementById('health-checks-body');
            checksBody.innerHTML = '';

            for (const [key, check] of Object.entries(data.checks)) {
                let icon = 'ri-checkbox-circle-line';
                if (check.status === 'warning') icon = 'ri-error-warning-line';
                if (check.status === 'error') icon = 'ri-close-circle-line';

                let details = '';
                if (key === 'database' || key === 'cache') {
                    details = check.status === 'ok' ? `Latency: ${check.latency_ms}ms` : (check.message || 'Error');
                } else if (key === 'memory') {
                    details = `${check.usage_mb}MB / ${check.limit_mb > 0 ? check.limit_mb + 'MB' : 'Unlimited'} (${check.used_percent}%)`;
                } else if (key === 'disk') {
                    details = `${check.free_gb}GB free of ${check.total_gb}GB (${check.used_percent}%)`;
                }

                // Bar logic for bounded metrics
                let barHtml = '';
                if (key === 'memory' || key === 'disk') {
                    barHtml = `
                    <div class="progress-bar">
                        <div class="progress-fill ${check.status}" style="width: ${check.used_percent}%"></div>
                    </div>`;
                }

                const checkHtml = `
                <div class="check-item ${check.status}">
                    <div class="check-icon ${check.status}"><i class="${icon}"></i></div>
                    <div class="check-info">
                        <div class="check-title">${key.replace('_', ' ')}</div>
                        <div class="check-desc">${details}</div>
                        ${barHtml}
                    </div>
                    <div class="check-status ${check.status}">${check.status}</div>
                </div>
                `;
                checksBody.innerHTML += checkHtml;
            }

            // Extensions
            const extList = document.getElementById('extensions-list');
            if (data.system?.extensions) {
                extList.innerHTML = data.system.extensions.map(ext => `<span class="tag">${ext}</span>`).join('');
            } else {
                extList.innerHTML = '<span class="text-muted">No extension data available.</span>';
            }

            // Footer
            document.getElementById('footer-time').innerText = new Date().getFullYear();
        }
    </script>
</body>

</html>