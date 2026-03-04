# Schema Facade: Database Schema Management

The `Plugs\Database\Schema` class provides a static API for managing your database structure — creating tables, altering columns, dropping tables, and introspecting the schema.

> [!NOTE]
> This page covers the **Schema facade** for DDL operations (table management). For the **Typed Model Schema** system that replaces `$fillable` + `$casts` + `$rules`, see [Schema Overview](schema-overview.md).

---

## Creating Tables

```php
use Plugs\Database\Schema;
use Plugs\Database\Blueprint;

Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title', 255);
    $table->text('body');
    $table->integer('user_id')->unsigned();
    $table->boolean('is_published')->default(false);
    $table->timestamps();
});
```

The callback receives a `Blueprint` instance. Use it to define columns, indexes, and constraints.

---

## Altering Tables

```php
Schema::table('posts', function (Blueprint $table) {
    $table->string('subtitle', 255)->nullable();
    $table->dropColumn('legacy_field');
});
```

---

## Dropping Tables

```php
Schema::dropIfExists('posts');   // safe — no error if table doesn't exist
Schema::drop('posts');           // throws if table doesn't exist
```

---

## Renaming Tables

```php
Schema::rename('old_posts', 'posts');
```

---

## Truncating Tables

```php
Schema::truncate('posts');
```

> [!CAUTION]
> `truncate` deletes **all rows** and resets auto-increment counters. This cannot be rolled back.

---

## Introspection

### Check if a Table Exists

```php
if (Schema::hasTable('posts')) {
    // ...
}
```

### Check if a Column Exists

```php
if (Schema::hasColumn('posts', 'subtitle')) {
    // ...
}
```

### List All Tables

```php
$tables = Schema::getTables();
// ['users', 'posts', 'comments', ...]
```

### Get Column Details

```php
$columns = Schema::getColumns('posts');
// Returns the full SHOW COLUMNS result set
```

---

## Foreign Key Constraints

Temporarily disable foreign key checks for operations like seeding or truncating:

```php
Schema::disableForeignKeyConstraints();

Schema::truncate('posts');
Schema::truncate('comments');

Schema::enableForeignKeyConstraints();
```

---

## Raw SQL

For DDL statements the Blueprint doesn't support:

```php
Schema::raw('ALTER TABLE posts ADD FULLTEXT INDEX idx_title (title)');
```

---

## Connection Management

By default, `Schema` uses the default database connection. You can override it:

```php
use Plugs\Database\Connection;

// Use a specific connection
Schema::setConnection(Connection::getInstance(null, 'analytics'));

// Or change the default
Schema::setDefaultConnection('analytics');
```

---

## Using Schema in Migrations

Migrations are the primary consumer of the Schema facade. A typical migration looks like:

```php
namespace App\Database\Migrations;

use Plugs\Database\Migration;
use Plugs\Database\Schema;
use Plugs\Database\Blueprint;

class CreatePostsTable extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->integer('user_id')->unsigned();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
}
```

Run migrations with:

```bash
php theplugs migrate
php theplugs migrate:rollback
php theplugs migrate:fresh      # drop all & re-run
```

---

## Blueprint Instance (Advanced)

For testing or inspection, get a raw Blueprint without executing:

```php
$blueprint = Schema::getBlueprint('posts');
```

---

## Next Steps

- [Migrations](../migrations.md) — migration workflow.
- [Schema Overview](schema-overview.md) — model-level Typed Schema system.
- [Model Overview](overview.md) — PlugModel CRUD and features.
