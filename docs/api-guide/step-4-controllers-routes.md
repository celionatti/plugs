# Step 4: Controllers & Routes

The Controller acts as the conductor, orchestrating the Model, Request, and Resource. We'll use the CLI to generate our entire feature stack in a nested namespace for better organization.

## 1. Generating the Feature Stack

We'll create a `V1` version of our controller:

```bash
php theplugs make:controller V1/ProductController --api --model=Product --requests
```

This ensures our API is versioned from day one.

## 2. Implementing the Controller

Open `app/Http/Controllers/V1/ProductController.php`. Notice how the `PlugResource` and `FormRequest` classes work together:

```php
namespace App\Http\Controllers\V1;

use App\Models\Product;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductCollection;
use App\Http\Requests\StoreProductRequest;
use Plugs\Base\Controller\Controller;

class ProductController extends Controller
{
    public function index(): ProductCollection
    {
        // Return a collection of todos
        return new ProductCollection(Product::all());
    }

    public function store(StoreProductRequest $request): ProductResource
    {
        // Validation is automatically handled by StoreProductRequest
        $product = Product::create($request->validated());

        return new ProductResource($product);
    }

    public function show(int $id): ProductResource
    {
        $product = Product::findOrFail($id);

        return new ProductResource($product);
    }
}
```

## 3. Registering Routes

Routes are typically registered in `routes/api.php`. Use the `apiResource` helper for standard endpoints:

```php
use App\Http\Controllers\V1\ProductController;
use Plugs\Http\Route;

Route::prefix('v1')->group(function() {
    Route::apiResource('products', ProductController::class);
});
```

## OpenAPI Documentation

Plugs can automatically generate an OpenAPI (Swagger) specification for your routes using the `route:openapi` command.

### Automated Metadata Extraction

The OpenAPI generator is "DocBlock aware." It uses reflection to read your controller method comments and injects them as route summaries and descriptions in the generated `openapi.json`.

#### Example

```php
class UserController extends Controller
{
    /**
     * Get user profile
     *
     * returns the full profile data for the authenticated user,
     * including badges and verification status.
     */
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        // ...
    }
}
```

Running `php theplugs route:openapi` will produce:

- **Summary**: "Get user profile"
- **Description**: "returns the full profile data..."

### Usage

```bash
php theplugs route:openapi
```

The specification will be saved to `openapi.json` in your project root.

This single line registers:

- `GET /v1/products` (index)
- `POST /v1/products` (store)
- `GET /v1/products/{id}` (show)
- `PUT/PATCH /v1/products/{id}` (update) - Use `PUT` for full updates and `PATCH` for partial updates.
- `DELETE /v1/products/{id}` (destroy)

## 4. Why Use API Controllers?

By using the `--api` flag, Plugs generates a controller without `create` and `edit` methods, which are only needed for traditional HTML-based web apps. This keeps your API codebase lean and focused.

---

[Next Step: Verification & Testing →](step-5-testing.md)
