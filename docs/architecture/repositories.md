# Repositories

The Repository Pattern separates the data access logic from the business logic. It allows you to keep your controllers and services clean and makes your application easier to test and maintain.

## Generating Repositories

Generate a repository class using the `make:repository` command.

```bash
php theplugs make:repository UserRepository
```

### Options

- `--model=User`: Associate with a specific model.
- `--interface`: Generate an interface alongside the repository.
- `--strict`: Add strict type declarations.

Example:

```bash
php theplugs make:repository ProductRepository --model=Product --interface
```

This will create:

- `app/Repositories/ProductRepository/ProductRepository.php`
- `app/Repositories/Interfaces/ProductRepositoryInterface.php`

## Structure

### Interface

The interface defines the contract for your repository.

```php
<?php

namespace App\Repositories\Interfaces;

use App\Models\Product;
use Plugs\Database\Collection;

interface ProductRepositoryInterface
{
    public function all(): Collection;
    public function find(int $id): ?Product;
    public function create(array $data): ?Product;
}
```

### Implementation

The repository implements the interface.

```php
<?php

namespace App\Repositories\ProductRepository;

use App\Models\Product;
class ProductRepository implements ProductRepositoryInterface
{
    public function all(): Collection
    {
        return Product::all();
    }

    public function find(int $id): ?Product
    {
        return Product::find($id);
    }

    public function create(array $data): ?Product
    {
        return Product::create($data);
    }
}
```

## Usage

Inject the repository interface into your controllers or services.

```php
use App\Repositories\Interfaces\ProductRepositoryInterface;

public function __construct(ProductRepositoryInterface $products)
{
    $this->products = $products;
}
```

### Binding

Don't forget to bind the interface to the implementation in your ServiceProvider.

```php
$this->app->bind(
    ProductRepositoryInterface::class,
    ProductRepository::class
);
```
