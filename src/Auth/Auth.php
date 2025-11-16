<?php

declare(strict_types=1);

namespace Plugs\Auth;

/*
|--------------------------------------------------------------------------
| Enhanced Auth Class - Production Ready
|--------------------------------------------------------------------------
|
| PSR-compliant Authentication Class for Plugs Framework
| - Works with any database table structure
| - Integrates with Connection and PlugModel
| - Supports standard email/password and OAuth2 social authentication
| - Schema-agnostic design with automatic column detection
*/

use PDO;
use PDOException;
use Exception;
use Plugs\Container\Container;
use Plugs\Database\Connection;
use Plugs\Session\Session;
use Plugs\Base\Model\PlugModel;
use Psr\Log\LoggerInterface;

class Auth
{
    private Connection $connection;
    private Session $session;
    private ?LoggerInterface $logger;
    private array $config;
    private ?PlugModel $user = null;
    private array $tableSchema = [];

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
        $this->connection = $connection;
        $this->session = $session;
        $this->logger = $logger;

        $this->config = array_merge([
            // Model configuration
            'user_model' => null, // User model class (optional)
            'table' => 'users',
            'primary_key' => 'id',

            // Authentication columns (auto-detected if not specified)
            'email_column' => null,
            'password_column' => null,
            'remember_token_column' => null,
            'last_login_column' => null,

            // Password configuration
            'password_algo' => PASSWORD_BCRYPT,
            'password_cost' => 12,

            // Session configuration
            'session_key' => env('AUTH_SESSION_ID') ?? 'auth_user_id',
            'remember_token_name' => env('REMEMBER_TOKEN') ?? 'remember_token',
            'remember_days' => 30,

            // OAuth configuration
            'oauth' => [],
            'oauth_table' => 'oauth_accounts',

            // Remember tokens table
            'remember_tokens_table' => 'remember_tokens',

            // Timestamps
            'use_timestamps' => true,
            'created_at_column' => 'created_at',
            'updated_at_column' => 'updated_at',
        ], $config);

        // Auto-detect table schema
        $this->detectTableSchema();

        // Load user from session
        $this->loadUserFromSession();
    }

    /**
     * Create Auth instance from Container
     */
    public static function make(?array $config = null): self
    {
        $container = Container::getInstance();

        $connection = $container->bound('db')
            ? $container->make('db')
            : Connection::getInstance();

        $session = $container->bound(Session::class)
            ? $container->make(Session::class)
            : new Session();

        $logger = $container->bound(LoggerInterface::class)
            ? $container->make(LoggerInterface::class)
            : null;

        if ($config === null && file_exists(BASE_PATH . '/config/auth.php')) {
            $config = require BASE_PATH . '/config/auth.php';
        }

        return new self($connection, $session, $config ?? [], $logger);
    }

    /**
     * Detect table schema and map columns
     */
    private function detectTableSchema(): void
    {
        try {
            $table = $this->config['table'];

            // Get all columns from the table
            $stmt = $this->connection->query("DESCRIBE {$table}");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $this->tableSchema = [
                'all_columns' => $columns,
                'has_timestamps' => false,
            ];

            // Auto-detect email column
            if (!$this->config['email_column']) {
                $emailPatterns = ['email', 'user_email', 'username', 'login'];
                foreach ($emailPatterns as $pattern) {
                    if (in_array($pattern, $columns)) {
                        $this->config['email_column'] = $pattern;
                        break;
                    }
                }
            }

            // Auto-detect password column
            if (!$this->config['password_column']) {
                $passwordPatterns = ['password', 'user_password', 'pass', 'passwd'];
                foreach ($passwordPatterns as $pattern) {
                    if (in_array($pattern, $columns)) {
                        $this->config['password_column'] = $pattern;
                        break;
                    }
                }
            }

            // Detect timestamp columns
            if (
                in_array($this->config['created_at_column'], $columns) &&
                in_array($this->config['updated_at_column'], $columns)
            ) {
                $this->tableSchema['has_timestamps'] = true;
            }

            // Detect last login column
            if (!$this->config['last_login_column']) {
                $loginPatterns = ['last_login', 'last_login_at', 'logged_in_at'];
                foreach ($loginPatterns as $pattern) {
                    if (in_array($pattern, $columns)) {
                        $this->config['last_login_column'] = $pattern;
                        break;
                    }
                }
            }

            // Store detected schema
            $this->tableSchema['email_column'] = $this->config['email_column'];
            $this->tableSchema['password_column'] = $this->config['password_column'];

            $this->log('info', 'Table schema detected', [
                'table' => $table,
                'email_column' => $this->config['email_column'],
                'password_column' => $this->config['password_column'],
            ]);

        } catch (PDOException $e) {
            $this->log('error', "Failed to detect table schema: {$e->getMessage()}");
            throw new Exception("Failed to detect table schema. Please configure columns manually.");
        }
    }

    /**
     * Get user model instance
     */
    public function getUserModel(): PlugModel
    {
        if ($this->config['user_model']) {
            $modelClass = $this->config['user_model'];
            return new $modelClass();
        }

        // Create anonymous model with CORRECT configuration
        $table = $this->config['table'];
        $primaryKey = $this->config['primary_key'];
        $hasTimestamps = $this->tableSchema['has_timestamps'];

        // Create anonymous model for the table with proper constructor
        return new class ($table, $primaryKey, $hasTimestamps) extends PlugModel {
            public function __construct(?string $table = null, ?string $primaryKey = null, ?bool $hasTimestamps = true)
            {
                if ($table) {
                    $this->table = $table;
                }
                if ($primaryKey) {
                    $this->primaryKey = $primaryKey;
                }

                $this->timestamps = $hasTimestamps ?? true;
                $this->fillable = []; // IMPORTANT
                $this->guarded = [];     // IMPORTANT
                parent::__construct();
            }
        };
    }

    /**
     * Register a new user
     */
    public function register(string $email, string $password, array $userData = []): bool
    {
        try {
            if (!$this->config['email_column'] || !$this->config['password_column']) {
                throw new Exception("Email or password column not configured/detected");
            }

            if ($this->userExists($email)) {
                $this->log('warning', "Registration failed: Email already exists", ['email' => $email]);
                return false;
            }

            $hashedPassword = password_hash($password, $this->config['password_algo'], [
                'cost' => $this->config['password_cost']
            ]);

            // Build insert data
            $insertData = [
                $this->config['email_column'] => $email,
                $this->config['password_column'] => $hashedPassword,
            ];

            // Add user data for columns that exist
            foreach ($userData as $key => $value) {
                if (in_array($key, $this->tableSchema['all_columns'])) {
                    $insertData[$key] = $value;
                    error_log("Added field: $key");
                } else {
                    error_log("Skipped field $key (not in table)");
                }
            }

            // Add timestamps if supported
            if ($this->tableSchema['has_timestamps']) {
                $now = date('Y-m-d H:i:s');

                if (!isset($insertData[$this->config['created_at_column']])) {
                    $insertData[$this->config['created_at_column']] = $now;
                }

                if (!isset($insertData[$this->config['updated_at_column']])) {
                    $insertData[$this->config['updated_at_column']] = $now;
                }
            }

            // Use PlugModel for insertion
            $model = $this->getUserModel();
            $user = $model::create($insertData);

            if ($user) {
                $this->log('info', "User registered successfully", ['email' => $email]);
                return true;
            }

            return false;

        } catch (Exception $e) {
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
            if (!$this->config['email_column'] || !$this->config['password_column']) {
                throw new Exception("Email or password column not configured");
            }

            $model = $this->getUserModel();
            $user = $model::where($this->config['email_column'], $email)->first();

            if (!$user || !password_verify($password, $user->getAttribute($this->config['password_column']))) {
                $this->log('warning', "Login failed: Invalid credentials", ['email' => $email]);
                return false;
            }

            // Check if password needs rehashing
            if (password_needs_rehash($user->getAttribute($this->config['password_column']), $this->config['password_algo'])) {
                $this->updatePassword($user->getKey(), $password);
            }

            $this->setUser($user);

            if ($remember) {
                $this->createRememberToken($user->getKey());
            }

            $this->updateLastLogin($user->getKey());
            $this->session->regenerate();

            $this->log('info', "User logged in", ['user_id' => $user->getKey()]);

            return true;

        } catch (Exception $e) {
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

            $sql = "SELECT u.* FROM {$this->config['table']} u 
                    INNER JOIN {$this->config['remember_tokens_table']} rt ON u.{$this->config['primary_key']} = rt.user_id 
                    WHERE rt.token = ? AND rt.expires_at > NOW() 
                    LIMIT 1";

            $result = $this->connection->fetch($sql, [$hashedToken]);

            if ($result) {
                $model = $this->getUserModel();
                // Use hydrate to create model instance from result
                $user = $model::hydrate($result);
                $this->setUser($user);
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
            setcookie($this->config['remember_token_name'], '', time() - 3600, '/', '', true, true);
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
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
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
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
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
            $model = $this->getUserModel();

            // Check if user exists by OAuth provider
            $sql = "SELECT u.{$this->config['primary_key']} FROM {$this->config['table']} u 
                    INNER JOIN {$this->config['oauth_table']} o ON u.{$this->config['primary_key']} = o.user_id 
                    WHERE o.provider = ? AND o.provider_id = ? 
                    LIMIT 1";

            $result = $this->connection->fetch($sql, [$provider, $userData['provider_id']]);

            if ($result) {
                // Find the user using the model's find method
                $user = $model::find($result[$this->config['primary_key']]);

                if ($user) {
                    $this->setUser($user);
                    $this->updateLastLogin($user->getKey());
                    return true;
                }
            }

            // Check if user exists by email
            if (!empty($userData['email']) && $this->config['email_column']) {
                $user = $model::where($this->config['email_column'], $userData['email'])->first();

                if ($user) {
                    // Link OAuth account to existing user
                    $this->linkOAuthAccount($user->getKey(), $provider, $userData);
                    $this->setUser($user);
                    return true;
                }
            }

            // Create new user
            $userId = $this->createOAuthUser($userData);
            if ($userId) {
                $this->linkOAuthAccount($userId, $provider, $userData);

                $user = $model::find($userId);

                if ($user) {
                    $this->setUser($user);
                    return true;
                }
            }

            return false;

        } catch (Exception $e) {
            $this->log('error', "OAuth login/register error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Create user from OAuth data
     */
    private function createOAuthUser(array $userData): ?int
    {
        $insertData = [];

        // Only add columns that exist in the table
        $columnMappings = [
            'email' => $this->config['email_column'],
            'name' => 'name',
            'avatar' => 'avatar',
        ];

        foreach ($columnMappings as $key => $column) {
            if ($column && in_array($column, $this->tableSchema['all_columns']) && isset($userData[$key])) {
                $insertData[$column] = $userData[$key];
            }
        }

        // Add timestamps if supported
        if ($this->tableSchema['has_timestamps']) {
            $now = date('Y-m-d H:i:s');
            $insertData[$this->config['created_at_column']] = $now;
            $insertData[$this->config['updated_at_column']] = $now;
        }

        try {
            $model = $this->getUserModel();
            $user = $model::create($insertData);
            return $user ? (int) $user->getKey() : null;
        } catch (Exception $e) {
            $this->log('error', "Failed to create OAuth user: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Link OAuth account to user
     */
    private function linkOAuthAccount(int $userId, string $provider, array $userData): void
    {
        $sql = "INSERT INTO {$this->config['oauth_table']} (user_id, provider, provider_id, created_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE updated_at = NOW()";

        $this->connection->execute($sql, [$userId, $provider, $userData['provider_id']]);
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
     * Get current user (returns PlugModel instance)
     */
    public function user(): ?PlugModel
    {
        return $this->user;
    }

    /**
     * Get user as array
     */
    public function userArray(): ?array
    {
        return $this->user ? $this->user->toArray() : null;
    }

    /**
     * Get user ID
     */
    public function id()
    {
        return $this->user ? $this->user->getKey() : null;
    }

    /**
     * Check if email exists
     */
    // private function userExists(string $email): bool
    // {
    //     if (!$this->config['email_column']) {
    //         return false;
    //     }

    //     $model = $this->getUserModel();
    //     return $model::where($this->config['email_column'], $email)->exists();
    // }

    private function userExists(string $email): bool
    {
        try {
            if (!$this->config['email_column']) {
                return false;
            }

            $model = $this->getUserModel();
            $emailColumn = $this->config['email_column'];

            // Try exists() method
            try {
                $exists = $model::where($emailColumn, $email)->exists();
                return $exists;
            } catch (Exception $e) {
                // Fallback to count
                try {
                    $count = $model::where($emailColumn, $email)->count();
                    return $count > 0;
                } catch (Exception $e2) {
                    return false;
                }
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Set current user
     */
    private function setUser(PlugModel $user): void
    {
        // Remove password from attributes for security
        if ($this->config['password_column']) {
            $user->makeHidden($this->config['password_column']);
        }

        $this->user = $user;
        $this->session->set($this->config['session_key'], $user->getKey());
    }

    /**
     * Load user from session
     */
    private function loadUserFromSession(): void
    {
        $userId = $this->session->get($this->config['session_key']);

        if ($userId) {
            try {
                $model = $this->getUserModel();
                $user = $model::find($userId);

                if ($user) {
                    if ($this->config['password_column']) {
                        $user->makeHidden($this->config['password_column']);
                    }
                    $this->user = $user;
                }
            } catch (Exception $e) {
                $this->log('error', "Session load error: {$e->getMessage()}");
            }
        }
    }

    /**
     * Update last login timestamp
     */
    private function updateLastLogin(int $userId): void
    {
        if (!$this->config['last_login_column']) {
            return;
        }

        try {
            $model = $this->getUserModel();
            $user = $model::find($userId);

            if ($user) {
                $user->setAttribute($this->config['last_login_column'], date('Y-m-d H:i:s'));
                $user->save();
            }
        } catch (Exception $e) {
            $this->log('error', "Failed to update last login: {$e->getMessage()}");
        }
    }

    /**
     * Update user password
     */
    private function updatePassword(int $userId, string $password): void
    {
        if (!$this->config['password_column']) {
            return;
        }

        $hashedPassword = password_hash($password, $this->config['password_algo'], [
            'cost' => $this->config['password_cost']
        ]);

        try {
            $model = $this->getUserModel();
            $user = $model::find($userId);

            if ($user) {
                $user->setAttribute($this->config['password_column'], $hashedPassword);
                $user->save();
            }
        } catch (Exception $e) {
            $this->log('error', "Failed to update password: {$e->getMessage()}");
        }
    }

    /**
     * Create remember token
     */
    private function createRememberToken(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);

        $sql = "INSERT INTO {$this->config['remember_tokens_table']} (user_id, token, expires_at, created_at) 
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), NOW())";

        $this->connection->execute($sql, [
            $userId,
            $hashedToken,
            $this->config['remember_days']
        ]);

        setcookie(
            $this->config['remember_token_name'],
            $token,
            [
                'expires' => time() + ($this->config['remember_days'] * 24 * 3600),
                'path' => '/',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
    }

    /**
     * Delete remember token
     */
    private function deleteRememberToken(string $token): void
    {
        $hashedToken = hash('sha256', $token);
        $this->connection->execute(
            "DELETE FROM {$this->config['remember_tokens_table']} WHERE token = ?",
            [$hashedToken]
        );
    }

    /**
     * Validate user credentials without logging in
     */
    public function validate(string $email, string $password): bool
    {
        if (!$this->config['email_column'] || !$this->config['password_column']) {
            return false;
        }

        $model = $this->getUserModel();
        $user = $model::where($this->config['email_column'], $email)->first();

        return $user && password_verify($password, $user->getAttribute($this->config['password_column']));
    }

    /**
     * Get the session instance
     */
    public function getSession(): Session
    {
        return $this->session;
    }

    /**
     * Get the connection instance
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Get table schema information
     */
    public function getTableSchema(): array
    {
        return $this->tableSchema;
    }

    /**
     * Get configuration
     */
    public function getConfig(?string $key = null)
    {
        if ($key) {
            return $this->config[$key] ?? null;
        }
        return $this->config;
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