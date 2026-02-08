<?php

declare(strict_types=1);

namespace Plugs\View;

/*
|--------------------------------------------------------------------------
| FragmentRenderer Class
|--------------------------------------------------------------------------
|
| Handles HTMX/Turbo fragment extraction and rendering.
| Enables partial page updates without full page reloads.
|
| @package Plugs\View
*/

class FragmentRenderer
{
    /**
     * Extracted fragments from compiled content
     */
    private array $fragments = [];

    /**
     * Teleport targets for content relocation
     */
    private array $teleports = [];

    /**
     * Current fragment being captured
     */
    private ?string $currentFragment = null;

    /**
     * CSP Nonce for script tags
     */
    private ?string $nonce = null;

    /**
     * Set the CSP nonce
     */
    public function setNonce(?string $nonce): void
    {
        $this->nonce = $nonce;
    }

    /**
     * Fragment output buffer level
     */
    private ?int $fragmentBufferLevel = null;

    /**
     * Start capturing a fragment
     *
     * @param string $name Fragment name
     * @return void
     */
    public function startFragment(string $name): void
    {
        if ($this->currentFragment !== null) {
            throw new \RuntimeException(
                sprintf('Cannot start fragment [%s] while fragment [%s] is still open', $name, $this->currentFragment)
            );
        }

        $this->currentFragment = $name;
        $this->fragmentBufferLevel = ob_get_level();
        ob_start();
    }

    /**
     * End the current fragment capture
     *
     * @return string Fragment content
     */
    public function endFragment(): string
    {
        if ($this->currentFragment === null) {
            throw new \RuntimeException('No fragment is currently being captured');
        }

        $content = '';

        while (ob_get_level() > $this->fragmentBufferLevel) {
            $content = ob_get_clean() . $content;
        }

        $name = $this->currentFragment;
        $this->fragments[$name] = $content;
        $this->currentFragment = null;
        $this->fragmentBufferLevel = null;

        return $content;
    }

    /**
     * Check if a fragment exists
     *
     * @param string $name
     * @return bool
     */
    public function hasFragment(string $name): bool
    {
        return isset($this->fragments[$name]);
    }

    /**
     * Get a fragment's content
     *
     * @param string $name
     * @return string|null
     */
    public function getFragment(string $name): ?string
    {
        return $this->fragments[$name] ?? null;
    }

    /**
     * Get all fragments
     *
     * @return array
     */
    public function getFragments(): array
    {
        return $this->fragments;
    }

    /**
     * Clear all fragments
     *
     * @return void
     */
    public function clearFragments(): void
    {
        $this->fragments = [];
        $this->teleports = [];
    }

    /**
     * Teleport targets stack for nested teleports
     */
    private array $teleportStack = [];

    /**
     * Start capturing teleport content
     *
     * @param string $target Target selector (e.g., '#modal', '.sidebar')
     * @return void
     */
    public function startTeleport(string $target): void
    {
        ob_start();
        $this->teleportStack[] = $target;
        $this->teleports[$target] = ['level' => ob_get_level()];
    }

    /**
     * End teleport capture
     *
     * @param string|null $target Target selector (optional, uses stack if null)
     * @return string Captured content
     */
    public function endTeleport(?string $target = null): string
    {
        $target = $target ?? array_pop($this->teleportStack);

        if (!$target || !isset($this->teleports[$target])) {
            throw new \RuntimeException(sprintf('No teleport started for target [%s]', $target ?? 'unknown'));
        }

        $content = ob_get_clean();
        $this->teleports[$target]['content'] = $content;

        return $content;
    }

    /**
     * Get teleported content for a target
     *
     * @param string $target
     * @return string|null
     */
    public function getTeleport(string $target): ?string
    {
        return $this->teleports[$target]['content'] ?? null;
    }

    /**
     * Get all teleport targets and their content
     *
     * @return array
     */
    public function getTeleports(): array
    {
        return array_map(fn($t) => $t['content'] ?? '', $this->teleports);
    }

    /**
     * Render teleport script tags for moving content
     *
     * @return string JavaScript to relocate content
     */
    public function renderTeleportScripts(): string
    {
        if (empty($this->teleports)) {
            return '';
        }

        $nonceAttr = $this->nonce ? sprintf(' nonce="%s"', htmlspecialchars($this->nonce)) : '';
        $scripts = [sprintf('<script%s>', $nonceAttr)];
        $scripts[] = '(function() {';

        foreach ($this->teleports as $target => $data) {
            if (!isset($data['content'])) {
                continue;
            }

            $escapedContent = json_encode($data['content']);
            $escapedTarget = json_encode($target);
            $escapedNonce = json_encode($this->nonce);

            // Robust script that moves content and ensures script execution with CSP nonce
            $scripts[] = sprintf(
                'var target = document.querySelector(%s); 
                if (target) { 
                    var temp = document.createElement("div"); 
                    temp.innerHTML = %s; 
                    var nonce = %s;
                    Array.from(temp.childNodes).forEach(function(node) {
                        if (node.nodeType === 1 && node.tagName === "SCRIPT") { 
                            var script = document.createElement("script"); 
                            Array.from(node.attributes).forEach(function(attr) { script.setAttribute(attr.name, attr.value); });
                            if (nonce) script.setAttribute("nonce", nonce);
                            script.text = node.innerHTML;
                            document.head.appendChild(script).parentNode.removeChild(script);
                        } else { 
                            target.appendChild(node); 
                        } 
                    });
                }',
                $escapedTarget,
                $escapedContent,
                $escapedNonce
            );
        }

        $scripts[] = '})();';
        $scripts[] = '</script>';

        return implode("\n", $scripts);
    }

    /**
     * Check if this is an HTMX request
     *
     * @return bool
     */
    public static function isHtmxRequest(): bool
    {
        $request = function_exists('request') ? request() : null;
        if ($request) {
            return strtolower($request->getHeaderLine('hx-request')) === 'true';
        }
        return isset($_SERVER['HTTP_HX_REQUEST']) && strtolower((string) $_SERVER['HTTP_HX_REQUEST']) === 'true';
    }

    /**
     * Get the HTMX target element ID
     *
     * @return string|null
     */
    public static function getHtmxTarget(): ?string
    {
        $request = function_exists('request') ? request() : null;
        if ($request) {
            return $request->getHeaderLine('hx-target') ?: null;
        }
        return $_SERVER['HTTP_HX_TARGET'] ?? null;
    }

    /**
     * Get the HTMX trigger element ID
     *
     * @return string|null
     */
    public static function getHtmxTrigger(): ?string
    {
        $request = function_exists('request') ? request() : null;
        if ($request) {
            return $request->getHeaderLine('hx-trigger') ?: null;
        }
        return $_SERVER['HTTP_HX_TRIGGER'] ?? null;
    }

    /**
     * Get the HTMX trigger name
     *
     * @return string|null
     */
    public static function getHtmxTriggerName(): ?string
    {
        $request = function_exists('request') ? request() : null;
        if ($request) {
            return $request->getHeaderLine('hx-trigger-name') ?: null;
        }
        return $_SERVER['HTTP_HX_TRIGGER_NAME'] ?? null;
    }

    /**
     * Get the current HTMX URL
     *
     * @return string|null
     */
    public static function getHtmxCurrentUrl(): ?string
    {
        $request = function_exists('request') ? request() : null;
        if ($request) {
            return $request->getHeaderLine('hx-current-url') ?: null;
        }
        return $_SERVER['HTTP_HX_CURRENT_URL'] ?? null;
    }

    /**
     * Check if this is a Turbo Frame request
     *
     * @return bool
     */
    public static function isTurboFrameRequest(): bool
    {
        $request = function_exists('request') ? request() : null;
        if ($request) {
            return $request->hasHeader('turbo-frame');
        }
        return isset($_SERVER['HTTP_TURBO_FRAME']);
    }

    /**
     * Get Turbo Frame ID
     *
     * @return string|null
     */
    public static function getTurboFrameId(): ?string
    {
        $request = function_exists('request') ? request() : null;
        if ($request) {
            return $request->getHeaderLine('turbo-frame') ?: null;
        }
        return $_SERVER['HTTP_TURBO_FRAME'] ?? null;
    }

    /**
     * Check if this is any type of partial request
     *
     * @return bool
     */
    public static function isPartialRequest(): bool
    {
        return !empty(self::getRequestedFragment()) || self::isHtmxRequest() || self::isTurboFrameRequest();
    }

    /**
     * Get requested fragment name from headers
     *
     * @return string|null
     */
    public static function getRequestedFragment(): ?string
    {
        $request = function_exists('request') ? request() : null;
        if ($request) {
            if ($request->hasHeader('hx-target')) {
                return ltrim($request->getHeaderLine('hx-target'), '#');
            }
            if ($request->hasHeader('turbo-frame')) {
                return $request->getHeaderLine('turbo-frame');
            }
            if ($request->hasHeader('x-fragment')) {
                return $request->getHeaderLine('x-fragment');
            }
        }

        // HTMX
        if (isset($_SERVER['HTTP_HX_TARGET'])) {
            return ltrim($_SERVER['HTTP_HX_TARGET'], '#');
        }

        // Turbo
        if (isset($_SERVER['HTTP_TURBO_FRAME'])) {
            return $_SERVER['HTTP_TURBO_FRAME'];
        }

        // Custom header
        if (isset($_SERVER['HTTP_X_FRAGMENT'])) {
            return $_SERVER['HTTP_X_FRAGMENT'];
        }

        return null;
    }

    /**
     * Extract a fragment from rendered HTML
     *
     * @param string $html Full HTML content
     * @param string $fragmentId Fragment ID to extract
     * @return string|null Extracted fragment or null if not found
     */
    public static function extractFromHtml(string $html, string $fragmentId): ?string
    {
        // Try to extract by data-fragment attribute
        $pattern = sprintf(
            '/<[^>]*data-fragment=["\']%s["\'][^>]*>(.*?)<\/[^>]*>/s',
            preg_quote($fragmentId, '/')
        );

        if (preg_match($pattern, $html, $matches)) {
            return $matches[0];
        }

        // Try to extract by ID
        $pattern = sprintf(
            '/<[^>]*id=["\']%s["\'][^>]*>(.*?)<\/[^>]*>/s',
            preg_quote($fragmentId, '/')
        );

        if (preg_match($pattern, $html, $matches)) {
            return $matches[0];
        }

        return null;
    }
}
