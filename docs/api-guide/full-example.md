# Full Example: Product Management API

This page provides a complete, production-ready implementation of a Product Management API, combining all the concepts learned in the [API Guide](introduction.md).

## 1. Migration

```php
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
```

## 2. Model

```php
namespace App\Models;

use Plugs\Base\Model\PlugModel;
use Plugs\Database\Traits\SoftDeletes;

class Product extends PlugModel
{
    use SoftDeletes;

    protected array $fillable = ['name', 'slug', 'description', 'price', 'stock_quantity', 'is_active'];
    protected array $casts = ['price' => 'decimal:2', 'is_active' => 'boolean'];
}
```

## 3. Resources

### Single Resource
```php
namespace App\Http\Resources;

use Plugs\Http\Resources\PlugResource;

class ProductResource extends PlugResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'sku' => $this->resource->slug,
            'description' => $this->resource->description,
            'pricing' => [
                'base' => $this->resource->price,
                'formatted' => '$' . number_format($this->resource->price, 2),
            ],
            'status' => $this->when($this->resource->is_active, 'available', 'out_of_stock'),
            'created_at' => $this->resource->created_at,
        ];
    }
}
```

### Collection
```php
namespace App\Http\Resources;

use Plugs\Http\Resources\PlugResourceCollection;

class ProductCollection extends PlugResourceCollection
{
    public function toArray(): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total_count' => $this->collection->count(),
                'api_version' => '1.0.0',
            ]
        ];
    }
}
```

## 4. FormRequest

```php
namespace App\Http\Requests;

use Plugs\Http\Request;

class StoreProductRequest extends Request
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
        ];
    }
}
```

## 5. Controller (V1)

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
        return new ProductCollection(Product::paginate(15));
    }

    public function store(StoreProductRequest $request): ProductResource
    {
        $product = Product::create($request->validated());
        return new ProductResource($product);
    }

    public function show(int $id): ProductResource
    {
        return new ProductResource(Product::findOrFail($id));
    }

    public function update(StoreProductRequest $request, int $id): ProductResource
    {
        $product = Product::findOrFail($id);
        $product->update($request->validated());
        return new ProductResource($product);
    }

    public function destroy(int $id): \Plugs\Http\JsonResponse
    {
        Product::findOrFail($id)->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }
}
```

## 6. Routes

```php
use App\Http\Controllers\V1\ProductController;
use Plugs\Http\Route;

Route::prefix('v1')->group(function() {
    Route::apiResource('products', ProductController::class);
});
```
