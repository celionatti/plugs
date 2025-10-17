<?php

declare(strict_types=1);

/**
 * Render debug error page with detailed information
 */
function renderDebugErrorPage(Exception $e): void
{
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);

    $trace = $e->getTrace();
    $file = $e->getFile();
    $line = $e->getLine();
    
    // Get code snippet around the error line
    $codeSnippet = getCodeSnippet($file, $line);
    
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars(get_class($e)) . '</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "Nunito", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f7f8fa;
            color: #2d3748;
            line-height: 1.6;
            font-size: 14px;
        }
        
        .error-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2.5rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .error-header-content {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .error-title {
            font-size: 1.1rem;
            font-weight: 600;
            opacity: 0.9;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .error-message {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1rem;
            line-height: 1.3;
        }
        
        .error-location {
            font-size: 0.95rem;
            opacity: 0.9;
            font-family: "Fira Code", Consolas, Monaco, monospace;
        }
        
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .error-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card-header {
            padding: 1.25rem 1.5rem;
            background: #f7fafc;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 0;
        }
        
        .code-editor {
            background: #282c34;
            color: #abb2bf;
            font-family: "Fira Code", Consolas, Monaco, monospace;
            font-size: 13px;
            overflow-x: auto;
        }
        
        .code-line {
            display: flex;
            padding: 0.25rem 0;
            min-height: 22px;
        }
        
        .code-line:hover {
            background: #2c313a;
        }
        
        .line-number {
            color: #5c6370;
            text-align: right;
            padding: 0 1rem;
            min-width: 50px;
            user-select: none;
            flex-shrink: 0;
        }
        
        .line-content {
            flex: 1;
            padding-right: 1rem;
            white-space: pre;
        }
        
        .error-line {
            background: #7c2d12;
            border-left: 3px solid #dc2626;
        }
        
        .error-line .line-number {
            color: #fca5a5;
            font-weight: bold;
        }
        
        .error-line .line-content {
            color: #fecaca;
        }
        
        .stack-trace {
            list-style: none;
        }
        
        .stack-item {
            border-bottom: 1px solid #e2e8f0;
        }
        
        .stack-header {
            padding: 1rem 1.5rem;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: start;
            gap: 1rem;
        }
        
        .stack-header:hover {
            background: #f7fafc;
        }
        
        .stack-number {
            color: #667eea;
            font-weight: 700;
            font-size: 0.9rem;
            min-width: 30px;
        }
        
        .stack-info {
            flex: 1;
        }
        
        .stack-class {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.25rem;
        }
        
        .stack-file {
            font-size: 0.85rem;
            color: #718096;
            font-family: "Fira Code", Consolas, Monaco, monospace;
        }
        
        .stack-toggle {
            color: #a0aec0;
            font-size: 1.2rem;
            transition: transform 0.2s;
        }
        
        .stack-toggle.active {
            transform: rotate(90deg);
        }
        
        .stack-code {
            display: none;
            border-top: 1px solid #e2e8f0;
        }
        
        .stack-code.active {
            display: block;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            padding: 1.5rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .info-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #718096;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            color: #2d3748;
            font-size: 0.9rem;
            word-break: break-all;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            background: #f7fafc;
        }
        
        .tab {
            padding: 1rem 1.5rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            font-weight: 600;
            color: #718096;
        }
        
        .tab:hover {
            color: #4a5568;
        }
        
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content {
            display: none;
            padding: 1.5rem;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .request-item {
            padding: 0.75rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            gap: 1rem;
        }
        
        .request-item:last-child {
            border-bottom: none;
        }
        
        .request-key {
            color: #667eea;
            font-weight: 600;
            min-width: 120px;
        }
        
        .request-value {
            color: #2d3748;
            word-break: break-all;
            font-family: "Fira Code", Consolas, Monaco, monospace;
            font-size: 0.85rem;
        }
        
        .actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: white;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
            transform: translateY(-1px);
        }
        
        @media (max-width: 768px) {
            .error-header {
                padding: 1.5rem 1rem;
            }
            
            .error-message {
                font-size: 1.25rem;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .stack-header {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .tabs {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="error-header">
        <div class="error-header-content">
            <div class="error-title">' . htmlspecialchars(get_class($e)) . '</div>
            <div class="error-message">' . htmlspecialchars($e->getMessage()) . '</div>
            <div class="error-location">
                📁 ' . htmlspecialchars(basename($file)) . ' : ' . htmlspecialchars((string)$line) . '
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="error-card">
            <div class="card-header">
                🔍 Error Source
            </div>
            <div class="card-body">
                <div class="code-editor">
                    ' . $codeSnippet . '
                </div>
            </div>
        </div>
        
        <div class="error-card">
            <div class="card-header">
                📚 Stack Trace
            </div>
            <div class="card-body">
                <ul class="stack-trace">
                    ' . renderStackTrace($trace) . '
                </ul>
            </div>
        </div>
        
        <div class="error-card">
            <div class="tabs">
                <div class="tab active" onclick="switchTab(event, \'request\')">Request</div>
                <div class="tab" onclick="switchTab(event, \'server\')">Server</div>
                <div class="tab" onclick="switchTab(event, \'details\')">Details</div>
            </div>
            <div class="tab-content active" id="request">
                ' . renderRequestInfo() . '
            </div>
            <div class="tab-content" id="server">
                ' . renderServerInfo() . '
            </div>
            <div class="tab-content" id="details">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Exception</div>
                        <div class="info-value">' . htmlspecialchars(get_class($e)) . '</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Code</div>
                        <div class="info-value">' . htmlspecialchars((string)$e->getCode()) . '</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">File</div>
                        <div class="info-value">' . htmlspecialchars($file) . '</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Line</div>
                        <div class="info-value">' . htmlspecialchars((string)$line) . '</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="actions">
            <a href="/" class="btn btn-primary">
                <span>🏠</span>
                <span>Go to Homepage</span>
            </a>
            <button onclick="location.reload()" class="btn btn-secondary">
                <span>🔄</span>
                <span>Reload Page</span>
            </button>
            <button onclick="history.back()" class="btn btn-secondary">
                <span>↩️</span>
                <span>Go Back</span>
            </button>
        </div>
    </div>
    
    <script>
        function toggleStack(element) {
            const codeBlock = element.nextElementSibling;
            const toggle = element.querySelector(".stack-toggle");
            
            codeBlock.classList.toggle("active");
            toggle.classList.toggle("active");
        }
        
        function switchTab(event, tabName) {
            const tabs = document.querySelectorAll(".tab");
            const contents = document.querySelectorAll(".tab-content");
            
            tabs.forEach(tab => tab.classList.remove("active"));
            contents.forEach(content => content.classList.remove("active"));
            
            event.target.classList.add("active");
            document.getElementById(tabName).classList.add("active");
        }
    </script>
</body>
</html>';

    echo $html;
}

function getCodeSnippet(string $file, int $line, int $context = 10): string
{
    if (!file_exists($file)) {
        return '<div class="code-line"><div class="line-number">-</div><div class="line-content">File not found</div></div>';
    }
    
    $lines = file($file);
    $start = max(0, $line - $context - 1);
    $end = min(count($lines), $line + $context);
    
    $output = '';
    for ($i = $start; $i < $end; $i++) {
        $currentLine = $i + 1;
        $isErrorLine = $currentLine === $line;
        $class = $isErrorLine ? ' error-line' : '';
        
        $output .= '<div class="code-line' . $class . '">';
        $output .= '<div class="line-number">' . $currentLine . '</div>';
        $output .= '<div class="line-content">' . htmlspecialchars($lines[$i]) . '</div>';
        $output .= '</div>';
    }
    
    return $output;
}

function renderStackTrace(array $trace): string
{
    $output = '';
    
    foreach ($trace as $index => $item) {
        $class = $item['class'] ?? '';
        $function = $item['function'] ?? '';
        $file = $item['file'] ?? 'unknown';
        $line = $item['line'] ?? 0;
        
        $callSignature = $class ? "$class::$function()" : "$function()";
        
        $output .= '<li class="stack-item">';
        $output .= '<div class="stack-header" onclick="toggleStack(this)">';
        $output .= '<div class="stack-number">#' . $index . '</div>';
        $output .= '<div class="stack-info">';
        $output .= '<div class="stack-class">' . htmlspecialchars($callSignature) . '</div>';
        $output .= '<div class="stack-file">' . htmlspecialchars($file) . ':' . $line . '</div>';
        $output .= '</div>';
        $output .= '<div class="stack-toggle">▶</div>';
        $output .= '</div>';
        
        if ($file !== 'unknown' && file_exists($file)) {
            $output .= '<div class="stack-code">';
            $output .= '<div class="code-editor">' . getCodeSnippet($file, $line, 5) . '</div>';
            $output .= '</div>';
        }
        
        $output .= '</li>';
    }
    
    return $output;
}

function renderRequestInfo(): string
{
    $output = '<div>';
    
    $items = [
        'Method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
        'URI' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
        'Query String' => $_SERVER['QUERY_STRING'] ?? 'None',
        'IP Address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'Referrer' => $_SERVER['HTTP_REFERER'] ?? 'None',
        'User Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
    ];
    
    foreach ($items as $key => $value) {
        $output .= '<div class="request-item">';
        $output .= '<div class="request-key">' . htmlspecialchars($key) . '</div>';
        $output .= '<div class="request-value">' . htmlspecialchars($value) . '</div>';
        $output .= '</div>';
    }
    
    $output .= '</div>';
    return $output;
}

function renderServerInfo(): string
{
    $output = '<div>';
    
    $items = [
        'PHP Version' => PHP_VERSION,
        'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'Server Name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
        'Server IP' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
        'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        'Script Filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'Unknown',
    ];
    
    foreach ($items as $key => $value) {
        $output .= '<div class="request-item">';
        $output .= '<div class="request-key">' . htmlspecialchars($key) . '</div>';
        $output .= '<div class="request-value">' . htmlspecialchars($value) . '</div>';
        $output .= '</div>';
    }
    
    $output .= '</div>';
    return $output;
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