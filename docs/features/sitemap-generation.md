# Sitemap Generation

Plugs provides a built-in way to generate dynamic XML sitemaps for your application, helping search engines like Google index your content more efficiently.

## 1. Overview

The sitemap system allows you to:

- Generate dynamic entries for database-driven content (e.g., articles, products).
- Include static pages and custom URLs.
- Control `changefreq` and `priority` for better SEO indexing.
- Automatic XML delivery with correct content-type headers.

## 2. Implementation

To implement a sitemap, you typically need a Controller, a View, and a Route.

### Controller

The controller should fetch the necessary data and return a view with an XML content-type.

```php
namespace App\Http\Controllers;

use App\Models\Article;
use Plugs\Http\Response;

class SitemapController
{
    public function index()
    {
        $articles = Article::where('status', 'published')->get();

        $staticLinks = [
            ['url' => url('/'), 'lastmod' => date('Y-m-d'), 'freq' => 'daily', 'priority' => '1.0'],
            ['url' => url('/about'), 'lastmod' => date('Y-m-d'), 'freq' => 'monthly', 'priority' => '0.8'],
        ];

        return response()
            ->view('sitemap', compact('articles', 'staticLinks'))
            ->header('Content-Type', 'text/xml');
    }
}
```

### View (`resources/views/sitemap.plug.php`)

Your sitemap view must output valid XML.

> [!IMPORTANT]
> To avoid conflicts with PHP's `short_open_tag` configuration, always echo the XML declaration within a PHP block.

```php
@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach($staticLinks as $link)
    <url>
        <loc>{{ $link['url'] }}</loc>
        <lastmod>{{ $link['lastmod'] }}</lastmod>
        <changefreq>{{ $link['freq'] }}</changefreq>
        <priority>{{ $link['priority'] }}</priority>
    </url>
    @endforeach

    @foreach($articles as $article)
    <url>
        <loc>{{ route('articles.show', ['article' => $article->slug]) }}</loc>
        <lastmod>{{ $article->updated_at->format('Y-m-d') }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
    @endforeach
</urlset>
```

### Routing

Register a route to handle the sitemap request:

```php
$router->get('/sitemap.xml', [SitemapController::class, 'index']);
```

## 3. SEO Best Practices

- **Update Frequency**: Ensure your `lastmod` tags are accurate to help search engines prioritize crawling.
- **Priority**: Use priority tags from `0.0` to `1.0`. Your homepage should usually be `1.0`.
- **Large Sitemaps**: If you have more than 50,000 URLs, you should consider using a Sitemap Index to group multiple sitemap files.

## 4. Troubleshooting

### Syntax error: unexpected token 'version'

If you see an error related to `version` in your sitemap view, it is likely because the `<?xml` declaration is being misinterpreted by PHP. Ensure you use the `@php echo` syntax mentioned in the [View section](#view-resourcesviewssitemapplugphp).
