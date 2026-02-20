# Services

Services are the layer where your business logic should reside. They act as a bridge between your controllers and your repositories (or models). By moving logic into services, you keep your controllers thin and your code reusable.

## Generating Services

Use the `make:service` command to generate a new service class.

```bash
php theplugs make:service UserService
```

### Options

- `--model=User`: Associate the service with a model.
- `--repository`: Inject a repository for the associated model.
- `--interface`: Generate an interface alongside the service.
- `--strict`: Add strict type declarations.

Example:

```bash
php theplugs make:service OrderService --model=Order --repository --interface
```

This will create:

- `app/Services/OrderService.php`
- `app/Services/OrderServiceInterface.php` (if using interface)

## Structure

A service class typically contains methods that perform specific business operations. It often depends on a repository for data access.

```php
<?php

namespace App\Services;

use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use App\Events\OrderCreated;

class OrderService
{
    protected $orders;

    public function __construct(OrderRepositoryInterface $orders)
    {
        $this->orders = $orders;
    }

    /**
     * Create a new order and handle related business logic.
     */
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            // Calculate totals, apply discounts, etc.
            $data['total'] = $this->calculateTotal($data);

            // Create the order via repository
            $order = $this->orders->create($data);

            // Dispatch event
            OrderCreated::dispatch($order);

            return $order;
        });
    }

    protected function calculateTotal(array $data): float
    {
        // ... implementation
        return 100.00;
    }
}
```

## Usage

Inject the service into your controller.

```php
use App\Services\OrderService;

public function store(StoreOrderRequest $request, OrderService $orderService)
{
    $order = $orderService->createOrder($request->validated());

    return response()->json($order, 201);
}
```

## Service vs Action

- **Actions** are great for single, specific tasks (e.g., `CreateUserAction`, `GenerateInvoiceAction`).
- **Services** are better for grouping related business logic for a domain entity (e.g., `UserService` handling creation, updating, profile management, etc.).

You can use both! A Service might actually coordinate multiple Actions to complete a complex process.
