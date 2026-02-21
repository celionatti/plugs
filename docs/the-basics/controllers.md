# Controllers

Controllers are the entry points for your application's HTTP requests. They handle incoming requests, interact with your domain logic (Services, Actions, Repositories), and return responses.

## Generating Controllers

The framework provides a powerful `make:controller` command to generate various types of controllers:

```bash
php theplugs make:controller UserController
```

### Options

- `--resource` (`-r`): Generate a resource controller with standard CRUD methods (index, create, store, show, edit, update, destroy).
- `--api`: Generate an API controller (excludes create/edit methods).
- `--invokable` (`-i`): Generate a single-action controller (`__invoke` method).
- `--model=ModelName`: Associate the controller with a model.
- `--requests`: Generate FormRequest classes for validation.
- `--test`: Generate a test class for the controller.

## Resource Controllers

Resource controllers provide a standardized way to handle CRUD operations.

```bash
php theplugs make:controller ProductController --resource --model=Product
```

Generated structure:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Plugs\Base\Controller\Controller;

class ProductController extends Controller
{
    public function index()
    {
        // List products
    }

    public function store(Request $request)
    {
        // Manual validation using the Validator
        $validated = \Plugs\Security\Validator::make($request->all(), [
            'name' => 'required|string',
            'price' => 'required|numeric',
        ])->validateOrFail();

        // Create product
        $product = Product::create($validated);

        return response()->json($product, 201);
    }

    public function show($id)
    {
        // Show product
    }

    public function update(Request $request, $id)
    {
        // Update product
    }

    public function destroy($id)
    {
        // Delete product
    }
}
```

## API Controllers

API controllers are streamlined for API development, omitting methods for returning HTML views (create, edit).

```bash
php theplugs make:controller Api/V1/UserController --api --model=User
```

## Dependency Injection

You can inject services, repositories, or actions directly into your controller's constructor or methods.

```php
<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use App\Repositories\Interfaces\ProductRepositoryInterface;

class OrderController extends Controller
{
    protected $userService;
    protected $products;

    public function __construct(
        UserService $userService,
        ProductRepositoryInterface $products
    ) {
        $this->userService = $userService;
        $this->products = $products;
    }

    public function index()
    {
        $products = $this->products->all();
        // ...
    }
}
```

## Single Action Controllers

For complex actions that don't fit into the standard CRUD verbs, you can use invokable controllers.

```bash
php theplugs make:controller ProvisionServerController --invokable
```

Usage:

```php
class ProvisionServerController extends Controller
{
    public function __invoke(Request $request)
    {
        // Provisioning logic...
    }
}
```

Route registration:

```php
Route::post('/server/provision', ProvisionServerController::class);
```
