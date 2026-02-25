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

## 2. Auto-Discovery

Plugs automatically discovers and registers your application's Service Providers.

- **Location**: All classes in `app/Providers/` are scanned and loaded automatically.
- **Customization**: You no longer need to manually list providers in an `app.php` config file.

## 3. Deployment Optimization

For production, you can cache your configuration to eliminate filesystem hits:

```bash
# Compile defaults and .env into a single cache file
php theplugs optimize
```

## 4. Manual Overrides

To override a default that isn't in `.env`, create a corresponding file in `config/`. The framework will **recursively merge** your settings into the defaults.

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
