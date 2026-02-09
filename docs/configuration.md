# Configuration System

The Plugs Framework uses a "Smart Defaults" configuration system. This means you don't need to maintain a folder full of configuration files. Most settings are handled automatically with sensible defaults, and environment-specific values are managed via your `.env` file.

## 1. Environment Variables (.env)

The primary way to configure your application is through the `.env` file in the root directory.

### Common Settings

**Application**
- `APP_NAME`: The name of your application.
- `APP_ENV`: `local` or `production`.
- `APP_DEBUG`: `true` to show errors, `false` to hide them.
- `APP_URL`: The base URL of your app.

**Database**
- `DB_CONNECTION`: `mysql`, `pgsql`, or `sqlite`.
- `DB_HOST`: Database host (e.g., `127.0.0.1`).
- `DB_PORT`: Database port (e.g., `3306`).
- `DB_DATABASE`: Database name.
- `DB_USERNAME`: Database user.
- `DB_PASSWORD`: Database password.

**Mail**
- `MAIL_MAILER`: `smtp`.
- `MAIL_HOST`: SMTP host (e.g., `smtp.mailtrap.io`).
- `MAIL_PORT`: SMTP port.
- `MAIL_USERNAME`: SMTP username.
- `MAIL_PASSWORD`: SMTP password.
- `MAIL_FROM_ADDRESS`: Default sender address.

**Security**
- `CSRF_ENABLED`: `true` or `false`.
- `SECURITY_SHIELD`: `true` to enable the security shield middleware.

## 2. Default Configuration

If a configuration file does not exist in the `config/` directory, the framework loads default values from `Plugs\Config\DefaultConfig`.

Key defaults include:
- **Cache**: Uses `file` driver by default.
- **Session**: Uses `file` driver with a 120-minute lifetime.
- **Logging**: Logs to `storage/logs/plugs.log` using the `file` channel.
- **Queue**: Uses `sync` (synchronous) driver by default.

## 3. Customizing Configuration

If you need to override a default setting that isn't exposed in `.env`, or if you need to add custom configuration logic, you can create a PHP file in the `config/` directory.

The framework will **merge** your custom file with the defaults. You only need to return the specific keys you want to override.

**Example: Customizing Mail**
Create `config/mail.php`:

```php
<?php

return [
    'from' => [
        'address' => 'hello@mydomain.com',
        'name' => 'Support Team',
    ],
    // All other settings (driver, host, etc.) are still loaded from defaults/.env
];
```

## 4. Service Providers

Service Providers in `app/Providers` are **automatically discovered** and registered. You do not need to manually add them to a configuration file.

To create a new provider:
```bash
php plg make:provider MyServiceProvider
```

It will be automatically loaded on the next request.
