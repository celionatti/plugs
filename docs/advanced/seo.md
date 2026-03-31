# SEO & Metadata Management

The **Plugs Framework** provides a zero-config, high-performance SEO system that automates metadata generation, canonical URL resolution, and search engine file management (Sitemaps and Robots.txt).

---

## 1. Quick Start with `@seo`

The easiest way to render SEO metadata in your application is using the `@seo` directive in your layout's `<head>`.

```html
<!-- resources/views/layouts/app.plug.php -->
<head>
    @seo
</head>
```

The `@seo` directive automatically pulls data from the `SEO` facade. If no data is set, it uses the defaults defined in `config/seo.php`.

### Manual Overrides
You can pass custom data directly to the directive:

```html
@seo(['title' => 'Custom Page Title', 'description' => 'A custom description just for this page.'])
```

### PRO-TIP: Native `<seo />` Tag
For a cleaner, more HTML-friendly syntax, use the native `<seo />` tag:

```html
<!-- In your layout.plug.php -->
<head>
    <!-- Simple usage -->
    <seo />
    
    <!-- With dynamic overrides -->
    <seo title="Special Page" :description="$post->summary" />
</head>
```
The tag and the directive are fully interchangeable and support all the same parameters.

---

## 2. Automated Model SEO (`HasSeo`)

If you want your models (like Posts or Products) to handle their own SEO metadata, use the `HasSeo` trait.

### Setup
Add the trait to your model and optionally define a `seoMap()`:

```php
namespace App\Models;

use Plugs\Database\Traits\HasSeo;
use Plugs\Base\Model\PlugModel;

class Post extends PlugModel
{
    use HasSeo;

    /**
     * Map model fields to SEO attributes.
     */
    public function seoMap(): array
    {
        return [
            'title'       => 'post_title',
            'description' => 'summary',
            'image'       => 'cover_image',
        ];
    }
}
```

### Usage in Controller
Just call `toSeo()` on a model instance to prepare the metadata for the view:

```php
public function show($id)
{
    $post = Post::find($id);
    
    // Automatically sets title, description, image, and canonical URL
    $post->toSeo();

    return view('posts.show', compact('post'));
}
```

---

## 3. SEO Support Class

The `Plugs\Support\SEO` class is the engine behind the system. It handles social tags (OpenGraph, Twitter) and canonical URLs automatically.

### Auto-Canonical
By default, the framework resolves the current URL as the canonical link. You can manually set it too:

```php
SEO::setUrl('https://example.com/custom-url');
```

### Social & Rich Results
You can add custom JSON-LD for rich search results:

```php
SEO::addJsonLd([
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => 'Plugs Framework',
    'url' => 'https://plugs.dev'
]);
```

---

## 4. Automatic Sitemaps & Robots.txt

Plugs handles search engine management files automatically. Unlike other frameworks, you **don't need to run a command** to generate these files; they are served dynamically based on your configuration.

### How to Visit
Once your application is running, you can visit these URLs directly:

- **Sitemap**: `your-app.com/sitemap.xml`
- **Robots**: `your-app.com/robots.txt`

### Dynamic Sitemap Generation
The framework generates a Google-compatible `sitemap.xml` on the fly. You can also customize the generation programmatically in your own controllers if needed:

```php
use Plugs\Support\Sitemap;

$xml = Sitemap::create()
    ->add(url('/'), date('Y-m-d'), 'daily', 1.0)
    ->add(url('/blog'), date('Y-m-d'), 'weekly', 0.8)
    ->render();

return response($xml)->withHeader('Content-Type', 'application/xml');
```

### Dynamic Robots.txt Management
Your `robots.txt` is also served dynamically and automatically includes a link to your sitemap:

```php
use Plugs\Support\Robots;

$robots = Robots::create()
    ->userAgent('*')
    ->allow('/')
    ->disallow('/admin')
    ->sitemap(url('/sitemap.xml'))
    ->render();

return response($robots)->withHeader('Content-Type', 'text/plain');
```

---

## 5. Zero-Config Defaults

The framework provides professional SEO defaults out of the box. Even without a `config/seo.php` file, Plugs will:
- ✅ Automatically enable and serve `/sitemap.xml`.
- ✅ Automatically enable and serve `/robots.txt`.
- ✅ Automatically resolve canonical URLs.

To customize these, publish your config using:
```bash
php theplugs config:publish seo
```

---

## 6. Metadata Utilities

The `Plugs\Utils\Seo` helper provides smart processing for raw content:

- **`generateTitle($text)`**: Truncates and cleans text for optimal title length (60 chars).
- **`generateDescription($content)`**: Strips HTML and creates a concise summary (160 chars).
- **`generateKeywords($content)`**: Extracts the most frequent relevant words from your text.

---

## Next Steps
- Learn more about **[View Directives](../views/engine.md)**.
- Explore **[Schema & Data Models](../database/orm.md)**.
