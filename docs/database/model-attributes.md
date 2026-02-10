# Advanced Model Attributes

Plugs provides several specialized attributes to control model behavior regarding persistence, consistency, and serialization.

## 1. Immutability

The `#[Immutable]` attribute prevents a model from being modified after its initial creation. Any call to `save()` on an existing immutable model will throw a `DatabaseException`.

```php
use Plugs\Database\Attributes\Immutable;

#[Immutable]
class AuditLog extends PlugModel {
    // This record can never be updated once created.
}
```

## 2. Versioning (Optimistic Concurrency)

The `#[Versioned]` attribute enables optimistic locking. It expects a `version` column (integer) in the database. Every update increments the version and includes it in the `WHERE` clause.

```php
use Plugs\Database\Attributes\Versioned;

#[Versioned]
class Account extends PlugModel {
    // Prevents "lost updates" from concurrent requests.
}
```

If another process updated the record in the meantime, a `ConcurrencyException` is thrown.

## 3. Serialization Profiles

The `#[Serialized]` attribute provides fine-grained control over how models are converted to arrays or JSON, supporting different "profiles" for different contexts (e.g., 'public' vs 'internal').

```php
use Plugs\Database\Attributes\Serialized;

#[Serialized(
    visible: ['id', 'name', 'email'],
    hidden: ['password'],
    appends: ['full_name'],
    profile: 'public'
)]
class User extends PlugModel {
    // ...
}
```

### Switching Profiles
You can switch the active serialization profile at runtime:

```php
$user->setSerializationProfile('internal');
return json_encode($user);
```

## 4. Internal Framework Properties

The Plugs framework uses typed properties for various internal features (e.g., `allowRawQueries`, `strictCasting`, `recordedEvents`). To prevent these internal properties from being incorrectly identified as database columns, the framework automatically filters out any typed properties declared within the `Plugs\` namespace during model persistence.

This means you can safely use framework-provided traits and extend `PlugModel` without worrying about internal property names colliding with your database schema.
