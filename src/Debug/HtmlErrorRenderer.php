<?php

declare(strict_types=1);

namespace Plugs\Debug;

use Throwable;

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

        $html = $this->getDebugHeader($shortClass, $nonce);
        $html .= $this->getDebugBody($e, $className, $file, $line, $frames, $nonce);
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
    <style' . $nonceAttr . '>
        .plugs-error-page {
            --bg-body: #080b12; --bg-sidebar: rgba(15, 23, 42, 0.95); --bg-card: rgba(30, 41, 59, 0.3);
            --bg-header: rgba(8, 11, 18, 0.9); --border-color: rgba(255, 255, 255, 0.08);
            --text-primary: #f8fafc; --text-secondary: #94a3b8; --text-muted: #64748b;
            --accent-primary: #8b5cf6; --accent-secondary: #3b82f6; --danger: #ef4444;
            --code-bg: rgba(13, 17, 23, 0.5); --highlight-bg: rgba(239, 68, 68, 0.1);
            font-family: sans-serif; background-color: var(--bg-body); color: var(--text-primary); height: 100vh; display: flex; flex-direction: column; overflow: hidden; font-size: 15px; margin: 0; padding: 0; box-sizing: border-box;
        }
        .plugs-error-page * { box-sizing: border-box; }
        .plugs-error-page .header { height: 64px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; padding: 0 2rem; background-color: var(--bg-header); backdrop-filter: blur(10px); z-index: 50; justify-content: space-between; }
        .plugs-error-page .brand { font-size: 1.75rem; font-weight: 700; color: var(--accent-primary); text-decoration: none; }
        .plugs-error-page .container { display: flex; flex: 1; overflow: hidden; }
        .plugs-error-page .sidebar { width: 420px; background-color: var(--bg-sidebar); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; flex-shrink: 0; }
        .plugs-error-page .stack-list { overflow-y: auto; flex: 1; list-style: none; }
        .plugs-error-page .stack-item { border-bottom: 1px solid var(--border-color); padding: 1rem 1.5rem; cursor: pointer; }
        .plugs-error-page .stack-item.active { background: rgba(139, 92, 246, 0.1); border-left: 3px solid var(--accent-primary); }
        .plugs-error-page .content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .plugs-error-page .error-banner { padding: 3rem; border-bottom: 1px solid var(--border-color); }
        .plugs-error-page .exception-message { font-size: 1.75rem; font-weight: 600; margin-bottom: 1rem; }
        .plugs-error-page .code-viewer-container { background: var(--code-bg); flex-shrink: 0; }
        .plugs-error-page .code-viewer { display: none; padding: 0; }
        .plugs-error-page .code-viewer.active { display: block; }
        .plugs-error-page .code-table { width: 100%; border-collapse: collapse; font-family: monospace; }
        .plugs-error-page .code-row.error-line { background: var(--highlight-bg); }
        .plugs-error-page .code-line-num { width: 60px; text-align: right; padding: 0 1rem; color: var(--text-muted); border-right: 1px solid var(--border-color); }
        .plugs-error-page .code-content { padding: 0 1.5rem; white-space: pre; }
    </style>
</head>
<body class="plugs-error-page">
    <header class="header"><a href="/" class="brand">Plugs</a></header>';
    }

    /**
     * Get debug body content.
     */
    protected function getDebugBody(Throwable $e, string $className, string $file, int $line, array $frames, ?string $nonce = null): string
    {
        $html = '<div class="container">
        <aside class="sidebar">
            <ul class="stack-list">';
        foreach ($frames as $index => $frame) {
            $active = $index === 0 ? 'active' : '';
            $f = $frame['file'] ?? '{internal}';
            $l = $frame['line'] ?? '-';
            $method = ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'];
            $html .= '<li class="stack-item ' . $active . '" data-index="' . $index . '" data-file="' . htmlspecialchars(basename($f)) . '" data-line="' . $l . '">
                <div style="font-size: 0.8rem; color: var(--text-secondary);">' . htmlspecialchars(basename($f)) . ':' . $l . '</div>
                <div style="color: var(--accent-primary); font-weight: 500;">' . htmlspecialchars($method) . '</div>
            </li>';
        }
        $html .= '</ul>
        </aside>
        <main class="content">
            <div class="error-banner">
                <div style="color: var(--danger); font-size: 0.8rem; font-weight: 700;">' . htmlspecialchars($className) . '</div>
                <h1 class="exception-message">' . htmlspecialchars($e->getMessage()) . '</h1>
                <div style="color: var(--text-secondary); font-family: monospace;" id="active-location">' . htmlspecialchars(basename($file)) . ' | Line ' . $line . '</div>
            </div>
            <div class="code-viewer-container">';
        foreach ($frames as $index => $frame) {
            $active = $index === 0 ? 'active' : '';
            $f = $frame['file'] ?? '';
            $l = $frame['line'] ?? 0;
            $snippet = ($f && file_exists($f)) ? $this->getCodeSnippet($f, $l) : '<div style="padding: 2rem; color: var(--text-muted)">No preview</div>';
            $html .= '<div id="code-' . $index . '" class="code-viewer ' . $active . '">' . $snippet . '</div>';
        }
        $html .= '</div>
        </main>
    </div>';
        return $html;
    }

    /**
     * Get debug footer/scripts.
     */
    protected function getDebugFooter(?string $nonce = null): string
    {
        $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';
        return '<script' . $nonceAttr . '>
        document.querySelectorAll(".stack-item").forEach(el => {
            el.addEventListener("click", function() {
                const index = this.getAttribute("data-index");
                document.querySelectorAll(".stack-item").forEach(i => i.classList.remove("active"));
                this.classList.add("active");
                document.querySelectorAll(".code-viewer").forEach(v => v.classList.remove("active"));
                const viewer = document.getElementById("code-" + index);
                if (viewer) viewer.classList.add("active");
                document.getElementById("active-location").textContent = this.getAttribute("data-file") + " | Line " + this.getAttribute("data-line");
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
        $start = max(0, $line - 11);
        $end = min(count($lines), $line + 10);
        $output = '<table class="code-table">';
        for ($i = $start; $i < $end; $i++) {
            $currentLine = $i + 1;
            $class = $currentLine === $line ? 'error-line' : '';
            $output .= '<tr class="code-row ' . $class . '">
                <td class="code-line-num">' . $currentLine . '</td>
                <td class="code-content">' . htmlspecialchars($lines[$i]) . '</td>
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
        $titles = [404 => 'Not Found', 500 => 'Server Error'];
        $title = $titles[$statusCode] ?? 'Error';
        $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';
        return '<!DOCTYPE html><html><head><title>' . $statusCode . '</title><style' . $nonceAttr . '>body{background:#080b12;color:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;}h1{font-size:4rem;margin-bottom:1rem;}</style></head><body><div><h1>' . $statusCode . '</h1><p>' . $title . '</p></div></body></html>';
    }
}
