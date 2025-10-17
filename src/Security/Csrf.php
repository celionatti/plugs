<?php

declare(strict_types=1);

namespace Plugs\Security;

/*
|--------------------------------------------------------------------------
| Csrf Class
|--------------------------------------------------------------------------
|
| This class is for generating and validating CSRF tokens to protect against
| Cross-Site Request Forgery attacks.
*/

class Csrf
{
    public static function generate(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    public static function token(): string
    {
        return self::generate();
    }
    
    public static function verify(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $sessionToken = $_SESSION['csrf_token'] ?? null;
        
        return $sessionToken !== null && hash_equals($sessionToken, $token);
    }
    
    public static function field(): string
    {
        $token = self::token();
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}