<?php

declare(strict_types=1);

namespace Plugs\View;

/**
 * Interface ViewEngineInterface
 *
 * Defines the contract for all view engines in the Plugs framework.
 *
 * @package Plugs\View
 */
interface ViewEngineInterface
{
    /**
     * Render a view template with data.
     *
     * @param string $view View name
     * @param array $data View data
     * @param bool $isComponent Whether it's a component
     * @return string
     */
    public function render(string $view, array $data = [], bool $isComponent = false): string;

    /**
     * Render a specific fragment/section of a view.
     *
     * @param string $view View name
     * @param string $fragment Fragment name
     * @param array $data View data
     * @return string
     */
    public function renderFragment(string $view, string $fragment, array $data = []): string;

    /**
     * Render the view smartly based on request type (HTMX/Turbo).
     *
     * @param string $view View name
     * @param array $data View data
     * @return string
     */
    public function renderSmart(string $view, array $data = []): string;

    /**
     * Share data across all views.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function share(string $key, $value): void;

    /**
     * Check if a view template exists.
     *
     * @param string $view View name
     * @return bool
     */
    public function exists(string $view): bool;

    /**
     * Get any teleported scripts (for HTMX/SPA support).
     *
     * @return string
     */
    public function getTeleportScripts(): string;

    /**
     * Set whether to suppress the parent layout.
     *
     * @param bool $suppress
     * @return void
     */
    public function suppressLayout(bool $suppress = true): void;

    /**
     * Set a specific section to render for SPA partials.
     *
     * @param string|null $section
     * @return void
     */
    public function requestSection(?string $section): void;

    /**
     * Convert a string to PascalCase.
     *
     * @param string $input
     * @return string
     */
    public function anyToPascalCase(string $input): string;

    /**
     * Set the CSP nonce for scripts and styles.
     *
     * @param string $nonce
     * @return void
     */
    public function setCspNonce(string $nonce): void;
}
