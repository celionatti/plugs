# ORM (Object-Relational Mapper)

The Plugs ORM provides an expressive, fluent API for interacting with your database. Every database table has a corresponding "Model" used to interact with that table.

---

## 1. Model Definition

Models are stored in `app/Models/` and extend `Plugs\Base\Model\PlugModel`.

```php
namespace App\Models;

use Plugs\Base\Model\PlugModel;
use Plugs\Database\Traits\SoftDeletes;

class Product extends PlugModel
{
    use SoftDeletes;

    protected $table = 'products'; // Optional if follows convention
    protected $fillable = ['name', 'price', 'category_id'];
    
    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
```

### Mass-Assignment Protection
Use `$fillable` (whitelist) or `$guarded` (blacklist) to prevent mass-assignment vulnerabilities when using `Model::create()` or `Model::update()`.

---

## 2. Relationships

Relationships are defined as methods on your model class. They return **Relationship Proxies**, allowing you to chain query constraints before execution.

### One-to-One
```php
public function profile() { return $this->hasOne(Profile::class); }
```

### One-to-Many
```php
public function comments() { return $this->hasMany(Comment::class); }
```

### Many-to-Many
Requires a pivot table (e.g., `role_user`).
```php
public function roles() { return $this->belongsTo(Role::class); }
```

### Polymorphic Relationships
Allows a model to belong to more than one type of model on a single association.
```php
// Imageable trait or direct morph
public function imageable() { return $this->morphTo(); }
```

---

## 3. Model Retrieval

In addition to standard query builder methods, models provide several high-level retrieval helpers:

### Basic Retrieval
```php
$user = User::find(1);
$user = User::findOrFail(1);
$user = User::findOr(1, fn() => "Fallback logic");

$user = User::where('active', 1)->first();
$user = User::where('active', 1)->firstOrFail();
```

### Existence Check
Models support the same `exists()` and `doesntExist()` methods as the query builder:
```php
if (User::where('email', $email)->exists()) {
    // ...
}
```

### Instantiating if Missing
The `firstOrNew` method finds the first record matching attributes or returns a new model instance if not found. Note that this instance is **not** persisted to the database until you call `save()`.
```php
$user = User::firstOrNew(['email' => 'new@example.com']);
```

### Lazy vs Eager Loading
- **Lazy Loading**: Relationship data is loaded only when you access the property.
- **Eager Loading**: Use `with()` to load relationships upfront and prevent the N+1 query problem.

```php
// Eager Loading
$posts = Post::with('author', 'comments')->get();

// Lazy Eager Loading
$books->load('author');
```

### Relationship Counts
Efficiently load counts using subqueries:
```php
$users = User::withCount('posts')->get();
echo $users[0]->posts_count;
```

---

## 4. Query Scopes

Scopes allow you to reuse common query constraints:

```php
public function scopeActive($query) { return $query->where('is_active', true); }

// Usage
$activeUsers = User::active()->get();
```

---

## 5. Persistence & Deletion

### Standard Persistence
```php
$user = new User(['name' => 'John']);
$user->save();

$user->update(['name' => 'Jane']);
```

### Advanced Persistence
- **Quiet Updates**: Update without firing events or timestamps: `$user->updateQuietly(['last_login' => now()])`.
- **Atomic Operations**: `User::where('id', 1)->increment('votes', 5)`.
- **Cloning**: Create a copy without primary keys: `$clone = $user->replicate()`.
- **Finding or Creating**: `User::firstOrCreate(['email' => '...'])` finds or saves a new record.
- **Updating or Creating**: `User::updateOrCreate(['email' => '...'], ['name' => '...'])`.
- **Change Tracking**: Check what happened: `if ($user->wasChanged('email')) { ... }`.

### Deletion
```php
$user->delete();
User::destroy(1);
```
