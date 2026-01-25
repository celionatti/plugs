# Pagination Documentation

The Plugs framework provides a powerful, flexible, and visually stunning pagination system. It supports everything from simple database pagination to advanced AJAX "Load More" patterns and SEO-optimized metadata.

## Table of Contents
- [Basic Usage](#basic-usage)
- [Paginate PlugModel](#paginate-plugmodel)
- [Displaying Results](#displaying-results)
- [Rendering Links](#rendering-links)
    - [Built-in Styles](#built-in-styles)
    - [View Templates (Tailwind/Bootstrap)](#view-templates)
- [Advanced Features](#advanced-features)
    - [Go to Page](#go-to-page)
    - [JSON-LD for SEO](#json-ld-for-seo)
    - [AJAX Load More](#ajax-load-more)
    - [Custom Presenters](#custom-presenters)
- [Configuration Options](#configuration-options)

---

## Basic Usage

### Paginating an Array
You can use the global `paginate()` helper to paginate a simple array of data.

```php
$data = [/* array of items */];
$paginator = paginate($data, 10); // 10 items per page

// In your view
foreach ($paginator->items() as $item) {
    echo $item->name;
}
```

## Paginate PlugModel

The most common use case is paginating database results using `PlugModel`.

```php
// In your controller
$users = User::paginate(15);
return view('users.index', compact('users'));
```

### Getting a Pagination Object
If you need the full `Pagination` object with all advanced methods specifically from a model:

```php
$pagination = User::paginateLinks(15);
```

## Displaying Results

In your `.plug.php` view, iterate over the `items()`:

```php
@foreach ($users['data'] as $user)
    <p>{{ $user->name }}</p>
@endforeach

{!! $users['paginator']->render() !!}
```

## Rendering Links

### Built-in Styles
Plugs comes with several built-in rendering styles using the "Shades of Green" theme.

| Style | Method | Description |
|---|---|---|
| **Standard** | `render()` | Full pagination with numbers and arrows. |
| **Floating** | `renderFloating()` | Modern, centered with glassmorphism. |
| **Simple** | `renderSimple()` | Previous/Next buttons only. |
| **Compact** | `renderCompact()` | Numbers only, no arrows or text. |

```php
// Render with the modern floating style
{!! $pagination->renderFloating() !!}
```

> [!TIP]
> To use the "Shades of Green" CSS, make sure to include `Pagination::getStyles()` in your head:
> ```html
> <head>
>     {!! \Plugs\Paginator\Pagination::getStyles() !!}
> </head>
> ```

### View Templates (Tailwind / Bootstrap) {#view-templates}
If your project uses a CSS framework, you can render pagination using specialized view templates.

```php
// Render using Tailwind CSS
{!! $pagination->links('pagination.tailwind') !!}

// Render using Bootstrap 5
{!! $pagination->links('pagination.bootstrap') !!}
```

## Advanced Features

### Go to Page
Allow users to jump to a specific page number.

```php
$pagination->setOptions(['show_goto' => true]);
echo $pagination->render();
```

### JSON-LD for SEO
Generate hidden schema.org metadata for search engines to index your paginated content better.

```php
// Add this in your view to inject metadata
{!! $pagination->renderJsonLd() !!}
```

### AJAX Load More
Enable infinite scroll or "Load More" buttons.

```php
// 1. Set options
$pagination->setOptions([
    'ajax_enabled' => true,
    'ajax_container' => '#user-list' // The ID of the element wrapping your items
]);

// 2. Render the button
echo $pagination->renderLoadMore();

// 3. Inject the JS script
{!! $pagination->getAjaxScript() !!}
```

### Custom Presenters
Completely override the rendering logic with a callback.

```php
$pagination->setPresenter(function($p) {
    return "<div class='custom'>Page " . $p->currentPage() . "</div>";
});

echo $pagination->render();
```

## Configuration Options

| Option | Default | Description |
|---|---|---|
| `max_links` | `7` | Max page numbers to show. |
| `theme` | `'green'` | UI theme (`green`, `dark`). |
| `rounded` | `true` | Use circle buttons. |
| `shadow` | `true` | Apply subtle shadows. |
| `animated` | `true` | Enable hover animations. |
| `prev_text` | `SVG` | Custom text or icon for previous. |
| `info_format` | `Showing...` | Template for the info text. |

```php
$pagination->setOptions([
    'max_links' => 5,
    'theme' => 'dark',
    'rounded' => false
]);
```
