# Smart Relationship Engine

The Smart Relationship Engine goes beyond traditional lazy/eager loading by adding intelligent monitoring, predictive loading, and deep performance visibility.

## Features

### 1. Automatic N+1 Detection

The engine automatically monitors relationship access patterns. In development mode, if it detects multiple identical lazy loads on models within the same collection context, it identifies a potential N+1 performance issue.

### 2. Predictive Loading

Instead of just warning about N+1 queries, the engine can proactively resolve them. When a pattern is detected (e.g., after 3 identical lazy loads), the engine triggers **Predictive Loading**, which eager-loads the relationship for the remaining models in the collection in a single batch query.

### 3. Query Tree Visualization

Developers can visualize the hierarchical execution of queries. This makes it easy to see which parent query triggered specific child/lazy queries.

## Usage

### Enabling Monitoring

Monitoring is typically enabled in development environments:

```php
use Plugs\Database\RelationMonitor;

RelationMonitor::getInstance()->enable(true);
```

### Visualizing the Query Tree

You can render a hierarchical tree of executed queries at any point:

```php
use Plugs\Database\Utils\QueryTreeVisualizer;
use Plugs\Database\RelationMonitor;

$queries = RelationMonitor::getInstance()->getQueries();
echo QueryTreeVisualizer::visualize($queries);
```

### Example Tree Output

```text
↳ [4e6b8f4b] SELECT * FROM `users` LIMIT 5 (0.01ms)
  ↳ [0b3387d1] SELECT * FROM `profiles` WHERE `user_id` = ? LIMIT 1 (0.02ms)
  ↳ [914fb293] SELECT * FROM `profiles` WHERE `user_id` = ? LIMIT 1 (0.01ms)
↳ [b790d54d] SELECT * FROM `profiles` WHERE `user_id` IN (3, 4, 5) (0.14ms)
```

## How it Works

- **Context Awareness**: Models are aware of the `Collection` they belong to and the `Query` ID that fetched them.
- **Hierarchical Tracking**: The `RelationMonitor` maintains a stack of query contexts to link parent and child queries accurately.
- **Batch Optimization**: When predictive loading is triggered, it uses a `WHERE IN` clause to fetch all missing values for the pending models.
