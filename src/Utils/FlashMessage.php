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
            'icon' => 'bi-check2-circle',
            'title' => 'Success',
        ],
        'error' => [
            'class' => 'plugs-alert plugs-alert-error',
            'icon' => 'bi-exclamation-octagon-fill',
            'title' => 'Error',
        ],
        'warning' => [
            'class' => 'plugs-alert plugs-alert-warning',
            'icon' => 'bi-exclamation-triangle-fill',
            'title' => 'Warning',
        ],
        'info' => [
            'class' => 'plugs-alert plugs-alert-info',
            'icon' => 'bi-info-circle-fill',
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
    /* OKLCH Colors - Modern, perceptually uniform */
    --plugs-success-l: 65%;
    --plugs-error-l: 55%;
    --plugs-warning-l: 75%;
    --plugs-info-l: 65%;

    --plugs-success: oklch(var(--plugs-success-l) 0.18 145);
    --plugs-error: oklch(var(--plugs-error-l) 0.22 25);
    --plugs-warning: oklch(var(--plugs-warning-l) 0.15 85);
    --plugs-info: oklch(var(--plugs-info-l) 0.15 245);

    --flash-bg-opacity: 0.1;
    --flash-blur: 15px;
    --flash-radius: 16px;
    --flash-padding: clamp(0.75rem, 2vw, 1.25rem);
    --flash-width: min(calc(100% - 2rem), 420px);
    --flash-top: clamp(1rem, 5vh, 5rem);
    --flash-right: clamp(1rem, 5vw, 2rem);
}

@media (prefers-color-scheme: dark) {
    :root {
        --plugs-success-l: 75%;
        --plugs-error-l: 65%;
        --plugs-warning-l: 85%;
        --plugs-info-l: 75%;
        --flash-bg-opacity: 0.2;
    }
}

.plugs-flash-container {
    position: fixed;
    top: var(--flash-top);
    right: var(--flash-right);
    z-index: 9999;
    width: var(--flash-width);
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
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
    align-items: flex-start;
    gap: 1.15rem;
    position: relative;
    backdrop-filter: blur(var(--flash-blur));
    -webkit-backdrop-filter: blur(var(--flash-blur));
    box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
    border: 1px solid transparent;
    overflow: hidden;
    animation: plugs-bounce-in 0.7s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

.plugs-alert::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 6px;
    height: 100%;
    transition: width 0.3s ease;
}

.plugs-alert:hover::before {
    width: 10px;
}

.plugs-alert-success { background: oklch(from var(--plugs-success) l c h / var(--flash-bg-opacity)); border-color: oklch(from var(--plugs-success) l c h / 0.2); color: var(--plugs-success); }
.plugs-alert-success::before { background: var(--plugs-success); }

.plugs-alert-error { background: oklch(from var(--plugs-error) l c h / var(--flash-bg-opacity)); border-color: oklch(from var(--plugs-error) l c h / 0.2); color: var(--plugs-error); }
.plugs-alert-error::before { background: var(--plugs-error); }

.plugs-alert-warning { background: oklch(from var(--plugs-warning) l c h / var(--flash-bg-opacity)); border-color: oklch(from var(--plugs-warning) l c h / 0.2); color: var(--plugs-warning); }
.plugs-alert-warning::before { background: var(--plugs-warning); }

.plugs-alert-info { background: oklch(from var(--plugs-info) l c h / var(--flash-bg-opacity)); border-color: oklch(from var(--plugs-info) l c h / 0.2); color: var(--plugs-info); }
.plugs-alert-info::before { background: var(--plugs-info); }

.plugs-alert-header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-weight: 800;
    margin-bottom: 0.35rem;
    font-size: 1rem;
    letter-spacing: -0.02em;
}

.plugs-alert-header i {
    font-size: 1.35rem;
}

.plugs-alert-message {
    flex: 1;
    line-height: 1.6;
    font-size: 0.925rem;
    font-weight: 500;
}

.plugs-alert-close {
    background: none;
    border: none;
    color: inherit;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 10px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    opacity: 0.55;
    margin-left: auto;
    display: flex;
    align-items: center;
    justify-content: center;
}

.plugs-alert-close:hover {
    opacity: 1;
    background-color: oklch(from currentColor l c h / 0.12);
    transform: rotate(90deg) scale(1.1);
}

@keyframes plugs-bounce-in {
    0% {
        transform: translateX(110%) scale(0.9);
        opacity: 0;
    }
    60% {
        transform: translateX(-15px) scale(1.02);
    }
    100% {
        transform: translateX(0) scale(1);
        opacity: 1;
    }
}

@keyframes plugs-bounce-out {
    0% {
        transform: translateX(0) scale(1);
        opacity: 1;
    }
    100% {
        transform: translateX(110%) scale(0.9);
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

        $_SESSION[self::SESSION_KEY][] = [
            'type' => $type,
            'message' => $message,
            'title' => $title,
            'timestamp' => time(),
        ];
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

        foreach ($_SESSION[self::SESSION_KEY] as $flash) {
            if ($flash['type'] === $type) {
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
                // Filter out internal keys like _delete_next if present
                if (is_string($key) && str_starts_with($key, '_')) {
                    continue;
                }

                $messages[$key] = $flash;
                if ($clear) {
                    unset($_SESSION[self::SESSION_KEY][$key]);
                }
            }
        } else {
            if (isset($allFlsh[$type])) {
                $content = $allFlsh[$type];
                $messages = is_array($content) && !isset($content['message']) ? $content : [$content];
                if ($clear) {
                    unset($_SESSION[self::SESSION_KEY][$type]);
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
    protected static function renderStyles(array $options): string
    {
        if (self::$stylesRendered || !$options['include_styles']) {
            return '';
        }

        self::$stylesRendered = true;

        // Use custom CSS file if provided
        if (!empty($options['custom_css_path'])) {
            return '<link rel="stylesheet" href="' . htmlspecialchars($options['custom_css_path'], ENT_QUOTES, 'UTF-8') . '">';
        }

        return self::$defaultStyles;
    }

    /**
     * Render all flash messages as HTML
     */
    public static function render(array $options = []): string
    {
        $messages = self::get();

        if (empty($messages)) {
            return '';
        }

        $options = array_merge(self::$renderOptions, $options);

        $html = self::renderStyles($options);

        $containerClass = $options['container_class'];
        if ($options['position'] === 'static') {
            $containerClass .= ' static';
        }

        $html .= '<div class="' . $containerClass . '">';

        foreach ($messages as $flash) {
            $html .= self::renderMessage($flash, $options);
        }

        $html .= '</div>';

        // Add auto-dismiss JavaScript if enabled
        if ($options['auto_dismiss']) {
            $html .= self::renderAutoDismissScript($options['dismiss_delay']);
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

        // Icon and title
        if ($options['show_icon'] || $options['show_title']) {
            $html .= '<div class="plugs-alert-header">';

            if ($options['show_icon']) {
                $html .= '<i class="bi ' . $typeConfig['icon'] . '"></i>';
            }

            if ($options['show_title'] && $title) {
                $html .= '<span>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span>';
            }

            $html .= '</div>';
        }

        // Message
        $html .= '<div class="plugs-alert-message">' . $message . '</div>';

        // Close button
        if ($options['show_close']) {
            $html .= '<button type="button" class="plugs-alert-close" onclick="const alert=this.parentElement;alert.style.animation=\'plugs-bounce-out 0.5s ease forwards\';setTimeout(()=>alert.remove(),500)" aria-label="Close">';
            $html .= '<i class="bi bi-x-lg"></i>';
            $html .= '</button>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render auto-dismiss JavaScript
     */
    protected static function renderAutoDismissScript(int $delay): string
    {
        return <<<HTML
        <script>
        (function() {
            const containerSelector = '.plugs-flash-container .plugs-alert';
            const alerts = document.querySelectorAll(containerSelector);
            alerts.forEach((alert, index) => {
                // Stagger the dismissal if there are multiple alerts
                const dismissTime = {$delay} + (index * 600);
                setTimeout(() => {
                    alert.style.animation = 'plugs-bounce-out 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards';
                    setTimeout(() => {
                        alert.remove();
                    }, 600);
                }, dismissTime);
            });
        })();
        </script>
        HTML;
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
    public static function renderType(string $type, array $options = []): string
    {
        $messages = self::get($type);

        if (empty($messages)) {
            return '';
        }

        $options = array_merge(self::$renderOptions, $options);

        $html = self::renderStyles($options);

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
