<?php

declare(strict_types=1);

/**
 * Render debug error page with detailed information
 */
function renderDebugErrorPage(Throwable $e): void
{
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);

    $trace = $e->getTrace();
    $file = $e->getFile();
    $line = $e->getLine();

    // Prepare frames including the main exception location
    $frames = [];
    $frames[] = [
        'file' => $file,
        'line' => $line,
        'function' => '{main}',
        'class' => '',
        'type' => '',
        'args' => []
    ];
    foreach ($trace as $t) {
        $frames[] = $t;
    }

    $className = get_class($e);
    $shortClass = basename(str_replace('\\', '/', $className));

    $html = '<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plugs &middot; ' . htmlspecialchars($shortClass) . '</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Dancing+Script:wght@700&display=swap");

        :root {
            --bg-body: #0f172a;
            --bg-sidebar: #1e293b;
            --bg-card: #1e293b;
            --bg-header: #0f172a;
            --border-color: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-primary: #8b5cf6;
            --accent-secondary: #3b82f6;
            --danger: #ef4444;
            --warning: #f59e0b;
            --success: #10b981;
            --code-bg: #0d1117;
            --highlight-bg: rgba(239, 68, 68, 0.15);
            --highlight-border: #ef4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: "Outfit", sans-serif;
            background-color: var(--bg-body);
            color: var(--text-primary);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            font-size: 15px;
            line-height: 1.5;
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

        /* Header */
        .header {
            height: 64px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            padding: 0 2rem;
            background-color: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(8px);
            z-index: 50;
            justify-content: space-between;
        }

        .brand {
            font-family: "Dancing Script", cursive;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            text-decoration: none;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .exception-meta {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .php-version {
            font-family: "JetBrains Mono", monospace;
            font-size: 0.75rem;
            color: var(--text-muted);
            background: rgba(255,255,255,0.05);
            padding: 4px 8px;
            border-radius: 4px;
        }

        /* Main Layout */
        .container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* Sidebar - Stack Trace */
        .sidebar {
            width: 420px;
            background-color: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .sidebar-title {
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .stack-list {
            overflow-y: auto;
            flex: 1;
            list-style: none;
        }
        
        .stack-item {
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
            cursor: pointer;
            transition: background 0.2s;
            position: relative;
        }
        
        .stack-item:hover { background-color: rgba(255,255,255,0.02); }
        .stack-item.active { background-color: rgba(139, 92, 246, 0.1); border-left: 3px solid var(--accent-primary); }
        
        .stack-index {
            position: absolute;
            right: 1.5rem;
            top: 1rem;
            font-family: "JetBrains Mono", monospace;
            font-size: 0.75rem;
            color: var(--text-muted);
            opacity: 0.5;
        }
        
        .stack-file {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin-bottom: 4px;
            word-break: break-all;
            line-height: 1.4;
        }
        
        .stack-method {
            font-family: "JetBrains Mono", monospace;
            font-size: 0.8125rem;
            color: var(--accent-secondary);
            font-weight: 500;
        }
        
        .stack-line {
            display: inline-block;
            background: rgba(255,255,255,0.1);
            padding: 0 4px;
            border-radius: 3px;
            color: var(--text-primary);
            font-size: 0.75rem;
            margin-right: 6px;
        }

        /* Content Area */
        .content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            position: relative;
        }

        .error-banner {
            background: linear-gradient(to right, rgba(239, 68, 68, 0.1), transparent);
            border-bottom: 1px solid var(--border-color);
            padding: 2.5rem;
            flex-shrink: 0;
        }

        .exception-type {
            font-size: 0.875rem;
            color: var(--danger);
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .exception-type::before {
            content: "";
            display: block;
            width: 8px;
            height: 8px;
            background: var(--danger);
            border-radius: 50%;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.2);
        }

        .exception-message {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            line-height: 1.3;
        }
        
        .exception-location {
            font-family: "JetBrains Mono", monospace;
            font-size: 0.875rem;
            background: var(--code-bg);
            padding: 0.75rem 1rem;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Code Viewer */
        .code-viewer-container {
            border-bottom: 1px solid var(--border-color);
            background: var(--code-bg);
            flex-shrink: 0;
            min-height: 100px;
        }

        .code-viewer {
            padding: 0;
            overflow-x: auto;
            display: none;
        }

        .code-viewer.active {
            display: block;
        }
        
        .code-table {
            width: 100%;
            border-collapse: collapse;
            font-family: "JetBrains Mono", monospace;
            font-size: 0.875rem;
        }
        
        .code-row {
            line-height: 1.6;
        }
        
        .code-line-num {
            width: 60px;
            text-align: right;
            padding: 0 1rem;
            color: var(--text-muted);
            user-select: none;
            border-right: 1px solid var(--border-color);
            vertical-align: top;
        }
        
        .code-content {
            padding: 0 1.5rem;
            color: var(--text-secondary);
            white-space: pre;
            position: relative;
        }
        
        .code-row.error-line {
            background-color: var(--highlight-bg);
        }
        
        .code-row.error-line .code-line-num {
            color: var(--danger);
            font-weight: bold;
            border-right-color: var(--danger);
        }
        
        .code-row.error-line .code-content {
            color: var(--text-primary);
        }

        /* Sections */
        .section-container {
            padding: 2.5rem;
        }
        
        .tabs {
            display: flex;
            gap: 2rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }
        
        .tab {
            padding-bottom: 1rem;
            color: var(--text-secondary);
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            position: relative;
        }
        
        .tab:hover { color: var(--text-primary); }
        
        .tab.active {
            color: var(--accent-primary);
        }
        
        .tab.active::after {
            content: "";
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--accent-primary);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .info-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.25rem;
        }
        
        .info-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        
        .info-value {
            font-family: "JetBrains Mono", monospace;
            font-size: 0.8125rem;
            color: var(--text-secondary);
            word-break: break-all;
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        @media (max-width: 1024px) {
            .container { flex-direction: column-reverse; overflow: auto; }
            .sidebar { width: 100%; height: 300px; border-right: none; border-top: 1px solid var(--border-color); }
            .content { overflow: visible; }
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="/" class="brand">Plugs</a>
        <div class="exception-meta">
            <span class="php-version">PHP ' . PHP_VERSION . '</span>
        </div>
    </header>

    <div class="container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">Stack Trace</div>
            </div>
            <ul class="stack-list">
                ';
    foreach ($frames as $index => $frame) {
        $active = $index === 0 ? 'active' : '';
        $f = $frame['file'] ?? '{internal}';
        $l = $frame['line'] ?? '-';
        $method = $frame['class'] ? $frame['class'] . ($frame['type'] ?? '::') . $frame['function'] : $frame['function'];

        $html .= '
                    <li class="stack-item ' . $active . '" onclick="switchFrame(' . $index . ', this)" data-file="' . htmlspecialchars(basename($f)) . '" data-line="' . $l . '">
                        <span class="stack-index">' . $index . '</span>
                        <div class="stack-file"><span class="stack-line">' . $l . '</span>' . htmlspecialchars(basename($f)) . '</div>
                        <div class="stack-method">' . htmlspecialchars($method) . '</div>
                    </li>';
    }
    $html .= '
            </ul>
        </aside>

        <main class="content">
            <div class="error-banner">
                <div class="exception-type">' . htmlspecialchars($className) . '</div>
                <h1 class="exception-message">' . htmlspecialchars($e->getMessage()) . '</h1>
                <div class="exception-location" id="active-location">
                    <span class="loc-file">' . htmlspecialchars(basename($file)) . '</span>
                    <span style="color: var(--border-color)">|</span>
                    <span class="loc-line">Line ' . $line . '</span>
                </div>
            </div>

            <div class="code-viewer-container">
                ';
    foreach ($frames as $index => $frame) {
        $active = $index === 0 ? 'active' : '';
        $f = $frame['file'] ?? '';
        $l = $frame['line'] ?? 0;
        $snippet = ($f && file_exists($f)) ? getCodeSnippet($f, $l) : '<div style="padding: 2rem; color: var(--text-muted)">No code preview available</div>';

        $html .= '<div id="code-' . $index . '" class="code-viewer ' . $active . '">' . $snippet . '</div>';
    }
    $html .= '
            </div>

            <div class="section-container">
                <div class="tabs">
                    <div class="tab active" onclick="switchTab(event, \'request\')">Request</div>
                    <div class="tab" onclick="switchTab(event, \'environment\')">Environment</div>
                    <div class="tab" onclick="switchTab(event, \'headers\')">Headers</div>
                </div>
                
                <div id="request" class="tab-content active">
                    <div class="info-grid">
                        ' . renderRequestGrid() . '
                    </div>
                </div>

                <div id="environment" class="tab-content">
                    <div class="info-grid">
                         ' . renderEnvGrid() . '
                    </div>
                </div>
                
                 <div id="headers" class="tab-content">
                    <div class="info-grid">
                         ' . renderHeadersGrid() . '
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function switchTab(event, tabId) {
            document.querySelectorAll(".tab").forEach(t => t.classList.remove("active"));
            document.querySelectorAll(".tab-content").forEach(c => c.classList.remove("active"));
            
            event.target.classList.add("active");
            document.getElementById(tabId).classList.add("active");
        }
        
        function switchFrame(index, element) {
            document.querySelectorAll(".stack-item").forEach(i => i.classList.remove("active"));
            element.classList.add("active");
            
            document.querySelectorAll(".code-viewer").forEach(v => v.classList.remove("active"));
            const targetCode = document.getElementById("code-" + index);
            if (targetCode) targetCode.classList.add("active");

            const locFile = document.querySelector("#active-location .loc-file");
            const locLine = document.querySelector("#active-location .loc-line");
            if (locFile && locLine) {
                locFile.textContent = element.getAttribute("data-file");
                locLine.textContent = "Line " + element.getAttribute("data-line");
            }
        }
    </script>
</body>
</html>';

    echo $html;
}

function getCodeSnippet(string $file, int $line, int $context = 15): string
{
    if (!file_exists($file)) {
        return '<div style="padding: 2rem; color: var(--text-muted)">File not found</div>';
    }

    $lines = file($file);
    $start = max(0, $line - $context - 1);
    $end = min(count($lines), $line + $context);

    $output = '<table class="code-table">';
    for ($i = $start; $i < $end; $i++) {
        $currentLine = $i + 1;
        $isErrorLine = $currentLine === (int) $line;
        $class = $isErrorLine ? 'error-line' : '';

        $output .= '<tr class="code-row ' . $class . '">';
        $output .= '<td class="code-line-num">' . $currentLine . '</td>';
        $output .= '<td class="code-content">' . htmlspecialchars($lines[$i]) . '</td>';
        $output .= '</tr>';
    }
    $output .= '</table>';

    return $output;
}

function renderStackTrace(array $trace): string
{
    // Integrated into main function for interactive view
    return '';
}

function renderRequestGrid(): string
{
    $data = [
        'Method' => $_SERVER['REQUEST_METHOD'] ?? '-',
        'URL' => $_SERVER['REQUEST_URI'] ?? '-',
        'Protocol' => $_SERVER['SERVER_PROTOCOL'] ?? '-',
        'IP Address' => $_SERVER['REMOTE_ADDR'] ?? '-',
    ];
    return renderGridItems($data);
}

function renderEnvGrid(): string
{
    $data = [
        'PHP Version' => PHP_VERSION,
        'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? '-',
        'Interface' => $_SERVER['GATEWAY_INTERFACE'] ?? '-',
    ];
    return renderGridItems($data);
}

function renderHeadersGrid(): string
{
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $name = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
            $headers[$name] = $value;
        }
    }
    return renderGridItems($headers);
}

function renderGridItems(array $items): string
{
    $html = '';
    foreach ($items as $label => $value) {
        $html .= '<div class="info-card">';
        $html .= '<div class="info-label">' . htmlspecialchars($label) . '</div>';
        $html .= '<div class="info-value">' . htmlspecialchars((string) $value) . '</div>';
        $html .= '</div>';
    }
    return $html;
}

/**
 * Render production error page without sensitive information
 */
function renderProductionErrorPage(Exception $e, int $statusCode = 500): void
{
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code($statusCode);

    $errorMessages = [
        400 => ['title' => 'Bad Request', 'message' => 'The request could not be understood by the server.', 'icon' => '⚠️'],
        401 => ['title' => 'Unauthorized', 'message' => 'You need to be authenticated to access this resource.', 'icon' => '🔒'],
        403 => ['title' => 'Forbidden', 'message' => 'You don\'t have permission to access this resource.', 'icon' => '🚫'],
        404 => ['title' => 'Not Found', 'message' => 'The page you are looking for could not be found.', 'icon' => '🔍'],
        419 => ['title' => 'Page Expired', 'message' => 'Your session has expired. Please refresh and try again.', 'icon' => '⏱️'],
        429 => ['title' => 'Too Many Requests', 'message' => 'You have made too many requests. Please slow down and try again later.', 'icon' => '🚦'],
        500 => ['title' => 'Server Error', 'message' => 'Something went wrong on our end. We\'ve been notified and are working to fix the issue.', 'icon' => '🚧'],
        503 => ['title' => 'Service Unavailable', 'message' => 'We\'re temporarily offline for maintenance. Please try again shortly.', 'icon' => '🔧'],
    ];

    $error = $errorMessages[$statusCode] ?? $errorMessages[500];

    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $statusCode . ' - ' . htmlspecialchars($error['title']) . '</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap");
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "Nunito", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        body::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: moveBackground 20s linear infinite;
            pointer-events: none;
        }
        
        @keyframes moveBackground {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        .error-container {
            text-align: center;
            max-width: 600px;
            width: 100%;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 3.5rem 2.5rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .error-icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            display: inline-block;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .error-code {
            font-size: clamp(4rem, 10vw, 6rem);
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 1rem;
            text-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }
        
        h1 {
            font-size: clamp(1.75rem, 4vw, 2.25rem);
            margin-bottom: 1rem;
            color: #2d3748;
            font-weight: 700;
        }
        
        .divider {
            width: 60px;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 1.5rem auto;
            border-radius: 2px;
        }
        
        .message {
            font-size: clamp(1rem, 2.5vw, 1.15rem);
            line-height: 1.7;
            margin: 1.5rem 0 2rem;
            color: #4a5568;
        }
        
        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2.5rem;
        }
        
        .btn {
            padding: 14px 32px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-family: inherit;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .btn > span {
            position: relative;
            z-index: 1;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
        }
        
        .btn-primary:active {
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #f7fafc;
            color: #4a5568;
            border: 2px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #edf2f7;
            border-color: #cbd5e0;
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .btn-secondary:active {
            transform: translateY(-1px);
        }
        
        .footer {
            margin-top: 2.5rem;
            padding-top: 2rem;
            border-top: 1px solid #e2e8f0;
            color: #718096;
            font-size: 0.9rem;
        }
        
        .footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }
        
        .footer a:hover {
            color: #764ba2;
        }
        
        @media (max-width: 640px) {
            .error-container { 
                padding: 2.5rem 1.5rem; 
                border-radius: 16px;
            }
            .actions { 
                flex-direction: column; 
                width: 100%;
            }
            .btn { 
                width: 100%; 
                justify-content: center; 
            }
            .error-icon {
                font-size: 4rem;
            }
        }
        
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">' . $error['icon'] . '</div>
        <div class="error-code">' . $statusCode . '</div>
        <h1>' . htmlspecialchars($error['title']) . '</h1>
        <div class="divider"></div>
        <div class="message">' . htmlspecialchars($error['message']) . '</div>
        <div class="actions">
            <a href="/" class="btn btn-primary">
                <span>🏠</span>
                <span>Go to Homepage</span>
            </a>
            <button onclick="location.reload()" class="btn btn-secondary">
                <span>🔄</span>
                <span>Try Again</span>
            </button>
        </div>
        <div class="footer">
            Need help? <a href="/contact">Contact Support</a>
        </div>
    </div>
</body>
</html>';

    echo $html;
}