# Feature Modules

Plugs supports a **Feature Module** architecture that lets you organize your application into self-contained, auto-discovered modules. Each module acts like a **mini-app** with its own Controllers, Models, Routes, and Migrations — all automatically wired into the framework.

> [!NOTE]
> Feature Modules are different from the core **Modular First** system (`src/Module/`). Core modules handle framework-level services (Session, Database, Cache). Feature Modules organize your **application** code into reusable, isolated features.

## Why Feature Modules?

As applications grow beyond a handful of controllers and models, the traditional flat structure breaks down:

```
app/
  Http/Controllers/
    AuthController.php
    CartController.php
    ProductController.php
    OrderController.php
    ReviewController.php
    SettingsController.php      ← Dozens of controllers in one folder
  Models/
    User.php
    Cart.php
    Product.php
    ...                         ← Hard to know what belongs to what
```

Feature Modules solve this by grouping related files together. Each module is isolated, testable, and can even be extracted into its own package later:

```
modules/
  Auth/
    Controllers/AuthController.php
    Models/User.php
    Routes/web.php
    Migrations/

  Store/
    Controllers/ProductController.php
    Controllers/CartController.php
    Models/Product.php
    Models/Cart.php
    Routes/web.php
    Routes/api.php
    Migrations/
```

### Benefits

- **Organized** — Related code lives together, not scattered across `app/`
- **Self-contained** — Each module has its own routes, models, controllers, and migrations
- **Auto-discovered** — Drop a module in `modules/` and it just works
- **Isolated** — Modules can be enabled, disabled, or removed without touching other code
- **Scalable** — Large teams can work on separate modules without conflicts
- **Reusable** — Extract a module and reuse it across projects

---

## Quick Start

### 1. Scaffold a Module

```bash
php theplugs make:feature-module Auth
```

This creates the full directory structure:

```
modules/
  Auth/
    AuthModule.php              ← Service provider
    Controllers/
    Models/
    Routes/
      web.php                   ← Web routes (→ /auth/...)
      api.php                   ← API routes (→ /api/auth/...)
    Migrations/
```

### 2. Add a Controller

Create `modules/Auth/Controllers/LoginController.php`:

```php
<?php

namespace Modules\Auth\Controllers;

use Plugs\Http\ResponseFactory;

class LoginController
{
    public function showForm()
    {
        return view('auth.login');
    }

    public function login($request)
    {
        // Handle login logic
        return ResponseFactory::redirect('/dashboard');
    }
}
```

### 3. Define Routes

Edit `modules/Auth/Routes/web.php`:

```php
<?php

use Plugs\Facades\Route;
use Modules\Auth\Controllers\LoginController;

Route::get('/login', [LoginController::class, 'showForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
```

These routes are automatically prefixed with `/auth`, so:
- `GET /auth/login` → shows the login form
- `POST /auth/login` → handles login
- `POST /auth/logout` → handles logout

### 4. Add a Model

Create `modules/Auth/Models/User.php`:

```php
<?php

namespace Modules\Auth\Models;

use Plugs\Base\Model\PlugModel;

class User extends PlugModel
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email', 'password'];
}
```

### 5. Add a Migration

Create `modules/Auth/Migrations/2026_01_01_000001_create_users_table.php` — the migration will be picked up automatically by all `migrate` commands.

### 6. Run Autoload

```bash
composer dump-autoload
```

That's it. Your `Auth` module is live. No manual registration needed.

---

## Module Structure

Every feature module lives inside the `modules/` directory at the project root. The convention is:

```
modules/{ModuleName}/
  {ModuleName}Module.php       ← Optional service provider
  Controllers/                 ← PSR-4: Modules\{Name}\Controllers
  Models/                      ← PSR-4: Modules\{Name}\Models
  Routes/
    web.php                    ← Routes prefixed with /{name}/...
    api.php                    ← Routes prefixed with /api/{name}/...
  Migrations/                  ← Auto-scanned by migrate commands
```

| Directory     | Purpose                                       | Namespace                         |
|---------------|-----------------------------------------------|-----------------------------------|
| `Controllers/`| Request handlers                               | `Modules\{Name}\Controllers`     |
| `Models/`     | Database models                                | `Modules\{Name}\Models`          |
| `Routes/`     | Route definitions (`web.php`, `api.php`)       | —                                 |
| `Migrations/` | Database migrations                            | —                                 |

> [!TIP]
> You can add any additional directories your module needs — `Services/`, `Events/`, `Middleware/`, `Views/`, etc. Only the directories listed above receive special framework treatment.

---

## The Module Service Provider

Each module can optionally have a `{Name}Module.php` file that extends `AbstractFeatureModule`. This gives you control over:

- Route prefix and middleware
- Container bindings
- Boot-time logic

```php
<?php

namespace Modules\Store;

use Plugs\FeatureModule\AbstractFeatureModule;
use Plugs\Container\Container;
use Plugs\Plugs;

class StoreModule extends AbstractFeatureModule
{
    public function getName(): string
    {
        return 'Store';
    }

    /**
     * Custom URL prefix (default: lowercase module name).
     * Return '' for no prefix.
     */
    public function getRoutePrefix(): string
    {
        return 'shop'; // Routes at /shop/... instead of /store/...
    }

    /**
     * Middleware applied to all routes in this module.
     */
    public function getMiddleware(): array
    {
        return ['web', 'auth'];
    }

    /**
     * Register services in the container.
     */
    public function register(Container $container): void
    {
        $container->singleton('cart', fn() => new Services\CartService());
    }

    /**
     * Boot-time logic (runs after all modules are registered).
     */
    public function boot(Plugs $app): void
    {
        // Register event listeners, etc.
    }
}
```

If no `{Name}Module.php` exists, the framework creates a **Convention Module** automatically — using the module name as the prefix and applying no middleware.

---

## View Support

Each feature module can have its own `Views/` directory. These views are automatically registered with a **namespace** matching the lowercase name of the module.

### Referencing Module Views

You can render module views using the `namespace::view` syntax:

```php
// In a controller within 'Store' module
public function index() {
    return view('store::products.index');
}
```

This will look for the template at `modules/Store/Views/products/index.plug.php`.

### Module Layouts

Modules can define their own layouts. Simply create the layout in the `Views/layouts/` directory of your module:

```blade
{{-- modules/Admin/Views/layouts/admin.plug.php --}}
@extends('layouts.app') {{-- Can extend global layout --}}

@section('content')
    <div class="admin-sidebar">...</div>
    <div class="admin-main">
        @yield('admin-content')
    </div>
@endsection
```

Then extend it in your module views:

```blade
@extends('admin::layouts.admin')

@section('admin-content')
    <h1>Dashboard</h1>
@endsection
```

### Module Components

Components inside a module's `Views/components/` directory are also namespaced:

```html
<x-store::product-card :product="$product" />
```

---

## Configuration

Feature modules are designed to be **Zero-Config**. By default, they are auto-discovered and loaded automatically using internal defaults.

If you need to customize the behavior, you can publish a configuration file:

```bash
php theplugs config:publish modules
```

The configuration options include:

```php
return [
    // Auto-discover all modules in modules/ directory (default: true)
    'auto_discover' => true,

    // Explicitly enable specific modules (used when auto_discover is false)
    'enabled' => [],

    // Disable specific modules (overrides auto_discover)
    'disabled' => [],

    // Per-module settings, accessible via config('modules.settings.ModuleName')
    'settings' => [],
];
```

### Internal Power
The framework includes these defaults in `Plugs\Config\DefaultConfig`. If a `config/modules.php` file does not exist, the framework seamlessly falls back to these internal values. This keeps your application folder clean and focuses your attention purely on your code.

---

## Route Loading

Module routes are loaded automatically after the application's main `routes/web.php` and `routes/api.php`.

### Web Routes

Each module's `Routes/web.php` is loaded inside a route group with:

| Attribute    | Value                                |
|------------- |--------------------------------------|
| `prefix`     | `strtolower($moduleName)` or custom  |
| `namespace`  | `Modules\{Name}\Controllers`        |
| `as`         | `{name}.` (route name prefix)        |
| `middleware`  | From `getMiddleware()` if set       |

So a route defined as `Route::get('/dashboard', ...)` in **Auth** module becomes `/auth/dashboard` with name `auth.dashboard`.

### API Routes

Each module's `Routes/api.php` is loaded inside the global `/api` prefix group, with the module prefix added on top. So the final URL is `/api/{module}/...`.

### Removing the Prefix

If you want a module's routes at the root level (no prefix), override `getRoutePrefix()`:

```php
public function getRoutePrefix(): string
{
    return ''; // Routes at / instead of /auth/
}
```

---

## Migrations

All `migrate` commands automatically scan module `Migrations/` directories alongside the main `database/Migrations/` folder:

```bash
# Runs migrations from database/Migrations/ + all modules/*/Migrations/
php theplugs migrate

# Shows status from all paths
php theplugs migrate:status

# Rollback, reset, fresh, validate — all module-aware
php theplugs migrate:rollback
php theplugs migrate:fresh
```

Migrations from all modules are sorted together by filename and follow the same dependency-resolution logic as standard migrations.

---

## CLI Commands

### Scaffolding

```bash
# Full module with routes and migrations
php theplugs make:feature-module Auth

# Skip route files
php theplugs make:feature-module Auth --no-routes

# Skip migrations directory
php theplugs make:feature-module Auth --no-migrations

# Overwrite existing module
php theplugs make:feature-module Auth --force
```

Alias: `g:fmod` → `make:feature-module`

---

## Autoloading

Feature modules use the `Modules\\` PSR-4 namespace, mapped to the `modules/` directory in `composer.json`:

```json
{
  "autoload-dev": {
    "psr-4": {
      "Modules\\": "modules/"
    }
  }
}
```

After creating a new module, always run:

```bash
composer dump-autoload
```

> [!IMPORTANT]
> If you plan to distribute modules as Composer packages, move the `Modules\\` entry from `autoload-dev` to `autoload` in your production `composer.json`.

---

## Boot Lifecycle

Feature modules follow a strict two-phase lifecycle, similar to core modules:

```
1. Bootstrapper boots core modules (Database, Session, Cache...)
2. FeatureModuleManager discovers feature modules
3. Phase 1: register() — each module registers container bindings
4. Phase 2: boot() — each module runs boot logic
5. Kernel loads routes (including module routes)
6. Application handles request
```

This ensures that all bindings are available before any module's `boot()` method runs.

---

## Example: Multi-Module Application

Here's a real-world layout for an e-commerce application:

```
modules/
  Auth/
    AuthModule.php
    Controllers/
      LoginController.php
      RegisterController.php
    Models/User.php
    Routes/web.php
    Migrations/

  Store/
    StoreModule.php
    Controllers/
      ProductController.php
      CartController.php
      CheckoutController.php
    ├── Models/             (Module models)
    ├── Views/              (Module views)
    ├── Routes/             (Module routes)
    │   ├── web.php
    │   └── api.php
    └── Migrations/         (Module migrations)

  Admin/
    AdminModule.php
    Controllers/
      DashboardController.php
      SettingsController.php
    Middleware/
      AdminMiddleware.php
    Routes/web.php
```

Each module is completely self-contained. The Auth module handles login/registration. The Store module handles products and checkout. The Admin module handles the back office. They can share models via namespace imports when needed.

---

> [!TIP]
> Start with Feature Modules early in your project — even if you only have one module. It's much easier to add modules to an organized codebase than to refactor a flat structure later.
