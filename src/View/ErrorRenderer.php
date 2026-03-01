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
     * Render a generic error for production mode
     *
     * @return string HTML
     */
    private static function renderProductionError(): string
    {
        return <<<HTML
        <div style="display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #0f172a; color: #f8fafc; font-family: 'Outfit', sans-serif; text-align: center; padding: 2rem;">
            <div style="background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(24px); padding: 3rem; border-radius: 32px; border: 1px solid rgba(255, 255, 255, 0.1); max-width: 500px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);">
                <h1 style="font-size: 4rem; margin-bottom: 1rem; color: #10b981; font-weight: 800;">500</h1>
                <h2 style="font-size: 1.75rem; margin-bottom: 1.5rem; font-weight: 700;">Something went wrong.</h2>
                <p style="color: #94a3b8; margin-bottom: 2.5rem; line-height: 1.6;">We encountered an unexpected error. Our team has been notified. Please try again later.</p>
                <a href="/" style="display: inline-block; background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 0.875rem 2rem; border-radius: 14px; text-decoration: none; font-weight: 600; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3); transition: transform 0.2s;">Return Home</a>
            </div>
        </div>
        HTML;
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
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plugs Error: {$message}</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --card: #1e293b;
            --primary: #10b981;
            --primary-glow: rgba(16, 185, 129, 0.4);
            --accent: #06b6d4;
            --danger: #ef4444;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.08);
            --glass: rgba(15, 23, 42, 0.8);
            --glass-light: rgba(255, 255, 255, 0.03);
        }

        * { box-sizing: border-box; }

        body {
            background: var(--bg);
            background-image: radial-gradient(circle at 0% 0%, rgba(16, 185, 129, 0.05) 0%, transparent 50%),
                              radial-gradient(circle at 100% 100%, rgba(6, 182, 212, 0.05) 0%, transparent 50%);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem;
            animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 28px;
            padding: 0;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        header {
            padding: 2.5rem;
            background: linear-gradient(to bottom, var(--glass-light), transparent);
            border-bottom: 1px solid var(--border);
        }

        .error-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 1rem;
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        h1 {
            margin: 0;
            font-size: clamp(1.5rem, 5vw, 2.75rem);
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.2;
            color: var(--text);
        }

        .location-path {
            margin-top: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-muted);
            font-family: 'Fira Code', monospace;
            font-size: 0.9rem;
            overflow-wrap: anywhere;
        }

        .location-path .line {
            color: var(--danger);
            font-weight: 700;
        }

        .main-pane {
            padding: 2rem;
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
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(to right, var(--border), transparent);
        }

        /* Code Box */
        .code-container {
            border-radius: 16px;
            background: #0b1120;
            border: 1px solid var(--border);
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.3);
            margin-bottom: 2.5rem;
            overflow: hidden;
        }

        .code-filename {
            padding: 0.75rem 1.25rem;
            background: rgba(255, 255, 255, 0.03);
            border-bottom: 1px solid var(--border);
            font-family: 'Fira Code', monospace;
            font-size: 0.8rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .code-viewport {
            padding: 1rem 0;
            overflow-x: auto;
        }

        .code-row {
            display: flex;
            position: relative;
        }

        .code-row:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .code-row.active {
            background: rgba(239, 68, 68, 0.08);
        }

        .code-row.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: var(--danger);
            box-shadow: 0 0 10px var(--danger);
        }

        .line-no {
            width: 60px;
            min-width: 60px;
            text-align: right;
            padding-right: 1.5rem;
            color: #4b5563;
            user-select: none;
            font-family: 'Fira Code', monospace;
            font-size: 0.85rem;
        }

        .active .line-no { color: var(--danger); font-weight: 700; }

        .line-content {
            font-family: 'Fira Code', monospace;
            font-size: 0.9rem;
            color: #e5e7eb;
            white-space: pre;
        }

        /* Stack Trace */
        .stack-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .stack-item {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1rem;
            transition: all 0.2s;
        }

        .stack-item:hover {
            background: rgba(255,255,255,0.04);
            border-color: rgba(255,255,255,0.15);
            transform: translateX(4px);
        }

        .stack-call {
            color: var(--accent);
            font-family: 'Fira Code', monospace;
            font-weight: 600;
            display: block;
            margin-bottom: 0.35rem;
        }

        .stack-file {
            color: var(--text-muted);
            font-size: 0.8rem;
            font-family: 'Fira Code', monospace;
            opacity: 0.8;
            word-break: break-all;
        }

        /* Tables & Lists */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .meta-table th {
            text-align: left;
            padding: 0.75rem 0;
            font-weight: 500;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
        }

        .meta-table td {
            text-align: right;
            padding: 0.75rem 0;
            color: var(--text);
            border-bottom: 1px solid var(--border);
            word-break: break-all;
        }

        .badge-pill {
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            background: var(--primary);
            color: white;
        }

        .data-dump {
            background: #000;
            padding: 1rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-family: 'Fira Code', monospace;
            overflow-x: auto;
            max-height: 400px;
            color: #6ee7b7;
            border: 1px solid var(--border);
        }

        footer {
            margin-top: 3rem;
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        /* Syntax colors */
        .token-keyword { color: #f472b6; font-weight: 600; }
        .token-string { color: #34d399; }
        .token-var { color: #93c5fd; }
        .token-comment { color: #6b7280; font-style: italic; }
    </style>
</head>
<body>
    <div class="container">
        <div class="glass-card">
            <header>
                <div class="error-badge">{$class}</div>
                <h1>{$message}</h1>
                <div class="location-path">
                    {$file} <span class="line">:{$line}</span>
                </div>
            </header>

            <div class="main-pane">
                <div class="section-header">Execution Snippet</div>
                <div class="code-container">
                    <div class="code-filename">{$file}</div>
                    <div class="code-viewport">
                        {$codeSnippet}
                    </div>
                </div>

                <div class="section-header">Trace Stack</div>
                <div class="stack-list">
                    {$traceHtml}
                </div>

                <div class="section-header" style="margin-top: 3rem;">Environment</div>
                <table class="meta-table">
                    <tr><th>PHP</th><td>{$phpVersion}</td></tr>
                    <tr><th>System</th><td>{$os}</td></tr>
                    <tr><th>Memory</th><td>{$memory}</td></tr>
                    <tr><th>Debug</th><td><span class="badge-pill" style="background: var(--accent);">Enabled</span></td></tr>
                </table>

                <div class="section-header" style="margin-top: 3rem;">View Data</div>
                <div class="data-dump">
                    <pre>{$dataHtml}</pre>
                </div>
            </div>
        </div>

        <footer>
            &copy; <?php echo date('Y'); ?> Plugs Framework &bull; Built for Plugs by Celio Natti
        </footer>
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
