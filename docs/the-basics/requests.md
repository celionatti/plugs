# Requests

Plugs provides a powerful `ServerRequest` object that extends the standard PSR-7 interface with many convenient methods for accessing input and validating data.

## Accessing Input

You can access all input data (query parameters and post body) using the following methods:

```php
// Get all input
$data = $request->all();

// Get specific field
$email = $request->input('email');

// Get only specific fields
$data = $request->only(['username', 'password']);

// Get all except specific fields
$data = $request->except(['_token']);
```

### Input Casting

The request object provides helpers to automatically cast input to specific types:

- `$request->boolean('subscribed')` - Returns `true` for `"1"`, `"true"`, `"on"`, or `"yes"`.
- `$request->integer('age')` - Casts input to an integer.
- `$request->string('bio')` - Casts input to a string.
- `$request->clamp('rating', 1, 5)` - Casts to integer and restricts the value between min and max.

## Validation

### Inline Validation

The most convenient way to validate a request is using the `validate()` method directly in your controller.

> [!IMPORTANT]
> The `validate()` method returns **only** the data that was validated. This protects your application against mass assignment vulnerabilities from extra, unvalidated fields.

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'body' => 'required',
    ]);

    // $validated contains ONLY 'title' and 'body'
    Post::create($validated);
}
```

If validation fails:

- For **Web requests**, it automatically redirects back with flash errors and old input.
- For **API requests**, it throws a `ValidationException` which the framework renders as a 422 JSON response.

## Form Requests

For more complex validation logic, you can use Form Request classes. Use the `make:request` command to generate one:

```bash
php theplugs make:request StoreUserRequest
```

### Structure

A Form Request class contains `authorize` and `rules` methods:

```php
namespace App\Http\Requests;

use Plugs\Http\Requests\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Return true to allow, false to deny
        return $this->user()->can('create', Product::class);
    }

    /**
     * Understanding Authorization:
     * - true: Continues to rules()
     * - false: Throws AuthorizationException (403 Forbidden)
     */

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
```

### Usage

Type-hint the request in your controller. Plugs handles validation before your code even runs:

```php
public function store(StoreProductRequest $request)
{
    $validated = $request->validated();
}
```

## Sanitization

You can define sanitizers in Form Requests to clean data before validation:

```php
public function sanitizers(): array
{
    return [
        'name' => 'trim|capitalize',
        'email' => 'trim|lowercase',
    ];
}
```
