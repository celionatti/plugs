<?php

declare(strict_types=1);

namespace Plugs\Session;

/*
|--------------------------------------------------------------------------
| SessionManager Class
|--------------------------------------------------------------------------
|
| This class manages session configuration and initialization.
| It provides methods to start sessions with custom settings and
| retrieve the session instance.
*/

class SessionManager
{
    private $config;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'lifetime' => 120,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
            'save_path' => null,
        ], $config);
    }
    
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        
        // Set session configuration
        session_set_cookie_params([
            'lifetime' => $this->config['lifetime'] * 60,
            'path' => $this->config['path'],
            'domain' => $this->config['domain'],
            'secure' => $this->config['secure'],
            'httponly' => $this->config['httponly'],
            'samesite' => $this->config['samesite'],
        ]);
        
        // Set custom save path if provided
        if ($this->config['save_path'] !== null) {
            if (!is_dir($this->config['save_path'] ?? '')) {
                mkdir($this->config['save_path'] ?? '', 0755, true);
            }
            session_save_path($this->config['save_path']);
        }
        
        session_start();
        
        // Regenerate session ID periodically for security
        $this->regenerateIfNeeded();
    }
    
    private function regenerateIfNeeded(): void
    {
        if (!isset($_SESSION['_last_regenerated'])) {
            $_SESSION['_last_regenerated'] = time();
        }
        
        // Regenerate every 30 minutes
        if (time() - $_SESSION['_last_regenerated'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_last_regenerated'] = time();
        }
    }
    
    public function getSession(): Session
    {
        return new Session();
    }
}