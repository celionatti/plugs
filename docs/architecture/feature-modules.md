# Feature Modules

**Feature Modules** are the recommended way to organize your application code in Plugs. While Core Modules handle framework-level services, Feature Modules encapsulate your **application-specific** logic (Controllers, Models, Routes, Migrations) into self-contained directories.

---

## 1. Why Use Feature Modules?

As applications grow, a flat `app/` folder can become cluttered. Feature Modules allow you to group related code by domain:

```text
modules/
  Auth/
    Controllers/
    Models/User.php
    Routes/web.php
  Blog/
    Controllers/
    Models/Post.php
    Routes/web.php
    Migrations/
```

- **Isolated**: Each module is self-contained and easy to test.
- **Auto-Discovered**: Drop a new module into the `modules/` folder, and it's automatically registered.
- **Reusable**: Easily move an entire feature between projects.

---

## 2. Creating a Module

Use the CLI to scaffold a new feature module:

```bash
# General purpose module
php theplugs make:feature-module Shop

# Specialized Auth module (Pre-configured with User model and migrations)
php theplugs make:auth-module Auth
```

---

## 3. Module Structure

A standard feature module follows this layout:

- `Controllers/`: Request handlers.
- `Models/`: Database models unique to this feature.
- `Routes/`: `web.php` and `api.php` files (automatically prefixed).
- `Migrations/`: Database schema changes for this module.
- `Views/`: Module-specific templates (e.g., `view('shop::index')`).

---

## 4. Routing and Prefixes

By default, module routes are automatically prefixed with the lowercase name of the module.

- `modules/Shop/Routes/web.php` → `GET /shop/...`
- `modules/Shop/Routes/api.php` → `GET /api/shop/...`

### Customizing the Prefix
You can customize the prefix or middleware by creating a `[Name]Module.php` provider in the module root.

```php
class ShopModule extends AbstractFeatureModule
{
    public function getRoutePrefix(): string
    {
        return 'store'; // Changes /shop to /store
    }

    public function getMiddleware(): array
    {
        return ['web', 'auth']; // Apply middleware to all module routes
    }
}
```

---

## Next Steps
Explore the [Basics](../basics/routing.md) to learn more about how routing works inside and outside of modules.
