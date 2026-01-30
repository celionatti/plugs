# Step 1: Modeling & Data

The foundation of any great API is a solid data model. In this step, we'll create our `Product` model along with its database migration, factory, and seeder.

## 1. Generating the Foundation

Using the Plugs CLI, we can generate everything we need in one go:

```bash
php theplugs make:model Product -mfs
```

This command creates:
*   `app/Models/Product.php` (The Model)
*   `database/migrations/xxxx_xx_xx_xxxxxx_create_products_table.php` (The Migration)
*   `database/factories/ProductFactory.php` (The Factory)
*   `database/seeders/ProductSeeder.php` (The Seeder)

## 2. Defining the Schema

Open your new migration file and define the products table:

```php
<?php

declare(strict_types=1);

use Plugs\Database\Migration;
use Plugs\Database\Blueprint;
use Plugs\Database\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('stock_quantity');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

Run the migration:
```bash
php theplugs migrate
```

## 3. Configuring the Model

Our `Product` model should handle mass assignment and soft deletes. Plugs makes this easy:

```php
namespace App\Models;

use Plugs\Base\Model\PlugModel;
use Plugs\Database\Traits\SoftDeletes;

class Product extends PlugModel
{
    use SoftDeletes;

    protected array $fillable = [
        'name', 'slug', 'description', 'price', 'stock_quantity', 'is_active'
    ];
    
    protected array $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'stock_quantity' => 'integer'
    ];
}
```

## 4. Factories for Demo Data

To test our API effectively, we need realistic data. Edit `database/factories/ProductFactory.php`:

```php
public function definition(): array
{
    $name = $this->faker->productName();
    return [
        'name' => $name,
        'slug' => Str::slug($name),
        'description' => $this->faker->paragraph(),
        'price' => $this->faker->randomFloat(2, 10, 1000),
        'stock_quantity' => $this->faker->numberBetween(0, 100),
        'is_active' => true,
    ];
}
```

## 5. Seeding the Database

Finally, call your factory in `database/seeders/ProductSeeder.php`:

```php
public function run(): void
{
    Product::factory()->count(50)->create();
}
```

Seed the data:
```bash
php theplugs db:seed --class=ProductSeeder
```

---
[Next Step: Resources & Transformation →](step-2-resources.md)
