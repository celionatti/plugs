<?php

declare(strict_types=1);

/**
 * Plugs Framework Installer Configuration
 * 
 * This file contains all the installation requirements and settings.
 */

return [
    // Minimum PHP version required
    'php_version' => '8.0.0',

    // Required PHP extensions
    'extensions' => [
        'pdo' => 'PDO (Database Access)',
        'pdo_mysql' => 'PDO MySQL Driver',
        'mbstring' => 'Multibyte String',
        'openssl' => 'OpenSSL',
        'json' => 'JSON',
        'curl' => 'cURL',
        'fileinfo' => 'File Information',
        'tokenizer' => 'Tokenizer',
        'xml' => 'XML Parser',
    ],

    // Optional but recommended extensions
    'optional_extensions' => [
        'pdo_pgsql' => 'PDO PostgreSQL Driver',
        'pdo_sqlite' => 'PDO SQLite Driver',
        'gd' => 'GD Image Library',
        'imagick' => 'ImageMagick',
        'zip' => 'ZIP Archive',
        'redis' => 'Redis',
        'memcached' => 'Memcached',
    ],

    // Directories to create
    'directories' => [
        'app',
        'app/Components',
        'app/Http',
        'app/Http/Controllers',
        'app/Middlewares',
        'app/Models',
        'bootstrap',
        'config',
        'database',
        'database/migrations',
        'resources',
        'resources/views',
        'resources/views/layouts',
        'resources/views/home',
        'resources/views/components',
        'routes',
        'storage',
        'storage/framework',
        'storage/framework/profiler',
        'storage/logs',
        'storage/views',
    ],

    // Directories that need write permissions
    'writable_directories' => [
        'storage',
        'storage/framework',
        'storage/logs',
        'storage/views',
        'public/assets/cache',
    ],

    // Files to generate from templates
    'files' => [
        'bootstrap/boot.php' => 'boot.php.template',
        'config/app.php' => 'config/app.php.template',
        'config/auth.php' => 'config/auth.php.template',
        'config/database.php' => 'config/database.php.template',
        'config/hash.php' => 'config/hash.php.template',
        'config/mail.php' => 'config/mail.php.template',
        'config/middleware.php' => 'config/middleware.php.template',
        'config/security.php' => 'config/security.php.template',
        'config/services.php' => 'config/services.php.template',
        'routes/web.php' => 'routes/web.php.template',
        'routes/api.php' => 'routes/api.php.template',
        'routes/default.php' => 'routes/default.php.template',
        'app/Http/Controllers/HomeController.php' => 'controllers/HomeController.php.template',
        'resources/views/layouts/default.plug.php' => 'views/layouts/default.plug.php.template',
        'resources/views/home/index.plug.php' => 'views/home/index.plug.php.template',
        '.env' => 'env.template',
        'composer.json' => 'composer.json.template',
    ],

    // Database types supported
    'database_types' => [
        'mysql' => [
            'name' => 'MySQL',
            'default_port' => 3306,
            'driver' => 'pdo_mysql',
        ],
        'pgsql' => [
            'name' => 'PostgreSQL',
            'default_port' => 5432,
            'driver' => 'pdo_pgsql',
        ],
        'sqlite' => [
            'name' => 'SQLite',
            'default_port' => null,
            'driver' => 'pdo_sqlite',
        ],
    ],

    // Timezones
    'timezones' => [
        'UTC' => 'UTC (Coordinated Universal Time)',
        'Africa/Lagos' => 'Africa/Lagos (WAT)',
        'Africa/Johannesburg' => 'Africa/Johannesburg (SAST)',
        'Africa/Cairo' => 'Africa/Cairo (EET)',
        'Africa/Nairobi' => 'Africa/Nairobi (EAT)',
        'America/New_York' => 'America/New York (EST)',
        'America/Chicago' => 'America/Chicago (CST)',
        'America/Denver' => 'America/Denver (MST)',
        'America/Los_Angeles' => 'America/Los Angeles (PST)',
        'America/Sao_Paulo' => 'America/Sao Paulo (BRT)',
        'Asia/Dubai' => 'Asia/Dubai (GST)',
        'Asia/Kolkata' => 'Asia/Kolkata (IST)',
        'Asia/Singapore' => 'Asia/Singapore (SGT)',
        'Asia/Tokyo' => 'Asia/Tokyo (JST)',
        'Asia/Shanghai' => 'Asia/Shanghai (CST)',
        'Europe/London' => 'Europe/London (GMT)',
        'Europe/Paris' => 'Europe/Paris (CET)',
        'Europe/Berlin' => 'Europe/Berlin (CET)',
        'Europe/Moscow' => 'Europe/Moscow (MSK)',
        'Australia/Sydney' => 'Australia/Sydney (AEST)',
        'Pacific/Auckland' => 'Pacific/Auckland (NZST)',
    ],
];
