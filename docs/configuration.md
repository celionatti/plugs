# Configuration System

Plugs features a **Zero-Config** philosophy. By using "Smart Defaults" and internal auto-wiring, you can run a full-featured application with just a `.env` file.

## 1. Zero-Config & Smart Defaults

The framework maintains internal defaults for all core components (Mail, Cache, Database, etc.) in `Plugs\Config\DefaultConfig`. You **do not** need to create files in `config/` unless you want deep customization.

### Native .env Integration

Most common settings are mapped directly to environment variables:

| Feature         | Key                | Default |
| --------------- | ------------------ | ------- |
| **Cache**       | `CACHE_DEFAULT`    | `file`  |
| **Session**     | `SESSION_DRIVER`   | `file`  |
| **Queue**       | `QUEUE_CONNECTION` | `sync`  |
| **Event Bus**   | `EVENT_BUS_DRIVER` | `sync`  |
| **Security**    | `SECURITY_SHIELD`  | `true`  |
| **Views**       | `VIEW_STREAMING`   | `false` |
| **Views Flush** | `VIEW_AUTO_FLUSH`  | `50`    |

### Redis Cache Setup

For high-performance distributed caching, Plugs supports Redis through two methods:

- **Option A (Native)**: Use the `phpredis` extension (`CACHE_DRIVER=redis`).
- **Option B (Composer)**: Install `predis/predis` (`CACHE_DRIVER=predis`).

1. **Configure `.env`**:
   ```env
   CACHE_DRIVER=redis # or predis
   REDIS_HOST=127.0.0.1
   ```
2. **Registration**: In `AppServiceProvider.php`, use `Cache::extend('redis', fn() => new RedisCacheDriver())` or `Cache::extend('predis', fn() => new PredisCacheDriver())`.

See full [Caching Feature Guide](./features/caching.md) for more details.

## 2. Auto-Discovery

Plugs automatically discovers and registers your application's Service Providers.

- **Location**: All classes in `app/Providers/` are scanned and loaded automatically.
- **Customization**: You no longer need to manually list providers in an `app.php` config file.

## 3. Deployment Optimization

For production, you can cache your configuration to eliminate filesystem hits and `.env` parsing overhead:

```bash
# Compile defaults and .env into a single cache file
php theplugs config:cache
```

You can also use the all-in-one optimization command:

```bash
# Optimizes routes, container, and configuration
php theplugs optimize
```

## 4. Exporting Configuration

While the framework works perfectly with zero configuration, you might want to have the files handy for more granular customization. Use the `config:publish` command to export the default settings to your `config/` directory.

### Publish Specific File

```bash
# Export only the database configuration
php theplugs config:publish database
```

### Publish All Files

```bash
# Export all available framework configurations
php theplugs config:publish --all
```

The command will automatically format the defaults into standard PHP arrays using modern `[]` syntax.

### Available Configuration Modules

You can publish any of the following configuration modules:

- `ai`: Artificial Intelligence provider settings (OpenAI, Gemini, etc.)
- `app`: Core application settings (name, env, providers, paths)
- `assets`: Asset pipeline settings (minification, versioning)
- `auth`: Authentication settings (user model, tables, OAuth)
- `billing`: Tax and fee calculations
- `cache`: Cache drivers and path settings
- `database`: DB connections, pooling, and load balancing
- `filesystems`: Storage disks (local, public, s3)
- `hash`: Password hashing algorithms (Argon2, Bcrypt)
- `logging`: Log channels and handlers
- `mail`: SMTP and mail driver settings
- `middleware`: Global and group middleware aliases
- `opcache`: PHP OpCache optimization settings
- `queue`: Queue connections (sync, database, redis)
- `security`: CSRF, CSP, CORS, and Security Shield settings
- `seo`: Default meta tags, titles, and robots settings
- `services`: Third-party service credentials (GitHub, Google)
- `view`: Template engine and view path settings

## 5. Manual Overrides

To override a default that isn't in `.env`, create a corresponding file in `config/` (or publish it using the command above). The framework will **recursively merge** your settings into the defaults.

**Example: `config/mail.php`**

```php
<?php
return [
    'from' => [
        'address' => 'noreply@yourdomain.com',
        'name' => 'System'
    ]
];
```

_Note: You only need to return the keys you wish to change._
