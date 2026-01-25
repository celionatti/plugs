# Request Validation & Sanitization

The Plugs framework provides a powerful way to validate and sanitize user input using Form Requests. This is especially useful for maintaining clean controllers and ensuring security.

## Creating a Form Request

You can create a form request by extending the `Plugs\Http\Requests\FormRequest` class. 

### Modern Example: Blog Post Request

Here is how you can handle both regular text fields and rich HTML content (from a text editor) safely in a single request.

```php
<?php

namespace App\Http\Requests;

use Plugs\Http\Requests\FormRequest;

class CreateBlogPostRequest extends FormRequest
{
    /**
     * Define validation rules.
     */
    public function rules(): array
    {
        return [
            'title'   => 'required|string|max:255',
            'content' => 'required|safe_html', // Strict check for dangerous tags
            'excerpt' => 'nullable|string|max:500',
            'status'  => 'required|in:draft,published',
        ];
    }

    /**
     * Define sanitization rules.
     * These correspond to methods in the Sanitizer class.
     */
    public function sanitizers(): array
    {
        return [
            'title'   => 'string',   // Escapes ALL HTML
            'content' => 'safeHtml', // Allows formatting but strips scripts/XSS
            'excerpt' => 'string',   // Escapes ALL HTML
        ];
    }
}
```

---

## Validation Rules

The `rules()` method defines how fields should be validated before any processing occurs.

| Rule | Description |
|------|-------------|
| `required` | Field must be present and not empty. |
| `string` | Field must be a string. |
| `numeric` | Field must be a number. |
| `safe_html` | **(Security)** Blocks content containing `<script>`, `<iframe>`, or event handlers (like `onclick`). |
| `email` | Field must be a valid email address. |
| `max:N` | Maximum length or value. |
| `in:a,b` | Value must be one of the specified options. |

---

## Sanitization Rules

The `sanitizers()` method defines how data should be cleaned **after** validation passes. This ensures that even if validation is loose, your database remains clean.

### Available Sanitizers

- **`string`**: The most common. It uses `htmlspecialchars` to escape all HTML, making it safe for display.
- **`safeHtml`**: Specifically designed for Blog Content/Rich Text Editors.
    - **Allows**: `<p>`, `<a>`, `<b>`, `<strong>`, `<ul>`, `<li>`, `<img>`, `<table>`, etc.
    - **Strips**: `<script>`, `<style>`, `<iframe>`, `<object>`, and dangerous attributes like `onclick` or `javascript:` links.
- **`email`**: Sanitizes email addresses.
- **`int` / `float`**: Casts values to the correct numeric type.
- **`url`**: Sanitizes URLs.

---

## Usage in Controllers

To use a Form Request, simply type-hint it in your controller method.

```php
public function store(CreateBlogPostRequest $request)
{
    // 1. Run validation
    if (!$request->validate()) {
        return back()->withErrors($request->errors());
    }

    // 2. Get the CLEANED data
    // This applies all rules from the sanitizers() method automatically.
    $data = $request->sanitized();

    // Now $data['title'] is fully escaped
    // And $data['content'] has safe HTML tags for front-end display.
    
    Post::create($data);

    return redirect('/blog')->with('success', 'Post created safely!');
}
```

## Handling Validation Errors

When a request fails validation, you typically want to redirect back to the form with the error messages and the user's previously entered data (so they don't have to type it again).

### The Fluent Pattern
You can now chain your response in the controller:

```php
if (!$request->validate()) {
    return back()
        ->withErrors($request->errors())
        ->withInput(); // Flashes current input to session
}
```

### In Your View
Use the `@error` and `@old` directives to handle this gracefully:

```html
<form method="POST" action="/blog">
    <div class="form-group">
        <label>Title</label>
        <input type="text" name="title" value="@old('title')">
        
        @error('title')
            <span class="text-danger">{{ $errors->first('title') }}</span>
        @enderror
    </div>
</form>
```

---

## Flash Messages

Flash messages are one-time alerts stored in the session for the next request. They are perfect for "Success" or "Info" notifications.

The Plugs framework features a dedicated, premium flash system that supports titles, modern OKLCH colors, and animations.

👉 **[Read the Full Flash Messages Guide](file:///docs/features/flash-messages.md)**
