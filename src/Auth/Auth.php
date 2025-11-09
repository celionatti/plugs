<?php

declare(strict_types=1);

namespace Plugs\Auth;

/*
|--------------------------------------------------------------------------
| Auth Class
|--------------------------------------------------------------------------
|
| PSR-compliant Authentication Class for Plugs Framework
| Supports standard email/password and OAuth2 social authentication
*/

use PDO;
use PDOException;
use Plugs\Container\Container;
use Plugs\Database\Connection;
use Plugs\Session\Session;
use Psr\Log\LoggerInterface;

class Auth
{
    private Connection $db;
    private Session $session;
    private ?LoggerInterface $logger;
    private array $config;
    private ?array $user = null;

    // OAuth2 endpoints
    private const OAUTH_ENDPOINTS = [
        'google' => [
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'user_url' => 'https://www.googleapis.com/oauth2/v2/userinfo'
        ],
        'facebook' => [
            'auth_url' => 'https://www.facebook.com/v18.0/dialog/oauth',
            'token_url' => 'https://graph.facebook.com/v18.0/oauth/access_token',
            'user_url' => 'https://graph.facebook.com/me?fields=id,name,email'
        ],
        'github' => [
            'auth_url' => 'https://github.com/login/oauth/authorize',
            'token_url' => 'https://github.com/login/oauth/access_token',
            'user_url' => 'https://api.github.com/user'
        ],
        'discord' => [
            'auth_url' => 'https://discord.com/api/oauth2/authorize',
            'token_url' => 'https://discord.com/api/oauth2/token',
            'user_url' => 'https://discord.com/api/users/@me'
        ]
    ];

    public function __construct(
        Connection $connection,
        Session $session,
        array $config = [],
        ?LoggerInterface $logger = null
    ) {
        $this->db = $connection;
        $this->session = $session;
        $this->logger = $logger;
        $this->config = array_merge([
            'password_algo' => PASSWORD_BCRYPT,
            'password_cost' => 12,
            'session_key' => env('AUTH_SESSION_ID') ?? 'auth_user_id',
            'remember_token_name' => env('REMEMBER_TOEKN') ?? 'remember_token',
            'remember_days' => 30,
            'oauth' => []
        ], $config);

        $this->loadUserFromSession();
    }

    /**
     * Create Auth instance from Container
     */
    public static function make(?array $config = null): self
    {
        $container = Container::getInstance();

        // Get or create database connection
        $connection = $container->bound('db')
            ? $container->make('db')
            : Connection::getInstance();

        // Get or create session
        $session = $container->bound(Session::class)
            ? $container->make(Session::class)
            : new Session();

        // Get logger if available
        $logger = $container->bound(LoggerInterface::class)
            ? $container->make(LoggerInterface::class)
            : null;

        // Get config if not provided
        if ($config === null && file_exists(BASE_PATH . '/config/auth.php')) {
            $config = require BASE_PATH . '/config/auth.php';
        }

        return new self($connection, $session, $config ?? [], $logger);
    }

    /**
     * Register a new user
     */
    public function register(string $email, string $password, array $userData = []): bool
    {
        try {
            if ($this->userExists($email)) {
                $this->log('warning', "Registration failed: Email already exists", ['email' => $email]);
                return false;
            }

            $hashedPassword = password_hash($password, $this->config['password_algo'], [
                'cost' => $this->config['password_cost']
            ]);

            $sql = "INSERT INTO users (name, email, password, created_at, updated_at) 
                    VALUES (:name, :email, :password, NOW(), NOW())";

            $result = $this->db->execute($sql, [
                'name' => $userData['name'] ?? null,
                'email' => $email,
                'password' => $hashedPassword,
            ]);

            if ($result) {
                $this->log('info', "User registered successfully", ['email' => $email]);
            }

            return $result;
        } catch (PDOException $e) {
            $this->log('error', "Registration error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Login with email and password
     */
    public function login(string $email, string $password, bool $remember = false): bool
    {
        try {
            $user = $this->db->fetch(
                "SELECT * FROM users WHERE email = :email LIMIT 1",
                ['email' => $email]
            );

            if (!$user || !password_verify($password, $user['password'])) {
                $this->log('warning', "Login failed: Invalid credentials", ['email' => $email]);
                return false;
            }

            // Check if password needs rehashing
            if (password_needs_rehash($user['password'], $this->config['password_algo'])) {
                $this->updatePassword($user['id'], $password);
            }

            $this->setUser($user);

            if ($remember) {
                $this->createRememberToken($user['id']);
            }

            $this->updateLastLogin($user['id']);
            $this->session->regenerate();
            $this->log('info', "User logged in", ['user_id' => $user['id']]);

            return true;
        } catch (PDOException $e) {
            $this->log('error', "Login error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Attempt to log in using remember token
     */
    public function loginFromRemember(): bool
    {
        $token = $_COOKIE[$this->config['remember_token_name']] ?? null;

        if (!$token) {
            return false;
        }

        try {
            $hashedToken = hash('sha256', $token);

            $result = $this->db->fetch(
                "SELECT u.* FROM users u 
                 INNER JOIN remember_tokens rt ON u.id = rt.user_id 
                 WHERE rt.token = :token AND rt.expires_at > NOW() 
                 LIMIT 1",
                ['token' => $hashedToken]
            );

            if ($result) {
                $this->setUser($result);
                $this->session->regenerate();
                return true;
            }
        } catch (PDOException $e) {
            $this->log('error', "Remember token error: {$e->getMessage()}");
        }

        return false;
    }

    /**
     * Logout current user
     */
    public function logout(): void
    {
        $this->user = null;
        $this->session->remove($this->config['session_key']);

        if (isset($_COOKIE[$this->config['remember_token_name']])) {
            $this->deleteRememberToken($_COOKIE[$this->config['remember_token_name']]);
            setcookie($this->config['remember_token_name'], '', time() - 3600, '/');
        }

        $this->session->regenerate();
        $this->log('info', "User logged out");
    }

    /**
     * Get OAuth2 authorization URL
     */
    public function getOAuthUrl(string $provider, string $redirectUri, array $scopes = []): ?string
    {
        if (!isset(self::OAUTH_ENDPOINTS[$provider])) {
            $this->log('error', "Unknown OAuth provider: {$provider}");
            return null;
        }

        if (!isset($this->config['oauth'][$provider])) {
            $this->log('error', "OAuth config missing for: {$provider}");
            return null;
        }

        $endpoint = self::OAUTH_ENDPOINTS[$provider];
        $config = $this->config['oauth'][$provider];

        $state = bin2hex(random_bytes(16));
        $this->session->set('oauth_state', $state);
        $this->session->set('oauth_provider', $provider);

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'response_type' => 'code'
        ];

        // Provider-specific scope handling
        switch ($provider) {
            case 'google':
                $params['scope'] = implode(' ', $scopes ?: ['email', 'profile']);
                $params['access_type'] = 'offline';
                break;
            case 'facebook':
                $params['scope'] = implode(',', $scopes ?: ['email', 'public_profile']);
                break;
            case 'github':
                $params['scope'] = implode(' ', $scopes ?: ['user:email']);
                break;
            case 'discord':
                $params['scope'] = implode(' ', $scopes ?: ['identify', 'email']);
                break;
        }

        return $endpoint['auth_url'] . '?' . http_build_query($params);
    }

    /**
     * Handle OAuth2 callback
     */
    public function handleOAuthCallback(string $code, string $state, string $redirectUri): bool
    {
        if (!$this->session->has('oauth_state') || $state !== $this->session->get('oauth_state')) {
            $this->log('error', "OAuth state mismatch");
            return false;
        }

        $provider = $this->session->get('oauth_provider');
        if (!$provider || !isset(self::OAUTH_ENDPOINTS[$provider])) {
            $this->log('error', "Invalid OAuth provider in session");
            return false;
        }

        $this->session->remove('oauth_state');
        $this->session->remove('oauth_provider');

        $accessToken = $this->getOAuthAccessToken($provider, $code, $redirectUri);
        if (!$accessToken) {
            return false;
        }

        $userData = $this->getOAuthUserData($provider, $accessToken);
        if (!$userData) {
            return false;
        }

        return $this->loginOrRegisterOAuthUser($provider, $userData);
    }

    /**
     * Get OAuth2 access token
     */
    private function getOAuthAccessToken(string $provider, string $code, string $redirectUri): ?string
    {
        $endpoint = self::OAUTH_ENDPOINTS[$provider];
        $config = $this->config['oauth'][$provider];

        $params = [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ];

        $ch = curl_init($endpoint['token_url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->log('error', "OAuth token request failed", ['provider' => $provider, 'code' => $httpCode]);
            return null;
        }

        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }

    /**
     * Get OAuth2 user data
     */
    private function getOAuthUserData(string $provider, string $accessToken): ?array
    {
        $endpoint = self::OAUTH_ENDPOINTS[$provider];

        $ch = curl_init($endpoint['user_url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
                'User-Agent: Plugs-Auth-Client'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->log('error', "OAuth user data request failed", ['provider' => $provider]);
            return null;
        }

        $data = json_decode($response, true);
        return $this->normalizeOAuthUserData($provider, $data);
    }

    /**
     * Normalize OAuth user data
     */
    private function normalizeOAuthUserData(string $provider, array $data): array
    {
        $normalized = ['provider' => $provider];

        switch ($provider) {
            case 'google':
                $normalized['provider_id'] = $data['id'];
                $normalized['email'] = $data['email'];
                $normalized['name'] = $data['name'];
                $normalized['avatar'] = $data['picture'] ?? null;
                break;
            case 'facebook':
                $normalized['provider_id'] = $data['id'];
                $normalized['email'] = $data['email'] ?? null;
                $normalized['name'] = $data['name'];
                break;
            case 'github':
                $normalized['provider_id'] = $data['id'];
                $normalized['email'] = $data['email'];
                $normalized['name'] = $data['name'] ?? $data['login'];
                $normalized['avatar'] = $data['avatar_url'] ?? null;
                break;
            case 'discord':
                $normalized['provider_id'] = $data['id'];
                $normalized['email'] = $data['email'];
                $normalized['name'] = $data['username'];
                $normalized['avatar'] = isset($data['avatar'])
                    ? "https://cdn.discordapp.com/avatars/{$data['id']}/{$data['avatar']}.png"
                    : null;
                break;
        }

        return $normalized;
    }

    /**
     * Login or register user via OAuth
     */
    private function loginOrRegisterOAuthUser(string $provider, array $userData): bool
    {
        try {
            // Check if user exists by OAuth provider
            $user = $this->db->fetch(
                "SELECT u.* FROM users u 
                 INNER JOIN oauth_accounts o ON u.id = o.user_id 
                 WHERE o.provider = :provider AND o.provider_id = :provider_id 
                 LIMIT 1",
                [
                    'provider' => $provider,
                    'provider_id' => $userData['provider_id']
                ]
            );

            if ($user) {
                $this->setUser($user);
                $this->updateLastLogin($user['id']);
                return true;
            }

            // Check if user exists by email
            if (!empty($userData['email'])) {
                $user = $this->db->fetch(
                    "SELECT * FROM users WHERE email = :email LIMIT 1",
                    ['email' => $userData['email']]
                );

                if ($user) {
                    // Link OAuth account to existing user
                    $this->linkOAuthAccount($user['id'], $provider, $userData);
                    $this->setUser($user);
                    return true;
                }
            }

            // Create new user
            $userId = $this->createOAuthUser($userData);
            if ($userId) {
                $this->linkOAuthAccount($userId, $provider, $userData);

                $user = $this->db->fetch(
                    "SELECT * FROM users WHERE id = :id LIMIT 1",
                    ['id' => $userId]
                );

                if ($user) {
                    $this->setUser($user);
                    return true;
                }
            }

            return false;
        } catch (PDOException $e) {
            $this->log('error', "OAuth login/register error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Create user from OAuth data
     */
    private function createOAuthUser(array $userData): ?int
    {
        $sql = "INSERT INTO users (email, name, avatar, created_at, updated_at) 
                VALUES (:email, :name, :avatar, NOW(), NOW())";

        $result = $this->db->execute($sql, [
            'email' => $userData['email'] ?? null,
            'name' => $userData['name'],
            'avatar' => $userData['avatar'] ?? null
        ]);

        return $result ? (int)$this->db->lastInsertId() : null;
    }

    /**
     * Link OAuth account to user
     */
    private function linkOAuthAccount(int $userId, string $provider, array $userData): void
    {
        $sql = "INSERT INTO oauth_accounts (user_id, provider, provider_id, created_at) 
                VALUES (:user_id, :provider, :provider_id, NOW())
                ON DUPLICATE KEY UPDATE updated_at = NOW()";

        $this->db->execute($sql, [
            'user_id' => $userId,
            'provider' => $provider,
            'provider_id' => $userData['provider_id']
        ]);
    }

    /**
     * Check if user is authenticated
     */
    public function check(): bool
    {
        return $this->user !== null;
    }

    /**
     * Check if user is a guest (not authenticated)
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Get current user
     */
    public function user(): ?array
    {
        return $this->user;
    }

    /**
     * Get user ID
     */
    public function id(): ?int
    {
        return $this->user['id'] ?? null;
    }

    /**
     * Check if email exists
     */
    private function userExists(string $email): bool
    {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as count FROM users WHERE email = :email",
            ['email' => $email]
        );
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Set current user
     */
    private function setUser(array $user): void
    {
        unset($user['password']);
        $this->user = $user;
        $this->session->set($this->config['session_key'], $user['id']);
    }

    /**
     * Load user from session
     */
    private function loadUserFromSession(): void
    {
        $userId = $this->session->get($this->config['session_key']);

        if ($userId) {
            try {
                $user = $this->db->fetch(
                    "SELECT * FROM users WHERE id = :id LIMIT 1",
                    ['id' => $userId]
                );

                if ($user) {
                    unset($user['password']);
                    $this->user = $user;
                }
            } catch (PDOException $e) {
                $this->log('error', "Session load error: {$e->getMessage()}");
            }
        }
    }

    /**
     * Update last login timestamp
     */
    private function updateLastLogin(int $userId): void
    {
        $this->db->execute(
            "UPDATE users SET last_login = NOW() WHERE id = :id",
            ['id' => $userId]
        );
    }

    /**
     * Update user password
     */
    private function updatePassword(int $userId, string $password): void
    {
        $hashedPassword = password_hash($password, $this->config['password_algo'], [
            'cost' => $this->config['password_cost']
        ]);

        $this->db->execute(
            "UPDATE users SET password = :password WHERE id = :id",
            ['password' => $hashedPassword, 'id' => $userId]
        );
    }

    /**
     * Create remember token
     */
    private function createRememberToken(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);

        $sql = "INSERT INTO remember_tokens (user_id, token, expires_at, created_at) 
                VALUES (:user_id, :token, DATE_ADD(NOW(), INTERVAL :days DAY), NOW())";

        $this->db->execute($sql, [
            'user_id' => $userId,
            'token' => $hashedToken,
            'days' => $this->config['remember_days']
        ]);

        setcookie(
            $this->config['remember_token_name'],
            $token,
            time() + ($this->config['remember_days'] * 24 * 3600),
            '/',
            '',
            true,
            true
        );
    }

    /**
     * Delete remember token
     */
    private function deleteRememberToken(string $token): void
    {
        $hashedToken = hash('sha256', $token);
        $this->db->execute(
            "DELETE FROM remember_tokens WHERE token = :token",
            ['token' => $hashedToken]
        );
    }

    /**
     * Validate user credentials without logging in
     */
    public function validate(string $email, string $password): bool
    {
        $user = $this->db->fetch(
            "SELECT password FROM users WHERE email = :email LIMIT 1",
            ['email' => $email]
        );

        return $user && password_verify($password, $user['password']);
    }

    /**
     * Get the session instance
     */
    public function getSession(): Session
    {
        return $this->session;
    }

    /**
     * Log message
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }
}
