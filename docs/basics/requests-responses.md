# Requests & Responses

In Plugs, every HTTP interaction is centered around the **Request** entering the system and the **Response** leaving it. Both follow the **PSR-7** standard, ensuring consistency and interoperability.

---

## 1. The Request Object

The request object provides a simple interface for interacting with the current HTTP request.

### Accessing the Request
You can get the request instance in a controller via type-hinting or using the `request()` helper:
```php
public function store(Request $request) { ... }
```

### Retrieving Input
```php
// Get all input
$data = $request->all();

// Get specific input with fallback
$name = $request->input('name', 'Guest');

// Check for presence
if ($request->has('email')) { ... }
```

### Headers and Metadata
```php
$token = $request->header('X-Auth-Token');
$ip = $request->ip();
$isJson = $request->wantsJson();
```

---

## 2. The Response Object

Your routes or controllers must always return a response object.

### Creating Responses
You can use the `response()` helper or the `ResponseFactory` facade:

```php
// Basic string response
return response('Success', 200);

// JSON response
return response()->json(['status' => 'ok']);

// Custom headers
return response('Created', 201)->withHeader('X-Custom', 'Value');
```

### Redirects
```php
// Simple redirect
return redirect('/home');

// Redirect with flash data
return redirect('/login')->withError('Please log in first.');

// Redirect back
return back();
```

---

## 3. Specialized Responses

### File Responses
```php
// Display a file in the browser (e.g., an image)
return response()->file($storagePath);

// Force a download
return response()->download($pdfPath, 'invoice.pdf');
```

### Streaming
For large JSON datasets, use `streamJson()` to conserve memory:
```php
return response()->streamJson($largeCollection);
```

---

## Next Steps
Ensure your application is secure by learning about [Authentication](../security/authentication.md).
