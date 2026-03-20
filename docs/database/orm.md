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

## 3. Querying & Execution

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

## Next Steps
Master complex queries using the [Query Builder](./query-builder.md).
