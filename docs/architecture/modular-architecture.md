# Modular First Architecture

Plugs is built with a **Modular First** philosophy. This means that the core engine is kept as minimal as possible, and all high-level features (Session, Database, Auth, AI, etc.) are implemented as independent, plug-and-play **Modules**.

## Core Concept

In a traditional framework, the core often has hardcoded dependencies on various services. In Plugs, the core only knows how to boot modules. This allows you to:

- **Disable any feature**: Instantly turn off sessions, storage, or even the database.
- **Go Stateless**: Disable the `Session` module and your app becomes completely stateless.
- **Reduce Footprint**: Only boot the modules your application actually needs for its current context (Web, API, CLI, etc.).

## The Module Interface

Every module must implement the `Plugs\Module\ModuleInterface`:

```php
namespace Plugs\Module;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Plugs;

interface ModuleInterface
{
    public function getName(): string;
    public function shouldBoot(ContextType $context): bool;
    public function register(Container $container): void;
    public function boot(Plugs $app): void;
}
```

## Usage

### Dynamically Disabling Modules

You can disable any core or custom module before the framework boots. This is typically done in your entry point (e.g., `public/index.php` or a custom bootstrap file).

```php
use Plugs\Framework;

// Make the application completely stateless
Framework::disableModule('Session');

// Disable AI features if not needed
Framework::disableModule('Ai');
```

### Checking Module Status

```php
if (Framework::isModuleEnabled('Database')) {
    // Perform database operations
}
```

## Sample: Custom Module

Creating a custom module is straightforward.

1. **Create the Module Class**:

```php
namespace App\Modules;

use Plugs\Module\ModuleInterface;
use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Plugs;

class MyCustomModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'CustomAnalytics';
    }

    public function shouldBoot(ContextType $context): bool
    {
        // Only boot in Web context
        return $context === ContextType::Web;
    }

    public function register(Container $container): void
    {
        $container->singleton('analytics', function() {
            return new AnalyticsService();
        });
    }

    public function boot(Plugs $app): void
    {
        // Initialization logic after all modules are registered
    }
}
```

2. **Register the Module**:

```php
use Plugs\Framework;
use App\Modules\MyCustomModule;

Framework::addModule(MyCustomModule::class);
```

## Module Generator (CLI)

You can easily scaffold a new custom module using the `theplugs` CLI tool. This will create a module class in `app/Modules/`.

```bash
# Create a standard module
php theplugs make:module MyModule

# Create a web-only module
php theplugs make:module MyModule --web

# Create an API-only module
php theplugs make:module MyModule --api
```

### Registry & Booting

After creating your module, you must register it in your application's bootstrap process (e.g., `src/Bootstrap/Bootstrapper.php` or `bootstrap/boot.php`):

```php
use Plugs\Module\ModuleManager;
use App\Modules\MyModule;

ModuleManager::getInstance()->addModule(MyModule::class);
```

## Core Modules List

Below are some of the core modules you can interact with:

| Module Name  | Description                         | Default Context |
| ------------ | ----------------------------------- | --------------- |
| `Session`    | Session management and cookies      | Web             |
| `Database`   | DB Connection and Model support     | All             |
| `Auth`       | User authentication system          | All             |
| `Cache`      | Caching and Rate Limiting           | All             |
| `Log`        | System logging                      | All             |
| `View`       | Template engine and view management | Web             |
| `Ai`         | AI Integration and LLM support      | All             |
| `Encryption` | Security and Hashing                | All             |
| `Events`     | Event Dispatcher                    | All             |

---

> [!TIP]
> Use `Framework::disableModule('Session');` when building high-performance APIs to eliminate session overhead entirely.
