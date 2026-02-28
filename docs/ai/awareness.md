# AI-Aware Core (Fully Observable Metadata)

Plugs is a next-generation "AI-Aware" framework. It exposes its internal state, architecture, and execution flow as structured metadata, allowing AI agents to analyze, optimize, and refactor your application with high precision.

## Structured Metadata

Through the `AIManager`, you can access a complete snapshot of the application context:

```php
$ai = app('ai');
$context = $ai->getAppContext();
```

The context includes the following structured data:

### 1. Route Map

A full list of all registered routes, including:

- HTTP Methods
- URIs & Patterns
- Middleware stack
- Handler type (Controller, Closure, etc.)

### 2. Service Container Graph

A map of the application's dependency injection state:

- All active bindings (Abstract → Concrete)
- Shared singletons and scoped instances
- Contextual binding overrides
- Service aliases

### 3. Database Schema

Deep reflection on the connected database:

- Table names
- Column names and types
- Primary and foreign keys
- Indexes

### 4. Event Timeline

A sequential record of the current request lifecycle:

- Event names
- Time offsets (ms) from request start
- Event-specific metadata (e.g., SQL queries executed)

## Using Awareness for AI Optimizations

With this metadata, an AI module can provide intelligent feedback:

| Scenario               | AI capability                                            | Source Metadata            |
| ---------------------- | -------------------------------------------------------- | -------------------------- |
| **N+1 Detection**      | Identify repeated identical queries in a single request. | `timeline` (QueryExecuted) |
| **Route Optimization** | Suggest merging middleware or refactoring handlers.      | `routes`                   |
| **DTO Generation**     | Auto-generate Data Transfer Objects based on DB tables.  | `database`                 |
| **Refactoring**        | Recommend moving logic from controllers to services.     | `container` + `routes`     |

## Event Timeline Registry

Events are automatically recorded when the `AiModule` is booted. You can manually inspect the timeline:

```php
use Plugs\AI\Metadata\EventTimelineRegistry;

$timeline = EventTimelineRegistry::getTimeline();
```

## Privacy & Security

The AI-Aware Core captures structural information, not user data. Sensitive configuration values and request payloads are filtered or masked before being exposed to the AI driver to ensure security.
