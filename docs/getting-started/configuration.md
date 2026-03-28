# Configuration

Plugs follows a **Zero-Config** philosophy. By using "Smart Defaults" and internal auto-wiring, you can run a full-featured application with just a `.env` file.

---

## 1. Zero-Config Philosophy

The framework maintains internal defaults for all core components (Mail, Cache, Database, etc.). You **do not** need to create files in `config/` unless you want deep customization.

### Native .env Integration

Most common settings are mapped directly to environment variables:

| Feature | .env Key | Default |
| --- | --- | --- |
| **App Name** | `APP_NAME` | `Plugs` |
| **Environment** | `APP_ENV` | `local` |
| **Debug Mode** | `APP_DEBUG` | `true` |
| **Cache Driver** | `CACHE_DRIVER` | `file` |
| **Session Driver** | `SESSION_DRIVER` | `file` |
| **Database** | `DB_CONNECTION` | `mysql` |
| **Payments** | `DEFAULT_PAYMENT_PLATFORM` | `paystack` |
| **Payouts** | `DEFAULT_PAYOUT_PLATFORM` | `paystack` |
| **Notifications** | `SMS_SID`, `SMS_TOKEN` | `(empty)` |
| **Uploader** | `UPLOADER_MAX_SIZE` | `10485760` (10MB) |
| **CSS Engine** | `CSS_ENABLED` | `true` |
| **CSS Output** | `CSS_OUTPUT` | `public/build/plugs.css` |
| **CSS Scan Paths** | `CSS_SCAN_PATHS` | `resources/views,modules,app/Components` |

> [!TIP]
> **Auto Dark Mode**: Plugs includes a "Smart Theme" engine. By adding the `auto-dark` class to an element, the framework automatically generates dark mode variants for you. [Learn more](../views/css-engine.md#auto-dark-mode-engine).

---

## 2. Using the `config()` Helper

You can access configuration values from anywhere in your application using the `config()` helper function.

```php
// Get a value with a fallback
$appName = config('app.name', 'Default Name');

// Get nested values using dot notation
$dbHost = config('database.connections.mysql.host');
```

---

## 3. Customizing Configuration

If you need to override a default that isn't available via `.env`, you can "publish" the configuration file or create it manually in the `config/` directory.

### Publishing Config Files

Use the CLI to export default settings to your `config/` directory for customization:

```bash
# Export all configuration files
php theplugs config:publish --all

# Export a specific module (e.g., database)
php theplugs config:publish database
```

### Manual Overrides

You only need to return the keys you wish to change. The framework will **recursively merge** your settings into the defaults.

**Example: `config/mail.php`**
```php
<?php
return [
    'from' => [
        'address' => 'hello@example.com',
        'name' => 'My App',
    ],
];
```

---

## 4. Production Optimization

In production, parsing `.env` files and merging arrays on every request can be expensive. Plugs allows you to compile all configuration into a single cached file.

```bash
php theplugs config:cache
```

> [!IMPORTANT]
> Once configuration is cached, changes to `.env` or `config/` files will not take effect until the cache is cleared using `php theplugs config:clear`.

---

## Next Steps

Explore the [Request Lifecycle](../architecture/lifecycle.md) to see how configuration is loaded at boot time.
