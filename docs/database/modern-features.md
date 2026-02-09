# Database: Modern Model Features

The Plugs framework has been upgraded to support modern Laravel-style model features, including improved attribute handling, dynamic casting, and built-in support for unique identifiers.

## Modern Attributes

Starting with the latest version, you can define accessors and mutators using the `Plugs\Database\Eloquent\Attribute` class. This provides a cleaner syntax and better type safety.

```php
use Plugs\Base\Model\PlugModel;
use Plugs\Database\Eloquent\Attribute;

class User extends PlugModel
{
    /**
     * Get the user's name in uppercase and set as lowercase.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => strtoupper($value),
            set: fn ($value) => strtolower($value),
        );
    }
}
```

The attribute method should be in `camelCase` and return an `Attribute` object.

## Dynamic Casting

While the `$casts` property is still supported, you may now define a `casts()` method for more dynamic casting logic.

```php
class User extends PlugModel
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'options' => 'array',
        ];
    }
}
```

The `casts()` method takes precedence over the `$casts` property.

## Unique Identifiers (UUIDs & ULIDs)

You can easily use UUIDs or ULIDs for your model's primary keys by using the provided traits.

### Using UUIDs

To use UUIDs, add the `HasUuids` trait to your model. It will automatically generate a UUID when the model is created.

```php
use Plugs\Database\Traits\HasUuids;

class User extends PlugModel
{
    use HasUuids;
}
```

### Using ULIDs

To use ULIDs (Universally Unique Lexicographically Sortable Identifiers), add the `HasUlids` trait:

```php
use Plugs\Database\Traits\HasUlids;

class User extends PlugModel
{
    use HasUlids;
}
```

## Pruning Models

The `Prunable` trait allows you to easily clean up obsolete records from your database.

```php
use Plugs\Database\Traits\Prunable;

class Log extends PlugModel
{
    use Prunable;

    /**
     * Get the prunable model query.
     */
    public function prunable()
    {
        return static::where('created_at', '<=', now()->subMonth());
    }
}
```

## Production Enhancements

### Transaction Helper

The `PlugModel::transaction()` method allows you to execute a closure within a database transaction with automatic retry support.

```php
PlugModel::transaction(function () {
    $user = User::create([...]);
    $user->profile()->create([...]);
}, 3); // 3 attempts if a deadlock or connection error occurs
```

### Advanced Event Registration

You can now register model events statically, which is particularly useful for service providers or traits.

```php
User::creating(function ($user) {
    if (!$user->slug) {
        $user->slug = Str::slug($user->name);
    }
});
```

Available static hooks: `creating`, `created`, `updating`, `updated`, `saving`, `saved`, `deleting`, `deleted`.
