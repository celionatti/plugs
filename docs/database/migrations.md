# Database: Migrations

Migrations are like version control for your database, allowing your team to define and share the application's database schema definition.

## Creating Migrations

To create a migration, use the `make:migration` command:

```bash
php theplugs make:migration create_users_table
```

The new migration will be placed in your `database/migrations` directory.

## Migration Structure

A migration class contains two methods: `up` and `down`. The `up` method is used to add new tables, columns, or indexes to your database, while the `down` method should reverse the operations performed by the `up` method.

```php
use Plugs\Database\Migration;
use Plugs\Database\Blueprint;
use Plugs\Database\Schema;

class CreateUsersTable extends Migration
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
}
```

## Running Migrations

To run all of your outstanding migrations, execute the `migrate` command:

```bash
php theplugs migrate
```

### Rolling Back Migrations

To rollback the latest migration operation, you may use the `rollback` command:

```bash
php theplugs migrate:rollback
```

You may rollback a specific number of migrations by providing the `step` option:

```bash
php theplugs migrate:rollback --step=5
```

The `migrate:reset` command will roll back all of your application's migrations:

```bash
php theplugs migrate:reset
```

## Fresh Migrations

The `migrate:fresh` command will drop all tables from the database and then execute the `migrate` command:

```bash
php theplugs migrate:fresh
```

> [!WARNING]
> The `migrate:fresh` command will drop all of your tables regardless of their prefix. This command should be used with caution when developing on a database that is shared with other applications.

In production environments, this command requires the `--force` flag:

```bash
php theplugs migrate:fresh --force
```

## Checking Migration Status

The `migrate:status` command provides a summary of which migrations have been run and which are pending:

```bash
php theplugs migrate:status
```

The enhanced status output includes:
- **Migration Name**: The filename of the migration.
- **Ran?**: Whether the migration has been executed.
- **Batch**: The batch number the migration was run in.
- **Ran At**: The exact timestamp when the migration was executed.
- **Status**: Whether the migration file is "Intact" or "Modified" (detected via SHA-256 checksums).

## Migration Integrity

Plugs automatically tracks the integrity of your migrations using checksums. If a migration file is modified after it has already been executed, the `migrate:status` and `migrate:validate` commands will warn you.

To validate the integrity of all executed migrations:

```bash
php theplugs migrate:validate
```

## Execution Logging

Every migration action is logged in the `migration_logs` table. This log allows you to audit internal schema changes and includes:
- **Query**: The exact SQL queries executed.
- **Duration**: The duration of the execution in milliseconds.
- **Memory**: Memory usage during the migration.
- **Action**: The action performed (`up` or `down`).
- **Batch**: The migration batch associated with the log.
