# Migrations & Seeding

Migrations are version control for your database, allowing you to define and share your application's schema. Seeders and Factories provide a way to populate your database with test or initial data.

---

## 1. Migrations

### Creating Migrations
Generate a new migration for a table:
```bash
php theplugs make:migration create_users_table
```

### Migration Structure
Migrations use anonymous classes for simplicity:

```php
use Plugs\Database\Migration;
use Plugs\Database\Blueprint;
use Plugs\Database\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('users'); }
};
```

### Running Migrations
- `php theplugs migrate`: Run pending migrations.
- `php theplugs migrate:rollback`: Rollback the latest batch.
- `php theplugs migrate:fresh`: Wipe the database and re-run all migrations.

---

## 2. Seeders

Seeders allow you to populate your database with initial data.

### Creating Seeders
```bash
php theplugs make:seeder UserSeeder
```

### Usage
```php
class UserSeeder extends Seeder {
    public function run(): void {
        DB::table('users')->insert([
            'name' => 'Admin',
            'email' => 'admin@example.com'
        ]);
    }
}
```

Run seeders via `php theplugs db:seed` or `php theplugs migrate:fresh --seed`.

---

## 3. Factories

Factories define how to generate "fake" models for testing and development.

### Defining Factories
```php
class UserFactory extends Factory {
    public function definition(): array {
        return [
            'name' => 'User ' . uniqid(),
            'email' => 'user' . uniqid() . '@example.com',
        ];
    }
}
```

### Using Factories
```php
User::factory()->count(10)->create();
```

---

## Next Steps
Explore [Advanced Database Features](./advanced.md) for master-slave setups and backups.
