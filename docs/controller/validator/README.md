# Validation Usage Guide

The Plugs framework provides a comprehensive validation system through the `Validator` class, which integrates seamlessly with the `ErrorMessage` class for error handling.

## Quick Start

```php
use Plugs\Security\Validator;

$data = $_POST; // or $request->getFormData()

$rules = [
    'email' => 'required|email',
    'password' => 'required|min:8',
];

$validator = new Validator($data, $rules);

if ($validator->validate()) {
    // Validation passed
    $cleanData = $validator->validated();
} else {
    // Validation failed
    $errors = $validator->errors();
}
```

## Documentation Files

This directory contains several example files to help you understand validation:

1. **basic-example.php** - Simple validation examples
2. **example.php** - Real-world controller examples with nested data
3. **validation-rules-guide.php** - Comprehensive guide covering ALL validation rules

## Available Validation Rules

### Basic Rules
- `required` - Field must be present and not empty
- `nullable` - Allows null values
- `sometimes` - Only validates if field is present

### Type Validation
- `string` - Must be a string
- `numeric` - Must be numeric (int or float)
- `integer` - Must be an integer
- `array` - Must be an array
- `boolean` - Must be boolean (true, false, 0, 1, "0", "1")

### Size/Length Constraints
- `min:value` - Minimum length/value
- `max:value` - Maximum length/value
- `between:min,max` - Between min and max
- `size:value` - Exact size/length
- `digits:length` - Exact number of digits
- `digits_between:min,max` - Digits between range

### String Format
- `email` - Valid email address
- `url` - Valid URL format
- `alpha` - Only alphabetic characters
- `alpha_num` - Only alphanumeric characters
- `alpha_dash` - Letters, numbers, dashes, underscores
- `lowercase` - Must be lowercase
- `uppercase` - Must be uppercase
- `starts_with:val1,val2` - Starts with one of the values
- `ends_with:val1,val2` - Ends with one of the values
- `contains:value` - Contains the value
- `regex:pattern` - Matches regex pattern
- `not_regex:pattern` - Does NOT match regex pattern

### Network
- `ip` - Valid IP address (v4 or v6)
- `ipv4` - Valid IPv4 address
- `ipv6` - Valid IPv6 address
- `active_url` - URL with valid DNS record

### Date & Time
- `date` - Valid date
- `date_format:format` - Date matches specific format
- `before:date` - Date before another date
- `after:date` - Date after another date
- `timezone` - Valid timezone identifier

### Field Comparison
- `confirmed` - Must match {field}_confirmation
- `same:field` - Must match another field
- `different:field` - Must be different from another field

### Lists & Arrays
- `in:val1,val2,val3` - Value must be in list
- `not_in:val1,val2,val3` - Value must NOT be in list
- `items.*` - Validate all array items
- `items.*.field` - Validate nested array fields

### Password Security
- `password:min_8,letters,numbers,symbols,mixed` - Custom password rules
- `strong_password` - Preset strong password (min 8, upper, lower, number, symbol)

### File Upload
- `file` - Must be an uploaded file
- `image` - Must be an image file
- `mimes:ext1,ext2` - File extension validation

### Conditional Validation
- `required_if:field,value` - Required if field equals value
- `required_unless:field,value` - Required unless field equals value
- `required_with:field1,field2` - Required if any field is present
- `required_without:field1,field2` - Required if all fields are absent

### Miscellaneous
- `json` - Valid JSON string
- `uuid` - Valid UUID format

### Database Rules (with HasValidation trait)
- `unique:table,column` - Value must be unique in database
- `exists:table,column` - Value must exist in database

## Custom Messages

You can provide custom error messages:

```php
$messages = [
    'email.required' => 'We need your email address',
    'email.email' => 'Please provide a valid email',
    'password.min' => 'Password must be at least 8 characters',
];

$validator = new Validator($data, $rules, $messages);
```

## Custom Attribute Names

Make error messages more user-friendly:

```php
$attributes = [
    'email_address' => 'Email',
    'user_name' => 'Username',
];

$validator = new Validator($data, $rules, [], $attributes);
```

## Working with Errors

```php
$errors = $validator->errors(); // Returns ErrorMessage instance

// Check if there are any errors
if ($errors->any()) {
    // Get first error for a field
    $firstError = $errors->first('email');
    
    // Get all errors for a field
    $fieldErrors = $errors->get('email');
    
    // Get all errors
    $allErrors = $errors->all();
    
    // Check if field has error
    if ($errors->has('email')) {
        // ...
    }
}
```

## Advanced Examples

### Nested Array Validation

```php
$data = [
    'items' => [
        ['name' => 'Item 1', 'price' => 10.99],
        ['name' => 'Item 2', 'price' => 25.50],
    ],
];

$rules = [
    'items.*.name' => 'required|string|max:100',
    'items.*.price' => 'required|numeric|min:0',
];
```

### Combining with Custom Logic

```php
$validator = new Validator($data, $rules);
$validator->validate();
$errors = $validator->errors();

// Add custom validation
if ($data['custom_check']) {
    $errors->add('field', 'Custom error message');
}
```

### In Controllers

```php
public function store($request, $response)
{
    $validator = new Validator($request->getFormData(), [
        'name' => 'required|string|max:100',
        'email' => 'required|email',
    ]);
    
    if ($validator->fails()) {
        return $this->view->render('form', [
            'errors' => $validator->errors(),
            'old' => $request->getFormData(),
        ]);
    }
    
    // Use validated data only
    $data = $validator->validated();
    
    // Process...
}
```

## Full Examples

See the example files in this directory for complete, working code:

- `basic-example.php` - Quick start examples
- `example.php` - Real-world controller usage
- `validation-rules-guide.php` - Complete reference with all rules

## Integration with ErrorMessage

The Validator class returns an `ErrorMessage` instance, which provides:

- `any()` - Check if there are errors
- `has($field)` - Check if field has errors
- `first($field)` - Get first error for field
- `get($field)` - Get all errors for field
- `all()` - Get all errors
- `add($field, $message)` - Add custom error
- `forget($field)` - Remove field errors
- `clear()` - Clear all errors

This seamless integration ensures consistent error handling throughout your application.
