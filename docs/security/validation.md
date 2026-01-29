# Validation

Plugs provides a powerful validation system for validating incoming request data. The `Validator` class offers a comprehensive set of validation rules and supports conditional validation.

## Basic Usage

### Using the Validator Class

```php
use Plugs\Security\Validator;

$validator = new Validator();

$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 25
];

$rules = [
    'name' => 'required|string|max:255',
    'email' => 'required|email',
    'age' => 'required|integer|min:18'
];

if ($validator->validate($data, $rules)) {
    // Validation passed
    $validated = $validator->validated();
} else {
    // Validation failed
    $errors = $validator->errors();
}
```

## Available Validation Rules

### Basic Rules

| Rule | Description |
|------|-------------|
| `required` | Field must be present and not empty |
| `nullable` | Field can be null (stops validation if null) |
| `sometimes` | Only validate if field is present |
| `string` | Field must be a string |
| `integer` | Field must be an integer |
| `numeric` | Field must be numeric |
| `boolean` | Field must be boolean |
| `array` | Field must be an array |

### String Rules

| Rule | Description |
|------|-------------|
| `alpha` | Field must contain only letters |
| `alpha_num` | Field must contain only letters and numbers |
| `alpha_dash` | Field may contain letters, numbers, dashes, and underscores |
| `starts_with:foo,bar` | Field must start with one of the given values |
| `ends_with:foo,bar` | Field must end with one of the given values |
| `lowercase` | Field must be lowercase |
| `uppercase` | Field must be uppercase |

### Size Rules

| Rule | Description |
|------|-------------|
| `min:value` | Minimum length (string) or value (numeric) |
| `max:value` | Maximum length (string) or value (numeric) |
| `size:value` | Exact size/length |
| `between:min,max` | Value must be between min and max |
| `gt:field` | Field must be greater than another field |
| `gte:field` | Field must be greater than or equal to another field |
| `lt:field` | Field must be less than another field |
| `lte:field` | Field must be less than or equal to another field |

### Format Rules

| Rule | Description |
|------|-------------|
| `email` | Field must be a valid email address |
| `url` | Field must be a valid URL |
| `ip` | Field must be a valid IP address |
| `ipv4` | Field must be a valid IPv4 address |
| `ipv6` | Field must be a valid IPv6 address |
| `mac_address` | Field must be a valid MAC address |
| `uuid` | Field must be a valid UUID |
| `ulid` | Field must be a valid ULID |
| `json` | Field must be valid JSON |
| `regex:pattern` | Field must match the given regex pattern |

### Date Rules

| Rule | Description |
|------|-------------|
| `date` | Field must be a valid date |
| `date_format:format` | Field must match date format |
| `before:date` | Field must be before the given date |
| `after:date` | Field must be after the given date |
| `before_or_equal:date` | Field must be before or equal to date |
| `after_or_equal:date` | Field must be after or equal to date |

### Numeric Rules

| Rule | Description |
|------|-------------|
| `digits:value` | Field must be numeric with exact digit count |
| `digits_between:min,max` | Field's digit count must be between min and max |
| `min_digits:value` | Field must have at least n digits |
| `max_digits:value` | Field must have at most n digits |
| `multiple_of:value` | Field must be a multiple of the given value |
| `decimal:min,max` | Field must have specified decimal places |

### Comparison Rules

| Rule | Description |
|------|-------------|
| `same:field` | Field must match another field |
| `different:field` | Field must differ from another field |
| `confirmed` | Field must have a matching `{field}_confirmation` field |
| `in:foo,bar,baz` | Field must be one of the listed values |
| `not_in:foo,bar,baz` | Field must not be one of the listed values |

### Database Rules

| Rule | Description |
|------|-------------|
| `unique:table,column` | Field must be unique in the database table |
| `exists:table,column` | Field must exist in the database table |

### Acceptance Rules

| Rule | Description |
|------|-------------|
| `accepted` | Field must be "yes", "on", "1", or true (for checkboxes) |
| `declined` | Field must be "no", "off", "0", or false |

### Presence Rules

| Rule | Description |
|------|-------------|
| `present` | Field must be present (can be empty) |
| `prohibited` | Field must not be present |
| `required_if:field,value` | Required if another field has a specific value |
| `required_unless:field,value` | Required unless another field has a specific value |
| `required_with:field1,field2` | Required if any of the specified fields are present |
| `required_without:field1,field2` | Required if any of the specified fields are absent |

### File Rules

| Rule | Description |
|------|-------------|
| `file` | Field must be a successfully uploaded file |
| `image` | Field must be an image (jpg, png, gif, bmp, svg, webp) |
| `mimes:jpeg,png,pdf` | Field must have one of the specified MIME types |

## Conditional Validation

### Sometimes Rule

Only validate the field if it's present in the input:

```php
$rules = [
    'nickname' => 'sometimes|string|max:50'
];

// If 'nickname' is not in the input, no validation runs
// If 'nickname' IS in the input, it must be a string with max 50 chars
```

### Nullable Rule

Allow the field to be null:

```php
$rules = [
    'middle_name' => 'nullable|string|max:100'
];

// If middle_name is null or empty, validation passes
// If middle_name has a value, it must be a valid string
```

### Required If/Unless

```php
$rules = [
    'payment_method' => 'required',
    'card_number' => 'required_if:payment_method,credit_card',
    'bank_account' => 'required_unless:payment_method,credit_card'
];
```

## Custom Error Messages

You can customize error messages per rule:

```php
$rules = [
    'email' => 'required|email|unique:users,email'
];

$messages = [
    'email.required' => 'We need your email address.',
    'email.email' => 'That doesn\'t look like a valid email.',
    'email.unique' => 'This email is already registered.'
];

$validator->validate($data, $rules, $messages);
```

## Working with Errors

### Getting All Errors

```php
$errors = $validator->errors();
// Returns: ['email' => ['The email field is required.']]
```

### Getting First Error for a Field

```php
$firstError = $validator->errors()['email'][0] ?? null;
```

### Checking if Validation Failed

```php
if (!$validator->validate($data, $rules)) {
    return response()->json(['errors' => $validator->errors()], 422);
}
```

## Form Request Validation

For cleaner controllers, create dedicated request classes:

```php
<?php

namespace App\Http\Requests;

use Plugs\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'age' => 'nullable|integer|gte:18'
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already taken.'
        ];
    }
}
```

Use in your controller:

```php
public function store(StoreUserRequest $request)
{
    // Request is automatically validated
    $user = User::create($request->validated());
    
    return redirect('/users/' . $user->id);
}
```

## Complete Example

```php
<?php

namespace App\Http\Controllers;

use Plugs\Security\Validator;

class RegistrationController extends Controller
{
    public function store()
    {
        $validator = new Validator();
        
        $rules = [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'age' => 'required|integer|gte:18|lte:120',
            'phone' => 'nullable|regex:/^[0-9]{10,15}$/',
            'terms' => 'accepted',
            'referral_code' => 'sometimes|string|exists:referrals,code',
            'avatar' => 'nullable|image|max:2048'
        ];
        
        $data = request()->all();
        
        if (!$validator->validate($data, $rules)) {
            return back()
                ->withErrors($validator->errors())
                ->withInput();
        }
        
        // Create the user...
        $user = User::create($validator->validated());
        
        return redirect('/dashboard');
    }
}
```
