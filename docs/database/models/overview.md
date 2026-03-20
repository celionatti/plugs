# PlugModel: Overview

The `PlugModel` is the heart of the Plugs ORM. Every model in your application extends `Plugs\Base\Model\PlugModel` and maps to a single database table. It provides an Eloquent-inspired API for CRUD operations, relationships, validation, and much more.

## Generating a Model

```bash
php theplugs make:model Product
```

### Generator Flags

| Flag           | Short | Description                                         |
| -------------- | ----- | --------------------------------------------------- |
| `--migration`  | `-m`  | Scaffold a table migration                          |
| `--controller` | `-c`  | Scaffold a resource controller                      |
| `--factory`    | `-f`  | Scaffold a model factory                            |
| `--all`        | `-a`  | Generate migration, controller, factory, and seeder |
| `--schema`     |       | Use the Typed Schema pattern (recommended)          |

```bash
# Generate model + migration + schema
php theplugs make:model Post --schema --migration
```

---

## Minimal Model

```php
namespace App\Models;

use Plugs\Base\Model\PlugModel;

class Product extends PlugModel
{
    protected $table = 'products';

    protected $fillable = ['name', 'price', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'price'     => 'float',
        'password'  => 'encrypted', // Secure Encrypt-then-MAC
    ];
}
```

### Encryption Security
Attributes cast as `encrypted` use **Encrypt-then-MAC** (AES-256-CBC + HMAC-SHA256). This ensures data integrity and prevents padding oracle attacks.

> [!TIP]
> If you omit `$table`, Plugs automatically derives it from the class name using **snake_case + plural** convention — `Product` becomes `products`, `BlogPost` becomes `blog_posts`.

---

## Primary Key

The default primary key is `id`. Override it when needed:

```php
protected $primaryKey = 'uuid';
```

Access the key:

```php
$model->getKey();        // value of the primary key
$model->getKeyName();    // 'id' (or your override)
```

---

## CRUD Operations

### Create

```php
// Via static create
$user = User::create(['name' => 'Emma', 'email' => 'emma@example.com']);

// Via constructor + save
$user = new User(['name' => 'Emma']);
$user->save();
```

### Read

```php
$user  = User::find(1);
$users = User::where('active', '=', 1)->get();
$first = User::where('email', '=', 'emma@example.com')->first();
```

### Update

```php
$user->name = 'Jane';
$user->save();

// Or with fill
$user->fill(['name' => 'Jane']);
$user->save();
```

### Delete

```php
$user->delete();

// Destroy by ID(s)
User::destroy(1);
User::destroy(1, 2, 3);
User::destroy([1, 2, 3]);
```

---

## Upsert Helpers

```php
// Find or create
$user = User::firstOrCreate(['email' => 'emma@example.com']);

// Update existing or create new
$user = User::updateOrCreate(
    ['email' => 'emma@example.com'],   // search attributes
    ['name' => 'Emma Watson']          // values to set
);
```

---

## Refresh

Re-fetch the model from the database:

```php
$user->refresh();
```

---

## Existence Check

```php
$user->exists();   // true if the model was loaded from or saved to the database
```

---

## Dirty Attributes

```php
$user->isDirty();          // any attribute changed?
$user->isDirty('name');    // specific attribute changed?
$user->getOriginal();      // all original values
$user->getOriginal('name');
```

---

## Raw Queries

When the query builder isn't enough:

```php
// Returns a Collection of hydrated models
$users = User::raw('SELECT * FROM users WHERE votes > ?', [100]);

// Returns a boolean (row count > 0)
User::statement('UPDATE users SET votes = 0 WHERE id = ?', [1]);
```

---

## Transactions

```php
// Closure-based (recommended)
User::transaction(function () {
    User::create(['name' => 'A']);
    User::create(['name' => 'B']);
});

// Manual
User::beginTransaction();
try {
    User::create(['name' => 'A']);
    User::commit();
} catch (\Throwable $e) {
    User::rollBack();
    throw $e;
}
```

Nested transactions use database **savepoints** automatically.

---

## Caching & Query Logging

### Query Logging

```php
User::enableQueryLog();

$users = User::where('active', '=', 1)->get();

$log = User::getQueryLog();
// [['query' => '...', 'bindings' => [...], 'time' => 0.003, 'timestamp' => '...']]

User::flushQueryLog();
User::disableQueryLog();
```

### Query Cache

```php
User::enableCache(ttl: 600);   // cache for 10 minutes
// ... queries are cached automatically ...
User::flushCache();
User::disableCache();
```

---

## AI & Advanced Search

### Keyword Search
The `Searchable` trait provides a `keywordSearch` scope that supports searching across relationships:

```php
// Search name in users AND bio in profiles relationship
$users = User::keywordSearch('developer')->get();
```

To configure searchable columns:
```php
protected $searchable = ['name', 'profile.bio'];
```

### AI Queries
Generate queries from natural language prompts:
```php
$users = User::ai('find all active users from Lagos who joined last month')->get();
```
> [!NOTE]
> AI-generated queries are restricted to `SELECT` operations for safety.

---

## Lazy Loading Prevention

In development, catch N+1 problems:

```php
User::preventLazyLoading(true);  // throws LazyLoadingDisabledException
```

---

## Serialisation

```php
$user->toArray();
$user->toJson();
(string) $user;            // triggers toJson()

// As an HTTP response
$user->toResponse(200, 'OK');

// As an API resource
$user->resource();
```

---

## Built-in Traits

PlugModel ships with a rich set of composable traits. Each is documented separately:

| Trait              | Purpose                                      |
| ------------------ | -------------------------------------------- |
| `HasAttributes`    | Mass assignment, casting, accessors/mutators |
| `HasRelationships` | One-to-many, belongs-to, many-to-many, etc.  |
| `HasValidation`    | Auto-validate on save                        |
| `HasTimestamps`    | `created_at` / `updated_at` management       |
| `SoftDeletes`      | Trash and restore records                    |
| `HasSchema`        | Typed Model Schema system                    |
| `HasFactory`       | Model factory support                        |
| `HasEvents`        | Model lifecycle events                       |
| `HasScopes`        | Global and local query scopes                |
| `HasDomainRules`   | Domain-level update / delete restrictions    |
| `HasDomainEvents`  | Domain event dispatching                     |
| `HasTenancy`       | Multi-tenant scoping                         |
| `Authorizable`     | Policy-based authorisation                   |
| `HasImmutability`  | `#[Immutable]` attribute support             |
| `HasVersioning`    | Optimistic concurrency (`#[Versioned]`)      |
| `HasSerialization` | Serialisation profiles (`#[Serialized]`)     |
| `HasObservability` | Event observation                            |
| `HasDiagnostics`   | Performance diagnostics                      |
| `Searchable`       | Full-text search support                     |
| `Prunable`         | Automatic record pruning                     |
| `HasAI`            | AI-assisted queries                          |

---

## Next Steps

- [Typed Model Schema](schema-overview.md) — replace `$fillable` + `$casts` + `$rules` with a single `$schema` declaration.
- [Schema Field Reference](schema-fields.md) — every field type and its API.
- [Query Builder](../query-builder.md) — fluent queries.
- [Relationships](../relationships.md) — defining and using relationships.
