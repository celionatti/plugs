# SEO & Sitemaps

Optimizing your application for search engines is streamlined with Plugs' automated sitemap generation and performance-focused metadata management.

---

## 1. Sitemap Generation

Generate dynamic, search-engine friendly sitemaps with a single command.

```bash
php theplugs sitemap:generate
```

### Configuration
Define which models and routes should be included in your `config/sitemap.php`:

```php
return [
    'models' => [
        App\Models\Post::class => [
            'frequency' => 'daily',
            'priority' => 0.8,
        ],
    ],
];
```

---

## 2. Meta Tag Management

Easily manage SEO metadata from your controllers or layouts:

```php
// In layout
<title>@yield('title', 'My App')</title>
<meta name="description" content="@yield('meta_description')">
```

---

## Next Steps
Enhance your content with the [Rich Text Editor](./editor.md).
