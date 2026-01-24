<?php

declare(strict_types=1);

namespace Plugs\Debug;

use Psr\Http\Message\ServerRequestInterface;
use Plugs\Http\ResponseFactory;

/**
 * Profiler Dashboard Controller
 * 
 * Provides routes for viewing and managing profiler data.
 */
class ProfilerController
{
    /**
     * Display profiler dashboard with profile list
     */
    public function index(ServerRequestInterface $request)
    {
        $profiles = Profiler::getProfiles(50);

        return ResponseFactory::html($this->renderDashboard($profiles));
    }

    /**
     * Display single profile detail
     */
    public function show(ServerRequestInterface $request, string $id)
    {
        $profile = Profiler::getProfile($id);

        if (!$profile) {
            return ResponseFactory::html($this->render404(), 404);
        }

        return ResponseFactory::html($this->renderProfile($profile));
    }

    /**
     * Delete a single profile
     */
    public function destroy(ServerRequestInterface $request, string $id)
    {
        Profiler::deleteProfile($id);

        return ResponseFactory::json(['success' => true, 'message' => 'Profile deleted']);
    }

    /**
     * Clear all profiles
     */
    public function clear(ServerRequestInterface $request)
    {
        $count = Profiler::clearProfiles();

        return ResponseFactory::json(['success' => true, 'deleted' => $count]);
    }

    /**
     * Get profile data as JSON (for toolbar)
     */
    public function latest(ServerRequestInterface $request)
    {
        $profiles = Profiler::getProfiles(1);

        return ResponseFactory::json($profiles[0] ?? null);
    }

    /**
     * Render the dashboard HTML
     */
    private function renderDashboard(array $profiles): string
    {
        $profileRows = '';
        foreach ($profiles as $profile) {
            $statusClass = $this->getStatusClass($profile['request']['status_code'] ?? 200);
            $durationClass = $this->getDurationClass($profile['duration']);
            $queryCount = $profile['database']['query_count'] ?? 0;

            $profileRows .= sprintf('
                <tr class="profile-row" onclick="window.location.href=\'/plugs/profiler/%s\'">
                    <td class="col-time">%s</td>
                    <td class="col-method"><span class="method-badge method-%s">%s</span></td>
                    <td class="col-path">%s</td>
                    <td class="col-status"><span class="status-badge %s">%s</span></td>
                    <td class="col-duration %s">%s ms</td>
                    <td class="col-memory">%s</td>
                    <td class="col-queries">%d</td>
                    <td class="col-actions">
                        <button class="btn-icon" onclick="event.stopPropagation(); deleteProfile(\'%s\')" title="Delete">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </td>
                </tr>',
                htmlspecialchars($profile['id']),
                htmlspecialchars($profile['datetime']),
                strtolower($profile['request']['method'] ?? 'GET'),
                htmlspecialchars($profile['request']['method'] ?? 'GET'),
                htmlspecialchars($profile['request']['path'] ?? '/'),
                $statusClass,
                $profile['request']['status_code'] ?? 200,
                $durationClass,
                number_format($profile['duration'], 2),
                htmlspecialchars($profile['memory']['peak_formatted'] ?? ($profile['memory_peak_formatted'] ?? '0 B')),
                $queryCount,
                htmlspecialchars($profile['id'])
            );
        }

        return $this->getBaseHtml('Profiler Dashboard', sprintf('
            <div class="dashboard-header">
                <h1>Profiler Dashboard</h1>
                <div class="header-actions">
                    <span class="profile-count">%d profiles</span>
                    <button class="btn btn-danger" onclick="clearAllProfiles()">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Clear All
                    </button>
                </div>
            </div>
            
            <div class="table-container">
                <table class="profiles-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Method</th>
                            <th>Path</th>
                            <th>Status</th>
                            <th>Duration</th>
                            <th>Memory</th>
                            <th>Queries</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        %s
                    </tbody>
                </table>
                %s
            </div>
            
            <script>
                const csrfToken = document.querySelector(\'meta[name="csrf-token"]\')?.getAttribute(\'content\');

                async function deleteProfile(id) {
                    if (!confirm("Delete this profile?")) return;
                    try {
                        const response = await fetch("/plugs/profiler/" + id, { 
                            method: "DELETE",
                            headers: {
                                "X-CSRF-TOKEN": csrfToken,
                                "Accept": "application/json"
                            }
                        });
                        
                        if (response.ok) {
                            location.reload();
                        } else {
                            const data = await response.json();
                            alert("Error: " + (data.error || "Failed to delete profile"));
                        }
                    } catch (e) {
                        alert("Error: " + e.message);
                    }
                }
                
                async function clearAllProfiles() {
                    if (!confirm("Delete all profiles?")) return;
                    try {
                        const response = await fetch("/plugs/profiler/clear", { 
                            method: "POST",
                            headers: {
                                "X-CSRF-TOKEN": csrfToken,
                                "Accept": "application/json"
                            }
                        });
                        
                        if (response.ok) {
                            location.reload();
                        } else {
                            const data = await response.json();
                            alert("Error: " + (data.error || "Failed to clear profiles"));
                        }
                    } catch (e) {
                        alert("Error: " + e.message);
                    }
                }
            </script>',
            count($profiles),
            $profileRows,
            empty($profiles) ? '<div class="empty-state"><p>No profiles recorded yet.</p></div>' : ''
        ));
    }

    /**
     * Render single profile detail
     */
    private function renderProfile(array $profile): string
    {
        $queries = '';
        foreach (($profile['database']['queries'] ?? []) as $i => $query) {
            $queries .= sprintf('
                <div class="query-item">
                    <div class="query-header">
                        <span class="query-number">#%d</span>
                        <span class="query-time">%s ms</span>
                    </div>
                    <pre class="query-sql">%s</pre>
                </div>',
                $i + 1,
                number_format($query['time'] ?? 0, 2),
                htmlspecialchars($query['query'] ?? '')
            );
        }

        $timeline = '';
        foreach ($profile['timeline'] ?? [] as $name => $segment) {
            if ($segment['duration'] === null)
                continue;
            $percentage = min(100, ($segment['duration'] / max($profile['duration'], 1)) * 100);
            $timeline .= sprintf('
                <div class="timeline-item">
                    <div class="timeline-label">%s</div>
                    <div class="timeline-bar-wrap">
                        <div class="timeline-bar" style="width: %s%%"></div>
                    </div>
                    <div class="timeline-duration">%s ms</div>
                </div>',
                htmlspecialchars($segment['label']),
                number_format($percentage, 1),
                number_format($segment['duration'], 2)
            );
        }

        $views = '';
        foreach ($profile['views'] ?? [] as $view) {
            $views .= sprintf(
                '<div class="view-item"><span class="view-name">%s</span><span class="view-time">%s ms</span></div>',
                htmlspecialchars($view['name']),
                number_format($view['duration'], 2)
            );
        }

        return $this->getBaseHtml('Profile: ' . $profile['request']['path'], sprintf('
            <div class="profile-header">
                <a href="/plugs/profiler" class="back-link">← Back to Dashboard</a>
                <div class="profile-title">
                    <span class="method-badge method-%s">%s</span>
                    <h1>%s</h1>
                </div>
                <div class="profile-meta">
                    <span class="meta-item">%s</span>
                    <span class="meta-item">Status: <span class="%s">%s</span></span>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Duration</div>
                    <div class="stat-value %s">%s ms</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Memory Peak</div>
                    <div class="stat-value">%s</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Queries</div>
                    <div class="stat-value">%d</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Files</div>
                    <div class="stat-value">%d</div>
                </div>
            </div>
            
            <div class="section">
                <h2>Timeline</h2>
                <div class="timeline-container">%s</div>
            </div>
            
            %s
            
            <div class="section">
                <h2>Database Queries (%d)</h2>
                <div class="queries-container">%s</div>
            </div>',
            strtolower($profile['request']['method']),
            htmlspecialchars($profile['request']['method']),
            htmlspecialchars($profile['request']['path']),
            htmlspecialchars($profile['datetime']),
            $this->getStatusClass($profile['request']['status_code'] ?? 200),
            $profile['request']['status_code'] ?? 200,
            $this->getDurationClass($profile['duration']),
            number_format($profile['duration'], 2),
            htmlspecialchars($profile['memory']['peak_formatted'] ?? ($profile['memory_peak_formatted'] ?? '0 B')),
            $profile['database']['query_count'] ?? 0,
            $profile['files']['count'] ?? 0,
            $timeline ?: '<p class="empty">No timeline data</p>',
            !empty($views) ? sprintf('<div class="section"><h2>Views Rendered</h2><div class="views-container">%s</div></div>', $views) : '',
            count($profile['database']['queries'] ?? []),
            $queries ?: '<p class="empty">No queries recorded</p>'
        ));
    }

    /**
     * Render 404 page
     */
    private function render404(): string
    {
        return $this->getBaseHtml('Profile Not Found', '
            <div class="error-page">
                <h1>Profile Not Found</h1>
                <p>The requested profile could not be found.</p>
                <a href="/plugs/profiler" class="btn btn-primary">Back to Dashboard</a>
            </div>
        ');
    }

    /**
     * Get status class for styling
     */
    private function getStatusClass(int $status): string
    {
        if ($status >= 500)
            return 'status-error';
        if ($status >= 400)
            return 'status-warning';
        if ($status >= 300)
            return 'status-redirect';
        return 'status-success';
    }

    /**
     * Get duration class for styling
     */
    private function getDurationClass(float $duration): string
    {
        if ($duration > 1000)
            return 'duration-slow';
        if ($duration > 200)
            return 'duration-medium';
        return 'duration-fast';
    }

    /**
     * Get base HTML template
     */
    private function getBaseHtml(string $title, string $content): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="' . (function_exists('csrf_token') ? csrf_token() : '') . '">
    <title>' . htmlspecialchars($title) . ' | Plugs</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Dancing+Script:wght@700&display=swap");
        
        :root {
            --bg-body: #080b12;
            --bg-card: rgba(30, 41, 59, 0.4);
            --bg-card-hover: rgba(30, 41, 59, 0.6);
            --border-color: rgba(255, 255, 255, 0.08);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-primary: #8b5cf6;
            --accent-secondary: #3b82f6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: "Outfit", sans-serif;
            background-color: var(--bg-body);
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(139, 92, 246, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 85% 85%, rgba(59, 130, 246, 0.05) 0%, transparent 40%);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.5;
        }
        
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 4px; }
        
        .header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(8, 11, 18, 0.8);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .brand {
            font-family: "Dancing Script", cursive;
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }
        
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .dashboard-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .profile-count {
            color: var(--text-muted);
            font-size: 0.875rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: white;
        }
        
        .btn-danger {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.3);
        }
        
        .btn-icon {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .btn-icon:hover {
            color: var(--danger);
            background: rgba(239, 68, 68, 0.1);
        }
        
        .table-container {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .profiles-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        
        .profiles-table th {
            text-align: left;
            padding: 1rem;
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border-color);
        }
        
        .profiles-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .profile-row {
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .profile-row:hover {
            background: var(--bg-card-hover);
        }
        
        .method-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-family: "JetBrains Mono", monospace;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .method-get { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .method-post { background: rgba(59, 130, 246, 0.2); color: var(--accent-secondary); }
        .method-put { background: rgba(245, 158, 11, 0.2); color: var(--warning); }
        .method-patch { background: rgba(139, 92, 246, 0.2); color: var(--accent-primary); }
        .method-delete { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-family: "JetBrains Mono", monospace;
            font-size: 0.75rem;
        }
        
        .status-success { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .status-redirect { background: rgba(59, 130, 246, 0.2); color: var(--accent-secondary); }
        .status-warning { background: rgba(245, 158, 11, 0.2); color: var(--warning); }
        .status-error { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        
        .duration-fast { color: var(--success); }
        .duration-medium { color: var(--warning); }
        .duration-slow { color: var(--danger); }
        
        .col-time { color: var(--text-muted); font-family: "JetBrains Mono", monospace; font-size: 0.8rem; }
        .col-path { font-family: "JetBrains Mono", monospace; }
        .col-duration, .col-memory, .col-queries { font-family: "JetBrains Mono", monospace; text-align: right; }
        .col-actions { text-align: right; }
        
        .empty-state {
            padding: 3rem;
            text-align: center;
            color: var(--text-muted);
        }
        
        /* Profile Detail Styles */
        .profile-header {
            margin-bottom: 2rem;
        }
        
        .back-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            display: inline-block;
        }
        
        .back-link:hover {
            color: var(--text-primary);
        }
        
        .profile-title {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .profile-title h1 {
            font-family: "JetBrains Mono", monospace;
            font-size: 1.5rem;
            font-weight: 500;
        }
        
        .profile-meta {
            display: flex;
            gap: 1.5rem;
            margin-top: 0.5rem;
            color: var(--text-muted);
            font-size: 0.875rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
        }
        
        .stat-label {
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-family: "JetBrains Mono", monospace;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .section h2 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-secondary);
        }
        
        .timeline-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.5rem 0;
        }
        
        .timeline-label {
            width: 150px;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .timeline-bar-wrap {
            flex: 1;
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .timeline-bar {
            height: 100%;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border-radius: 4px;
        }
        
        .timeline-duration {
            width: 80px;
            text-align: right;
            font-family: "JetBrains Mono", monospace;
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        
        .query-item {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }
        
        .query-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .query-number {
            color: var(--text-muted);
            font-size: 0.75rem;
        }
        
        .query-time {
            font-family: "JetBrains Mono", monospace;
            font-size: 0.75rem;
            color: var(--accent-primary);
        }
        
        .query-sql {
            font-family: "JetBrains Mono", monospace;
            font-size: 0.8rem;
            color: var(--text-secondary);
            white-space: pre-wrap;
            word-break: break-all;
            margin: 0;
        }
        
        .views-container {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .view-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 4px;
        }
        
        .view-name {
            font-family: "JetBrains Mono", monospace;
            font-size: 0.8rem;
        }
        
        .view-time {
            font-family: "JetBrains Mono", monospace;
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        
        .empty {
            color: var(--text-muted);
            font-style: italic;
        }
        
        .error-page {
            text-align: center;
            padding: 4rem 2rem;
        }
        
        .error-page h1 {
            margin-bottom: 1rem;
        }
        
        .error-page p {
            color: var(--text-muted);
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="/" class="brand">Plugs</a>
        <nav>
            <a href="/plugs/profiler" style="color: var(--text-muted); text-decoration: none;">Profiler</a>
        </nav>
    </header>
    <main class="main-content">
        ' . $content . '
    </main>
</body>
</html>';
    }
}
