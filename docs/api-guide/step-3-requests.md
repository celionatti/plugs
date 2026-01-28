# Step 3: Validation & Requests

Never trust incoming data. Plugs uses **FormRequest** classes to handle validation logic outside of your controllers.

## 1. Generating Requests

Generate requests for storing and updating products:

```bash
php theplugs make:request StoreProductRequest
php theplugs make:request UpdateProductRequest
```

## 2. Defining Validation Rules

Open `app/Http/Requests/StoreProductRequest.php` and define your rules:

```php
public function rules(): array
{
    return [
        'name' => 'required|string|max:255',
        'price' => 'required|numeric|min:0',
        'stock_quantity' => 'required|integer|min:0',
        'description' => 'nullable|string',
    ];
}

public function authorize(): bool
{
    // Authorization logic goes here
    return true; 
}
```

## 3. Custom Error Messages

You can easily override default messages in your request:

```php
public function messages(): array
{
    return [
        'name.required' => 'A product name is essential.',
        'price.min' => 'We cannot give products away for free!',
    ];
}
```

## 4. Why Use FormRequests?

*   **Clean Controllers**: Your controller methods stay focused on logic, not validation.
*   **Reuse**: The same validation logic can be used across multiple controllers/actions.
*   **Automatic Response**: If validation fails, Plugs automatically returns a `422 Unprocessable Entity` JSON response with error details.

---
[Next Step: Controllers & Routes →](step-4-controllers-routes.md)
