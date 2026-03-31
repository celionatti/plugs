<?php

declare(strict_types=1);

namespace Plugs\Utils;

/**
 * FlashMessage Class
 *
 * Handles session-based flash messages with automatic cleanup.
 * Supports multiple message types and custom rendering.
 *
 * @example
 * // Set messages
 * FlashMessage::success('Category created successfully!');
 * FlashMessage::error('Failed to delete item.');
 *
 * // Display in view
 * echo FlashMessage::render();
 *
 * // Or check and display specific type
 * if (FlashMessage::has('success')) {
 *     echo FlashMessage::get('success');
 * }
 *
 * // Custom styling
 * FlashMessage::setRenderOptions([
 *     'include_styles' => false, // Use your own CSS
 *     'auto_dismiss' => true,
 *     'dismiss_delay' => 8000
 * ]);
 */
class FlashMessage
{
    protected const SESSION_KEY = '_flash';
    protected const OLD_INPUT_KEY = '_old_input';

    protected static array $types = [
        'success' => [
            'class' => 'plugs-alert plugs-alert-success',
            'title' => 'Success',
        ],
        'error' => [
            'class' => 'plugs-alert plugs-alert-error',
            'title' => 'Error',
        ],
        'warning' => [
            'class' => 'plugs-alert plugs-alert-warning',
            'title' => 'Warning',
        ],
        'info' => [
            'class' => 'plugs-alert plugs-alert-info',
            'title' => 'Info',
        ],
    ];

    protected static array $renderOptions = [
        'show_icon' => true,
        'show_title' => true,
        'show_close' => true,
        'auto_dismiss' => true,
        'dismiss_delay' => 8000,
        'container_class' => 'plugs-flash-container',
        'position' => 'fixed', // 'fixed' or 'static'
        'animation' => 'plugs-bounce',
        'include_styles' => true,
        'custom_css_path' => null,
    ];

    // Embedded CSS styles
    protected static string $defaultStyles = <<<'CSS'
<style>
/* ========== PLUGS FLASH MESSAGES ========== */
:root {
    /* OKLCH Colors - Framework Integrated */
    --plugs-success: oklch(0.723 0.219 149.579);
    --plugs-error: oklch(0.637 0.237 25.331);
    --plugs-warning: oklch(0.705 0.213 47.604);
    --plugs-info: oklch(0.623 0.214 259.815);

    --flash-bg-opacity: 0.08;
    --flash-border-opacity: 0.15;
    --flash-blur: 16px;
    --flash-radius: 20px;
    --flash-padding: 1.15rem;
    --flash-width: min(calc(100% - 2rem), 440px);
    --flash-top: 2rem;
    --flash-right: 2rem;
    --flash-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.07), 0 4px 6px -4px rgb(0 0 0 / 0.1);
}

@media (prefers-color-scheme: dark) {
    :root {
        --flash-bg-opacity: 0.15;
        --flash-border-opacity: 0.25;
        --flash-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.3), 0 8px 10px -6px rgb(0 0 0 / 0.4);
    }
}

.plugs-flash-container {
    position: fixed;
    top: var(--flash-top);
    right: var(--flash-right);
    z-index: 99999;
    width: var(--flash-width);
    display: flex;
    flex-direction: column;
    gap: 1rem;
    pointer-events: none;
}

.plugs-flash-container.static {
    position: relative;
    top: 0;
    right: 0;
    width: 100%;
    margin-bottom: 2rem;
}

.plugs-alert {
    pointer-events: auto;
    padding: var(--flash-padding);
    border-radius: var(--flash-radius);
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    backdrop-filter: blur(var(--flash-blur));
    -webkit-backdrop-filter: blur(var(--flash-blur));
    box-shadow: var(--flash-shadow);
    border: 1px solid transparent;
    overflow: hidden;
    animation: plugs-premium-in 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    font-family: 'Outfit', 'Inter', sans-serif;
}

.plugs-alert-success { 
    background: oklch(from var(--plugs-success) l c h / var(--flash-bg-opacity)); 
    border-color: oklch(from var(--plugs-success) l c h / var(--flash-border-opacity)); 
    color: var(--plugs-success); 
}

.plugs-alert-error { 
    background: oklch(from var(--plugs-error) l c h / var(--flash-bg-opacity)); 
    border-color: oklch(from var(--plugs-error) l c h / var(--flash-border-opacity)); 
    color: var(--plugs-error); 
}

.plugs-alert-warning { 
    background: oklch(from var(--plugs-warning) l c h / var(--flash-bg-opacity)); 
    border-color: oklch(from var(--plugs-warning) l c h / var(--flash-border-opacity)); 
    color: var(--plugs-warning); 
}

.plugs-alert-info { 
    background: oklch(from var(--plugs-info) l c h / var(--flash-bg-opacity)); 
    border-color: oklch(from var(--plugs-info) l c h / var(--flash-border-opacity)); 
    color: var(--plugs-info); 
}

.plugs-alert-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.plugs-alert-header {
    display: flex;
    align-items: center;
    font-weight: 700;
    font-size: 0.95rem;
    letter-spacing: -0.01em;
}

.plugs-alert-icon {
    flex-shrink: 0;
    width: 1.5rem;
    height: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.plugs-alert-icon svg {
    width: 100%;
    height: 100%;
}

.plugs-alert-message {
    font-size: 0.875rem;
    font-weight: 500;
    opacity: 0.9;
    line-height: 1.5;
}

.plugs-alert-close {
    flex-shrink: 0;
    background: none;
    border: none;
    color: currentColor;
    cursor: pointer;
    padding: 0.4rem;
    border-radius: 12px;
    transition: all 0.2s ease;
    opacity: 0.4;
    display: flex;
    align-items: center;
    justify-content: center;
}

.plugs-alert-close:hover {
    opacity: 1;
    background-color: oklch(from currentColor l c h / 0.1);
    transform: scale(1.1);
}

.plugs-alert-close svg {
    width: 1.2rem;
    height: 1.2rem;
}

@keyframes plugs-premium-in {
    0% {
        transform: translateY(20px) scale(0.95);
        opacity: 0;
    }
    100% {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
}

@keyframes plugs-premium-out {
    0% {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
    100% {
        transform: translateY(-20px) scale(0.95);
        opacity: 0;
    }
}

@media (max-width: 640px) {
    .plugs-flash-container {
        left: 1rem;
        right: 1rem;
        width: auto;
        top: 1rem;
    }
}
</style>
CSS;

    private static bool $stylesRendered = false;

    /**
     * Initialize session if not started
     */
    protected static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }

    /**
     * Set a flash message
     */
    public static function set(string $type, string $message, ?string $title = null): void
    {
        self::ensureSession();

        if (!isset(self::$types[$type])) {
            $type = 'info';
        }

        $newMessage = [
            'type' => $type,
            'message' => $message,
            'title' => $title,
            'timestamp' => time(),
        ];

        // Prevent exact duplicates in the same request/session
        foreach ($_SESSION[self::SESSION_KEY] as $existing) {
            if (is_array($existing) && 
                ($existing['type'] ?? '') === $type && 
                ($existing['message'] ?? '') === $message &&
                ($existing['title'] ?? null) === $title) {
                return;
            }
        }

        $_SESSION[self::SESSION_KEY][] = $newMessage;
    }

    /**
     * Set success message
     */
    public static function success(string $message, ?string $title = null): void
    {
        self::set('success', $message, $title);
    }

    /**
     * Set error message
     */
    public static function error(string $message, ?string $title = null): void
    {
        self::set('error', $message, $title);
    }

    /**
     * Set warning message
     */
    public static function warning(string $message, ?string $title = null): void
    {
        self::set('warning', $message, $title);
    }

    /**
     * Set info message
     */
    public static function info(string $message, ?string $title = null): void
    {
        self::set('info', $message, $title);
    }

    /**
     * Check if there are any flash messages
     */
    public static function has(?string $type = null): bool
    {
        self::ensureSession();

        if (empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        if ($type === null) {
            return true;
        }

        foreach ($_SESSION[self::SESSION_KEY] as $key => $flash) {
            // Check if key matches type (RedirectResponse format)
            if ($key === $type) {
                return true;
            }

            // Check if it's a structured array with matching type property
            if (is_array($flash) && isset($flash['type']) && $flash['type'] === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get flash messages
     */
    public static function get(?string $type = null, bool $clear = true): array
    {
        self::ensureSession();

        $allFlsh = $_SESSION[self::SESSION_KEY] ?? [];
        if (empty($allFlsh)) {
            return [];
        }

        $messages = [];

        if ($type === null) {
            foreach ($allFlsh as $key => $flash) {
                // Skip internal keys like _delete_next
                if (is_string($key) && str_starts_with($key, '_')) {
                    continue;
                }

                // Normalize simple string flash (from RedirectResponse)
                if (!is_array($flash)) {
                    $flash = [
                        'type' => is_string($key) ? $key : 'info',
                        'message' => (string)$flash,
                        'title' => null,
                        'timestamp' => time()
                    ];
                }

                $messages[] = $flash;

                if ($clear) {
                    unset($_SESSION[self::SESSION_KEY][$key]);
                }
            }

            // If we cleared individual keys, check if we should clear the whole bucket
            if ($clear && empty($_SESSION[self::SESSION_KEY])) {
                unset($_SESSION[self::SESSION_KEY]);
            }
        } else {
            // Get messages of a specific type
            foreach ($allFlsh as $key => $flash) {
                $isMatch = false;
                $normalized = $flash;

                // Check key match
                if ($key === $type) {
                    $isMatch = true;
                    if (!is_array($flash)) {
                        $normalized = [
                            'type' => $type,
                            'message' => (string)$flash,
                            'title' => null,
                            'timestamp' => time()
                        ];
                    }
                }
                // Check structured array match
                elseif (is_array($flash) && isset($flash['type']) && $flash['type'] === $type) {
                    $isMatch = true;
                }

                if ($isMatch) {
                    $messages[] = $normalized;
                    if ($clear) {
                        unset($_SESSION[self::SESSION_KEY][$key]);
                    }
                }
            }
        }

        return $messages;
    }

    /**
     * Get flash message without clearing (useful for view checks)
     */
    public static function peek(string $key, $default = null)
    {
        self::ensureSession();

        return $_SESSION[self::SESSION_KEY][$key] ?? $default;
    }

    /**
     * Get first message of a type
     */
    public static function first(?string $type = null, bool $clear = true): ?array
    {
        $messages = self::get($type, $clear);

        return !empty($messages) ? $messages[0] : null;
    }

    /**
     * Clear all flash messages or specific type
     */
    public static function clear(?string $type = null): void
    {
        self::ensureSession();

        if ($type === null) {
            $_SESSION[self::SESSION_KEY] = [];
        } else {
            $_SESSION[self::SESSION_KEY] = array_filter(
                $_SESSION[self::SESSION_KEY],
                fn ($flash) => $flash['type'] !== $type
            );
            $_SESSION[self::SESSION_KEY] = array_values($_SESSION[self::SESSION_KEY]);
        }
    }

    /**
     * Set render options
     */
    public static function setRenderOptions(array $options): void
    {
        self::$renderOptions = array_merge(self::$renderOptions, $options);
    }

    /**
     * Get render options
     */
    public static function getRenderOptions(): array
    {
        return self::$renderOptions;
    }

    /**
     * Set custom CSS styles
     */
    public static function setCustomStyles(string $css): void
    {
        self::$defaultStyles = "<style>\n{$css}\n</style>";
    }

    /**
     * Render styles once per page load
     */
    protected static function renderStyles(array $options, ?string $nonce = null): string
    {
        if (self::$stylesRendered || !$options['include_styles']) {
            return '';
        }

        self::$stylesRendered = true;

        // Use custom CSS file if provided
        if (!empty($options['custom_css_path'])) {
            return '<link rel="stylesheet" href="' . htmlspecialchars($options['custom_css_path'], ENT_QUOTES, 'UTF-8') . '"' . ($nonce ? ' nonce="' . $nonce . '"' : '') . '>';
        }

        $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';

        return str_replace('<style>', "<style{$nonceAttr}>", self::$defaultStyles);
    }

    /**
     * Render all flash messages as HTML
     */
    public static function render(array $options = [], ?string $nonce = null): string
    {
        $messages = self::get();

        if (empty($messages)) {
            return '';
        }

        $options = array_merge(self::$renderOptions, $options);

        $html = self::renderStyles($options, $nonce);

        $containerClass = $options['container_class'];
        if ($options['position'] === 'static') {
            $containerClass .= ' static';
        }

        $html .= '<div class="' . $containerClass . '">';

        foreach ($messages as $flash) {
            $html .= self::renderMessage($flash, $options);
        }

        $html .= '</div>';

        $nonce = $nonce ?? (function_exists('asset_manager') ? asset_manager()->getNonce() : null);

        // Add JavaScript for interactions (Dismiss & Auto-dismiss)
        if ($options['auto_dismiss'] || $options['show_close']) {
            $html .= self::renderScripts($options['auto_dismiss'], $options['dismiss_delay'], $nonce);
        }

        return $html;
    }

    /**
     * Render a single message
     */
    protected static function renderMessage(array $flash, array $options): string
    {
        $type = $flash['type'];
        $message = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
        $title = $flash['title'] ?? self::$types[$type]['title'];
        $typeConfig = self::$types[$type];

        $html = '<div class="' . $typeConfig['class'] . ' ' . $options['animation'] . '" role="alert">';

        // Icon
        if ($options['show_icon']) {
            $html .= '<div class="plugs-alert-icon">' . self::getIconSvg($type) . '</div>';
        }

        // Content (Header + Message)
        $html .= '<div class="plugs-alert-content">';

        if ($options['show_title'] && $title) {
            $html .= '<div class="plugs-alert-header"><span>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span></div>';
        }

        $html .= '<div class="plugs-alert-message">' . $message . '</div>';
        $html .= '</div>';

        // Close button
        if ($options['show_close']) {
            $html .= '<button type="button" class="plugs-alert-close" aria-label="Close">';
            $html .= self::getIconSvg('close');
            $html .= '</button>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render message scripts
     */
    protected static function renderScripts(bool $autoDismiss, int $delay, ?string $nonce = null): string
    {
        $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';
        $autoDismissStr = $autoDismiss ? 'true' : 'false';

        return <<<HTML
        <script{$nonceAttr}>
        (function() {
            // Event delegation for close buttons
            document.addEventListener('click', function(e) {
                const btn = e.target.closest('.plugs-alert-close');
                if (btn) {
                    const alert = btn.closest('.plugs-alert');
                    if (alert) {
                        alert.style.animation = 'plugs-premium-out 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards';
                        setTimeout(() => alert.remove(), 500);
                    }
                }
            });

            // Auto-dismiss functionality
            if ({$autoDismissStr}) {
                const containerSelector = '.plugs-flash-container .plugs-alert';
                const alerts = document.querySelectorAll(containerSelector);
                alerts.forEach((alert, index) => {
                    const dismissTime = {$delay} + (index * 600);
                    setTimeout(() => {
                        if (document.body.contains(alert)) {
                            alert.style.animation = 'plugs-premium-out 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards';
                            setTimeout(() => {
                                if (document.body.contains(alert)) alert.remove();
                            }, 600);
                        }
                    }, dismissTime);
                });
            }
        })();
        </script>
        HTML;
    }

    /**
     * Render all flash messages using the premium component
     * 
     * @param object $view The view engine instance (passed from @flashPremium directive)
     * @return string
     */
    public static function renderPremium($view): string
    {
        $messages = self::get();
        if (empty($messages)) {
            return '';
        }

        $html = '';
        foreach ($messages as $flash) {
            $html .= $view->renderComponent('notification', [
                'type' => $flash['type'],
                'message' => $flash['message'],
                'title' => $flash['title'] ?? ucfirst($flash['type']),
                'duration' => self::$renderOptions['dismiss_delay'] ?? 5000,
            ]);
        }

        return $html;
    }

    /**
     * Render as JSON
     */
    public static function toJson(): string
    {
        $messages = self::get();

        return json_encode([
            'flash_messages' => $messages,
            'has_messages' => !empty($messages),
        ]);
    }

    /**
     * Render for specific type only
     */
    public static function renderType(string $type, array $options = [], ?string $nonce = null): string
    {
        $messages = self::get($type);

        if (empty($messages)) {
            return '';
        }

        $options = array_merge(self::$renderOptions, $options);

        $html = self::renderStyles($options, $nonce);

        $containerClass = $options['container_class'];
        if ($options['position'] === 'static') {
            $containerClass .= ' static';
        }

        $html .= '<div class="' . $containerClass . '">';

        foreach ($messages as $flash) {
            $html .= self::renderMessage($flash, $options);
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Keep messages for next request (don't clear)
     */
    public static function keep(): void
    {
        // Messages are already in session, just don't clear them
        // This is useful if you want to show messages on next page too
    }

    /**
     * Reflash all current messages for next request
     */
    public static function reflash(): void
    {
        self::ensureSession();
        // Messages persist automatically until retrieved
    }

    /**
     * Flash old input for form repopulation
     */
    public static function flashInput(array $input): void
    {
        self::ensureSession();
        $_SESSION[self::OLD_INPUT_KEY] = $input;
    }

    /**
     * Get old input value
     */
    public static function old(string $key, $default = null)
    {
        self::ensureSession();

        if (!isset($_SESSION[self::OLD_INPUT_KEY])) {
            return $default;
        }

        $value = $_SESSION[self::OLD_INPUT_KEY][$key] ?? $default;

        // Clear after retrieval
        unset($_SESSION[self::OLD_INPUT_KEY][$key]);

        if (empty($_SESSION[self::OLD_INPUT_KEY])) {
            unset($_SESSION[self::OLD_INPUT_KEY]);
        }

        return $value;
    }

    /**
     * Check if old input exists
     */
    public static function hasOldInput(string $key): bool
    {
        self::ensureSession();

        return isset($_SESSION[self::OLD_INPUT_KEY][$key]);
    }

    /**
     * Get all old input
     */
    public static function oldInput(): array
    {
        self::ensureSession();
        $input = $_SESSION[self::OLD_INPUT_KEY] ?? [];
        unset($_SESSION[self::OLD_INPUT_KEY]);

        return $input;
    }

    /**
     * Register custom message type
     */
    public static function registerType(string $name, array $config): void
    {
        $defaults = [
            'class' => 'alert alert-' . $name,
            'icon' => 'bi-info-circle',
            'title' => ucfirst($name),
        ];

        self::$types[$name] = array_merge($defaults, $config);
    }

    /**
     * Get count of messages
     */
    public static function count(?string $type = null): int
    {
        self::ensureSession();

        if (empty($_SESSION[self::SESSION_KEY])) {
            return 0;
        }

        if ($type === null) {
            return count($_SESSION[self::SESSION_KEY]);
        }

        return count(array_filter(
            $_SESSION[self::SESSION_KEY],
            fn ($flash) => $flash['type'] === $type
        ));
    }

    /**
     * Get SVG icon markup for a message type
     */
    protected static function getIconSvg(string $type): string
    {
        switch ($type) {
            case 'success':
                return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>';
            case 'error':
                return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>';
            case 'warning':
                return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>';
            case 'info':
                return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>';
            case 'close':
                return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>';
            default:
                return '';
        }
    }

    /**
     * Check if empty
     */
    public static function isEmpty(): bool
    {
        return self::count() === 0;
    }

    /**
     * Reset styles rendered flag (useful for testing)
     */
    public static function resetStylesFlag(): void
    {
        self::$stylesRendered = false;
    }

    /**
     * Magic static call for custom types
     */
    public static function __callStatic(string $name, array $arguments): void
    {
        if (isset(self::$types[$name])) {
            $message = $arguments[0] ?? '';
            $title = $arguments[1] ?? null;
            self::set($name, $message, $title);
        }
    }
}
