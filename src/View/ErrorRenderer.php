<?php

declare(strict_types=1);

namespace Plugs\View;

use Throwable;

/**
 * ErrorRenderer Class
 *
 * Responsible for rendering high-quality, premium error pages for the Plugs framework.
 * Features glassmorphism UI, syntax highlighting, and detailed request/system context.
 * Refined for better grid layout and responsiveness.
 */
class ErrorRenderer
{
    /**
     * Max lines for stack trace display
     */
    private const MAX_TRACE_LINES = 15;

    /**
     * Render the exception to a premium HTML page
     *
     * @param Throwable $e The exception to render
     * @param string|null $view The view that failed (if any)
     * @param array $data The data passed to the view (if any)
     * @return string The rendered HTML
     */
    public static function render(Throwable $e, ?string $view = null, array $data = []): string
    {
        $isDebug = self::isDebugMode();

        if (!$isDebug) {
            return self::renderProductionError();
        }

        return self::renderDebugError($e, $view, $data);
    }

    /**
     * Cache for production error HTML
     */
    private static ?string $productionErrorCache = null;

    /**
     * Render a generic error for production mode.
     * Caches the result statically for maximum performance on subsequent errors.
     *
     * @return string HTML
     */
    private static function renderProductionError(): string
    {
        if (self::$productionErrorCache !== null) {
            return self::$productionErrorCache;
        }

        self::$productionErrorCache = <<<HTML
        <div style="display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #0f172a; color: #f8fafc; font-family: 'Outfit', sans-serif; text-align: center; padding: 2rem;">
            <div style="background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(24px); padding: 3rem; border-radius: 32px; border: 1px solid rgba(255, 255, 255, 0.1); max-width: 500px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);">
                <h1 style="font-size: 4rem; margin-bottom: 1rem; color: #10b981; font-weight: 800;">500</h1>
                <h2 style="font-size: 1.75rem; margin-bottom: 1.5rem; font-weight: 700;">Something went wrong.</h2>
                <p style="color: #94a3b8; margin-bottom: 2.5rem; line-height: 1.6;">We encountered an unexpected error. Our team has been notified. Please try again later.</p>
                <a href="/" style="display: inline-block; background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 0.875rem 2rem; border-radius: 14px; text-decoration: none; font-weight: 600; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3); transition: transform 0.2s;">Return Home</a>
            </div>
        </div>
        HTML;

        return self::$productionErrorCache;
    }

    /**
     * Render a detailed error for debug mode
     *
     * @param Throwable $e
     * @param string|null $view
     * @param array $data
     * @return string HTML
     */
    private static function renderDebugError(Throwable $e, ?string $view, array $data): string
    {
        $message = htmlspecialchars($e->getMessage());
        $file = htmlspecialchars($e->getFile());
        $line = $e->getLine();
        $class = get_class($e);
        $viewDisplay = $view ? htmlspecialchars($view) : 'N/A';

        // Get code snippet
        $codeSnippet = self::getCodeSnippet($e->getFile(), $e->getLine());

        // System context
        $phpVersion = PHP_VERSION;
        $os = PHP_OS;
        $memory = round(memory_get_usage() / 1024 / 1024, 2) . ' MB';

        // Request context
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        $traceHtml = self::renderTrace($e);
        $dataHtml = self::renderData($data);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plugs Error: {$message}</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        ::selection { background: rgba(139,92,246,0.3); color: #fff; }

        html, body {
            height: 100%;
            overflow: hidden;
        }

        body {
            background: #0c0f1a;
            color: #e2e8f0;
            font-family: "Outfit", sans-serif;
            line-height: 1.6;
        }

        /* ── Two-Column Shell ── */
        .shell {
            display: grid;
            grid-template-columns: 380px 1fr;
            height: 100vh;
            overflow: hidden;
        }

        /* ── LEFT PANEL ── */
        .left {
            background: #111827;
            border-right: 1px solid rgba(139,92,246,0.12);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .left-header {
            padding: 2rem 1.75rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .brand {
            font-family: "Outfit", sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #a78bfa, #60a5fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.02em;
        }

        .error-type {
            margin-top: 1.5rem;
            font-family: "Outfit", sans-serif;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #ef4444;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .error-msg {
            margin-top: 0.75rem;
            font-family: "Outfit", sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: #f8fafc;
            line-height: 1.4;
            word-break: break-word;
        }

        /* Info Sections */
        .info-section {
            padding: 1.25rem 1.75rem;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }

        .info-label {
            font-family: "Outfit", sans-serif;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            margin-bottom: 0.6rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.4rem 0;
            font-family: "Outfit", sans-serif;
            font-size: 0.8rem;
        }

        .info-row .key { color: #94a3b8; font-family: "Outfit", sans-serif; }
        .info-row .val { color: #e2e8f0; font-family: "Fira Code", monospace; font-size: 0.75rem; }

        .file-path {
            font-family: "Fira Code", monospace;
            font-size: 0.75rem;
            color: #94a3b8;
            word-break: break-all;
            line-height: 1.5;
        }

        .file-path .line-num { color: #ef4444; font-weight: 700; }

        .view-badge {
            display: inline-block;
            background: rgba(96,165,250,0.1);
            border: 1px solid rgba(96,165,250,0.25);
            color: #60a5fa;
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            font-family: "Fira Code", monospace;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .open-editor {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.75rem;
            padding: 0.5rem 1rem;
            background: rgba(139,92,246,0.15);
            border: 1px solid rgba(139,92,246,0.3);
            border-radius: 8px;
            color: #a78bfa;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .open-editor:hover {
            background: rgba(139,92,246,0.25);
            color: #c4b5fd;
        }

        .data-dump {
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 8px;
            padding: 1rem;
            font-family: "Fira Code", monospace;
            font-size: 0.7rem;
            color: #6ee7b7;
            max-height: 200px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }

        .left-footer {
            margin-top: auto;
            padding: 1rem 1.75rem;
            border-top: 1px solid rgba(255,255,255,0.04);
            font-size: 0.7rem;
            color: #475569;
            text-align: center;
        }

        /* ── RIGHT PANEL ── */
        .right {
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            background: #0c0f1a;
        }

        .right-header {
            padding: 1.25rem 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .right-header h2 {
            font-size: 0.85rem;
            font-weight: 700;
            color: #a78bfa;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .code-container {
            border-radius: 0;
            background: #080b14;
            overflow-x: auto;
            flex-shrink: 0;
        }

        .code-row {
            display: flex;
            font-family: "Fira Code", monospace;
            font-size: 13px;
            line-height: 1.7;
        }

        .code-row.active {
            background: rgba(239, 68, 68, 0.12);
            border-left: 3px solid #ef4444;
        }

        .line-no {
            width: 55px;
            min-width: 55px;
            text-align: right;
            padding: 0 1rem 0 0;
            color: #334155;
            user-select: none;
        }

        .active .line-no { color: #ef4444; font-weight: 700; }

        .line-content {
            padding-left: 1rem;
            white-space: pre;
            color: #cbd5e1;
            border-left: 1px solid rgba(255,255,255,0.04);
        }

        .token-keyword { color: #c084fc; font-weight: 600; }
        .token-string { color: #4ade80; }
        .token-var { color: #60a5fa; }
        .token-comment { color: #475569; font-style: italic; }

        /* Stack */
        .stack-section {
            padding: 1.5rem 2rem;
            flex: 1;
        }

        .stack-section h3 {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
            margin-bottom: 1rem;
        }

        .stack-item {
            padding: 0.7rem 0.9rem;
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.04);
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.15s;
            font-size: 0.8rem;
        }

        .stack-item:hover {
            background: rgba(139,92,246,0.06);
            border-color: rgba(139,92,246,0.15);
            transform: translateX(3px);
        }

        .stack-call {
            color: #60a5fa;
            font-family: "Fira Code", monospace;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .stack-file {
            color: #475569;
            font-family: "Fira Code", monospace;
            font-size: 0.7rem;
            margin-top: 0.15rem;
            word-break: break-all;
        }

        /* ── Responsive ── */
        @media (max-width: 900px) {
            html, body { height: auto; overflow: auto; }
            .shell {
                grid-template-columns: 1fr;
                grid-template-rows: auto 1fr;
                height: auto;
                overflow: visible;
            }
            .left {
                border-right: none;
                border-bottom: 1px solid rgba(139,92,246,0.12);
                overflow-y: visible;
            }
            .right {
                overflow-y: visible;
            }
            .left-footer { display: none; }
        }

        @media (max-width: 480px) {
            .left-header { padding: 1.5rem 1.25rem 1.25rem; }
            .info-section { padding: 1rem 1.25rem; }
            .stack-section { padding: 1.25rem; }
            .right-header { padding: 1rem 1.25rem; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <!-- LEFT PANEL -->
        <aside class="left">
            <div class="left-header">
                <div class="brand">⚡ Plugs</div>

                <div class="error-type">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4m0 4h.01"/></svg>
                    {$class}
                </div>
                <div class="error-msg">{$message}</div>
            </div>

            <div class="info-section">
                <div class="info-label">📄 Source</div>
                <div class="file-path">{$file} <span class="line-num">:{$line}</span></div>
                <a href="vscode://file/{$file}:{$line}" class="open-editor">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                    Open in Editor
                </a>
            </div>

            <div class="info-section">
                <div class="info-label">🖼️ Template</div>
                <span class="view-badge">{$viewDisplay}</span>
            </div>

            <div class="info-section">
                <div class="info-label">🌐 Request</div>
                <div class="info-row"><span class="key">Method</span><span class="val">{$method}</span></div>
                <div class="info-row"><span class="key">URL</span><span class="val">{$uri}</span></div>
            </div>

            <div class="info-section">
                <div class="info-label">⚙️ Environment</div>
                <div class="info-row"><span class="key">PHP</span><span class="val">{$phpVersion}</span></div>
                <div class="info-row"><span class="key">OS</span><span class="val">{$os}</span></div>
                <div class="info-row"><span class="key">Memory</span><span class="val">{$memory}</span></div>
            </div>

            <div class="info-section">
                <div class="info-label">📦 View Data</div>
                <div class="data-dump">{$dataHtml}</div>
            </div>

            <div class="left-footer">
                &copy; <?php echo date('Y'); ?> Plugs Framework
            </div>
        </aside>

        <!-- RIGHT PANEL -->
        <main class="right">
            <div class="right-header">
                <h2>Execution Snippet</h2>
                <span style="font-family:'Fira Code',monospace; font-size:0.7rem; color:#475569;">Line {$line}</span>
            </div>

            <div class="code-container">
                {$codeSnippet}
            </div>

            <div class="stack-section">
                <h3>Stack Trace</h3>
                {$traceHtml}
            </div>
        </main>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Get a code snippet around the error line
     *
     * @param string $file
     * @param int $line
     * @return string HTML
     */
    private static function getCodeSnippet(string $file, int $line): string
    {
        if (!is_file($file)) {
            return '<div class="code-row"><div class="line-no">?</div><div class="line-content">No access to file or file not found.</div></div>';
        }

        $lines = file($file);
        $start = max(0, $line - 6);
        $end = min(count($lines), $line + 5);
        $output = '';

        foreach ($start < count($lines) ? range($start, $end - 1) : [] as $i) {
            if (!isset($lines[$i]))
                continue;

            $currentLine = $i + 1;
            $content = $lines[$i];
            $isActive = ($currentLine === $line) ? ' active' : '';

            // 1. First encode to prevent breaking the HTML structure
            $highlighted = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

            // 2. Use unique placeholder tokens to avoid collisions
            $highlighted = preg_replace('/(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/', '##VAR##$1##/VAR##', $highlighted);
            $highlighted = preg_replace('/(\bpublic\b|\bprivate\b|\bprotected\b|\bclass\b|\bfunction\b|\breturn\b|\bif\b|\belse\b|\bforeach\b|\bas\b|\bstatic\b|\bthrow\b|\bnew\b|\btry\b|\bcatch\b)/', '##KEY##$1##/KEY##', $highlighted);
            $highlighted = preg_replace('/(&quot;|&#039;|\'|")(.*?)(&quot;|&#039;|\'|")/', '##STR##$1$2$3##/STR##', $highlighted);

            if (str_contains($content, '//')) {
                $highlighted = preg_replace('/(\/\/.*)/', '##COM##$1##/COM##', $highlighted);
            }

            // 3. Replace placeholders with actual HTML tags
            $finalContent = str_replace(
                ['##VAR##', '##/VAR##', '##KEY##', '##/KEY##', '##STR##', '##/STR##', '##COM##', '##/COM##'],
                ['<span class="token-var">', '</span>', '<span class="token-keyword">', '</span>', '<span class="token-string">', '</span>', '<span class="token-comment">', '</span>'],
                $highlighted
            );

            $output .= sprintf(
                '<div class="code-row%s"><div class="line-no">%d</div><div class="line-content">%s</div></div>',
                $isActive,
                $currentLine,
                rtrim($finalContent)
            );
        }

        return $output;
    }

    /**
     * Render the stack trace
     *
     * @param Throwable $e
     * @return string HTML
     */
    private static function renderTrace(Throwable $e): string
    {
        $trace = $e->getTrace();
        $output = '';
        $count = 0;

        foreach ($trace as $item) {
            if ($count >= self::MAX_TRACE_LINES) {
                $output .= '<div style="text-align: center; padding: 1rem; color: var(--text-muted); font-size: 0.8rem;">+ ' . (count($trace) - $count) . ' more stack frames</div>';
                break;
            }

            $func = ($item['class'] ?? '') . ($item['type'] ?? '') . $item['function'];
            $file = $item['file'] ?? 'Internal';
            $line = $item['line'] ?? '?';

            $output .= <<<HTML
            <div class="stack-item">
                <span class="stack-call">{$func}()</span>
                <span class="stack-file">{$file}:{$line}</span>
            </div>
            HTML;
            $count++;
        }

        return $output;
    }

    /**
     * Render view data
     *
     * @param array $data
     * @return string
     */
    private static function renderData(array $data): string
    {
        if (empty($data)) {
            return 'No data passed to view context.';
        }

        $displayData = [];
        foreach ($data as $key => $value) {
            if (is_object($value)) {
                $displayData[$key] = 'Object(' . get_class($value) . ')';
            } elseif (is_array($value)) {
                $displayData[$key] = 'Array(' . count($value) . ')';
            } else {
                $displayData[$key] = $value;
            }
        }

        return htmlspecialchars(var_export($displayData, true));
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    private static function isDebugMode(): bool
    {
        if (defined('APP_DEBUG')) {
            return (bool) constant('APP_DEBUG');
        }

        if (isset($_ENV['APP_DEBUG'])) {
            return filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) getenv('APP_DEBUG');
    }
}
