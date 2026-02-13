<?php

declare(strict_types=1);

namespace Plugs\Config;

class DefaultConfig
{
    public static function get(string $key): array
    {
        return match ($key) {
            'app' => self::app(),
            'auth' => self::auth(),
            'assets' => self::assets(),
            'cache' => self::cache(),
            'database' => self::database(),
            'filesystems' => self::filesystems(),
            'hash' => self::hash(),
            'logging' => self::logging(),
            'mail' => self::mail(),
            'middleware' => self::middleware(),
            'queue' => self::queue(),
            'security' => self::security(),
            'services' => self::services(),
            'ai' => self::ai(),
            'seo' => self::seo(),
            'opcache' => self::opcache(),
            default => [],
        };
    }

    private static function app(): array
    {
        return [
            'name' => env('APP_NAME', 'My Plugs App'),
            'env' => env('APP_ENV', 'local'),
            'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
            'url' => env('APP_URL', 'http://plugs.local'),
            'timezone' => env('APP_TIMEZONE', 'Africa/Lagos'),
            'key' => env('APP_KEY'),
            'locale' => env('APP_LOCALE', 'en'),
            'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
            'paths' => [
                'views' => base_path('resources/views'),
                'cache' => base_path('storage/cache'),
                'logs' => base_path('storage/logs'),
                'storage' => base_path('storage'),
            ],
            'required_files' => [
                'function' => base_path('utils/function.php'),
            ],
            'providers' => array_merge([
                \App\Providers\AppServiceProvider::class,
            ], self::discoverProviders()),
        ];
    }

    /** @var array|null Cached discovered providers */
    private static ?array $discoveredProviders = null;

    private static function discoverProviders(): array
    {
        if (self::$discoveredProviders !== null) {
            return self::$discoveredProviders;
        }

        // Simple auto-discovery for App\Providers
        $providers = [];
        $path = base_path('app/Providers');

        if (is_dir($path)) {
            $files = glob($path . '/*.php');
            foreach ($files as $file) {
                $class = 'App\\Providers\\' . basename($file, '.php');
                if (class_exists($class) && $class !== \App\Providers\AppServiceProvider::class) {
                    $providers[] = $class;
                }
            }
        }

        self::$discoveredProviders = $providers;

        return $providers;
    }

    private static function auth(): array
    {
        return [
            'user_model' => null,
            'table' => 'users',
            'primary_key' => 'id',
            'email_column' => 'email',
            'password_column' => 'password',
            'remember_token_column' => null,
            'last_login_column' => 'last_login_at',
            'password_algo' => PASSWORD_BCRYPT,
            'password_cost' => 12,
            'session_key' => 'auth_user_id',
            'remember_token_name' => 'remember_token',
            'remember_days' => 30,
            'oauth' => [
                'google' => [
                    'client_id' => env('GOOGLE_CLIENT_ID', ''),
                    'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
                ],
                'facebook' => [
                    'client_id' => env('FACEBOOK_CLIENT_ID', ''),
                    'client_secret' => env('FACEBOOK_CLIENT_SECRET', ''),
                ],
                'github' => [
                    'client_id' => env('GITHUB_CLIENT_ID', ''),
                    'client_secret' => env('GITHUB_CLIENT_SECRET', ''),
                ],
                'discord' => [
                    'client_id' => env('DISCORD_CLIENT_ID', ''),
                    'client_secret' => env('DISCORD_CLIENT_SECRET', ''),
                ],
            ],
            'oauth_table' => 'oauth_accounts',
            'remember_tokens_table' => 'remember_tokens',
            'use_timestamps' => true,
            'created_at_column' => 'created_at',
            'updated_at_column' => 'updated_at',
            'email_verification' => [
                'enabled' => env('AUTH_VERIFICATION', false),
                'token_length' => 6,
                'expiry_hours' => 24,
                'send_welcome_email' => true,
            ],
        ];
    }

    private static function database(): array
    {
        $default = env('DB_CONNECTION', 'mysql');

        return [
            'default' => $default,
            'connections' => [
                'mysql' => [
                    'driver' => 'mysql',
                    'read' => [
                        'host' => [env('DB_READ_HOST', env('DB_HOST', '127.0.0.1'))],
                    ],
                    'write' => [
                        'host' => [env('DB_HOST', '127.0.0.1')],
                    ],
                    'sticky' => true,
                    'sticky_window' => 0.5,
                    'port' => env('DB_PORT', '3306'),
                    'database' => env('DB_DATABASE', 'plugs'),
                    'username' => env('DB_USERNAME', 'root'),
                    'password' => env('DB_PASSWORD', ''),
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                    'options' => [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                        \PDO::ATTR_EMULATE_PREPARES => false,
                        \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    ],
                    'timeout' => 5,
                    'persistent' => false,
                    'max_idle_time' => 3600,

                    'pool' => [
                        'enabled' => env('DB_POOL_ENABLED', false),
                        'min_connections' => 2,
                        'max_connections' => 10,
                        'idle_timeout' => 300,
                        'connection_timeout' => 30,
                    ],

                    'load_balancing' => [
                        'strategy' => 'random',
                        'health_check_cooldown' => 30,
                        'max_failures' => 3,
                    ],
                ],
                'pgsql' => [
                    'driver' => 'pgsql',
                    'host' => env('DB_HOST', '127.0.0.1'),
                    'port' => env('DB_PORT', '5432'),
                    'database' => env('DB_DATABASE', 'plugs'),
                    'username' => env('DB_USERNAME', 'postgres'),
                    'password' => env('DB_PASSWORD', ''),
                    'charset' => 'utf8',
                    'prefix' => '',
                    'sslmode' => 'prefer',
                ],
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => env('DB_DATABASE', base_path('storage/database.sqlite')),
                    'prefix' => '',
                ],
            ],
        ];
    }

    private static function security(): array
    {
        return [
            'csrf' => [
                'enabled' => env('CSRF_ENABLED', true),
                'except' => [
                    '#^/api/#',
                    '#^/webhook/#',
                    '#^/public/upload$#',
                    '#^/plugs/media/upload$#',
                    '#^/plugs/component/action$#',
                    '#^/plugs/profiler#',
                ],
                'add_token_to_request' => true,
                'consume_request_tokens' => true,
                'log_failures' => true,
                'error_handler' => null,
                'csrf_config' => [
                    'token_lifetime' => 3600,
                    'use_per_request_tokens' => true,
                    'strict_mode' => true,
                    'use_masking' => true,
                    'context_bound' => false,
                ],
            ],
            'headers' => [
                'X-Frame-Options' => 'SAMEORIGIN',
                'X-Content-Type-Options' => 'nosniff',
                'X-XSS-Protection' => '1; mode=block',
                'Referrer-Policy' => 'strict-origin-when-cross-origin',
                'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
                'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
            ],
            'csp' => [
                'enabled' => env('CSP_ENABLED', false),
                'default-src' => ["'self'"],
                'script-src' => ["'self'", "'unsafe-eval'", "blob:", "https:", "http:"],
                'style-src' => ["'self'", "'unsafe-inline'", "https:", "http:"],
                'img-src' => ["'self'", "data:", "https:", "blob:"],
                'font-src' => ["'self'", "data:", "https:", "http:"],
                'connect-src' => ["'self'", "https:", "ws:", "wss:"],
            ],
            'rate_limit' => [
                'enabled' => false,
                'max_requests' => 60,
                'per_minutes' => 1,
            ],
            'cors' => [
                'enabled' => env('CORS_ENABLED', false),
                'allowed_origins' => ['*'],
                'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
                'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
                'max_age' => 86400,
            ],
            'session' => [
                'secure' => env('APP_ENV') === 'production',
                'httponly' => true,
                'samesite' => 'Lax',
                'lifetime' => 120,
            ],
            'security_shield' => [
                'enabled' => env('SECURITY_SHIELD', true),
                'whitelisted_ips' => ['127.0.0.1', '::1'],
                'risk_thresholds' => [
                    'deny' => 0.85,
                    'challenge_high' => 0.70,
                    'challenge_low' => 0.50,
                ],
                'config' => [
                    'rate_limits' => [
                        'login_attempts' => 5,
                        'login_window' => 900,
                        'ip_daily_limit' => 100,
                        'user_daily_limit' => 50,
                        'endpoint_limit' => 20,
                    ],
                    'bot_detection' => [
                        'suspicious_headers' => ['bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python-requests'],
                        'block_suspicious_bots' => true,
                    ],
                ],
                'rules' => [
                    'rate_limit' => true,
                    'bot_detection' => true,
                    'email' => true,
                    'behavior' => true,
                    'fingerprint' => false,
                ],
                'whitelist' => ['127.0.0.1', '::1'],
            ],
            'profiler' => [
                'enabled' => env('PROFILER_ENABLED', false),
            ],
        ];
    }

    private static function assets(): array
    {
        return [
            'minify' => env('APP_ENV') === 'production',
            'combine' => env('APP_ENV') === 'production',
            'versioning' => true,
            'sri' => true,
            'precompress' => env('APP_ENV') === 'production',
            'register' => [
                'css' => [],
                'js' => [],
            ],
        ];
    }

    private static function cache(): array
    {
        return [
            'default' => env('CACHE_DRIVER', 'file'),
            'drivers' => [
                'file' => [
                    'path' => storage_path('cache'),
                ],
            ],
        ];
    }

    private static function filesystems(): array
    {
        return [
            'default' => env('FILESYSTEM_DISK', 'local'),
            'disks' => [
                'local' => [
                    'driver' => 'local',
                    'root' => storage_path('app'),
                ],
                'public' => [
                    'driver' => 'local',
                    'root' => storage_path('app/public'),
                    'url' => env('APP_URL') . '/storage',
                    'visibility' => 'public',
                ],
                's3' => [
                    'driver' => 's3',
                    'key' => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
                    'bucket' => env('AWS_BUCKET'),
                    'url' => env('AWS_URL'),
                    'endpoint' => env('AWS_ENDPOINT'),
                    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
                    'root' => env('AWS_ROOT', ''),
                ],
            ],
        ];
    }

    private static function hash(): array
    {
        return [
            'driver' => env('HASH_DRIVER', 'argon2id'),
            'bcrypt' => [
                'rounds' => (int) (env('BCRYPT_ROUNDS', 12)),
            ],
            'argon' => [
                'memory' => (int) (env('ARGON_MEMORY', 65536)),
                'time' => (int) (env('ARGON_TIME', 4)),
                'threads' => (int) (env('ARGON_THREADS', 3)),
            ],
            'argon2id' => [
                'memory' => (int) (env('ARGON2ID_MEMORY', 65536)),
                'time' => (int) (env('ARGON2ID_TIME', 4)),
                'threads' => (int) (env('ARGON2ID_THREADS', 3)),
            ],
            'verify' => [
                'auto_rehash' => (bool) (env('HASH_AUTO_REHASH', false)),
            ],
        ];
    }

    private static function logging(): array
    {
        return [
            'default' => env('LOG_CHANNEL', 'file'),
            'channels' => [
                'file' => [
                    'driver' => 'single',
                    'path' => storage_path('logs/plugs.log'),
                    'level' => 'debug',
                ],
            ],
        ];
    }

    private static function mail(): array
    {
        return [
            'driver' => env('MAIL_DRIVER', 'smtp'),
            'host' => env('MAIL_HOST', 'smtp.mailtrap.io'),
            'port' => (int) (env('MAIL_PORT', 2525)),
            'username' => env('MAIL_USERNAME', ''),
            'password' => env('MAIL_PASSWORD', ''),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
                'name' => env('MAIL_FROM_NAME', 'My Plugs App'),
            ],
        ];
    }

    private static function middleware(): array
    {
        return [
            'aliases' => [
                'csrf' => \Plugs\Http\Middleware\CsrfMiddleware::class,
                'guest' => \App\Http\Middleware\GuestMiddleware::class,
                'auth' => \Plugs\Http\Middleware\AuthenticateMiddleware::class,
                'ai.optimize' => \Plugs\Http\Middleware\AIOptimizeMiddleware::class,
            ],

            'groups' => [
                'web' => [
                    \Plugs\Http\Middleware\ShareErrorsFromSession::class,
                ],
                'api' => [
                    \Plugs\Http\Middleware\ForceJsonMiddleware::class,
                ],
            ],
        ];
    }

    private static function queue(): array
    {
        return [
            'default' => env('QUEUE_CONNECTION', 'sync'),
            'connections' => [
                'sync' => [
                    'driver' => 'sync',
                ],
                'database' => [
                    'driver' => 'database',
                    'table' => 'jobs',
                    'queue' => 'default',
                ],
            ],
        ];
    }

    private static function services(): array
    {
        return [
            'github' => [
                'client_id' => env('GITHUB_CLIENT_ID', ''),
                'client_secret' => env('GITHUB_CLIENT_SECRET', ''),
                'redirect' => env('GITHUB_REDIRECT_URI', 'http://plugs.local/auth/github/callback'),
            ],
            'google' => [
                'client_id' => env('GOOGLE_CLIENT_ID', ''),
                'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
                'redirect' => env('GOOGLE_REDIRECT_URI', 'http://plugs.local/auth/google/callback'),
            ],
        ];
    }

    private static function ai(): array
    {
        return [
            'default' => env('AI_DRIVER', 'openai'),
            'providers' => [
                'openai' => [
                    'api_key' => env('OPENAI_API_KEY'),
                    'organization' => env('OPENAI_ORGANIZATION'),
                    'model' => env('OPENAI_MODEL', 'gpt-4o'),
                ],
                'anthropic' => [
                    'api_key' => env('ANTHROPIC_API_KEY'),
                    'model' => env('ANTHROPIC_MODEL', 'claude-opus-4-6'),
                ],
                'gemini' => [
                    'api_key' => env('GEMINI_API_KEY'),
                    'model' => env('GEMINI_MODEL', 'gemini-3-flash-preview'),
                ],
                'groq' => [
                    'api_key' => env('GROQ_API_KEY'),
                    'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
                ],
                'openrouter' => [
                    'api_key' => env('OPENROUTER_API_KEY'),
                    'model' => env('OPENROUTER_MODEL', 'openrouter/auto:free'),
                ],
            ],
        ];
    }

    private static function seo(): array
    {
        return [
            'default_title' => env('APP_NAME', 'Plugs Framework'),
            'title_appendix' => ' | ' . env('APP_NAME', 'Plugs'),
            'default_description' => 'A high-performance, modular PHP framework for modern web applications.',
            'default_image' => asset('assets/img/og-image.png'),
            'keywords' => 'php, framework, plugs, fast, modular, web development',
            'robots' => 'index, follow',
        ];
    }
    private static function opcache(): array
    {
        return [
            'enabled' => env('OPCACHE_ENABLED', true),
            'validate_timestamps' => env('OPCACHE_VALIDATE_TIMESTAMPS', false),
            'revalidate_freq' => env('OPCACHE_REVALIDATE_FREQ', 0),
            'max_accelerated_files' => env('OPCACHE_MAX_ACCELERATED_FILES', 10000),
            'memory_consumption' => env('OPCACHE_MEMORY_CONSUMPTION', 128),
            'interned_strings_buffer' => env('OPCACHE_INTERNED_STRINGS_BUFFER', 8),
            'fast_shutdown' => env('OPCACHE_FAST_SHUTDOWN', true),
        ];
    }
}
