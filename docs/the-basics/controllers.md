# Controllers

Controllers are the core of your application's logic. They handle requests, interact with services or models, and return responses.

## Generating Controllers

Use the `make:controller` command to generate controllers quickly:

```bash
php theplugs make:controller UserController
```

### Key Options

- `--resource` (`-r`): Generate a controller with all standard CRUD methods.
- `--api`: Generate an API-friendly controller (omits create/edit views).
- `--invokable` (`-i`): Create a single-action class with an `__invoke` method.
- `--model=Model`: Associate the controller with a specific model.

## Resource Controllers

A resource controller provides a standardized structure for managing data.

```php
namespace App\Http\Controllers;

use App\Models\Product;
use Plugs\Http\Message\Request;
use Plugs\Base\Controller\Controller;

class ProductController extends Controller
{
    public function index()
    {
        return view('products.index', [
            'products' => Product::all()
        ]);
    }

    public function store(Request $request)
    {
        // Convenient inline validation
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);

        Product::create($validated);

        return redirect()->route('products.index');
    }
}
```

## Dependency Injection

The framework automatically injects classes into your controller's constructor or methods via the Service Container.

```php
class OrderController extends Controller
{
    public function __construct(
        protected ProductRepository $products
    ) {}

    public function show(Request $request, $id)
    {
        $product = $this->products->find($id);
        // ...
    }
}
```

## Invokable Controllers

If a controller only performs one complex action, you can use the `__invoke` method.

```php
// Route
Route::post('/server/provision', ProvisionController::class);

// Controller
class ProvisionController extends Controller
{
    public function __invoke(Request $request)
    {
        // Logical flow...
    }
}
```
