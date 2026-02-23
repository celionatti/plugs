# Models

Models represent your data and business logic. They provide an intuitive, Eloquent-inspired API for interacting with your database.

## Generating Models

```bash
php theplugs make:model Product
```

### Options

- `--migration` (`-m`): Generate a table migration.
- `--controller` (`-c`): Generate a controller.
- `--all` (`-a`): Generate migration, controller, factory, and seeder.

## Model Structure

All models extend `Plugs\Base\Model\PlugModel`.

```php
namespace App\Models;

use Plugs\Base\Model\PlugModel;
use Plugs\Database\Traits\SoftDeletes;

class Product extends PlugModel
{
    use SoftDeletes;

    // Table name (defaults to snake_case plural of class)
    protected $table = 'products';

    // Mass-assignment protection
    protected $fillable = ['name', 'price', 'is_active'];

    // Automatic type casting
    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];
}
```

## Basic Operations

```php
// Creating
$user = User::create(['name' => 'Emma']);

// Querying
$users = User::where('active', 1)->get();
$user = User::find(1);

// Updating
$user->update(['name' => 'John']);

// Deleting
$user->delete();
```

## Relationships

Define relationships to link models together.

```php
// One-to-Many
public function reviews()
{
    return $this->hasMany(Review::class);
}

// Inverse
public function user()
{
    return $this->belongsTo(User::class);
}
```

## Query Scopes

Scopes allow you to reuse common query constraints:

```php
public function scopePopular($query)
{
    return $query->where('votes', '>', 100);
}

// Usage
$popular = Post::popular()->get();
```
