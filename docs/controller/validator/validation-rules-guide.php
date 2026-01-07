<?php

declare(strict_types=1);

/**
 * =========================================================================
 * VALIDATION USAGE GUIDE
 * =========================================================================
 * 
 * This guide demonstrates all available validation rules in the Plugs framework.
 * The Validator class provides comprehensive validation with integration to ErrorMessage.
 */

namespace App\Examples;

use Plugs\Security\Validator;
use Plugs\View\ErrorMessage;

class ValidationExamples
{
    /**
     * ========================================
     * 1. BASIC USAGE
     * ========================================
     */
    public function basicUsage()
    {
        $data = [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ];

        $rules = [
            'email' => 'required|email',
            'password' => 'required|min:8',
        ];

        // Create validator
        $validator = new Validator($data, $rules);

        // Run validation
        if ($validator->validate()) {
            // Validation passed
            $cleanData = $validator->validated();
        } else {
            // Validation failed
            $errors = $validator->errors(); // Returns ErrorMessage instance
        }

        // Alternative methods
        if ($validator->fails()) {
            // Handle failure
        }

        if ($validator->passes()) {
            // Handle success
        }
    }

    /**
     * ========================================
     * 2. CUSTOM MESSAGES & ATTRIBUTES
     * ========================================
     */
    public function customMessagesAndAttributes()
    {
        $data = ['username' => '', 'email' => 'invalid'];

        $rules = [
            'username' => 'required|min:3',
            'email' => 'required|email',
        ];

        // Custom messages for specific field.rule combinations
        $messages = [
            'username.required' => 'Please provide a username',
            'username.min' => 'Username must be at least 3 characters',
            'email.email' => 'Please provide a valid email address',
        ];

        // Custom attribute names (for better error messages)
        $attributes = [
            'username' => 'Username',
            'email' => 'Email Address',
        ];

        $validator = new Validator($data, $rules, $messages, $attributes);
        $validator->validate();

        // Get first error for a field
        $firstError = $validator->first('username');

        // Get all errors
        $allErrors = $validator->errors();
    }

    /**
     * ========================================
     * 3. STRING VALIDATION RULES
     * ========================================
     */
    public function stringValidation()
    {
        $data = [
            'name' => 'John Doe',
            'username' => 'john_doe',
            'code' => 'ABC123',
            'tag' => 'hello',
        ];

        $rules = [
            // String type
            'name' => 'required|string|min:3|max:50',

            // Alpha-dash (letters, numbers, dashes, underscores)
            'username' => 'required|alpha_dash',

            // Alpha-numeric only
            'code' => 'required|alpha_num',

            // Alpha characters only
            'tag' => 'alpha',

            // Case validation
            'lowercase' => 'lowercase',
            'uppercase' => 'uppercase',

            // String patterns
            'starts' => 'starts_with:hello,hi',
            'ends' => 'ends_with:.com,.net',
            'contains' => 'contains:word',
        ];

        $validator = new Validator($data, $rules);
        $validator->validate();
    }

    /**
     * ========================================
     * 4. NUMERIC VALIDATION RULES
     * ========================================
     */
    public function numericValidation()
    {
        $data = [
            'age' => 25,
            'price' => 99.99,
            'quantity' => '10',
            'rating' => 4,
        ];

        $rules = [
            // Numeric (integer or float)
            'age' => 'required|numeric|min:18|max:120',

            // Integer only
            'quantity' => 'integer',

            // Between min and max
            'rating' => 'between:1,5',

            // Exact number of digits
            'pin' => 'digits:4',

            // Digits between min and max length
            'phone' => 'digits_between:10,15',

            // Size (exact value for numbers)
            'percentage' => 'size:100',
        ];

        $validator = new Validator($data, $rules);
        $validator->validate();
    }

    /**
     * ========================================
     * 5. EMAIL & URL VALIDATION
     * ========================================
     */
    public function emailAndUrlValidation()
    {
        $data = [
            'email' => 'user@domain.com',
            'website' => 'https://example.com',
            'api_url' => 'https://api.example.com',
        ];

        $rules = [
            // Email validation
            'email' => 'required|email',

            // URL validation (format check only)
            'website' => 'required|url',

            // Active URL (checks DNS records)
            'api_url' => 'active_url',
        ];

        $validator = new Validator($data, $rules);
        $validator->validate();
    }

    /**
     * ========================================
     * 6. DATE VALIDATION RULES
     * ========================================
     */
    public function dateValidation()
    {
        $data = [
            'birthdate' => '1990-01-15',
            'appointment' => '2024-12-31',
            'event_date' => '2024-06-01 14:30:00',
        ];

        $rules = [
            // Date format validation
            'birthdate' => 'required|date',

            // Specific date format
            'formatted_date' => 'date_format:Y-m-d',

            // Date before another date
            'start_date' => 'before:2024-12-31',

            // Date after another date
            'end_date' => 'after:2024-01-01',
        ];

        $validator = new Validator($data, $rules);
        $validator->validate();
    }

    /**
     * ========================================
     * 7. ARRAY VALIDATION RULES
     * ========================================
     */
    public function arrayValidation()
    {
        $data = [
            'tags' => ['php', 'laravel', 'javascript'],
            'colors' => ['red', 'blue'],
        ];

        $rules = [
            // Must be an array
            'tags' => 'required|array',

            // Array size constraints
            'colors' => 'array|min:2|max:5',

            // Array element must be in list
            'status' => 'in:active,pending,completed',

            // Array element must NOT be in list
            'role' => 'not_in:super_admin,system',
        ];

        $validator = new Validator($data, $rules);
        $validator->validate();
    }

    /**
     * ========================================
     * 8. WILDCARD / NESTED ARRAY VALIDATION
     * ========================================
     */
    public function wildcardValidation()
    {
        $data = [
            'items' => [
                ['name' => 'Item 1', 'price' => 10.99],
                ['name' => 'Item 2', 'price' => 25.50],
            ],
            'users' => [
                ['email' => 'user1@test.com', 'age' => 25],
                ['email' => 'user2@test.com', 'age' => 30],
            ],
        ];

        $rules = [
            // Validate each item's name
            'items.*.name' => 'required|string|max:100',

            // Validate each item's price
            'items.*.price' => 'required|numeric|min:0',

            // Nested validation
            'users.*.email' => 'required|email',
            'users.*.age' => 'required|integer|min:18',
        ];

        $messages = [
            'items.*.name.required' => 'Each item must have a name',
            'items.*.price.min' => 'Price must be positive',
        ];

        $validator = new Validator($data, $rules, $messages);
        $validator->validate();
    }

    /**
     * ========================================
     * 9. PASSWORD VALIDATION RULES
     * ========================================
     */
    public function passwordValidation()
    {
        $data = [
            'password' => 'MyP@ssw0rd',
            'password_confirmation' => 'MyP@ssw0rd',
        ];

        $rules = [
            // Strong password (preset: min 8 chars, upper, lower, number, symbol)
            'strong_pass' => 'strong_password',

            // Custom password requirements
            'custom_pass' => 'password:min_8,letters,numbers,symbols,mixed',

            // Password confirmation
            'password' => 'required|confirmed',
        ];

        $validator = new Validator($data, $rules);
        $validator->validate();
    }

    /**
     * ========================================
     * 10. FIELD COMPARISON RULES
     * ========================================
     */
    public function fieldComparison()
    {
        $data = [
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'email' => 'user@example.com',
            'backup_email' => 'backup@example.com',
        ];

        $rules = [
            // Must match another field (with _confirmation suffix)
            'password' => 'confirmed',

            // Same as another field (specify field name)
            'confirm_email' => 'same:email',

            // Different from another field
            'backup_email' => 'different:email',
        ];

        $validator = new Validator($data, $rules);
        $validator->validate();
    }

    /**
     * ========================================
     * 11. CONDITIONAL VALIDATION RULES
     * ========================================
     */
    public function conditionalValidation()
    {
        $data = [
            'shipping_method' => 'express',
            'shipping_address' => '',
            'payment_method' => 'credit_card',
        ];

        $rules = [
            // Required if another field has specific value
            'shipping_address' => 'required_if:shipping_method,express',

            // Required unless another field has specific value
            'pickup_location' => 'required_unless:shipping_method,standard',

            // Required with another field (if other field is present)
            'card_number' => 'required_with:payment_method',

            // Required without another field (if other field is absent)
            'cash_amount' => 'required_without:card_number',
        ];

        $validator = new Validator($data, $rules);
        $validator->validate();
    }

    /**
     * ========================================
     * 12. FILE UPLOAD VALIDATION
     * ========================================
     */
    public function fileValidation()
    {
        // Assuming uploaded file is wrapped in UploadedFile object
        $data = [
            'avatar' => $uploadedFile, // Instance of \Plugs\Upload\UploadedFile
            'document' => $uploadedFile,
        ];

        $rules = [
            // Must be a file
            'avatar' => 'required|file',

            // Must be an image
            'profile_pic' => 'required|image',

            // File type validation (by extension)
            'document' => 'mimes:pdf,doc,docx',

            // File size (in KB)
            'avatar' => 'size:1024', // Exactly 1MB
        ];

        $validator = new Validator($data, $rules);
        $validator->validate();
    }

    /**
     * ========================================
     * 13. IP ADDRESS VALIDATION
     * ========================================
     */
    public function ipValidation()
    {
        $data = [
            'ip' => '192.168.1.1',
            'ipv4' => '192.168.1.1',
            'ipv6' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
        ];

        $rules = [
            // Any valid IP (v4 or v6)
            'ip' => 'required|ip',

            // IPv4 only
            'ipv4' => 'ipv4',

            // IPv6 only
            'ipv6' => 'ipv6',
        ];

        $validator = new Validator($data, $rules);
        $validator->validate();
    }

    /**
     * ========================================
     * 14. MISCELLANEOUS VALIDATION RULES
     * ========================================
     */
    public function miscellaneousValidation()
    {
        $data = [
            'agree' => true,
            'json_data' => '{"key":"value"}',
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'timezone' => 'America/New_York',
        ];

        $rules = [
            // Boolean (true, false, 0, 1, "0", "1")
            'agree' => 'boolean',

            // Valid JSON string
            'json_data' => 'json',

            // UUID format
            'id' => 'uuid',

            // Valid timezone
            'timezone' => 'timezone',

            // Nullable (allows null values)
            'optional_field' => 'nullable|string',

            // Sometimes (only validates if present)
            'sometimes_field' => 'sometimes|email',
        ];

        $validator = new Validator($data, $rules);
        $validator->validate();
    }

    /**
     * ========================================
     * 15. REGEX PATTERN VALIDATION
     * ========================================
     */
    public function regexValidation()
    {
        $data = [
            'phone' => '+1-555-123-4567',
            'slug' => 'my-blog-post',
        ];

        $rules = [
            // Match pattern
            'phone' => 'regex:/^\+?[0-9\-]+$/',

            // Must NOT match pattern
            'slug' => 'not_regex:/[A-Z]/', // No uppercase allowed
        ];

        $validator = new Validator($data, $rules);
        $validator->validate();
    }

    /**
     * ========================================
     * 16. USING VALIDATED DATA
     * ========================================
     */
    public function usingValidatedData()
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'extra_field' => 'should not be included',
        ];

        $rules = [
            'name' => 'required|string',
            'email' => 'required|email',
        ];

        $validator = new Validator($data, $rules);

        if ($validator->validate()) {
            // Only returns validated fields (name and email)
            $cleanData = $validator->validated();
            // $cleanData = ['name' => 'John Doe', 'email' => 'john@example.com']
        }
    }

    /**
     * ========================================
     * 17. WORKING WITH ERROR MESSAGES
     * ========================================
     */
    public function workingWithErrors()
    {
        $validator = new Validator($data, $rules);
        $validator->validate();

        $errors = $validator->errors(); // Returns ErrorMessage instance

        // Check if any errors
        if ($errors->any()) {
            // Get first error for a field
            $emailError = $errors->first('email');

            // Get all errors for a field
            $allEmailErrors = $errors->get('email');

            // Get all errors as array
            $allErrors = $errors->all();

            // Check if specific field has error
            if ($errors->has('email')) {
                // Handle email error
            }

            // Add manual error
            $errors->add('custom_field', 'Custom error message');

            // Clear specific field errors
            $errors->forget('email');

            // Clear all errors
            $errors->clear();
        }
    }

    /**
     * ========================================
     * 18. CONTROLLER INTEGRATION EXAMPLE
     * ========================================
     */
    public function controllerExample($request, $response)
    {
        $data = $request->getFormData(); // or $request->getPostBody()

        $rules = [
            'name' => 'required|string|max:100',
            'email' => 'required|email',
            'age' => 'required|integer|min:18',
            'agree' => 'required|boolean',
        ];

        $messages = [
            'name.required' => 'Name field is required',
            'email.email' => 'Please enter a valid email',
            'age.min' => 'You must be at least 18 years old',
        ];

        $validator = new Validator($data, $rules, $messages);

        if ($validator->fails()) {
            // Return to form with errors
            return $this->view->render('form', [
                'errors' => $validator->errors(),
                'old' => $data,
            ]);
        }

        // Get only validated data
        $validData = $validator->validated();

        // Process the data...

        return $response->redirect('/success');
    }

    /**
     * ========================================
     * 19. COMBINING VALIDATOR WITH CUSTOM LOGIC
     * ========================================
     */
    public function combinedValidation($request)
    {
        $data = $request->getFormData();

        // Use Validator for standard rules
        $validator = new Validator($data, [
            'username' => 'required|alpha_dash|min:3',
            'email' => 'required|email',
        ]);

        $validator->validate();
        $errors = $validator->errors();

        // Add custom business logic validation
        if (!empty($data['username'])) {
            // Check database for existing username
            $exists = User::where('username', $data['username'])->exists();
            if ($exists) {
                $errors->add('username', 'This username is already taken');
            }
        }

        // Add complex validation logic
        if (!empty($data['email']) && !empty($data['secondary_email'])) {
            if ($data['email'] === $data['secondary_email']) {
                $errors->add('secondary_email', 'Secondary email must be different from primary');
            }
        }

        return $errors;
    }

    /**
     * ========================================
     * 20. AVAILABLE VALIDATION RULES SUMMARY
     * ========================================
     */
    public function allAvailableRules()
    {
        return [
            // Required
            'required',
            'required_if:field,value',
            'required_unless:field,value',
            'required_with:field1,field2',
            'required_without:field1,field2',

            // Type
            'string',
            'numeric',
            'integer',
            'array',
            'boolean',

            // Size/Length
            'min:value',
            'max:value',
            'between:min,max',
            'size:value',
            'digits:length',
            'digits_between:min,max',

            // Format
            'email',
            'url',
            'active_url',
            'ip',
            'ipv4',
            'ipv6',
            'json',
            'uuid',
            'timezone',

            // String Patterns
            'alpha',
            'alpha_num',
            'alpha_dash',
            'regex:pattern',
            'not_regex:pattern',
            'lowercase',
            'uppercase',
            'starts_with:value1,value2',
            'ends_with:value1,value2',
            'contains:value',

            // Date
            'date',
            'date_format:format',
            'before:date',
            'after:date',

            // Comparison
            'confirmed',
            'same:field',
            'different:field',
            'in:value1,value2,value3',
            'not_in:value1,value2,value3',

            // Files
            'file',
            'image',
            'mimes:ext1,ext2',

            // Password
            'password:min_8,letters,numbers,symbols,mixed',
            'strong_password',

            // Wildcards
            'field.*',
            'field.*.subfield',

            // Modifiers
            'nullable',
            'sometimes',

            // Database (when using HasValidation trait)
            'unique:table,column',
            'exists:table,column',
        ];
    }
}
