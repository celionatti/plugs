# Query Builder

The Plugs Query Builder provides a fluent, convenient interface for creating and running database queries. It uses PDO parameter binding to protect your application against SQL injection attacks.

---

## 1. Retrieving Results

### Basic Retrieval
```php
use Plugs\Facades\DB;

$users = DB::table('users')->get();
$user = DB::table('users')->where('id', 1)->first();
$email = DB::table('users')->value('email');
```

### Exception Handling
```php
$user = DB::table('users')->findOrFail(5); // Throws 404 if not found
```

### Aggregates
```php
$count = DB::table('users')->count();
$maxPrice = DB::table('orders')->max('price');
```

---

## 2. Where Clauses

### Basic Wheres
```php
$users = DB::table('users')
    ->where('votes', '>', 100)
    ->where('status', 'active')
    ->get();
```

### Advanced Wheres
- **Logical Groups**: Use closures for nested conditions.
- **Null Checks**: `whereNull('deleted_at')`.
- **Collections**: `whereIn('id', [1, 2, 3])`.
- **Dates**: `whereDate('created_at', '2023-01-01')`.

```php
$users = DB::table('users')
    ->where('name', 'John')
    ->nestedWhere(function ($query) {
        $query->where('votes', '>', 50)->orWhere('title', 'Admin');
    })->get();
```

---

## 3. Joins

```php
$users = DB::table('users')
    ->join('profiles', 'users.id', '=', 'profiles.user_id')
    ->select('users.*', 'profiles.bio')
    ->get();
```

Supports `leftJoin`, `rightJoin`, and joining to subqueries via closures.

---

## 4. Ordering, Limit & Pagination

### Ordering
```php
$users = DB::table('users')->orderBy('name', 'desc')->latest()->get();
```

### Pagination
Plugs handles the current page detection and link generation automatically.
```php
$users = DB::table('users')->paginate(15);
```

---

## 5. Inserts, Updates & Deletes

```php
// Insert
DB::table('users')->insert(['email' => 'jane@example.com']);

// Update or Insert (Upsert)
DB::table('users')->updateOrInsert(['email' => 'john@example.com'], ['votes' => 1]);

// Delete
DB::table('users')->where('votes', '<', 50)->delete();
```

---

## Next Steps
Automate your database schema with [Migrations](./migrations.md).
