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
    protected const SESSION_KEY = '_flash_messages';
    protected const OLD_INPUT_KEY = '_old_input';

    protected static array $types = [
        'success' => [
            'class' => 'alert alert-success',
            'icon' => 'bi-check-circle-fill',
            'title' => 'Success',
        ],
        'error' => [
            'class' => 'alert alert-danger',
            'icon' => 'bi-x-circle-fill',
            'title' => 'Error',
        ],
        'warning' => [
            'class' => 'alert alert-warning',
            'icon' => 'bi-exclamation-triangle-fill',
            'title' => 'Warning',
        ],
        'info' => [
            'class' => 'alert alert-info',
            'icon' => 'bi-info-circle-fill',
            'title' => 'Info',
        ],
    ];

    protected static array $renderOptions = [
        'show_icon' => true,
        'show_title' => true,
        'show_close' => true,
        'auto_dismiss' => false, // Changed default to false
        'dismiss_delay' => 10000, // Increased to 10 seconds
        'container_class' => 'flash-messages-container',
        'position' => 'fixed', // 'fixed' or 'static'
        'animation' => 'fade',
        'include_styles' => true, // Set to false to use external CSS
        'custom_css_path' => null, // Path to custom CSS file
    ];

    // Embedded CSS styles
    protected static string $defaultStyles = <<<'CSS'
<style>
/* ========== FLASH MESSAGES ========== */
.flash-messages-container {
    position: fixed;
    top: 80px;
    right: 20px;
    z-index: 9999;
    max-width: 400px;
    width: 100%;
}
.flash-messages-container.static {
    position: relative;
    top: 0;
    right: 0;
    margin-bottom: 1.5rem;
}
.alert {
    padding: 1rem 1.25rem;
    margin-bottom: 1rem;
    border-radius: 8px;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    position: relative;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    animation: slideInRight 0.4s ease forwards;
    opacity: 1;
    transform: translateX(0);
}
.alert-success {
    background-color: rgba(40, 167, 69, 0.1);
    border-left: 4px solid #28a745;
}
.alert-success .alert-header {
    color: #28a745;
}
.alert-danger {
    background-color: rgba(220, 53, 69, 0.1);
    border-left: 4px solid #dc3545;
}
.alert-danger .alert-header {
    color: #dc3545;
}
.alert-warning {
    background-color: rgba(255, 193, 7, 0.1);
    border-left: 4px solid #ffc107;
}
.alert-warning .alert-header {
    color: #ffc107;
}
.alert-info {
    background-color: rgba(23, 162, 184, 0.1);
    border-left: 4px solid #17a2b8;
}
.alert-info .alert-header {
    color: #17a2b8;
}
.alert-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}
.alert-header i {
    font-size: 1.2rem;
}
.alert-message {
    flex: 1;
    line-height: 1.5;
    color: #333;
}
.alert-close {
    background: none;
    border: none;
    color: inherit;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: all 0.2s ease;
    opacity: 0.7;
    margin-left: auto;
}
.alert-close:hover {
    opacity: 1;
    background-color: rgba(0, 0, 0, 0.1);
}
@keyframes slideInRight {
    0% {
        transform: translateX(100%);
        opacity: 0;
    }
    100% {
        transform: translateX(0);
        opacity: 1;
    }
}
@keyframes fadeOut {
    0% {
        opacity: 1;
        transform: translateX(0);
    }
    100% {
        opacity: 0;
        transform: translateX(50px);
    }
}
@media (max-width: 768px) {
    .flash-messages-container {
        left: 10px;
        right: 10px;
        max-width: none;
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
            return !empty($_SESSION[self::SESSION_KEY]);
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

        if (empty($_SESSION[self::SESSION_KEY])) {
            return [];
        }

        $messages = [];

        if ($type === null) {
            $messages = $_SESSION[self::SESSION_KEY];
            if ($clear) {
                $_SESSION[self::SESSION_KEY] = [];
            }
        } else {
            foreach ($_SESSION[self::SESSION_KEY] as $key => $flash) {
                if ($flash['type'] === $type) {
                    $messages[] = $flash;
                    if ($clear) {
                        unset($_SESSION[self::SESSION_KEY][$key]);
                    }
                }
            }

            if ($clear) {
                $_SESSION[self::SESSION_KEY] = array_values($_SESSION[self::SESSION_KEY]);
            }
        }

        return $messages;
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
                fn($flash) => $flash['type'] !== $type
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
            $html .= '<div class="alert-header">';

            if ($options['show_icon']) {
                $html .= '<i class="bi ' . $typeConfig['icon'] . '"></i>';
            }

            if ($options['show_title'] && $title) {
                $html .= '<strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong>';
            }

            $html .= '</div>';
        }

        // Message
        $html .= '<div class="alert-message">' . $message . '</div>';

        // Close button
        if ($options['show_close']) {
            $html .= '<button type="button" class="alert-close" onclick="const alert=this.parentElement;alert.style.animation=\'fadeOut 0.5s ease forwards\';setTimeout(()=>alert.remove(),500)" aria-label="Close">';
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
            const alerts = document.querySelectorAll('.flash-messages-container .alert');
            alerts.forEach((alert, index) => {
                // Stagger the dismissal if there are multiple alerts
                const dismissTime = {$delay} + (index * 500);
                setTimeout(() => {
                    alert.style.animation = 'fadeOut 0.5s ease forwards';
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
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
            fn($flash) => $flash['type'] === $type
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
