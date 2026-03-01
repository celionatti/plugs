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
            header_remove('Content-Security-Policy'); // Ensure error page styles are not blocked
        }

        $trace = $e->getTrace();
        $file = $e->getFile();
        $line = $e->getLine();

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

        $analyzer = new ErrorAnalyzer();
        $suggestions = $analyzer->analyze($e);

        $html = $this->getDebugHeader($shortClass, $nonce);
        $html .= $this->getDebugBody($e, $className, $file, $line, $frames, $suggestions, $nonce);
        $html .= $this->getDebugFooter($nonce);

        echo $html;
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
            --bg: #0f172a;
            --card: #1e293b;
            --primary: #10b981;
            --accent: #06b6d4;
            --danger: #ef4444;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.08);
            --glass: rgba(15, 23, 42, 0.8);
            --glass-light: rgba(255, 255, 255, 0.03);
        }

        body.plugs-error-page {
            background-color: var(--bg);
            background-image: radial-gradient(circle at 15% 15%, rgba(16, 185, 129, 0.05) 0%, transparent 40%), 
                              radial-gradient(circle at 85% 85%, rgba(6, 182, 212, 0.05) 0%, transparent 40%);
            color: var(--text);
            font-family: "Outfit", sans-serif;
            margin: 0; padding: 0; min-height: 100vh; overflow-x: hidden;
        }

        .plugs-error-page * { box-sizing: border-box; }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--border);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        header.header {
            padding: 2.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(to bottom, var(--glass-light), transparent);
        }

        .brand {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .error-banner {
            padding: 2.5rem;
            border-bottom: 1px solid var(--border);
        }

        .exception-message {
            font-size: clamp(1.5rem, 4vw, 2.5rem);
            font-weight: 700;
            line-height: 1.2;
            margin: 1rem 0;
            color: var(--text);
        }

        .main-content {
            padding: 2.5rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
            font-weight: 700;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
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
        }

        .stack-item {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .stack-item:hover {
            background: rgba(255,255,255,0.04);
            transform: translateX(4px);
        }

        .stack-item.active {
            background: rgba(16, 185, 129, 0.05);
            border-color: var(--primary);
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.1);
        }

        .code-viewer-container {
            border-radius: 16px;
            background: #0b1120;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 3rem;
        }

        .code-table {
            width: 100%;
            border-collapse: collapse;
            font-family: "Fira Code", monospace;
            font-size: 14px;
        }

        .code-row.error-line {
            background: rgba(239, 68, 68, 0.12);
            position: relative;
        }

        .code-row.error-line::before {
            content: "";
            position: absolute;
            left: 0; top: 0; bottom: 0; width: 3px;
            background: var(--danger);
            box-shadow: 0 0 10px var(--danger);
        }

        .code-line-num {
            width: 65px;
            text-align: right;
            padding: 0.4rem 1.25rem;
            color: #4b5563;
            border-right: 1px solid rgba(255,255,255,0.05);
            user-select: none;
        }

        .error-line .code-line-num {
            color: var(--danger);
            font-weight: 700;
        }

        .code-content {
            padding: 0.4rem 1.5rem;
            white-space: pre;
            color: #e2e8f0;
        }

        .token-keyword { color: #f472b6; font-weight: 600; }
        .token-string { color: #34d399; }
        .token-var { color: #93c5fd; }
        .token-comment { color: #6b7280; font-style: italic; }

        .suggestions-box {
            background: rgba(16, 185, 129, 0.05);
            border: 1px solid rgba(16, 185, 129, 0.2);
            padding: 1.5rem;
            margin-top: 2rem;
            border-radius: 16px;
        }

        .file-location-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(0,0,0,0.2);
            padding: 1rem 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            font-family: "Fira Code", monospace;
            font-size: 0.9rem;
        }

        .vscode-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            font-size: 0.85rem;
            text-decoration: none;
            font-weight: 600;
        }

    </style>
</head>
<body class="plugs-error-page">
    <header class="header"><a href="/" class="brand">Plugs</a></header>';
    }

    /**
     * Get debug body content.
     */
    protected function getDebugBody(Throwable $e, string $className, string $file, int $line, array $frames, array $suggestions = [], ?string $nonce = null): string
    {
        $html = '<div class="container">
        <div class="glass-card">
            <header class="header">
                <a href="/" class="brand">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                    Plugs
                </a>
            </header>

            <div class="error-banner">
                <div style="color: var(--danger); font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">' . htmlspecialchars($className) . '</div>
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
            <div class="main-content">
                <div class="section-header">Execution Snippet</div>
                <div class="code-viewer-container">';
        foreach ($frames as $index => $frame) {
            $active = $index === 0 ? 'active' : '';
            $f = $frame['file'] ?? '';
            $l = $frame['line'] ?? 0;
            $snippet = ($f && file_exists($f)) ? $this->getCodeSnippet($f, $l) : '<div style="padding: 2.5rem; color: var(--text-muted); text-align: center;">No preview available for internal frames.</div>';
            $html .= '<div id="code-' . $index . '" class="code-viewer ' . $active . '">' . $snippet . '</div>';
        }
        $html .= '</div>

                <div class="section-header" style="margin-top: 3rem;">Stack Trace</div>
                <ul class="stack-list">';
        foreach ($frames as $index => $frame) {
            $active = $index === 0 ? 'active' : '';
            $f = $frame['file'] ?? '{internal}';
            $l = $frame['line'] ?? '-';
            $method = ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'];
            $html .= '<li class="stack-item ' . $active . '" data-index="' . $index . '" data-file="' . htmlspecialchars(basename($f)) . '" data-full-file="' . htmlspecialchars($f) . '" data-line="' . $l . '">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                    <span style="font-family: Fira Code; font-size: 0.85rem; color: var(--accent); font-weight: 600;">' . htmlspecialchars($method) . '()</span>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">' . htmlspecialchars(basename($f)) . ':' . $l . '</span>
                </div>
            </li>';
        }
        $html .= '</ul>
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
        $titles = [404 => 'Not Found', 403 => 'Forbidden', 401 => 'Unauthorized', 500 => 'Server Error', 503 => 'Service Unavailable'];
        $title = $titles[$statusCode] ?? 'Error';
        $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $statusCode . ' - ' . htmlspecialchars($title) . '</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style' . $nonceAttr . '>
        @import url("https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap");
        body {
            background-color: #080b12;
            background-image: radial-gradient(circle at 15% 15%, rgba(139, 92, 246, 0.08) 0%, transparent 40%), radial-gradient(circle at 85% 85%, rgba(59, 130, 246, 0.08) 0%, transparent 40%);
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            font-family: "Outfit", sans-serif;
            text-align: center;
        }
        .error-card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 3rem 4rem;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            transform: translateY(-20px);
        }
        h1 {
            font-size: 5rem;
            margin: 0;
            line-height: 1;
            background: linear-gradient(135deg, #8b5cf6, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        p {
            font-size: 1.5rem;
            color: #94a3b8;
            margin: 1rem 0 0 0;
            font-weight: 400;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <h1>' . $statusCode . '</h1>
        <p>' . htmlspecialchars($title) . '</p>
    </div>
</body>
</html>';
    }
}
