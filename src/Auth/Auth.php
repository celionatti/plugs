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
use Exception;
use PDOException;
use Plugs\Facades\Mail;
use Plugs\Session\Session;
use Psr\Log\LoggerInterface;
use Plugs\Container\Container;
use Plugs\Database\Connection;
use Plugs\Base\Model\PlugModel;

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
            // EMAIL VERIFICATION CONFIG
            'email_verification' => [
                'enabled' => false,
                'token_length' => 6,
                'expiry_hours' => 24,
                'send_welcome_email' => false,
            ],
            // PASSWORD RESET CONFIG
            'password_reset' => [
                'table' => 'password_resets',
                'token_length' => 64,
                'expiry_minutes' => 60,
                'throttle_seconds' => 60,
                'max_attempts' => 3,
            ],
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
                $emailPatterns = ['email', 'user_email', 'usermail', 'login'];
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

        // Store config in variables to use in anonymous class
        $table = $this->config['table'];
        $primaryKey = $this->config['primary_key'];
        $hasTimestamps = $this->tableSchema['has_timestamps'];

        // CRITICAL FIX: Anonymous class constructor MUST accept array $attributes
        // because PlugModel::create() calls new static($attributes)
        return new class ($table, $primaryKey, $hasTimestamps) extends PlugModel {
            private static ?string $_table = null;
            private static ?string $_primaryKey = null;
            private static ?bool $_hasTimestamps = null;

            // This constructor is called ONCE to set up the class configuration
            public function __construct($tableOrAttributes = [], $primaryKey = null, $hasTimestamps = null)
            {
                // First time initialization - setting up the class
                if (is_string($tableOrAttributes) && $primaryKey !== null) {
                    self::$_table = $tableOrAttributes;
                    self::$_primaryKey = $primaryKey;
                    self::$_hasTimestamps = $hasTimestamps ?? true;

                    $this->table = self::$_table;
                    $this->primaryKey = self::$_primaryKey;
                    $this->timestamps = self::$_hasTimestamps;
                    $this->fillable = [];
                    $this->guarded = [];

                    parent::__construct([]);
                }
                // Subsequent calls from create() with attributes array
                else if (is_array($tableOrAttributes)) {
                    $this->table = self::$_table ?? 'users';
                    $this->primaryKey = self::$_primaryKey ?? 'id';
                    $this->timestamps = self::$_hasTimestamps ?? true;
                    $this->fillable = [];
                    $this->guarded = [];

                    parent::__construct($tableOrAttributes);
                }
                // Fallback for no arguments
                else {
                    $this->table = self::$_table ?? 'users';
                    $this->primaryKey = self::$_primaryKey ?? 'id';
                    $this->timestamps = self::$_hasTimestamps ?? true;
                    $this->fillable = [];
                    $this->guarded = [];

                    parent::__construct([]);
                }
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
                }
            }

            // Generate verification token if enabled
            if ($this->config['email_verification']['enabled']) {
                $verificationToken = $this->generateVerificationToken();
                $expiryHours = $this->config['email_verification']['expiry_hours'];

                if (in_array('verification_token', $this->tableSchema['all_columns'])) {
                    $insertData['verification_token'] = $verificationToken;
                }

                if (in_array('verification_token_expires_at', $this->tableSchema['all_columns'])) {
                    $insertData['verification_token_expires_at'] = date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours"));
                }

                error_log("Generated verification token: $verificationToken");
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

                // Send verification email
                if ($this->config['email_verification']['enabled']) {
                    $sent = $this->sendVerificationEmail($user, $verificationToken ?? null);
                    error_log("Verification email sent: " . ($sent ? 'YES' : 'NO'));
                }

                return true;
            }

            return false;

        } catch (Exception $e) {
            $this->log('error', "Registration error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Generate verification token
     */
    private function generateVerificationToken(): string
    {
        $length = $this->config['email_verification']['token_length'];

        // Generate numeric code (e.g., 123456)
        if ($length <= 10) {
            $min = pow(10, $length - 1);
            $max = pow(10, $length) - 1;
            return (string) random_int($min, $max);
        }

        // Generate alphanumeric token
        return bin2hex(random_bytes(32));
    }

    /**
     * Send verification email
     */
    private function sendVerificationEmail(PlugModel $user, ?string $token = null): bool
    {
        try {
            $email = $user->getAttribute($this->config['email_column']);
            $name = $user->getAttribute('name') ?? 'User';
            $userId = $user->getKey();

            // Get or generate token
            if (!$token) {
                $token = $user->getAttribute('verification_token');
            }

            if (!$token) {
                error_log("No verification token available for user: $userId");
                return false;
            }

            // Build verification URL
            $baseUrl = rtrim(env('APP_URL', 'http://localhost'), '/');
            $verificationUrl = "{$baseUrl}/verify-email?token={$token}&email=" . urlencode($email);

            error_log("Verification URL: $verificationUrl");

            // Load email template
            $template = $this->getEmailTemplate();

            // Replace placeholders
            $emailBody = str_replace(
                [
                    '{{site_name}}',
                    '{{name}}',
                    '{{email}}',
                    '{{verification_url}}',
                    '{{verification_token}}',
                    '{{expiry_time}}',
                    '{{year}}',
                    '{{site_url}}'
                ],
                [
                    htmlspecialchars($name),
                    htmlspecialchars($email),
                    htmlspecialchars($verificationUrl),
                    htmlspecialchars($token),
                    $this->config['email_verification']['expiry_hours'],
                    date('Y'),
                    $baseUrl
                ],
                $template
            );

            // Send email using Mail facade
            $sent = Mail::send(
                $email,
                'Verify Your Email Address - NattiNation',
                $emailBody,
                true
            );

            if ($sent) {
                error_log("✅ Verification email sent to: $email");
                $this->log('info', "Verification email sent", ['email' => $email]);
            } else {
                error_log("❌ Failed to send verification email to: $email");
                $this->log('error', "Failed to send verification email", ['email' => $email]);
            }

            return $sent;

        } catch (Exception $e) {
            error_log("❌ Email sending error: " . $e->getMessage());
            $this->log('error', "Email sending error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Get email template
     */
    private function getEmailTemplate(): string
    {
        $templatePath = BASE_PATH . '/resources/views/emails/verify-email.plug.php';

        if (file_exists($templatePath)) {
            return file_get_contents($templatePath);
        }

        return $this->getDefaultEmailTemplate();
    }

    /**
     * Get default email template
     */
    private function getDefaultEmailTemplate(): string
    {
        return <<<'HTML'
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
                    .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center; }
                    .header h1 { margin: 0; font-size: 28px; }
                    .content { padding: 40px 30px; }
                    .content h2 { color: #333; margin: 0 0 20px; }
                    .content p { margin: 0 0 15px; color: #555; }
                    .button { display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 5px; font-weight: 600; margin: 20px 0; }
                    .token { background: #f8f9fa; padding: 20px; text-align: center; font-size: 32px; letter-spacing: 5px; margin: 20px 0; border: 2px dashed #dee2e6; font-family: monospace; }
                    .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; font-size: 14px; color: #856404; }
                    .footer { background: #f8f9fa; padding: 30px; text-align: center; color: #6c757d; font-size: 14px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>🎉 Welcome to {{site_name}}!</h1>
                    </div>
                    <div class="content">
                        <h2>Hi {{name}},</h2>
                        <p>Thank you for joining NattiNation! We're excited to have you as part of our community.</p>
                        <p>To complete your registration, please verify your email address:</p>
                        <div style="text-align: center;">
                            <a href="{{verification_url}}" class="button">Verify Email Address</a>
                        </div>
                        <p>Or use this verification code:</p>
                        <div class="token">{{verification_token}}</div>
                        <div class="warning">
                            ⚠️ This link expires in {{expiry_time}} hours. If you didn't create this account, please ignore this email.
                        </div>
                    </div>
                    <div class="footer">
                        <p><strong>NattiNation</strong></p>
                        <p>© {{year}} NattiNation. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            HTML;
    }

    /**
     * =====================================================
     * VERIFY EMAIL
     * =====================================================
     */
    public function verifyEmail(string $token, ?string $email = null): bool
    {
        try {
            $model = $this->getUserModel();
            $query = $model::where('verification_token', $token);

            if ($email) {
                $query = $query->where($this->config['email_column'], $email);
            }

            $user = $query->first();

            if (!$user) {
                error_log("Invalid verification token: $token");
                $this->log('warning', "Invalid verification token", ['token' => $token]);
                return false;
            }

            // Check if already verified
            if ($user->getAttribute('email_verified_at')) {
                error_log("Email already verified");
                $this->log('info', "Email already verified", ['email' => $user->getAttribute($this->config['email_column'])]);
                return true;
            }

            // Check if token expired
            $expiresAt = $user->getAttribute('verification_token_expires_at');
            if ($expiresAt && strtotime($expiresAt) < time()) {
                error_log("Verification token expired");
                $this->log('warning', "Verification token expired", ['token' => $token]);
                return false;
            }

            // Mark as verified
            $user->setAttribute('email_verified_at', date('Y-m-d H:i:s'));
            $user->setAttribute('verification_token', null);
            $user->setAttribute('verification_token_expires_at', null);

            if ($user->save()) {
                error_log("✅ Email verified successfully");
                $this->log('info', "Email verified successfully", [
                    'email' => $user->getAttribute($this->config['email_column'])
                ]);

                // Auto-login after verification
                $this->setUser($user);

                return true;
            }

            return false;

        } catch (Exception $e) {
            error_log("❌ Email verification error: " . $e->getMessage());
            $this->log('error', "Email verification error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Resend verification email
     */
    public function resendVerificationEmail(string $email): bool
    {
        try {
            $model = $this->getUserModel();
            $user = $model::where($this->config['email_column'], $email)->first();

            if (!$user) {
                error_log("User not found for resend: $email");
                return false;
            }

            // Check if already verified
            if ($user->getAttribute('email_verified_at')) {
                error_log("Email already verified, cannot resend");
                return false;
            }

            // Generate new token
            $token = $this->generateVerificationToken();
            $expiryHours = $this->config['email_verification']['expiry_hours'];

            $user->setAttribute('verification_token', $token);
            $user->setAttribute(
                'verification_token_expires_at',
                date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours"))
            );

            if ($user->save()) {
                return $this->sendVerificationEmail($user, $token);
            }

            return false;

        } catch (Exception $e) {
            error_log("❌ Resend verification error: " . $e->getMessage());
            $this->log('error', "Resend verification error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Check if user's email is verified
     */
    public function isEmailVerified(?PlugModel $user = null): bool
    {
        $user = $user ?? $this->user;

        if (!$user) {
            return false;
        }

        return !is_null($user->getAttribute('email_verified_at'));
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

            // Check email verification if enabled
            if ($this->config['email_verification']['enabled'] && !$this->isEmailVerified($user)) {
                $this->log('warning', "Login failed: Email not verified", ['email' => $email]);
                $this->session->set('pending_verification_email', $email);
                throw new Exception("Please verify your email address before logging in.");
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
     * =====================================================
     * PASSWORD RESET - SEND RESET LINK
     * =====================================================
     */
    public function sendPasswordResetLink(string $email): array
    {
        try {
            // Check throttling
            if (!$this->canSendResetLink($email)) {
                return [
                    'success' => false,
                    'message' => 'Please wait before requesting another reset link.',
                    'throttled' => true
                ];
            }

            // Check if user exists
            $model = $this->getUserModel();
            $user = $model::where($this->config['email_column'], $email)->first();

            if (!$user) {
                // Don't reveal if email exists - return success anyway
                $this->log('warning', "Password reset requested for non-existent email", ['email' => $email]);
                return [
                    'success' => true,
                    'message' => 'If that email exists, we\'ve sent a password reset link.'
                ];
            }

            // Generate reset token
            $token = $this->generateResetToken();
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->config['password_reset']['expiry_minutes']} minutes"));

            // Store reset token
            $this->storeResetToken($email, $token, $expiresAt);

            // Send reset email
            $sent = $this->sendPasswordResetEmail($user, $token);

            if ($sent) {
                $this->log('info', "Password reset link sent", ['email' => $email]);
                return [
                    'success' => true,
                    'message' => 'Password reset link has been sent to your email.'
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to send reset email. Please try again.'
            ];

        } catch (Exception $e) {
            $this->log('error', "Password reset error: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ];
        }
    }

    /**
     * Generate reset token
     */
    private function generateResetToken(): string
    {
        return bin2hex(random_bytes($this->config['password_reset']['token_length'] / 2));
    }

    /**
     * Store reset token in database
     */
    private function storeResetToken(string $email, string $token, string $expiresAt): void
    {
        $table = $this->config['password_reset']['table'];

        // Hash the token before storing
        $hashedToken = hash('sha256', $token);

        $sql = "INSERT INTO {$table} (email, token, expires_at, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)";

        $this->connection->execute($sql, [
            $email,
            $hashedToken,
            $expiresAt,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    /**
     * Check if user can send reset link (throttling)
     */
    private function canSendResetLink(string $email): bool
    {
        $table = $this->config['password_reset']['table'];
        $throttleSeconds = $this->config['password_reset']['throttle_seconds'];

        $sql = "SELECT COUNT(*) as count FROM {$table} 
                WHERE email = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
                AND used_at IS NULL";

        try {
            $stmt = $this->connection->query($sql, [$email, $throttleSeconds]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return ($result['count'] ?? 0) < $this->config['password_reset']['max_attempts'];
        } catch (Exception $e) {
            $this->log('error', "Throttle check error: {$e->getMessage()}");
            return true; // Allow on error
        }
    }

    /**
     * Send password reset email
     */
    private function sendPasswordResetEmail(PlugModel $user, string $token): bool
    {
        try {
            $email = $user->getAttribute($this->config['email_column']);
            $name = $user->getAttribute('name') ?? 'User';

            // Build reset URL
            $baseUrl = rtrim(env('APP_URL', 'http://localhost'), '/');
            $resetUrl = "{$baseUrl}/reset-password?token={$token}&email=" . urlencode($email);

            // Load template
            $template = $this->getPasswordResetTemplate();

            // Replace placeholders
            $emailBody = str_replace(
                [
                    '{{site_name}}',
                    '{{name}}',
                    '{{email}}',
                    '{{reset_url}}',
                    '{{reset_token}}',
                    '{{expiry_time}}',
                    '{{year}}',
                    '{{site_url}}'
                ],
                [
                    htmlspecialchars($name),
                    htmlspecialchars($email),
                    htmlspecialchars($resetUrl),
                    htmlspecialchars($token),
                    $this->config['password_reset']['expiry_minutes'],
                    date('Y'),
                    $baseUrl
                ],
                $template
            );

            // Send email
            $sent = Mail::send(
                $email,
                'Reset Your Password - NattiNation',
                $emailBody,
                true
            );

            if ($sent) {
                $this->log('info', "Password reset email sent", ['email' => $email]);
            } else {
                $this->log('error', "Failed to send password reset email", ['email' => $email]);
            }

            return $sent;

        } catch (Exception $e) {
            $this->log('error', "Password reset email error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Get password reset email template
     */
    private function getPasswordResetTemplate(): string
    {
        $templatePath = BASE_PATH . '/resources/views/emails/reset-password.plug.php';

        if (file_exists($templatePath)) {
            return file_get_contents($templatePath);
        }

        return $this->getDefaultResetTemplate();
    }

    /**
     * Default password reset template
     */
    private function getDefaultResetTemplate(): string
    {
        return <<<'HTML'
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
                    .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center; }
                    .header h1 { margin: 0; font-size: 28px; }
                    .content { padding: 40px 30px; }
                    .content h2 { color: #333; margin: 0 0 20px; }
                    .content p { margin: 0 0 15px; color: #555; }
                    .button { display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 5px; font-weight: 600; margin: 20px 0; }
                    .info-box { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; }
                    .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; font-size: 14px; color: #856404; }
                    .footer { background: #f8f9fa; padding: 30px; text-align: center; color: #6c757d; font-size: 14px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>🔒 Password Reset Request</h1>
                    </div>
                    <div class="content">
                        <h2>Hi {{name}},</h2>
                        <p>We received a request to reset your password for your {{site_name}} account.</p>
                        <p>Click the button below to choose a new password:</p>
                        <div style="text-align: center;">
                            <a href="{{reset_url}}" class="button">Reset Password</a>
                        </div>
                        <div class="info-box">
                            <strong>📧 Alternative Method:</strong><br>
                            If the button doesn't work, copy and paste this link into your browser:<br>
                            <a href="{{reset_url}}" style="word-break: break-all; color: #667eea;">{{reset_url}}</a>
                        </div>
                        <div class="warning">
                            ⚠️ <strong>Security Notice:</strong><br>
                            • This link will expire in <strong>{{expiry_time}} minutes</strong><br>
                            • If you didn't request this reset, please ignore this email and your password will remain unchanged<br>
                            • For security, we never send unsolicited password reset emails
                        </div>
                        <p style="margin-top: 30px; color: #666; font-size: 14px;">
                            If you're having trouble with password resets or suspect unauthorized access, please contact our support team immediately.
                        </p>
                    </div>
                    <div class="footer">
                        <p><strong>{{site_name}}</strong></p>
                        <p>© {{year}} {{site_name}}. All rights reserved.</p>
                        <p style="margin-top: 15px; font-size: 12px; color: #999;">
                            This email was sent to {{email}} because a password reset was requested for this account.
                        </p>
                    </div>
                </div>
            </body>
            </html>
            HTML;
    }

    /**
     * =====================================================
     * PASSWORD RESET - VERIFY TOKEN
     * =====================================================
     */
    public function verifyResetToken(string $token, string $email): bool
    {
        try {
            $table = $this->config['password_reset']['table'];
            $hashedToken = hash('sha256', $token);
            
            $sql = "SELECT * FROM {$table} 
                    WHERE token = ? 
                    AND email = ? 
                    AND expires_at > NOW() 
                    AND used_at IS NULL 
                    LIMIT 1";
            
            $result = $this->connection->fetch($sql, [$hashedToken, $email]);
            
            if ($result) {
                $this->log('info', "Valid reset token verified", ['email' => $email]);
                return true;
            }
            
            $this->log('warning', "Invalid or expired reset token", ['email' => $email]);
            return false;
            
        } catch (Exception $e) {
            $this->log('error', "Token verification error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * =====================================================
     * PASSWORD RESET - RESET PASSWORD
     * =====================================================
     */
    public function resetPassword(string $token, string $email, string $newPassword): array
    {
        try {
            // Verify token
            if (!$this->verifyResetToken($token, $email)) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired reset token. Please request a new one.'
                ];
            }

            // Get user
            $model = $this->getUserModel();
            $user = $model::where($this->config['email_column'], $email)->first();

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found.'
                ];
            }

            // Update password
            $hashedPassword = password_hash($newPassword, $this->config['password_algo'], [
                'cost' => $this->config['password_cost']
            ]);

            $user->setAttribute($this->config['password_column'], $hashedPassword);
            
            // Update password_reset_at if column exists
            if (in_array('password_reset_at', $this->tableSchema['all_columns'])) {
                $user->setAttribute('password_reset_at', date('Y-m-d H:i:s'));
            }

            if ($user->save()) {
                // Mark token as used
                $this->markResetTokenAsUsed($token);
                
                // Invalidate all remember tokens for security
                $this->invalidateAllRememberTokens($user->getKey());
                
                $this->log('info', "Password reset successful", ['email' => $email]);
                
                // Send confirmation email
                $this->sendPasswordChangedEmail($user);
                
                return [
                    'success' => true,
                    'message' => 'Password has been reset successfully. You can now login with your new password.'
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to reset password. Please try again.'
            ];

        } catch (Exception $e) {
            $this->log('error', "Password reset error: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ];
        }
    }

    /**
     * Mark reset token as used
     */
    private function markResetTokenAsUsed(string $token): void
    {
        try {
            $table = $this->config['password_reset']['table'];
            $hashedToken = hash('sha256', $token);
            
            $sql = "UPDATE {$table} SET used_at = NOW() WHERE token = ?";
            $this->connection->execute($sql, [$hashedToken]);
            
        } catch (Exception $e) {
            $this->log('error', "Failed to mark token as used: {$e->getMessage()}");
        }
    }

    /**
     * Invalidate all remember tokens for a user
     */
    private function invalidateAllRememberTokens(int $userId): void
    {
        try {
            $sql = "DELETE FROM {$this->config['remember_tokens_table']} WHERE user_id = ?";
            $this->connection->execute($sql, [$userId]);
            
            $this->log('info', "All remember tokens invalidated", ['user_id' => $userId]);
        } catch (Exception $e) {
            $this->log('error', "Failed to invalidate tokens: {$e->getMessage()}");
        }
    }

    /**
     * Send password changed confirmation email
     */
    private function sendPasswordChangedEmail(PlugModel $user): bool
    {
        try {
            $email = $user->getAttribute($this->config['email_column']);
            $name = $user->getAttribute('name') ?? 'User';
            $baseUrl = rtrim(env('APP_URL', 'http://localhost'), '/');
            $siteName = trim(env('APP_NAME', 'Natti Nation'));
            
            $emailBody = <<<'HTML'
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; margin: 0; padding: 20px; }
                        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                        .header { background: #28a745; color: white; padding: 30px 20px; text-align: center; }
                        .content { padding: 30px; }
                        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>✅ Password Changed Successfully</h1>
                        </div>
                        <div class="content">
                            <h2>Hi {$name},</h2>
                            <p>This email confirms that your password was successfully changed.</p>
                            <p><strong>Details:</strong></p>
                            <ul>
                                <li>Time: {date('F j, Y g:i A')}</li>
                                <li>IP Address: {$_SERVER['REMOTE_ADDR'] ?? 'Unknown'}</li>
                            </ul>
                            <div class="warning">
                                <strong>⚠️ Didn't change your password?</strong><br>
                                If you didn't make this change, please contact our support team immediately and secure your account.
                            </div>
                            <p>For your security, all existing sessions have been invalidated. You'll need to log in again with your new password.</p>
                        </div>
                        <div class="footer">
                            <p><strong>{$siteName}</strong></p>
                            <p>© {date('Y')} {$siteName}. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
                HTML;

            return Mail::send($email, 'Password Changed - NattiNation', $emailBody, true);
            
        } catch (Exception $e) {
            $this->log('error', "Failed to send password changed email: {$e->getMessage()}");
            return false;
        }
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
     * =====================================================
     * ADDITIONAL UTILITY METHODS
     * =====================================================
     */

    /**
     * Change password for authenticated user
     */
    public function changePassword(string $currentPassword, string $newPassword): array
    {
        try {
            if (!$this->check()) {
                return [
                    'success' => false,
                    'message' => 'You must be logged in to change your password.'
                ];
            }

            // Verify current password
            if (!password_verify($currentPassword, $this->user->getAttribute($this->config['password_column']))) {
                return [
                    'success' => false,
                    'message' => 'Current password is incorrect.'
                ];
            }

            // Update password
            $hashedPassword = password_hash($newPassword, $this->config['password_algo'], [
                'cost' => $this->config['password_cost']
            ]);

            $this->user->setAttribute($this->config['password_column'], $hashedPassword);
            
            if (in_array('password_reset_at', $this->tableSchema['all_columns'])) {
                $this->user->setAttribute('password_reset_at', date('Y-m-d H:i:s'));
            }

            if ($this->user->save()) {
                // Invalidate other sessions
                $this->invalidateAllRememberTokens($this->user->getKey());
                
                $this->log('info', "Password changed by user", ['user_id' => $this->user->getKey()]);
                
                // Send confirmation email
                $this->sendPasswordChangedEmail($this->user);
                
                return [
                    'success' => true,
                    'message' => 'Password changed successfully.'
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to change password. Please try again.'
            ];

        } catch (Exception $e) {
            $this->log('error', "Password change error: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => 'An error occurred. Please try again.'
            ];
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(array $data): array
    {
        try {
            if (!$this->check()) {
                return [
                    'success' => false,
                    'message' => 'You must be logged in.'
                ];
            }

            // Only update allowed fields that exist in table
            $allowedFields = ['name', 'username', 'phone', 'bio', 'avatar'];
            
            foreach ($data as $field => $value) {
                if (in_array($field, $allowedFields) && 
                    in_array($field, $this->tableSchema['all_columns'])) {
                    $this->user->setAttribute($field, $value);
                }
            }

            if ($this->user->save()) {
                $this->log('info', "Profile updated", ['user_id' => $this->user->getKey()]);
                return [
                    'success' => true,
                    'message' => 'Profile updated successfully.',
                    'user' => $this->user->toArray()
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to update profile.'
            ];

        } catch (Exception $e) {
            $this->log('error', "Profile update error: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => 'An error occurred.'
            ];
        }
    }

    /**
     * Delete user account
     */
    public function deleteAccount(string $password): array
    {
        try {
            if (!$this->check()) {
                return [
                    'success' => false,
                    'message' => 'You must be logged in.'
                ];
            }

            // Verify password
            if (!password_verify($password, $this->user->getAttribute($this->config['password_column']))) {
                return [
                    'success' => false,
                    'message' => 'Password is incorrect.'
                ];
            }

            $userId = $this->user->getKey();
            $email = $this->user->getAttribute($this->config['email_column']);

            // Delete user
            if ($this->user->delete()) {
                // Clean up related data
                $this->invalidateAllRememberTokens($userId);
                
                // Logout
                $this->logout();
                
                $this->log('info', "Account deleted", ['email' => $email]);
                
                return [
                    'success' => true,
                    'message' => 'Account deleted successfully.'
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to delete account.'
            ];

        } catch (Exception $e) {
            $this->log('error', "Account deletion error: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => 'An error occurred.'
            ];
        }
    }

    /**
     * Get user by email
     */
    public function getUserByEmail(string $email): ?PlugModel
    {
        try {
            $model = $this->getUserModel();
            return $model::where($this->config['email_column'], $email)->first();
        } catch (Exception $e) {
            $this->log('error', "Get user error: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Get user by ID
     */
    public function getUserById(int $id): ?PlugModel
    {
        try {
            $model = $this->getUserModel();
            return $model::find($id);
        } catch (Exception $e) {
            $this->log('error', "Get user error: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Check if password is valid for current user
     */
    public function checkPassword(string $password): bool
    {
        if (!$this->check()) {
            return false;
        }

        return password_verify($password, $this->user->getAttribute($this->config['password_column']));
    }

    /**
     * Refresh current user data from database
     */
    public function refreshUser(): bool
    {
        if (!$this->check()) {
            return false;
        }

        try {
            $fresh = $this->getUserById($this->user->getKey());
            
            if ($fresh) {
                $this->user = $fresh;
                if ($this->config['password_column']) {
                    $this->user->makeHidden($this->config['password_column']);
                }
                return true;
            }

            return false;
        } catch (Exception $e) {
            $this->log('error', "User refresh error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Clean up expired reset tokens
     */
    public function cleanExpiredResetTokens(): int
    {
        try {
            $table = $this->config['password_reset']['table'];
            
            $sql = "DELETE FROM {$table} WHERE expires_at < NOW()";
            $stmt = $this->connection->query($sql);
            
            $deleted = $stmt->rowCount();
            $this->log('info', "Cleaned expired reset tokens", ['count' => $deleted]);
            
            return $deleted;
        } catch (Exception $e) {
            $this->log('error', "Token cleanup error: {$e->getMessage()}");
            return 0;
        }
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
        error_log("[Auth:{$level}] {$message} " . json_encode($context));
    }
}