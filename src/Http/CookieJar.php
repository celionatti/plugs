<?php

declare(strict_types=1);

namespace Plugs\Http;

use Plugs\Security\Encrypter;
use Plugs\Container\Container;

/**
 * CookieJar
 *
 * A secure cookie manager for the Plugs framework.
 * Supports encryption, signing, and secure defaults.
 */
class CookieJar
{
    /**
     * @var Encrypter|null
     */
    protected ?Encrypter $encrypter = null;

    /**
     * Default cookie options.
     */
    protected array $defaults = [
        'path' => '/',
        'domain' => null,
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
        'encrypt' => true,
    ];

    /**
     * @param Encrypter|null $encrypter
     */
    public function __construct(?Encrypter $encrypter = null)
    {
        $this->encrypter = $encrypter;

        // Auto-detect secure flag based on HTTPS
        $this->defaults['secure'] = $this->isSecureConnection();

        // Read from config if available
        if (function_exists('config')) {
            $this->defaults = array_merge($this->defaults, config('security.session', []));
        }
    }

    /**
     * Set a cookie.
     *
     * @param string $name
     * @param mixed $value
     * @param int $minutes
     * @param array $options
     * @return void
     */
    public function set(string $name, $value, int $minutes = 0, array $options = []): void
    {
        $options = array_merge($this->defaults, $options);
        $expires = $minutes > 0 ? time() + ($minutes * 60) : 0;

        // Encrypt value if enabled and encrypter is present
        if ($options['encrypt'] && $this->encrypter && !is_null($value)) {
            $value = $this->encrypter->encrypt($value);
        }

        setcookie($name, (string) $value, [
            'expires' => $expires,
            'path' => $options['path'],
            'domain' => $options['domain'],
            'secure' => $options['secure'],
            'httponly' => $options['httponly'],
            'samesite' => $options['samesite'],
        ]);

        // Make it immediately available in $_COOKIE for the current request
        $_COOKIE[$name] = (string) $value;
    }

    /**
     * Get a cookie value.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function get(string $name, $default = null)
    {
        $value = $_COOKIE[$name] ?? $default;

        if ($value !== $default && $this->encrypter) {
            try {
                return $this->encrypter->decrypt((string) $value);
            } catch (\Exception $e) {
                // If decryption fails, the cookie might be tampered with or not encrypted
                return $default;
            }
        }

        return $value;
    }

    /**
     * Check if a cookie exists.
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($_COOKIE[$name]);
    }

    /**
     * Remove a cookie.
     *
     * @param string $name
     * @param string $path
     * @param string|null $domain
     * @return void
     */
    public function forget(string $name, string $path = '/', ?string $domain = null): void
    {
        $this->set($name, null, -2628000, ['path' => $path, 'domain' => $domain, 'encrypt' => false]);
        unset($_COOKIE[$name]);
    }

    /**
     * Check if the current connection is secure.
     */
    protected function isSecureConnection(): bool
    {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    }
}
