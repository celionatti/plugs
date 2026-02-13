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
        'args' => [],
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
            --bg-body: #080b12;
            --bg-sidebar: rgba(15, 23, 42, 0.95);
            --bg-card: rgba(30, 41, 59, 0.3);
            --bg-header: rgba(8, 11, 18, 0.9);
            --border-color: rgba(255, 255, 255, 0.08);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-primary: #8b5cf6;
            --accent-secondary: #3b82f6;
            --danger: #ef4444;
            --warning: #f59e0b;
            --success: #10b981;
            --code-bg: rgba(13, 17, 23, 0.5);
            --highlight-bg: rgba(239, 68, 68, 0.1);
            --highlight-border: #ef4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: "Outfit", sans-serif;
            background-color: var(--bg-body);
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(139, 92, 246, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 85% 85%, rgba(59, 130, 246, 0.05) 0%, transparent 40%);
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
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.2); }

        /* Header */
        .header {
            height: 64px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            padding: 0 2rem;
            background-color: var(--bg-header);
            backdrop-filter: blur(10px);
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
            padding: 4px 10px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
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
            backdrop-filter: blur(20px);
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .sidebar-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
            font-weight: 700;
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
            transition: all 0.2s;
            position: relative;
        }
        
        .stack-item:hover { background-color: rgba(255,255,255,0.02); }
        .stack-item.active { 
            background: linear-gradient(to right, rgba(139, 92, 246, 0.1), transparent);
            border-left: 3px solid var(--accent-primary); 
        }
        
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
            color: var(--accent-primary);
            font-weight: 500;
        }
        
        .stack-line {
            display: inline-block;
            background: rgba(255,255,255,0.05);
            padding: 2px 6px;
            border-radius: 4px;
            color: var(--text-primary);
            font-size: 0.7rem;
            margin-right: 8px;
            border: 1px solid var(--border-color);
        }

        /* Breadcrumbs / Paths */
        .render-path {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            margin-top: 1.5rem;
            font-size: 0.8125rem;
            color: var(--text-muted);
            background: rgba(255, 255, 255, 0.03);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .path-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .path-item:not(:last-child)::after {
            content: "›";
            color: var(--text-muted);
            font-size: 1.2rem;
            line-height: 1;
        }

        .path-view {
            color: var(--accent-primary);
            font-weight: 500;
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
            background: linear-gradient(to bottom, rgba(30, 41, 59, 0.4), transparent);
            border-bottom: 1px solid var(--border-color);
            padding: 3rem;
            flex-shrink: 0;
        }

        .exception-type {
            font-size: 0.875rem;
            color: var(--danger);
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .exception-type::before {
            content: "";
            display: block;
            width: 6px;
            height: 6px;
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
            background: var(--bg-card);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Code Viewer */
        .code-viewer-container {
            border-bottom: 1px solid var(--border-color);
            background: rgba(13, 17, 23, 0.3);
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
            background: rgba(255,255,255,0.01);
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
            border-right-color: rgba(239, 68, 68, 0.3);
            background-color: var(--highlight-bg);
        }
        
        .code-row.error-line .code-content {
            color: var(--text-primary);
        }

        /* Sections */
        .section-container {
            padding: 3rem;
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
            font-size: 0.95rem;
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
            box-shadow: 0 -2px 10px var(--accent-primary);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .info-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            transition: transform 0.2s;
        }
        
        .info-card:hover {
            border-color: rgba(255,255,255,0.15);
        }
        
        .info-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.05em;
        }
        
        .info-value {
            font-family: "JetBrains Mono", monospace;
            font-size: 0.875rem;
            color: var(--text-primary);
            word-break: break-all;
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease-out; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
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
    </header>';

    $nonce = function_exists('asset_manager') ? asset_manager()->getNonce() : null;
    $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';

    $html .= '

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
        $class = $frame['class'] ?? '';
        $method = $class ? $class . ($frame['type'] ?? '::') . $frame['function'] : $frame['function'];

        $html .= '
                    <li class="stack-item ' . $active . '" data-frame-index="' . $index . '" data-file="' . htmlspecialchars(basename($f)) . '" data-line="' . $l . '">
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
                <div class="exception-type">
                    ' . htmlspecialchars($className) . '
                    ' . ($e instanceof \Plugs\Exceptions\ViewException && $e->getFrameworkCode() ? '<span style="margin-left: 8px; opacity: 0.6">[' . $e->getFrameworkCode() . ']</span>' : '') . '
                </div>
                <h1 class="exception-message">' . htmlspecialchars($e->getMessage()) . '</h1>
                <div class="exception-location" id="active-location">
                    <span class="loc-file">' . htmlspecialchars(basename($file)) . '</span>
                    <span style="color: var(--border-color)">|</span>
                    <span class="loc-line">Line ' . $line . '</span>
                </div>';

    if ($e instanceof \Plugs\Exceptions\ViewException) {
        $context = method_exists($e, 'getContext') ? $e->getContext() : [];
        $renderPath = $context['render_path'] ?? [];
        $viewName = $e->getView();

        if ($viewName || !empty($renderPath)) {
            $html .= '<div class="render-path">';
            $html .= '<span style="margin-right: 8px; font-weight: 600; color: var(--text-secondary)">Render Path:</span>';

            if (empty($renderPath) && $viewName) {
                $html .= '<span class="path-item"><span class="path-view">' . htmlspecialchars($viewName) . '</span></span>';
            } else {
                foreach ($renderPath as $index => $pathView) {
                    $html .= '<span class="path-item"><span class="path-view">' . htmlspecialchars($pathView) . '</span></span>';
                }
            }
            $html .= '</div>';
        }
    }

    $html .= '
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
                    <div class="tab active" data-tab-target="request">Request</div>
                    <div class="tab" data-tab-target="environment">Environment</div>
                    <div class="tab" data-tab-target="headers">Headers</div>
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

    <script' . $nonceAttr . '>
        document.addEventListener("DOMContentLoaded", () => {
            document.addEventListener("click", (e) => {
                // Tabs
                const tab = e.target.closest(".tab");
                if (tab) {
                    const tabId = tab.getAttribute("data-tab-target");
                    if (tabId) switchTab(tab, tabId);
                }

                // Stack Frames
                const frame = e.target.closest(".stack-item");
                if (frame) {
                    const index = frame.getAttribute("data-frame-index");
                    if (index !== null) switchFrame(index, frame);
                }
            });
        });

        function switchTab(element, tabId) {
            document.querySelectorAll(".tab").forEach(t => t.classList.remove("active"));
            document.querySelectorAll(".tab-content").forEach(c => c.classList.remove("active"));
            
            element.classList.add("active");
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
function renderProductionErrorPage(Throwable $e, int $statusCode = 500): void
{
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code($statusCode);

    echo getProductionErrorHtml($statusCode);
}

/**
 * Get the HTML content for a production error page.
 */
function getProductionErrorHtml(int $statusCode = 500, ?string $title = null, ?string $message = null): string
{
    $errorMessages = [
        400 => ['title' => 'Bad Request', 'message' => 'The request could not be understood by the server.', 'icon' => '🛰️'],
        401 => ['title' => 'Unauthorized', 'message' => 'You need to be authenticated to access this resource.', 'icon' => '🔒'],
        403 => ['title' => 'Forbidden', 'message' => 'You don\'t have permission to access this resource.', 'icon' => '🚫'],
        404 => ['title' => 'Not Found', 'message' => 'The page you are looking for could not be found.', 'icon' => '🌌'],
        419 => ['title' => 'Page Expired', 'message' => 'Your session has expired. Please refresh and try again.', 'icon' => '⏱️'],
        429 => ['title' => 'Too Many Requests', 'message' => 'You have made too many requests. Please slow down.', 'icon' => '🚦'],
        500 => ['title' => 'Server Error', 'message' => 'Something went wrong on our end. We are working to fix it.', 'icon' => '🚧'],
        503 => ['title' => 'Service Unavailable', 'message' => 'We\'re temporarily offline for maintenance.', 'icon' => '🔧'],
    ];

    $error = $errorMessages[$statusCode] ?? $errorMessages[500];

    // Override if custom title/message provided
    $displayTitle = $title ?? $error['title'];
    $displayMessage = $message ?? $error['message'];

    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $statusCode . ' - ' . htmlspecialchars($displayTitle) . '</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&family=Dancing+Script:wght@700&display=swap");
        
        :root {
            --bg-body: #080b12;
            --bg-card: rgba(30, 41, 59, 0.5);
            --border-color: rgba(255, 255, 255, 0.08);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent-primary: #8b5cf6;
            --accent-secondary: #3b82f6;
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
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 20px;
            overflow: hidden;
        }

        .brand-container {
            position: absolute;
            top: 40px;
            text-align: center;
        }

        .brand {
            font-family: "Dancing Script", cursive;
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            text-decoration: none;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .error-card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 60px 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .error-icon {
            font-size: 4rem;
            margin-bottom: 24px;
            display: block;
        }

        .error-code {
            font-family: "Outfit", sans-serif;
            font-size: 6rem;
            line-height: 1;
            font-weight: 700;
            margin-bottom: 16px;
            opacity: 0.1;
            letter-spacing: -4px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(1.5);
            z-index: -1;
            pointer-events: none;
        }

        h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 16px;
            position: relative;
            color: var(--text-primary);
        }

        .message {
            color: var(--text-secondary);
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 40px;
            position: relative;
        }

        .actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            position: relative;
        }

        .btn {
            padding: 12px 28px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: white;
            box-shadow: 0 10px 15px -3px rgba(139, 92, 246, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(139, 92, 246, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        @media (max-width: 640px) {
            .error-card { padding: 40px 20px; }
            .error-code { font-size: 4.5rem; }
            .actions { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="brand-container">
        <a href="/" class="brand">Plugs</a>
    </div>

    <div class="error-card">
        <span class="error-icon">' . $error['icon'] . '</span>
        <h1>' . htmlspecialchars($displayTitle) . '</h1>
        <p class="message">' . htmlspecialchars($displayMessage) . '</p>
        <div class="error-code">' . $statusCode . '</div>
        <div class="actions">
            <a href="/" class="btn btn-primary">
                <span>🏠</span> Return Home
            </a>
            <button onclick="window.location.reload()" class="btn btn-secondary">
                <span>🔄</span> Try Again
            </button>
        </div>
    </div>

    <div class="footer" style="position: absolute; bottom: 40px; color: var(--text-secondary); font-size: 0.85rem; opacity: 0.5;">
        &copy; ' . date('Y') . ' Plugs Framework &middot; All Rights Reserved
    </div>
</body>
</html>';
}
