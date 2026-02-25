<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Flash Data Helper Functions
|--------------------------------------------------------------------------
|
| These helpers work with the RedirectResponse to retrieve flash data
| from the session on the next request.
*/

if (!function_exists('flash')) {
    /**
     * Get a flash value from the session
     */
    function flash(?string $key = null, mixed $default = null): mixed
    {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Return all flash data if no key specified
        if ($key === null) {
            $data = $_SESSION['_flash'] ?? [];
            unset($data['_delete_next']);

            return $data;
        }

        // Get specific flash value
        $value = $_SESSION['_flash'][$key] ?? $default;

        return $value;
    }
}

if (!function_exists('hasFlash')) {
    /**
     * Check if a flash key exists
     */
    function hasFlash(string $key): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return isset($_SESSION['_flash'][$key]);
    }
}

if (!function_exists('cleanFlash')) {
    /**
     * Clean up flash data (called automatically by framework)
     * Should be called at the end of each request
     */
    function cleanFlash(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            return;
        }

        // Delete flash data if marked for deletion
        if (isset($_SESSION['_flash']['_delete_next'])) {
            unset($_SESSION['_flash']);
        } else {
            // Mark current flash data for deletion on next request
            if (isset($_SESSION['_flash'])) {
                $_SESSION['_flash']['_delete_next'] = true;
            }
        }
    }
}

if (!function_exists('keepFlash')) {
    /**
     * Keep flash data for one more request
     */
    function keepFlash(array|string|null $keys = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($keys === null) {
            // Keep all flash data
            unset($_SESSION['_flash']['_delete_next']);

            return;
        }

        // Keep specific keys
        $keys = is_array($keys) ? $keys : [$keys];
        $currentFlash = $_SESSION['_flash'] ?? [];

        foreach ($keys as $key) {
            if (isset($currentFlash[$key])) {
                // Re-flash the specific key
                $_SESSION['_flash'][$key] = $currentFlash[$key];
            }
        }
    }
}

if (!function_exists('reflash')) {
    /**
     * Reflash all current flash data for another request
     * Alias for keepFlash()
     */
    function reflash(): void
    {
        keepFlash();
    }
}
