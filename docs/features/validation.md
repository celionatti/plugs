# Validation & Data Integrity

Plugs ensures that your application data is clean and valid with a robust, declarative validation system.

## 1. Validating Requests

The most common way to validate data is using the `validate` method directly on the `Request` object.

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'email' => 'required|email|unique:users',
        'password' => 'required|min:8|confirmed',
    ]);

    // The data is valid. $validated contains ONLY email and password.
}
```

### Manual Validator

If you need more control, you can use the `Validator` facade:

```php
use Plugs\Facades\Validator;

$validator = Validator::make($data, $rules);

if ($validator->fails()) {
    return redirect()->back()
        ->withErrors($validator)
        ->withInput();
}
```

## 2. Available Rules

Plugs ships with 30+ built-in rules including:

- `required`, `nullable`
- `email`, `url`, `ip`, `json`
- `unique`, `exists` (Database rules)
- `password` (Strong security defaults)
- `in`, `not_in`, `regex`
- `min`, `max`, `between` (Numeric, string, or file size)
- `dimensions`, `mimetypes` (Advanced file validation)

## 3. Custom Validation Rules

You can easily extend the validator with your own logic or use rule objects.

```php
Validator::extend('phone', function ($attribute, $value, $parameters, $validator) {
    return preg_match('/^[0-9]{10}$/', $value);
});
```

### Fluent Rules & Rule Objects

Plugs now supports a fluent interface for complex rules:

```php
use Plugs\Security\Rule;

$rules = [
    'email' => [
        'required',
        Rule::unique('users', 'email')->ignore($user->id)
    ],
    'password' => 'required|Password::defaults()',
    'avatar' => Rule::dimensions(['width' => 200, 'height' => 200]),
];
```

## 4. Error Messages

Plugs handles the localization and formatting of error messages for you, making it easy to display them in your views.

```php
@error('email')
    <div class="alert alert-danger">{{ $message }}</div>
@enderror
```
