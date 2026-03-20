# Modular First Architecture

Plugs is built on a **Modular First** philosophy. The core engine is kept as minimal as possible, with all high-level features (Session, Database, AI, etc.) implemented as independent, plug-and-play **Core Modules**.

---

## 1. The Core Philosophy

In traditional frameworks, the core often has hardcoded dependencies on various services. In Plugs:
- The core only knows how to **register** and **boot** modules.
- **Stateless by Choice**: Disable the `Session` module and your app becomes completely stateless effortlessly.
- **Context-Aware**: Modules only boot when their specific context (Web, CLI, API) is active.

## 2. The Module Interface

Every core module must implement the `Plugs\Module\ModuleInterface`. This ensures a consistent lifecycle across the framework.

```php
interface ModuleInterface
{
    public function getName(): string;
    public function shouldBoot(ContextType $context): bool;
    public function register(Container $container): void;
    public function boot(Plugs $app): void;
}
```

- **`register()`**: Bind services into the DI Container.
- **`boot()`**: Execute logic after all other modules have registered their services.

---

## 3. Managing Modules

### Disabling Core Modules
You can disable any core module before the framework boots, usually in `public/index.php`.

```php
use Plugs\Framework;

// Disable the built-in AI module if not needed
Framework::disableModule('Ai');

// Make the app completely stateless
Framework::disableModule('Session');
```

### Checking Module Status
Check if a feature is available before using it:

```php
if (Framework::isModuleEnabled('Database')) {
    // Database logic here
}
```

---

## 4. Default Core Modules

Plugs comes with these essential modules enabled by default:

| Module | Purpose | Context |
| --- | --- | --- |
| `Database` | ORM and Query Builder support | All |
| `Session` | State management and cookies | Web |
| `View` | Template engine and components | Web |
| `Ai` | LLM and AI Agent integration | All |
| `Queue` | Background job processing | All |

---

## Next Steps
Now that you understand the framework's internal modularity, learn how to organize your own application code using [Feature Modules](./feature-modules.md).
