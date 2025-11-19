<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| Input Error Helper Functions
|--------------------------------------------------------------------------
|
*/

if (!function_exists('old')) {
    /**
     * Get old input value after form submission
     * 
     * @param string $key Input field name
     * @param mixed $default Default value if old input doesn't exist
     * @return mixed
     */
    function old(string $key, $default = null)
    {
        // Check session for old input (flash data)
        if (isset($_SESSION['_old_input'][$key])) {
            return $_SESSION['_old_input'][$key];
        }

        // Fallback to current POST data (useful for validation errors)
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }

        return $default;
    }
}

if (!function_exists('flash_old_input')) {
    /**
     * Flash current input to session for next request
     * 
     * @param array|null $input Input data (defaults to $_POST)
     * @return void
     */
    function flash_old_input(?array $input = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['_old_input'] = $input ?? $_POST;
    }
}

if (!function_exists('clear_old_input')) {
    /**
     * Clear old input from session
     * 
     * @return void
     */
    function clear_old_input(): void
    {
        if (isset($_SESSION['_old_input'])) {
            unset($_SESSION['_old_input']);
        }
    }
}