# Database: Migrations

Migrations are version control for your database, allowing your team to define and share the application's database schema.

## Creating Migrations

```bash
php theplugs make:migration create_users_table
```

## Migration Structure

Plugs uses anonymous classes for migrations:

```php
use Plugs\Database\Migration;
use Plugs\Database\Blueprint;
use Plugs\Database\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

## Available Column Types

| Method                           | Description                    |
| -------------------------------- | ------------------------------ |
| `$table->id()`                   | Auto-incrementing Primary Key. |
| `$table->string('name', 255)`    | VARCHAR column.                |
| `$table->text('body')`           | TEXT column.                   |
| `$table->integer('votes')`       | INT column.                    |
| `$table->decimal('price', 8, 2)` | DECIMAL column.                |
| `$table->boolean('active')`      | BOOLEAN column.                |
| `$table->json('options')`        | JSON column.                   |
| `$table->timestamps()`           | `created_at` and `updated_at`. |
| `$table->softDeletes()`          | `deleted_at` for soft deletes. |

## Running Migrations

```bash
# Run pending migrations
php theplugs migrate

# Rollback latest batch
php theplugs migrate:rollback

# Wipe database and restart
php theplugs migrate:fresh
```

## Integrity & Audit

Plugs automatically tracks migration integrity:

- **Checksums**: Verifies that migration files haven't been tampered with after execution.
- **Audit Logs**: Every change is logged in `migration_logs` with SQL queries and execution time.
- **Status**: Use `php theplugs migrate:status` for a detailed integrity report.
