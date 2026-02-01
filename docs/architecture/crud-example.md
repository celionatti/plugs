# Complete CRUD Example

This guide demonstrates how to build a fully structured CRUD (Create, Read, Update, Delete) feature using all layers of the Plugs framework.

---

## 1. The Model
We'll build a "Product" management system.

```php
// app/Models/Product.php
namespace App\Models;

use Plugs\Base\Model\PlugModel;

class Product extends PlugModel
{
    protected $fillable = ['name', 'description', 'price', 'stock'];
}
```

---

## 2. The Repository
Abstracting the data access.

```php
// app/Repositories/ProductRepository.php
namespace App\Repositories;

use App\Models\Product;

class ProductRepository
{
    public function paginate(int $perPage = 15)
    {
        return Product::paginate($perPage);
    }

    public function find(int $id)
    {
        return Product::findOrFail($id);
    }

    public function store(array $data)
    {
        return Product::create($data);
    }

    public function update(int $id, array $data)
    {
        $product = Product::findOrFail($id);
        $product->update($data);
        return $product;
    }

    public function destroy(int $id)
    {
        return Product::destroy($id);
    }
}
```

---

## 3. The Service
Handling business logic (e.g., specific rules for stock).

```php
// app/Services/ProductService.php
namespace App\Services;

use App\Repositories\ProductRepository;
use Exception;

class ProductService
{
    protected $repository;

    public function __construct(ProductRepository $repository)
    {
        $this->repository = $repository;
    }

    public function createProduct(array $data)
    {
        // Business Rule: price must include tax calculation logic?
        if ($data['price'] <= 0) {
            throw new Exception("Price must be greater than zero.");
        }

        return $this->repository->store($data);
    }

    public function updateStock(int $id, int $newStock)
    {
        if ($newStock < 0) {
            throw new Exception("Stock cannot be negative.");
        }
        
        return $this->repository->update($id, ['stock' => $newStock]);
    }
}
```

---

## 4. The Resource
Transforming the model for the API.

```php
// app/Http/Resources/ProductResource.php
namespace App\Http\Resources;

use Plugs\Http\Resources\PlugResource;

class ProductResource extends PlugResource
{
    public function toArray(): array
    {
        return [
            'id'    => $this->resource->id,
            'name'  => $this->resource->name,
            'price' => (float) $this->resource->price,
            'stock' => (int) $this->resource->stock,
            'isLowStock' => $this->resource->stock < 10,
        ];
    }
}
```

---

## 5. The Request
Validating incoming form data.

```php
// app/Http/Requests/StoreProductRequest.php
namespace App\Http\Requests;

use Plugs\Http\Requests\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'  => 'required|string|max:255',
            'price' => 'required|numeric|min:0.01',
            'stock' => 'required|integer|min:0',
        ];
    }
}
```

---

## 5. The Controller
Orchestrating the flow.

```php
// app/Http/Controllers/ProductController.php
namespace App\Http\Controllers;

use App\Services\ProductService;
use App\Repositories\ProductRepository;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;

class ProductController extends Controller
{
    protected $service;
    protected $repository;

    public function __construct(ProductService $service, ProductRepository $repository)
    {
        $this->service = $service;
        $this->repository = $repository;
    }

    public function index()
    {
        $products = $this->repository->paginate();
        return ProductResource::collection($products)->toResponse();
    }

    public function store(StoreProductRequest $request)
    {
        try {
            $product = $this->service->createProduct($request->validated());
            return ProductResource::make($product)->toResponse(201, 'Product created');
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function show(int $id)
    {
        $product = $this->repository->find($id);
        return ProductResource::make($product)->toResponse();
    }
}
```

---

## Summary of the Flow

1.  **Request**: Received by the **Controller**.
2.  **Validation**: **FormRequest** automatically validates the data.
3.  **Controller**: Extracts validated data and passes it to the **Service**.
4.  **Service**: Applies business rules and calls the **Repository**.
5.  **Repository**: Executes the actual database query via the **Model**.
6.  **Resource**: The **Controller** transforms the result using a **Resource**.
7.  **Response**: The final JSON is returned.
