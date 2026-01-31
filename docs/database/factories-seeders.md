# Database: Factories & Seeders

Plugs provides a robust system for generating fake data and seeding your database. This is essential for testing and populating your application with initial data.

## Factories

Factories allow you to define a blueprint for your models, making it easy to generate consistent fake data for testing or seeding.

### Generating Factories

To create a new factory, use the `make:factory` console command:

```bash
php theplugs make:factory UserFactory --model=User
```

This will create a new factory file in `database/factories/`.

### Defining Factories

A factory class contains a `definition` method that returns the default attribute values for the model. You can use the built-in `$this->faker` to generate random data:

```php
namespace Database\Factories;

use App\Models\User;
use Plugs\Database\Factory\PlugFactory;

class UserFactory extends PlugFactory
{
    protected ?string $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'is_active' => true,
        ];
    }
}
```

### Using Factories

Since all Plugs models use the `HasFactory` trait by default, you can access the factory directly from the model:

#### Creating Models
The `create` method instantiates the model and saves it to the database:

```php
// Create a single user
$user = User::factory()->create();

// Create multiple users
$users = User::factory()->count(10)->create();
```

#### Making Models (In-Memory)
The `make` method creates model instances without saving them to the database:

```php
$user = User::factory()->make();
```

#### Overriding Attributes
You can pass an array of attributes to `make` or `create` to override the defaults:

```php
$user = User::factory()->create([
    'name' => 'John Doe',
]);
```

### Factory States

States allow you to define discrete variations of your models. You can define a state using the `state` method:

```php
$inactiveUser = User::factory()->state([
    'is_active' => false,
])->create();
```

### Or using a Closure for dynamic states:

```php
$unverifiedUser = User::factory()->state(function (array $attributes) {
    return ['email_verified_at' => null];
})->create();
```

### Sequences

If you want to cycle through a set of values for a specific attribute, you can use the `sequence` method:

```php
$users = User::factory()
    ->count(3)
    ->sequence(
        ['role' => 'admin'],
        ['role' => 'editor'],
        ['role' => 'user'],
    )
    ->create();
```

---

## Seeders

Seeders are used to populate your database with data. This is useful for initial setup, demo data, or test data.

### Generating Seeders

To create a new seeder, use the `make:seeder` console command:

```bash
php theplugs make:seeder UserSeeder
```

This will create a new seeder file in `database/seeders/`.

### Defining Seeders

A seeder class contains a `run` method where you define the seeding logic. You can use your factories within this method:

```php
namespace Database\Seeders;

use App\Models\User;
use Plugs\Database\Seeders\PlugSeeder;

class UserSeeder extends PlugSeeder
{
    public function run(): void
    {
        User::factory()->count(50)->create();
    }
}
```

### The DatabaseSeeder

The `database/seeders/DatabaseSeeder.php` is the main entry point for seeding. You can use the `call` method to run other seeders:

```php
public function run(): void
{
    $this->call([
        UserSeeder::class,
        PostSeeder::class,
    ]);
}
```

### Running Seeders

To run your seeders, use the `db:seed` console command:

```bash
# Run the default DatabaseSeeder
php theplugs db:seed

# Run a specific seeder class
php theplugs db:seed --class=UserSeeder
```

---

## Faker API

The Plugs framework includes a built-in, zero-dependency Faker instance available within your factories. Common methods include:

- `$this->faker->name()`
- `$this->faker->firstName()`
- `$this->faker->lastName()`
- `$this->faker->userName()`
- `$this->faker->email()`
- `$this->faker->unique()->safeEmail()`
- `$this->faker->company()`
- `$this->faker->jobTitle()`
- `$this->faker->address()`
- `$this->faker->city()`
- `$this->faker->country()`
- `$this->faker->numberBetween(int $min, int $max)`
- `$this->faker->randomFloat(int $decimals, float $min, float $max)`
- `$this->faker->date()`
- `$this->faker->dateTime()`
- `$this->faker->dateTimeBetween(string $startDate, string $endDate)`
- `$this->faker->word()`
- `$this->faker->sentence()`
- `$this->faker->paragraph()`
- `$this->faker->text(int $limit)`
- `$this->faker->slug()`
- `$this->faker->url()`
- `$this->faker->imageUrl(int $width, int $height, string $category)`
- `$this->faker->randomHtml(int $count)`
- `$this->faker->boolean()`
- `$this->faker->uuid()`

### Blog & Content Generation

For generating content rich applications like a blog, you can use these specialized methods:

```php
public function definition(): array
{
    return [
        'title' => $this->faker->sentence(),
        'slug' => $this->faker->slug(),
        'content' => $this->faker->randomHtml(5), // Generates 5 paragraphs of HTML
        'excerpt' => $this->faker->text(150), // Truncated text
        'banner_image' => $this->faker->imageUrl(1200, 600, 'Technology'),
        'author_username' => $this->faker->userName(),
        'published_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        'source_url' => $this->faker->url(),
    ];
}
```
