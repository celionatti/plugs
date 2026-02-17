# Asset Management

The **Asset Manager** is a powerful utility for managing, compiling, and optimizing your application's CSS, JavaScript, and image assets. It handles minification, versioning, CDN integration, and modern security practices like SRI and CSP.

---

## Basic Usage

The `AssetManager` is typically accessed via the service container or as a standalone utility.

### Registering Assets

You can register CSS and JS files with dependency management.

```php
$assets = new AssetManager();

// Register CSS with dependency
$assets->css('main', 'css/app.css');
$assets->css('theme', 'css/dark.css', ['main']);

// Register JS
$assets->js('app', 'js/main.js');
```

### Compiling and Minifying

Compile multiple files into one, with optional minification (enabled by default in production).

```php
// Compiles and minifies to assets/cache/compiled-[hash].css
$url = $assets->compileCSS(['main', 'theme']);

echo '<link rel="stylesheet" href="' . $url . '">';
```

---

## Advanced Features

### Cache Busting & Versioning

Plugs automatically appends a version hash to your asset URLs to ensure users always get the latest version after a deployment.

```php
echo $assets->url('css/style.css'); // Outputs: /css/style.css?v=8a2b3c4d
```

### CDN Support

Easily serve your assets from a CDN by setting a global CDN URL.

```php
$assets->setCdnUrl('https://cdn.example.com');
echo $assets->url('js/app.js'); // Outputs: https://cdn.example.com/js/app.js
```

### Security (SRI & CSP)

Plugs supports **Subresource Integrity (SRI)** and **Content Security Policy (CSP)** nonces.

```php
// Enable SRI
$assets->useSri(true);

// Set CSP Nonce
$assets->setNonce('random-nonce-value');

// Generate tags with integrity and nonce attributes
echo $assets->tags(['js/app.js'], 'js');
```

### Image Optimization

Resize and optimize images on the fly. Optimized images are cached for performance.

```php
// Simple usage
$url = $assets->image('uploads/photo.jpg', [
    'width' => 800,
    'height' => 600,
    'quality' => 80,
    'format' => 'webp'
]);

echo '<img src="' . $url . '">';
```

### Resource Hints

Improve performance by preloading or prefetching critical assets.

```php
echo $assets->resourceHint('/fonts/brand.woff2', 'preload');
```

### Precompression

Generate Gzip and Brotli versions of your assets automatically during compilation.

```php
$assets->setPrecompress(true);
$assets->compileJS(['app']); // Generates app.js, app.js.gz, and app.js.br
```

---

## Configuration

The `AssetManager` automatically configures itself based on your `APP_ENV`.

- **Development**: Minification and combination are disabled for easier debugging.
- **Production**: Assets are automatically combined, minified, and versioned.

You can override these settings manually:

```php
$assets->setMinify(false)
       ->setCombine(true)
       ->setVersioning(true);
```

---

## 🛠️ Framework Assets

Plugs provides several core assets for internal features like the Rich Text Editor and Lazy Loading. These are managed separately from your application assets.

### Publishing Assets

Use the CLI to publish framework assets to your `public/plugs` directory:

```bash
# Publish and minify all core assets
php theplugs make:plugs-assets --min
```

This will publish:

- `plugs-editor.js` & `plugs-editor.css`
- `plugs-lazy.js`

### Usage in Layouts

Once published, you can include them in your layouts using standard script/link tags:

```html
<link rel="stylesheet" href="/plugs/plugs-editor.min.css" />
<script src="/plugs/plugs-editor.min.js"></script>
<script src="/plugs/plugs-lazy.min.js"></script>
```
