# Form Requests & Validation

Form Requests are specialized classes that encapsulate validation and authorization logic. They allow you to remove validation boilerplate from your controllers, making your code cleaner and more maintainable.

---

## 🏗️ Creating a Form Request

Generate a request class using the CLI:

```bash
php theplugs make:request StoreUserRequest
```

This will create `app/Http/Requests/StoreUserRequest.php`.

---

## 🛠️ Request Implementation

A Form Request contains two main methods: `authorize()` and `rules()`.

```php
namespace App\Http\Requests;

use Plugs\Http\Requests\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Example: Only admins can create users
        // return auth()->user()->isAdmin();
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email address is already in use.',
        ];
    }
}
```

---

## 📦 Using in Controllers

When you type-hint a `FormRequest` in your controller method, the framework automatically:
1. Instantiates the request.
2. Calls the `authorize()` method.
3. Validates the incoming data against the `rules()`.

```php
namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\User;

class UserController extends Controller
{
    public function store(StoreUserRequest $request)
    {
        // If validation fails, the user is automatically redirected back with errors.
        // If it passes, we can get the validated data:
        $validatedData = $request->validated();

        User::create($validatedData);

        return redirect('/users')->with('success', 'User created!');
    }
}
```

---

## 🛡️ Sanitization

Plugs Form Requests support a unique `sanitizers()` method that allows you to clean data **after** it has been validated but **before** it is used.

```php
public function sanitizers(): array
{
    return [
        'name'  => 'string',   // Escapes HTML tags
        'email' => 'email',    // Sanitizes email format
        'bio'   => 'safeHtml', // Allows only safe HTML tags
    ];
}
```

Access the cleaned data in your controller using `$request->sanitized()`:

```php
$data = $request->sanitized();
```

---

## 💡 Key Rules for Validation

| Rule | Description |
|------|-------------|
| `required` | The field must be present in the input data and not empty. |
| `email` | The field must be formatted as an e-mail address. |
| `unique:table,column` | The field must be unique in the given database table. |
| `confirmed` | The field must have a matching field of `{field}_confirmation`. |
| `sometimes` | Only validate the field if it is present in the input. |
| `safeHtml` | Removes dangerous HTML tags while keeping formatting tags. |
