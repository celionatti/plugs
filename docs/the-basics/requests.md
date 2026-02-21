# Form Requests

Form Requests are custom request classes that encapsulate validation and authorization logic. They help keep your controllers clean by moving complex validation rules out of the controller methods.

## Generating Requests

Use the `make:request` command to generate a new form request class.

```bash
php theplugs make:request StoreUserRequest
```

### Options

- `--rules=name,email`: Pre-define validation rules for fields.
- `--auth`: Include an authorization method with a template.
- `--subDir=Api/V1`: Organize requests into subdirectories.

Example:

```bash
php theplugs make:request StoreProductRequest --rules=name,price,category_id --auth
```

## Structure

A Form Request class contains two main methods: `authorize` and `rules`.

```php
<?php

namespace App\Http\Requests;

use Plugs\Http\Requests\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price' => ['required', 'numeric', 'min:0'],
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'The selected category is invalid.',
        ];
    }
}
```

## Usage

Type-hint the request class in your controller method. The framework will automatically validate the incoming request before the controller method is called. If validation fails, a redirect or JSON error response will be generated automatically.

```php
public function store(StoreProductRequest $request)
{
    // The incoming request is valid...

    $validated = $request->validated();

    // Create product...
}
```

## Sanitization

You can also define sanitizers to clean input data before validation.

```php
public function sanitizers(): array
{
    return [
        'name' => 'trim|capitalize',
        'email' => 'trim|lowercase',
    ];
}
```
