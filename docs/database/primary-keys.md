# Primary Keys (UUID & ULID)

By default, the Plugs ORM assumes that each table has a primary key named `id` which is an auto-incrementing integer. However, modern applications often prefer universally unique identifiers (UUID) or universally unique lexicographically sortable identifiers (ULID).

The Plugs framework fully supports utilizing these custom primary keys seamlessly across the Query Builder, Relationships, and Route Model Binding.

---

## 1. Updating the Database Migration

When creating your table schema, instead of using the standard auto-incrementing integer (`$table->id()`), you should define the primary key as a `uuid` or `ulid` column.

**For UUIDs:**
```php
use Plugs\Database\Migration;
use Plugs\Database\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        $this->schema->create('users', function (Blueprint $table) {
            // Creates a 'uuid' column type and sets it as the primary key
            $table->uuid('id')->primary(); 
            
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('users');
    }
};
```

**For ULIDs:**
```php
// Use $table->ulid('id')->primary(); instead
```

> **Note:** If you prefer to name your column something other than `id`, such as `uuid`, you would write `$table->uuid('uuid')->primary();`.

---

## 2. Configuring the Model

In your `PlugModel`, you simply need to include the `HasUuids` or `HasUlids` trait. 

This trait automatically hooks into the model's `creating` lifecycle event and generates a unique identifier for the primary key before saving it to the database. It also automatically alerts the framework to stop treating the primary key as an auto-incrementing integer.

**For UUIDs:**
```php
namespace App\Models;

use Plugs\Base\Model\PlugModel;
use Plugs\Database\Traits\HasUuids;

class User extends PlugModel
{
    use HasUuids;

    // Optional: Only required if you named your primary key column something other than 'id'
    // protected $primaryKey = 'uuid'; 

    protected $fillable = [
        'name',
        'email',
    ];
}
```

**For ULIDs:**
```php
namespace App\Models;

use Plugs\Base\Model\PlugModel;
use Plugs\Database\Traits\HasUlids;

class User extends PlugModel
{
    use HasUlids;

    protected $fillable = [
        'name',
        'email',
    ];
}
```

---

## 3. Advanced Configurations

### Changing the Primary Key Name
If your primary key column is named `uuid` or `ulid` instead of `id`, you simply need to tell the model by defining the `$primaryKey` property:

```php
class User extends PlugModel
{
    use HasUuids;

    protected $primaryKey = 'uuid'; 
}
```

### Using Auto-Incrementing `id` alongside a `uuid` Column
It is completely fine to keep a traditional integer auto-incrementing `id` as your primary key, while still letting the framework automatically generate a `uuid` column for you.

To do this, you just need to override the `uniqueIds()` method provided by the trait, so it generates the UUID on your custom column instead of substituting the primary key:

```php
class User extends PlugModel
{
    use HasUuids;

    // The primary key remains 'id' by default (auto-incrementing)

    /**
     * Get the columns that should receive a unique identifier.
     */
    public function uniqueIds(): array
    {
        return ['uuid']; // Tell the trait to populate this column instead
    }
}
```
With this setup:
1. The framework still uses `id` for relationships and Route Model binding unless you override them.
2. The `uuid` column will automatically be populated when you create a new user.

---

## 4. Relationships & Model Binding

Because the framework dynamically checks the model's `$primaryKey` setting, your custom primary keys will completely work out of the box anywhere in the framework.

### Relationships
If you have a `Post` model that belongs to a UUID `User`, your migration for the `posts` table just needs to use the same data type for the foreign key:

```php
$table->uuid('user_id'); // Match the type of the parent's primary key
$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
```
When querying relationships utilizing `$user->posts` or `Post::with('user')->get()`, the framework will successfully join and query the alpha-numeric keys.

### Route Model Binding
When you define a route like `/users/{user}`:
```php
$router->get('/users/{user}', [UserController::class, 'show']);
```
The router will automatically fetch the model by matching the UUID/ULID string passed in the URL, as it queries based on `$model->getKeyName()`.
