# Helper Functions

Plugs includes a variety of global "helper" PHP functions. Many of these functions are used by the framework itself; however, you are free to use them in your own applications if you find them convenient.

## String Manipulation

### `truncateText()`

Truncate text to a maximum length while preserving whole words.

```php
$text = 'The quick brown fox jumps over the lazy dog';
$truncated = truncateText($text, 20);

// "The quick brown..."
```

### `normalizeWhitespace()`

Clean and normalize whitespace in text, replacing multiple spaces with a single space.

```php
$text = "  Hello   World!  ";
$clean = normalizeWhitespace($text);

// "Hello World!"
```

### `cleanHtmlText()`

Strip HTML tags and decode HTML entities.

```php
$html = '<p>Hello &amp; World!</p>';
$text = cleanHtmlText($html);

// "Hello & World!"
```

### `generateSlug()`

Generate a URL-friendly slug from a given string. Supports international characters if the `intl` extension is installed.

```php
$slug = generateSlug('Hello World! This is a slug.');

// "hello-world-this-is-a-slug"
```

### `blank()`

Determine if the given value is "blank". Unlike PHP's native `empty()`, this returns `false` for boolean `false`, integer `0`, and string `'0'`. It returns `true` for `null`, empty arrays, whitespace-only strings, and empty `Countable` objects.

```php
blank('');      // true
blank('   ');   // true
blank(null);    // true
blank(collect()); // true

blank(0);       // false
blank(true);    // false
blank(false);   // false
```

### `filled()`

Determine if the given value is not blank.

```php
filled(0);      // true
filled(true);   // true
filled(false);  // true
filled('');     // false
```

## SEO Helpers

### `generateSeoTitle()`

Generate an SEO-friendly title from a string, truncated to a safe length (default 60 chars).

```php
$title = generateSeoTitle('My Awesome Article Title Is Very Long And Needs Truncating');
```

### `generateSeoDescription()`

Generate an SEO description from content, cleaning HTML and truncating intelligently.

```php
$description = generateSeoDescription($htmlContent);
```

### `generateSeoKeywords()`

Extract significant keywords from content and title.

```php
$keywords = generateSeoKeywords($content, $title);
```

## Miscellaneous Utilities

### `retry()`

Retry an operation a given number of times.

```php
$value = retry(3, function ($attempts) {
    // Attempt the operation...
    return performUnstableApiCall();
}, 100); // 100ms delay between attempts
```

### `value()`

Return the default value of the given value. If the value is a `Closure`, it will be executed and its result returned.

```php
$result = value(true);       // true
$result = value(function () {
    return false;
});                          // false
```

### `tap()`

Pass the value to the given callback and return the value.

```php
return tap($user, function ($user) {
    $user->update(['active' => true]);
});
```

### `with()`

Return the given value. If a callback is passed, it is executed with the value.

```php
$value = with(new User, function ($user) {
    return $user->name;
});
```

### `data_get()`

Get an item from an array or object using "dot" notation.

```php
$data = ['products' => ['desk' => ['price' => 100]]];

$price = data_get($data, 'products.desk.price');
// 100

$default = data_get($data, 'products.desk.discount', 0);
// 0
```

### `abort()`

Throw an HTTP exception.

```php
abort(403, 'Unauthorized action.');
```

## View & Security Helpers

### `e()`

Escape HTML special characters for body content.

```php
echo e('<b>Bold</b>'); // &lt;b&gt;Bold&lt;/b&gt;
```

### `attr()`

Escape values for use in HTML attributes.

```php
echo attr('value="quoted"'); // value=&quot;quoted&quot;
```

### `js()`

Securely encode data for use in JavaScript.

```php
<script>
  const config = {{ js($data) }};
</script>
```

### `safeUrl()`

Sanitize a URL for use in `href` or `src`, neutralizing dangerous protocols like `javascript:`.

```php
<a href="{{ safeUrl($unsafe) }}">Link</a>
```

### `u()`

Alias for `query()`. Escapes values for URL query parameters.

```php
<a href="/search?q={{ u($query) }}">Search</a>
```

### `query()`

Escapes values for URL query parameters using `urlencode`.

```php
$url = '/search?q=' . query($term);
```
