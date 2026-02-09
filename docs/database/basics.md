# Database & ORM

Plugs provides a clean, expressive API for interacting with databases, whether you prefer raw SQL or a fluent Query Builder.

## 1. The DB Facade
The `DB` facade is your primary tool for executing queries. It handles connection pooling and prepared statements automatically.

```php
use Plugs\Support\Facades\DB;

// Executing a raw query
$users = DB::select("SELECT * FROM users WHERE active = ?", [true]);

// Executing an insert
DB::insert("INSERT INTO logs (message) VALUES (?)", ["System boot"]);
```

## 2. Query Builder
For most tasks, the Query Builder is the preferred way to interact with your data. It provides a readable, IDE-friendly syntax.

```php
$users = DB::table('users')
    ->where('last_login', '>', '2025-01-01')
    ->orderBy('name', 'asc')
    ->limit(10)
    ->get();
```

## 3. Models & Active Record
Plugs Models bridge your database tables with PHP objects.

```php
class User extends Model
{
    protected array $fillable = ['name', 'email'];
}

// Finding a user
$user = User::find(1);
echo $user->name;

// Saving changes
$user->name = "Updated Name";
$user->save();
```

## 4. Migrations
Keep your database schema in sync with your code using the built-in migration system.

```bash
# Create a new migration
php theplugs make:migration create_posts_table

# Run pending migrations
php theplugs migrate
```
