<?php

declare(strict_types=1);

if (defined('PLUGS_ERROR_LOADED'))
    return;
define('PLUGS_ERROR_LOADED', true);

use Plugs\Debug\HtmlErrorRenderer;

/**
 * Plugs Framework Error Utility - Refactored
 * 
 * Delegates rendering to Plugs\Debug\HtmlErrorRenderer.
 */

if (!function_exists('renderDebugErrorPage')) {
    /**
     * Render debug error page with detailed information
     */
    function renderDebugErrorPage(Throwable $e): void
    {
        (new HtmlErrorRenderer())->renderDebug($e);
    }
}

if (!function_exists('renderProductionErrorPage')) {
    /**
     * Render production error page without sensitive information
     */
    function renderProductionErrorPage(Throwable $e, int $statusCode = 500): void
    {
        (new HtmlErrorRenderer())->renderProduction($e, $statusCode);
    }
}

if (!function_exists('getProductionErrorHtml')) {
    /**
     * Get the HTML content for a production error page.
     */
    function getProductionErrorHtml(int $statusCode = 500, ?string $title = null, ?string $message = null): string
    {
        return (new HtmlErrorRenderer())->getProductionHtml($statusCode);
    }
}
