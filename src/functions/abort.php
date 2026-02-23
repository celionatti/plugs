<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Abort Helper Functions
|--------------------------------------------------------------------------
|
| Convenient shortcuts for throwing HTTP exceptions. These stop execution
| immediately and let the exception handler render the appropriate response.
|
| Usage:
|   abort(404);
|   abort(403, 'You shall not pass');
|   abort_if($user->isBanned(), 403, 'Account suspended');
|   abort_unless(auth()->check(), 401);
*/

use Plugs\Exceptions\HttpException;

if (!function_exists('abort')) {
    /**
     * Throw an HTTP exception.
     *
     * @param int         $code     HTTP status code
     * @param string      $message  Error message (optional — defaults to standard status text)
     * @param array       $headers  Additional response headers
     * @throws HttpException
     * @return never
     */
    function abort(int $code, string $message = '', array $headers = []): never
    {
        throw new HttpException($code, $message, null, $headers);
    }
}

if (!function_exists('abort_if')) {
    /**
     * Throw an HTTP exception if the given condition is true.
     *
     * @param bool        $condition
     * @param int         $code
     * @param string      $message
     * @param array       $headers
     * @throws HttpException
     */
    function abort_if(bool $condition, int $code, string $message = '', array $headers = []): void
    {
        if ($condition) {
            abort($code, $message, $headers);
        }
    }
}

if (!function_exists('abort_unless')) {
    /**
     * Throw an HTTP exception unless the given condition is true.
     *
     * @param bool        $condition
     * @param int         $code
     * @param string      $message
     * @param array       $headers
     * @throws HttpException
     */
    function abort_unless(bool $condition, int $code, string $message = '', array $headers = []): void
    {
        if (!$condition) {
            abort($code, $message, $headers);
        }
    }
}
