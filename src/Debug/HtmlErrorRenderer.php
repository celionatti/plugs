<?php

declare(strict_types=1);

namespace Plugs\Debug;

use Throwable;
use Plugs\Debug\ErrorAnalyzer;

/**
 * Plugs Framework HTML Error Renderer
 * 
 * Handles rendering of both debug and production error pages.
 */
class HtmlErrorRenderer
{
    /**
     * Render a detailed debug error page.
     * 
     * @param Throwable $e
     * @param string|null $nonce
     * @return void
     */
    public function renderDebug(Throwable $e, ?string $nonce = null): void
    {
        error_log($e->getMessage() . "\n" . $e->getTraceAsString());
        http_response_code(500);

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
            header_remove('Content-Security-Policy');
        }

        $trace = $e->getTrace();
        $file = $e->getFile();
        $line = $e->getLine();
        $className = get_class($e);
        $shortClass = basename(str_replace('\\', '/', $className));
        $message = htmlspecialchars($e->getMessage());
        $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';

        $analyzer = new ErrorAnalyzer();
        $suggestions = $analyzer->analyze($e);

        // Detect specific errors
        $msg = $e->getMessage();
        $isMissingComponent = str_contains($msg, 'not found') && (str_contains($msg, 'Controller') || str_contains($msg, 'Method'));
        $badgeText = $isMissingComponent ? 'Missing Component' : htmlspecialchars($className);
        $badgeColor = $isMissingComponent ? '#f59e0b' : '#ef4444';

        // Build smart frames - filter to relevant ones
        $allFrames = [];
        $allFrames[] = ['file' => $file, 'line' => $line, 'function' => '{main}', 'class' => '', 'type' => '', 'args' => []];
        foreach ($trace as $t) {
            $allFrames[] = $t;
        }

        $appFrames = [];
        $internalFrames = [];
        foreach ($allFrames as $i => $frame) {
            $f = $frame['file'] ?? '';
            $isInternal = !$f || str_contains($f, 'vendor') || str_contains($f, 'src' . DIRECTORY_SEPARATOR . 'Debug');
            if ($i === 0 || !$isInternal) {
                $appFrames[] = ['frame' => $frame, 'index' => $i];
            } else {
                $internalFrames[] = ['frame' => $frame, 'index' => $i];
            }
        }

        if (count($appFrames) > 5) {
            $extraFrames = array_slice($appFrames, 5);
            $appFrames = array_slice($appFrames, 0, 5);
            $internalFrames = array_merge($extraFrames, $internalFrames);
        }

        // Code snippet for the error line
        $mainSnippet = (file_exists($file)) ? $this->getCodeSnippet($file, $line) : '<div style="padding:2rem;color:#94a3b8;text-align:center;">No preview available.</div>';

        // Request info
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $phpVersion = PHP_VERSION;
        $os = PHP_OS;
        $memory = round(memory_get_usage() / 1024 / 1024, 2) . ' MB';

        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plugs &middot; ' . $shortClass . '</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style' . $nonceAttr . '>
        @import url("https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Fira+Code:wght@400;500&display=swap");
        * { margin:0; padding:0; box-sizing:border-box; }
        ::selection { background:rgba(139,92,246,0.3); color:#fff; }
        html, body { height:100%; overflow:hidden; }
        body { background:#0c0f1a; color:#e2e8f0; font-family:"Outfit",sans-serif; line-height:1.6; }

        .shell { display:grid; grid-template-columns:380px 1fr; height:100vh; overflow:hidden; }

        .left { background:#111827; border-right:1px solid rgba(139,92,246,0.12); display:flex; flex-direction:column; overflow-y:auto; }
        .left-header { padding:2rem 1.75rem 1.5rem; border-bottom:1px solid rgba(255,255,255,0.06); }
        .brand { font-family:"Outfit",sans-serif; font-size:1.5rem; font-weight:800; background:linear-gradient(135deg,#a78bfa,#60a5fa); -webkit-background-clip:text; -webkit-text-fill-color:transparent; letter-spacing:-0.02em; text-decoration:none; }
        .error-type { margin-top:1.5rem; font-family:"Outfit",sans-serif; font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; display:flex; align-items:center; gap:0.4rem; }
        .error-msg { margin-top:0.75rem; font-family:"Outfit",sans-serif; font-size:1.15rem; font-weight:700; color:#f8fafc; line-height:1.4; word-break:break-word; }
        .info-section { padding:1.25rem 1.75rem; border-bottom:1px solid rgba(255,255,255,0.04); }
        .info-label { font-family:"Outfit",sans-serif; font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#64748b; margin-bottom:0.6rem; }
        .info-row { display:flex; justify-content:space-between; align-items:center; padding:0.4rem 0; font-family:"Outfit",sans-serif; font-size:0.8rem; }
        .info-row .key { color:#94a3b8; }
        .info-row .val { color:#e2e8f0; font-family:"Fira Code",monospace; font-size:0.75rem; }
        .file-path { font-family:"Fira Code",monospace; font-size:0.75rem; color:#94a3b8; word-break:break-all; line-height:1.5; }
        .file-path .line-num { color:#ef4444; font-weight:700; }
        .open-editor { display:inline-flex; align-items:center; gap:0.5rem; margin-top:0.75rem; padding:0.5rem 1rem; background:rgba(139,92,246,0.15); border:1px solid rgba(139,92,246,0.3); border-radius:8px; color:#a78bfa; font-size:0.75rem; font-weight:600; text-decoration:none; transition:all 0.2s; }
        .open-editor:hover { background:rgba(139,92,246,0.25); color:#c4b5fd; }
        .suggestions-box { margin-top:1rem; padding:1rem; background:rgba(139,92,246,0.08); border:1px dashed rgba(139,92,246,0.3); border-radius:12px; }
        .left-footer { margin-top:auto; padding:1rem 1.75rem; border-top:1px solid rgba(255,255,255,0.04); font-size:0.7rem; color:#475569; text-align:center; }

        .right { display:flex; flex-direction:column; overflow-y:auto; background:#0c0f1a; }
        .right-header { padding:1.25rem 2rem; border-bottom:1px solid rgba(255,255,255,0.06); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
        .right-header h2 { font-family:"Outfit",sans-serif; font-size:0.85rem; font-weight:700; color:#a78bfa; text-transform:uppercase; letter-spacing:0.05em; }

        .code-container { background:#080b14; overflow-x:auto; flex-shrink:0; }
        .code-table { width:100%; border-collapse:collapse; font-family:"Fira Code",monospace; font-size:13px; }
        .code-row.error-line { background:rgba(239,68,68,0.12); position:relative; }
        .code-row.error-line::before { content:""; position:absolute; left:0; top:0; bottom:0; width:3px; background:#ef4444; }
        .code-line-num { width:55px; text-align:right; padding:0 1rem 0 0; color:#334155; border-right:1px solid rgba(255,255,255,0.04); user-select:none; }
        .error-line .code-line-num { color:#ef4444; font-weight:700; }
        .code-content { padding-left:1rem; white-space:pre; color:#cbd5e1; line-height:1.7; }
        .token-keyword { color:#c084fc; font-weight:600; }
        .token-string { color:#4ade80; }
        .token-var { color:#60a5fa; }
        .token-comment { color:#475569; font-style:italic; }

        .stack-section { padding:1.5rem 2rem; flex:1; }
        .stack-section h3 { font-family:"Outfit",sans-serif; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#64748b; margin-bottom:1rem; }
        .stack-item { padding:0.7rem 0.9rem; background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.04); border-radius:8px; margin-bottom:0.5rem; transition:all 0.15s; cursor:pointer; }
        .stack-item:hover { background:rgba(139,92,246,0.06); border-color:rgba(139,92,246,0.15); transform:translateX(3px); }
        .stack-item.active { background:rgba(139,92,246,0.1); border-color:rgba(139,92,246,0.3); }
        .stack-item.internal { opacity:0.45; }
        .stack-call { color:#60a5fa; font-family:"Fira Code",monospace; font-size:0.75rem; font-weight:500; }
        .stack-file { color:#475569; font-family:"Fira Code",monospace; font-size:0.7rem; margin-top:0.15rem; word-break:break-all; }
        .stack-preview { display:none; margin-top:0.75rem; border-top:1px solid rgba(255,255,255,0.06); padding-top:0.75rem; overflow-x:auto; }
        .stack-item.expanded .stack-preview { display:block; }
        .toggle-internal { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.08); color:#94a3b8; padding:0.5rem 1rem; border-radius:8px; font-family:"Outfit",sans-serif; font-size:0.75rem; cursor:pointer; transition:all 0.2s; margin-top:0.5rem; }
        .toggle-internal:hover { background:rgba(255,255,255,0.08); color:#e2e8f0; }
        .internal-frames { display:none; }
        .internal-frames.visible { display:block; }

        .detail-tabs { display:flex; gap:0.5rem; padding:1rem 2rem; border-bottom:1px solid rgba(255,255,255,0.06); background:rgba(0,0,0,0.15); }
        .detail-tab { padding:0.5rem 1rem; background:transparent; border:none; border-bottom:2px solid transparent; color:#64748b; font-family:"Outfit",sans-serif; font-weight:600; font-size:0.8rem; cursor:pointer; transition:all 0.2s; }
        .detail-tab:hover { color:#e2e8f0; }
        .detail-tab.active { color:#a78bfa; border-bottom-color:#a78bfa; }
        .detail-panel { display:none; padding:1.5rem 2rem; }
        .detail-panel.active { display:block; }
        .data-table { width:100%; border-collapse:collapse; font-size:0.8rem; background:rgba(0,0,0,0.2); border-radius:8px; overflow:hidden; border:1px solid rgba(139,92,246,0.1); }
        .data-table th, .data-table td { padding:0.7rem 1rem; text-align:left; border-bottom:1px solid rgba(139,92,246,0.08); }
        .data-table th { background:rgba(255,255,255,0.03); color:#94a3b8; font-weight:600; width:30%; font-family:"Outfit",sans-serif; }
        .data-table td { font-family:"Fira Code",monospace; color:#e2e8f0; word-break:break-all; font-size:0.75rem; }
        .data-table tr:last-child th, .data-table tr:last-child td { border-bottom:none; }

        @media (max-width:900px) {
            html, body { height:auto; overflow:auto; }
            .shell { grid-template-columns:1fr; grid-template-rows:auto 1fr; height:auto; overflow:visible; }
            .left { border-right:none; border-bottom:1px solid rgba(139,92,246,0.12); overflow-y:visible; }
            .right { overflow-y:visible; }
            .left-footer { display:none; }
        }
        @media (max-width:480px) {
            .left-header { padding:1.5rem 1.25rem 1.25rem; }
            .info-section { padding:1rem 1.25rem; }
            .stack-section { padding:1.25rem; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="left">
            <div class="left-header">
                <a href="/" class="brand">⚡ Plugs</a>
                <div class="error-type" style="color:' . $badgeColor . '">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4m0 4h.01"/></svg>
                    ' . $badgeText . '
                </div>
                <div class="error-msg">' . $message . '</div>
            </div>

            <div class="info-section">
                <div class="info-label">📄 Source</div>
                <div class="file-path" id="active-file-path">' . htmlspecialchars($file) . ' <span class="line-num">:' . $line . '</span></div>
                <a href="vscode://file/' . htmlspecialchars(realpath($file) ?: $file) . ':' . $line . '" id="active-vscode-btn" class="open-editor">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                    Open in Editor
                </a>
            </div>';

        if (!empty($suggestions)) {
            echo '<div class="info-section">
                <div class="info-label">💡 Suggestions</div>
                <div class="suggestions-box">';
            foreach ($suggestions as $suggestion) {
                $parsed = preg_replace('/\*\*(.*?)\*\*/', '<strong style="color:#f8fafc;">$1</strong>', htmlspecialchars($suggestion));
                $parsed = preg_replace('/`(.*?)`/', '<code style="background:rgba(0,0,0,0.3);padding:0.15rem 0.4rem;border-radius:6px;color:#60a5fa;font-family:Fira Code;font-size:0.7rem;">$1</code>', $parsed);
                echo '<div style="margin-bottom:0.4rem;color:#94a3b8;font-size:0.8rem;display:flex;gap:0.4rem;"><span style="color:#a78bfa;">•</span> ' . $parsed . '</div>';
            }
            echo '</div></div>';
        }

        echo '
            <div class="info-section">
                <div class="info-label">🌐 Request</div>
                <div class="info-row"><span class="key">Method</span><span class="val">' . $method . '</span></div>
                <div class="info-row"><span class="key">URL</span><span class="val">' . htmlspecialchars($uri) . '</span></div>
            </div>

            <div class="info-section">
                <div class="info-label">⚙️ Environment</div>
                <div class="info-row"><span class="key">PHP</span><span class="val">' . $phpVersion . '</span></div>
                <div class="info-row"><span class="key">OS</span><span class="val">' . $os . '</span></div>
                <div class="info-row"><span class="key">Memory</span><span class="val">' . $memory . '</span></div>
            </div>

            <div class="left-footer">&copy; ' . date('Y') . ' Plugs Framework</div>
        </aside>

        <main class="right">
            <div class="right-header">
                <h2>Execution Snippet</h2>
                <span style="font-family:Fira Code,monospace;font-size:0.7rem;color:#475569;">Line ' . $line . '</span>
            </div>

            <div class="code-container">' . $mainSnippet . '</div>

            <div class="stack-section">
                <h3>Stack Trace (' . count($appFrames) . ' app frames)</h3>';

        // Render app frames
        foreach ($appFrames as $entry) {
            $frame = $entry['frame'];
            $idx = $entry['index'];
            $f = $frame['file'] ?? '{internal}';
            $l = $frame['line'] ?? '-';
            $func = ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'];
            $activeClass = $idx === 0 ? ' active' : '';
            $previewHtml = '';
            if (isset($frame['file']) && file_exists($frame['file'])) {
                $previewHtml = $this->getCodeSnippet($frame['file'], (int) ($frame['line'] ?? 0));
            }

            echo '<div class="stack-item' . $activeClass . '" data-index="' . $idx . '" data-file="' . htmlspecialchars($f) . '" data-line="' . $l . '">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span class="stack-call">' . htmlspecialchars($func) . '()</span>
                    <span class="stack-file">' . htmlspecialchars(basename($f)) . ':' . $l . '</span>
                </div>
                <div class="stack-preview"><div class="code-container" style="border-radius:8px;max-height:200px;overflow:auto;">' . $previewHtml . '</div></div>
            </div>';
        }

        // Render internal frames toggle
        if (!empty($internalFrames)) {
            echo '<button class="toggle-internal" onclick="document.getElementById(\'internal-frames\').classList.toggle(\'visible\');this.textContent=this.textContent.includes(\'Show\')?\'Hide ' . count($internalFrames) . ' other frames\':\'Show ' . count($internalFrames) . ' other frames\';">Show ' . count($internalFrames) . ' other frames</button>';
            echo '<div id="internal-frames" class="internal-frames">';
            foreach ($internalFrames as $entry) {
                $frame = $entry['frame'];
                $idx = $entry['index'];
                $f = $frame['file'] ?? '{internal}';
                $l = $frame['line'] ?? '-';
                $func = ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'];
                $previewHtml = '';
                if (isset($frame['file']) && file_exists($frame['file'])) {
                    $previewHtml = $this->getCodeSnippet($frame['file'], (int) ($frame['line'] ?? 0));
                }

                echo '<div class="stack-item internal" data-index="' . $idx . '" data-file="' . htmlspecialchars($f) . '" data-line="' . $l . '">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span class="stack-call">' . htmlspecialchars($func) . '()</span>
                        <span class="stack-file">' . htmlspecialchars(basename($f)) . ':' . $l . '</span>
                    </div>
                    <div class="stack-preview"><div class="code-container" style="border-radius:8px;max-height:200px;overflow:auto;">' . $previewHtml . '</div></div>
                </div>';
            }
            echo '</div>';
        }

        echo '</div>

            <div class="detail-tabs">
                <button class="detail-tab active" data-panel="headers">Headers</button>
                <button class="detail-tab" data-panel="app">App</button>
                <button class="detail-tab" data-panel="env">Server</button>
            </div>

            <div id="panel-headers" class="detail-panel active">
                <table class="data-table">';
        foreach ($headers as $name => $value) {
            echo '<tr><th>' . htmlspecialchars($name) . '</th><td>' . htmlspecialchars($value) . '</td></tr>';
        }
        if (!empty($_POST)) {
            echo '</table><div style="font-family:Outfit;font-size:0.75rem;font-weight:700;text-transform:uppercase;color:#64748b;margin:1.5rem 0 0.75rem;">Post Data</div><table class="data-table">';
            foreach ($_POST as $key => $val) {
                $v = is_array($val) ? json_encode($val) : $val;
                echo '<tr><th>' . htmlspecialchars($key) . '</th><td>' . htmlspecialchars($v) . '</td></tr>';
            }
        }
        echo '</table>
            </div>

            <div id="panel-app" class="detail-panel">
                <table class="data-table">
                    <tr><th>Environment</th><td>' . (defined('APP_ENV') ? APP_ENV : 'development') . '</td></tr>
                    <tr><th>Debug Mode</th><td>' . (defined('APP_DEBUG') && APP_DEBUG ? 'Enabled' : 'Disabled') . '</td></tr>
                    <tr><th>Base Path</th><td>' . htmlspecialchars(realpath(__DIR__ . '/../../') ?: 'N/A') . '</td></tr>
                </table>
            </div>

            <div id="panel-env" class="detail-panel">
                <table class="data-table">
                    <tr><th>PHP Version</th><td>' . PHP_VERSION . '</td></tr>
                    <tr><th>SAPI</th><td>' . PHP_SAPI . '</td></tr>
                    <tr><th>OS</th><td>' . PHP_OS . '</td></tr>
                    <tr><th>Server</th><td>' . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . '</td></tr>
                    <tr><th>Memory Limit</th><td>' . ini_get('memory_limit') . '</td></tr>
                </table>
            </div>
        </main>
    </div>

    <script' . $nonceAttr . '>
        // Toggle stack item preview on click
        document.querySelectorAll(".stack-item").forEach(el => {
            el.addEventListener("click", function() {
                this.classList.toggle("expanded");
                // Update sidebar source info
                const file = this.getAttribute("data-file");
                const line = this.getAttribute("data-line");
                const pathEl = document.getElementById("active-file-path");
                if (pathEl && file !== "{internal}") {
                    pathEl.innerHTML = file + \' <span class="line-num">:\' + line + \'</span>\';
                }
                const btn = document.getElementById("active-vscode-btn");
                if (btn && file !== "{internal}") {
                    btn.href = "vscode://file/" + file + ":" + line;
                }
            });
        });

        // Detail tabs
        document.querySelectorAll(".detail-tab").forEach(btn => {
            btn.addEventListener("click", function() {
                document.querySelectorAll(".detail-tab").forEach(b => b.classList.remove("active"));
                this.classList.add("active");
                document.querySelectorAll(".detail-panel").forEach(p => p.classList.remove("active"));
                document.getElementById("panel-" + this.getAttribute("data-panel")).classList.add("active");
            });
        });
    </script>
</body>
</html>';
    }

    /**
     * Render a production error page.
     * 
     * @param Throwable $e
     * @param int $statusCode
     * @param string|null $nonce
     * @return void
     */
    public function renderProduction(Throwable $e, int $statusCode = 500, ?string $nonce = null): void
    {
        error_log($e->getMessage() . "\n" . $e->getTraceAsString());
        http_response_code($statusCode);

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
            header_remove('Content-Security-Policy'); // Ensure error page styles are not blocked
        }

        echo $this->getProductionHtml($statusCode, $nonce);
    }

    /**
     * Get debug head/styles.
     */
    protected function getDebugHeader(string $title, ?string $nonce = null): string
    {
        $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';
        return '<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plugs &middot; ' . htmlspecialchars($title) . '</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style' . $nonceAttr . '>
        @import url("https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Fira+Code:wght@400;500&display=swap");

        :root {
            --bg: #0b0f1a;
            --card: #151b2b;
            --primary: #a78bfa;
            --accent: #60a5fa;
            --danger: #ef4444;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --border: rgba(139, 92, 246, 0.15);
            --glass: rgba(15, 23, 42, 0.85);
            --glass-light: rgba(255, 255, 255, 0.03);
            --selection: rgba(139, 92, 246, 0.3);
        }

        ::selection { background: var(--selection); color: var(--text); }

        body.plugs-error-page {
            background-color: var(--bg);
            background-image: radial-gradient(circle at 10% 10%, rgba(139, 92, 246, 0.1) 0%, transparent 40%), 
                              radial-gradient(circle at 90% 90%, rgba(96, 165, 250, 0.1) 0%, transparent 40%);
            color: var(--text);
            font-family: "Outfit", sans-serif;
            margin: 0; padding: 0; min-height: 100vh; overflow-x: hidden;
            line-height: 1.5;
        }

        .plugs-error-page * { box-sizing: border-box; }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: clamp(1rem, 5vw, 4rem);
            animation: fadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.7);
        }

        header.header {
            padding: 2rem 2.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(to bottom, var(--glass-light), transparent);
        }

        .brand {
            font-family: "Outfit", sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, #a78bfa, #60a5fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            letter-spacing: -0.02em;
        }

        .error-banner {
            padding: 3rem 2.5rem;
            border-bottom: 1px solid var(--border);
            background: radial-gradient(circle at top left, rgba(239, 68, 68, 0.05), transparent 40%);
        }

        .exception-message {
            font-size: clamp(1.75rem, 5vw, 3rem);
            font-weight: 800;
            line-height: 1.1;
            margin: 1.5rem 0;
            color: var(--text);
            letter-spacing: -0.03em;
        }

        .main-content {
            padding: 3rem 2.5rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            margin-bottom: 2rem;
            color: var(--primary);
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.15em;
        }

        .section-header::after {
            content: "";
            flex: 1;
            height: 1px;
            background: linear-gradient(to right, var(--border), transparent);
        }

        /* Stack & Code */
        .stack-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 3rem;
            padding: 0;
            list-style: none;
        }

        .stack-item {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stack-item:hover {
            background: rgba(139, 92, 246, 0.05);
            transform: translateX(8px);
            border-color: rgba(139, 92, 246, 0.3);
        }

        .stack-item.active {
            background: rgba(139, 92, 246, 0.08);
            border-color: var(--primary);
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.1);
        }

        .code-viewer-container {
            border-radius: 20px;
            background: #080b14;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 3rem;
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.5);
        }

        .code-table {
            width: 100%;
            border-collapse: collapse;
            font-family: "Fira Code", monospace;
            font-size: 14px;
        }

        .code-row.error-line {
            background: rgba(239, 68, 68, 0.15);
            position: relative;
        }

        .code-row.error-line::before {
            content: "";
            position: absolute;
            left: 0; top: 0; bottom: 0; width: 4px;
            background: var(--danger);
            box-shadow: 4px 0 15px var(--danger);
            z-index: 10;
        }

        .code-line-num {
            width: 70px;
            text-align: right;
            padding: 0.6rem 1.5rem;
            color: #4b5563;
            border-right: 1px solid rgba(255,255,255,0.05);
            user-select: none;
            background: rgba(0,0,0,0.2);
        }

        .error-line .code-line-num {
            color: var(--danger);
            font-weight: 700;
            background: rgba(239, 68, 68, 0.1);
        }

        .code-content {
            padding: 0.6rem 1.5rem;
            white-space: pre;
            color: #e2e8f0;
        }

        .token-keyword { color: #c084fc; font-weight: 600; }
        .token-string { color: #4ade80; }
        .token-var { color: #60a5fa; }
        .token-comment { color: #64748b; font-style: italic; }

        .error-banner {
            padding: 3rem 2.5rem;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(to bottom, rgba(139, 92, 246, 0.05), transparent);
        }

        .exception-message {
            font-size: 2.2rem;
            font-weight: 850;
            margin: 1rem 0 1.5rem;
            line-height: 1.2;
            letter-spacing: -0.03em;
            word-break: break-word;
            background: linear-gradient(135deg, #fff 60%, rgba(255,255,255,0.7));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Tabs System */
        .tabs-nav {
            display: flex;
            gap: 0.5rem;
            padding: 0 2.5rem;
            margin-top: -1px;
            border-bottom: 1px solid var(--border);
            background: rgba(15, 23, 42, 0.4);
        }

        .tab-btn {
            padding: 1rem 1.5rem;
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            color: var(--text-muted);
            font-family: inherit;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tab-btn:hover {
            color: var(--text);
            background: rgba(255,255,255,0.03);
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: rgba(139, 92, 246, 0.05);
        }

        .tab-content {
            display: none;
            padding: 2.5rem;
            animation: fadeIn 0.3s ease-out;
        }

        .tab-content.active {
            display: block;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            background: rgba(0,0,0,0.2);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .data-table th, .data-table td {
            padding: 0.85rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .data-table th {
            background: rgba(255,255,255,0.03);
            color: var(--text-muted);
            font-weight: 600;
            width: 30%;
        }

        .data-table td {
            font-family: "Fira Code", monospace;
            color: var(--text);
            word-break: break-all;
        }

        .data-table tr:last-child th, .data-table tr:last-child td {
            border-bottom: none;
        }

        .stack-item {
            padding: 1rem 1.25rem;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stack-item.active {
            background: rgba(139, 92, 246, 0.1);
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.1);
        }

        .stack-item:hover:not(.active) {
            background: rgba(255,255,255,0.05);
            transform: translateX(4px);
        }

        .stack-item.internal {
            opacity: 0.5;
            font-size: 0.85rem;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            header.header { padding: 1.5rem; }
            .error-banner { padding: 2.5rem 1.5rem; }
            .tab-content { padding: 1.5rem; }
            .tabs-nav { padding: 0 1rem; overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .tab-btn { padding: 0.85rem 1rem; white-space: nowrap; }
            .exception-message { font-size: 1.75rem; }
            .file-location-bar { flex-direction: column; align-items: flex-start; }
            .vscode-btn { width: 100%; justify-content: center; }
            .stack-item:hover { transform: none; }
        }

    </style>
</head>
<body class="plugs-error-page">
    <header class="header"><a href="/" class="brand">⚡ Plugs</a></header>';
    }

    /**
     * Get debug body content.
     */
    protected function getDebugBody(Throwable $e, string $className, string $file, int $line, array $frames, array $suggestions = [], ?string $nonce = null, bool $isMissingComponent = false): string
    {
        $badgeColor = $isMissingComponent ? '#f59e0b' : 'var(--danger)';
        $badgeText = $isMissingComponent ? 'Missing Component' : htmlspecialchars($className);

        $html = '<div class="container">
        <div class="glass-card">
            <header class="header">
                <a href="/" class="brand">
                    ⚡ Plugs
                </a>
            </header>

            <div class="error-banner">
                <div style="color: ' . $badgeColor . '; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 0.5rem;">
                    ' . ($isMissingComponent ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4m0 4h.01"/></svg>' : '') . '
                    ' . $badgeText . '
                </div>
                <h1 class="exception-message">' . htmlspecialchars($e->getMessage()) . '</h1>
                <div class="file-location-bar">
                    <span id="active-location" style="color: var(--text-muted);">' . htmlspecialchars(basename($file)) . ' <span style="color: var(--danger);">: ' . $line . '</span></span>
                    <a href="vscode://file/' . htmlspecialchars(realpath($file) ?: $file) . ':' . $line . '" id="active-vscode-btn" class="vscode-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                        Open in VS Code
                    </a>
                </div>';

        if (!empty($suggestions)) {
            $html .= '<div class="suggestions-box">
                <div style="font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; color: var(--primary);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18h6m-3-13a4.5 4.5 0 00-4.5 4.5c0 1.8 1.5 3.5 1.5 4.5v2a1 1 0 001 1h4a1 1 0 001-1v-2c0-1 1.5-2.7 1.5-4.5A4.5 4.5 0 0012 5z"/></svg>
                    Possible Fixes
                </div>
                <ul style="margin: 0; padding-left: 1.5rem; list-style-type: none;">';
            foreach ($suggestions as $suggestion) {
                $parsed = preg_replace('/\*\*(.*?)\*\*/', '<strong style="color: var(--text);">$1</strong>', htmlspecialchars($suggestion));
                $parsed = preg_replace('/`(.*?)`/', '<code style="background: rgba(0,0,0,0.3); padding: 0.15rem 0.4rem; border-radius: 6px; color: var(--accent); font-family: Fira Code;">$1</code>', $parsed);
                $html .= '<li style="margin-bottom: 0.5rem; color: var(--text-muted); line-height: 1.6; display: flex; gap: 0.5rem;"><span style="color: var(--primary);">•</span> ' . $parsed . '</li>';
            }
            $html .= '</ul></div>';
        }

        $html .= '</div>
            <div class="tabs-nav">
                <button class="tab-btn active" data-tab="trace">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 6h16M4 12h16M4 18h7"/></svg>
                    Trace
                </button>
                <button class="tab-btn" data-tab="request">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12H3m0 0l3.5 3.5M3 12l3.5-3.5"/></svg>
                    Request
                </button>
                <button class="tab-btn" data-tab="app">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                    App
                </button>
                <button class="tab-btn" data-tab="env">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Environment
                </button>
            </div>

            <div id="tab-trace" class="tab-content active">
                <div class="section-title">Execution Snippet</div>
                <div class="code-viewer-container">';
        foreach ($frames as $index => $frame) {
            $active = $index === 0 ? 'active' : '';
            $f = $frame['file'] ?? '';
            $l = $frame['line'] ?? 0;
            $snippet = ($f && file_exists($f)) ? $this->getCodeSnippet($f, $l) : '<div style="padding: 2.5rem; color: var(--text-muted); text-align: center;">No preview available for internal frames.</div>';
            $html .= '<div id="code-' . $index . '" class="code-viewer ' . $active . '">' . $snippet . '</div>';
        }
        $html .= '</div>

                <div class="section-title" style="margin-top: 3rem;">Stack Trace</div>
                <ul class="stack-list">';
        foreach ($frames as $index => $frame) {
            $active = $index === 0 ? 'active' : '';
            $f = $frame['file'] ?? '{internal}';
            $l = $frame['line'] ?? '-';
            $method = ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'];
            $isInternal = !isset($frame['file']) || str_contains($f, 'vendor') || str_contains($f, 'src\Debug');

            $html .= '<li class="stack-item ' . $active . ' ' . ($isInternal ? 'internal' : '') . '" data-index="' . $index . '" data-file="' . htmlspecialchars(basename($f)) . '" data-full-file="' . htmlspecialchars($f) . '" data-line="' . $l . '">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                    <span style="font-family: Fira Code; font-size: 0.85rem; color: var(--accent); font-weight: 600;">' . htmlspecialchars($method) . '()</span>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">' . htmlspecialchars(basename($f)) . ':' . $l . '</span>
                </div>
            </li>';
        }
        $html .= '</ul>
            </div>

            <div id="tab-request" class="tab-content">
                <div class="section-title">Request Information</div>
                <table class="data-table">
                    <tr><th>Method</th><td>' . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . '</td></tr>
                    <tr><th>URL</th><td>' . ($_SERVER['REQUEST_URI'] ?? 'N/A') . '</td></tr>
                    <tr><th>IP Address</th><td>' . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . '</td></tr>
                    <tr><th>User Agent</th><td>' . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . '</td></tr>
                </table>

                <div class="section-title" style="margin-top: 2rem;">Headers</div>
                <table class="data-table">';
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        foreach ($headers as $name => $value) {
            $html .= '<tr><th>' . htmlspecialchars($name) . '</th><td>' . htmlspecialchars($value) . '</td></tr>';
        }
        $html .= '</table>';

        if (!empty($_POST)) {
            $html .= '<div class="section-title" style="margin-top: 2rem;">Post Data</div>
                <table class="data-table">';
            foreach ($_POST as $key => $value) {
                $val = is_array($value) ? json_encode($value) : $value;
                $html .= '<tr><th>' . htmlspecialchars($key) . '</th><td>' . htmlspecialchars($val) . '</td></tr>';
            }
            $html .= '</table>';
        }

        $html .= '</div>

            <div id="tab-app" class="tab-content">
                <div class="section-title">Application Snapshot</div>
                <table class="data-table">
                    <tr><th>Environment</th><td>' . (defined('APP_ENV') ? APP_ENV : 'development') . '</td></tr>
                    <tr><th>Debug Mode</th><td>' . (defined('APP_DEBUG') && APP_DEBUG ? 'Enabled' : 'Disabled') . '</td></tr>
                    <tr><th>Base Path</th><td>' . htmlspecialchars(realpath(__DIR__ . '/../../') ?: 'N/A') . '</td></tr>
                </table>
            </div>

            <div id="tab-env" class="tab-content">
                <div class="section-title">Server Environment</div>
                <table class="data-table">
                    <tr><th>PHP Version</th><td>' . PHP_VERSION . '</td></tr>
                    <tr><th>SAPI</th><td>' . PHP_SAPI . '</td></tr>
                    <tr><th>OS</th><td>' . PHP_OS . '</td></tr>
                    <tr><th>Server Software</th><td>' . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . '</td></tr>
                    <tr><th>Memory Limit</th><td>' . ini_get('memory_limit') . '</td></tr>
                    <tr><th>Max Execution Time</th><td>' . ini_get('max_execution_time') . 's</td></tr>
                </table>
            </div>
        </div>
    </div>';
        return $html;
    }

    /**
     * Get debug footer/scripts.
     */
    protected function getDebugFooter(?string $nonce = null): string
    {
        $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';
        return '<footer style="margin-top: 3rem; text-align: center; padding: 2.5rem; color: var(--text-muted); font-size: 0.85rem;">
            &copy; ' . date('Y') . ' Plugs Framework &bull; Built for Plugs by Celio Natti
        </footer>
        <script' . $nonceAttr . '>
        document.querySelectorAll(".stack-item").forEach(el => {
            el.addEventListener("click", function() {
                const index = this.getAttribute("data-index");
                document.querySelectorAll(".stack-item").forEach(i => i.classList.remove("active"));
                this.classList.add("active");
                document.querySelectorAll(".code-viewer").forEach(v => v.classList.remove("active"));
                const viewer = document.getElementById("code-" + index);
                if (viewer) viewer.classList.add("active");
                
                const file = this.getAttribute("data-full-file");
                const line = this.getAttribute("data-line");
                document.getElementById("active-location").innerHTML = this.getAttribute("data-file") + " <span style=\"color: var(--danger);\">: " + line + "</span>";
                
                const btn = document.getElementById("active-vscode-btn");
                if (file && file !== "{internal}") {
                    btn.style.display = "inline-flex";
                    btn.href = "vscode://file/" + file + ":" + line;
                } else {
                    btn.style.display = "none";
                }
            });
        });

        // Tab Switching Logic
        document.querySelectorAll(".tab-btn").forEach(btn => {
            btn.addEventListener("click", function() {
                const tabId = this.getAttribute("data-tab");
                
                // Update buttons
                document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
                this.classList.add("active");
                
                // Update content
                document.querySelectorAll(".tab-content").forEach(c => c.classList.remove("active"));
                document.getElementById("tab-" + tabId).classList.add("active");
            });
        });
        </script></body></html>';
    }

    /**
     * Get code snippet.
     */
    protected function getCodeSnippet(string $file, int $line): string
    {
        $lines = file($file);
        $start = max(0, $line - 6);
        $end = min(count($lines), $line + 5);
        $output = '<table class="code-table">';

        for ($i = $start; $i < $end; $i++) {
            $currentLine = $i + 1;
            $content = $lines[$i];
            $class = $currentLine === $line ? 'error-line' : '';

            // 1. Encode first
            $highlighted = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

            // 2. Highlighting placeholders
            $highlighted = preg_replace('/(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/', '##VAR##$1##/VAR##', $highlighted);
            $highlighted = preg_replace('/(\bpublic\b|\bprivate\b|\bprotected\b|\bclass\b|\bfunction\b|\breturn\b|\bif\b|\belse\b|\bforeach\b|\bas\b|\bstatic\b|\bthrow\b|\bnew\b|\btry\b|\bcatch\b)/', '##KEY##$1##/KEY##', $highlighted);
            $highlighted = preg_replace('/(&quot;|&#039;|\'|")(.*?)(&quot;|&#039;|\'|")/', '##STR##$1$2$3##/STR##', $highlighted);

            if (str_contains($content, '//')) {
                $highlighted = preg_replace('/(\/\/.*)/', '##COM##$1##/COM##', $highlighted);
            }

            // 3. Final Swap
            $finalContent = str_replace(
                ['##VAR##', '##/VAR##', '##KEY##', '##/KEY##', '##STR##', '##/STR##', '##COM##', '##/COM##'],
                ['<span class="token-var">', '</span>', '<span class="token-keyword">', '</span>', '<span class="token-string">', '</span>', '<span class="token-comment">', '</span>'],
                $highlighted
            );

            $output .= '<tr class="code-row ' . $class . '">
                <td class="code-line-num">' . $currentLine . '</td>
                <td class="code-content">' . rtrim($finalContent) . '</td>
            </tr>';
        }
        $output .= '</table>';
        return $output;
    }

    /**
     * Get production error page HTML.
     */
    public function getProductionHtml(int $statusCode = 500, ?string $nonce = null): string
    {
        $titles = [
            404 => 'Page Not Found',
            403 => 'Access Forbidden',
            401 => 'Unauthorized',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable'
        ];
        $descriptions = [
            404 => "The path you're looking for doesn't exist or has been moved.",
            403 => "You don't have permission to access this resource.",
            401 => "Please authenticate to access this page.",
            500 => "Something went wrong on our end. We're working on it.",
            503 => "We're briefly down for maintenance. Be right back!"
        ];

        $title = $titles[$statusCode] ?? 'Unexpected Error';
        $desc = $descriptions[$statusCode] ?? 'An error occurred while processing your request.';
        $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $statusCode . ' &middot; ' . htmlspecialchars($title) . '</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style' . $nonceAttr . '>
        @import url("https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap");
        
        :root {
            --bg: #0b0f1a;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #a78bfa;
            --accent: #60a5fa;
            --border: rgba(139, 92, 246, 0.15);
        }

        body {
            background-color: var(--bg);
            background-image: radial-gradient(circle at 15% 15%, rgba(139, 92, 246, 0.12) 0%, transparent 40%), 
                              radial-gradient(circle at 85% 85%, rgba(96, 165, 250, 0.12) 0%, transparent 40%);
            color: var(--text);
            font-family: "Outfit", sans-serif;
            margin: 0; padding: 0;
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 500px;
            padding: 2rem;
            animation: fadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .error-card {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--border);
            border-radius: 32px;
            padding: 3.5rem 2.5rem;
            text-align: center;
            box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.7);
        }

        .brand {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #a78bfa, #60a5fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 2.5rem;
            display: inline-block;
            letter-spacing: -0.02em;
        }

        .status-code {
            font-size: 8rem;
            font-weight: 900;
            line-height: 0.8;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #a78bfa, #60a5fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 30px rgba(139, 92, 246, 0.3));
        }

        .title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1rem;
            letter-spacing: -0.02em;
        }

        .description {
            color: var(--text-muted);
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }

        .btn {
            background: linear-gradient(135deg, #a78bfa, #60a5fa);
            color: white;
            text-decoration: none;
            padding: 0.85rem 2rem;
            border-radius: 14px;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-block;
            box-shadow: 0 10px 25px -5px rgba(139, 92, 246, 0.4);
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px -5px rgba(139, 92, 246, 0.5);
            filter: brightness(1.1);
        }

        @media (max-width: 480px) {
            .error-card { padding: 2.5rem 1.5rem; }
            .status-code { font-size: 6rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-card">
            <div class="brand">⚡ Plugs</div>
            <div class="status-code">' . $statusCode . '</div>
            <h1 class="title">' . htmlspecialchars($title) . '</h1>
            <p class="description">' . htmlspecialchars($desc) . '</p>
            <a href="/" class="btn">Return Home</a>
        </div>
    </div>
</body>
</html>';
    }
}
