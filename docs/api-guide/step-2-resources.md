# Step 2: Resources & Transformation

With our data ready, we need to transform it for the API. In Plugs, we use **API Resources** to decouple our database schema from our public API.

## 1. Generating Resources

We'll generate a resource for a single product and a collection for lists:

```bash
php theplugs make:resource Product --collection
```

This creates:
*   `app/Http/Resources/ProductResource.php`
*   `app/Http/Resources/ProductCollection.php`

## 2. Defining the Transformation

Open `app/Http/Resources/ProductResource.php`. This is where we define exactly what is returned:

```php
public function toArray(): array
{
    return [
        'id' => $this->resource->id,
        'name' => $this->resource->name,
        'sku' => $this->resource->slug, // Renaming fields for public use
        'description' => $this->resource->description,
        'pricing' => [
            'base' => $this->resource->price,
            'formatted' => '$' . number_format($this->resource->price, 2),
        ],
        'status' => $this->when($this->resource->is_active, 'available', 'out_of_stock'),
        
        // Timestamps are automatically converted to camelCase (createdAt, updatedAt)
        'created_at' => $this->resource->created_at,
    ];
}
```

## 3. Automatic camelCase

Plugs automatically converts `snake_case` keys in your `toArray()` to `camelCase` in the final JSON. 

For example, `'created_at' => $this->resource->created_at` becomes `"createdAt": "..."`.

> [!TIP]
> To disable this globally for a resource, set `public static bool $camelCase = false;`.

## 4. Conditional Attributes

Resources support powerful conditional methods:

*   **`when()`**: Include based on a boolean.
*   **`whenLoaded()`**: Include relationships only if eager-loaded.
*   **`mergeWhen()`**: Conditionally merge entire arrays.

Example:
```php
'inventory' => $this->when(auth()->user()->isAdmin(), [
    'quantity' => $this->resource->stock_quantity,
]),
```

---
[Next Step: Validation & Requests →](step-3-requests.md)
