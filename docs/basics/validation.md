# Validation

Validation is a critical part of every application. Plugs provides a fluent and expressive API to validate incoming data and protect your database.

---

## 1. Basic Validation

The most common way to validate is using the `validate()` method within your controller. If validation fails, Plugs automatically redirects the user back to their previous location with all input and error messages flashed to the session.

```php
public function store(Request $request)
{
    $validated = $this->validate([
        'title' => 'required|unique:posts|max:255',
        'body'  => 'required',
        'category_id' => 'required|exists:categories,id',
    ]);

    // Proceed with $validated data...
}
```

---

## 2. Available Rules

Plugs supports dozens of built-in validation rules:

| Rule | Description |
| --- | --- |
| `required` | The field must be present and not empty. |
| `unique:table,column` | The value must be unique in the database table. |
| `exists:table,id` | The value must exist in the database table. |
| `email` | The field must be a valid email address. |
| `numeric` | The field must be a number. |
| `image` | The field must be an image (jpeg, png, etc.). |
| `min:value` | The minimum size or value allowed. |
| `max:value` | The maximum size or value allowed. |

---

## 3. Custom Error Messages

You can customize the error messages by providing a second array to the `validate()` method:

```php
$this->validate($rules, [
    'title.required' => 'We need a title for your post!',
    'email.unique'   => 'This email is already registered.',
]);
```

---

## 4. Manual Validation

If you're building an API or need more control, you can use the `Validator` facade manually:

```php
use Plugs\Security\Validator;

$validator = new Validator($data, $rules);

if ($validator->fails()) {
    return response()->json($validator->errors(), 422);
}
```

---

## Next Steps
Build beautiful interfaces using the [View Engine](../views/engine.md).
