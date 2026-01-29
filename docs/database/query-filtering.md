# Database: Query Filtering

The Plugs framework provides a powerful query filtering system that allows you to safely apply request parameters to database queries. This is especially useful for building APIs and admin interfaces where users can filter, sort, and search data.

## Overview

The filtering system consists of three main components:

1. **QueryFilter** - Base class for creating custom filter classes
2. **Filterable** - Trait to add filter support to models
3. **HasQueryBuilder::filter()** - Method that accepts both arrays and filter objects

## Creating a Filter Class

Create a filter class by extending `QueryFilter`:

```php
<?php

namespace App\Http\Filters;

use Plugs\Database\Filters\QueryFilter;

class PostFilter extends QueryFilter
{
    /**
     * Filter by status
     */
    public function status($value)
    {
        $this->builder->where('status', '=', $value);
    }

    /**
     * Filter by category
     */
    public function category($value)
    {
        $this->builder->where('category_id', '=', $value);
    }

    /**
     * Search by title
     */
    public function search($value)
    {
        $this->builder->where('title', 'LIKE', "%{$value}%");
    }

    /**
     * Filter by date range
     */
    public function from_date($value)
    {
        $this->builder->where('created_at', '>=', $value);
    }

    public function to_date($value)
    {
        $this->builder->where('created_at', '<=', $value);
    }
}
```

Each public method in your filter class corresponds to a request parameter. When a request contains `?status=published`, the `status()` method will be called with `"published"` as the argument.

## Using the Filterable Trait

Add the `Filterable` trait to your model:

```php
<?php

namespace App\Models;

use Plugs\Base\Model\PlugModel;
use Plugs\Database\Traits\Filterable;

class Post extends PlugModel
{
    use Filterable;
    
    protected $table = 'posts';
}
```

This adds a `scopeFilter()` method to your model that can be called from the query builder.

## Applying Filters in Controllers

### Using a Filter Class

```php
<?php

namespace App\Http\Controllers;

use App\Http\Filters\PostFilter;
use App\Models\Post;

class PostController extends Controller
{
    public function index()
    {
        $filter = new PostFilter(request());
        $posts = Post::filter($filter)->paginate(15);
        
        return view('posts.index', compact('posts'));
    }
}
```

### Using Array-Based Filtering (Legacy)

The `filter()` method also accepts a plain array for simple filtering:

```php
$posts = Post::filter($_GET)->paginate(15);
```

## Sorting

The `QueryFilter` base class includes built-in sorting support:

```php
// Request: ?sort=created_at&direction=desc

class PostFilter extends QueryFilter
{
    // Sorting is handled automatically by the base class
    // No additional code needed!
}
```

The base class reads `sort` and `direction` parameters and applies them to the query.

## Relationship Filtering with whereHas

Filter records based on related model conditions:

```php
// Get all posts that have at least one approved comment
$posts = Post::query()
    ->whereHas('comments', function($query) {
        $query->where('approved', true);
    })
    ->get();
```

You can also use this in your filter classes:

```php
class PostFilter extends QueryFilter
{
    public function has_comments($value)
    {
        if ($value === 'yes') {
            $this->builder->whereHas('comments');
        }
    }

    public function popular($value)
    {
        if ($value) {
            $this->builder->whereHas('likes', function($q) {
                $q->where('count', '>', 10);
            });
        }
    }
}
```

### orWhereHas

Use `orWhereHas` for OR conditions:

```php
$posts = Post::query()
    ->whereHas('comments')
    ->orWhereHas('likes')
    ->get();
```

## Pagination with Preserved Filters

When using pagination with filters, all current query parameters are automatically preserved in the pagination links:

```php
$posts = Post::filter($filter)->paginate(15);

// If the current URL is: /posts?status=published&category=tech&page=1
// The next page link will be: /posts?status=published&category=tech&page=2
```

This works automatically with:
- `paginate()`
- `simplePaginate()`
- `search()`

## Raw SQL Expressions

For advanced queries, you can use raw expressions:

```php
use Plugs\Database\Raw;

$query->where('views', '>', new Raw('likes * 2'));
```

## Complete Example

### Filter Class

```php
<?php

namespace App\Http\Filters;

use Plugs\Database\Filters\QueryFilter;

class ArticleFilter extends QueryFilter
{
    public function status($value)
    {
        $this->builder->where('status', '=', $value);
    }

    public function author($value)
    {
        $this->builder->where('author_id', '=', $value);
    }

    public function tag($value)
    {
        $this->builder->whereHas('tags', function($q) use ($value) {
            $q->where('slug', '=', $value);
        });
    }

    public function search($value)
    {
        $this->builder->where(function($q) use ($value) {
            $q->where('title', 'LIKE', "%{$value}%")
              ->orWhere('content', 'LIKE', "%{$value}%");
        });
    }

    public function featured($value)
    {
        if ($value === 'yes') {
            $this->builder->where('is_featured', true);
        }
    }
}
```

### Model

```php
<?php

namespace App\Models;

use Plugs\Base\Model\PlugModel;
use Plugs\Database\Traits\Filterable;

class Article extends PlugModel
{
    use Filterable;
    
    protected $table = 'articles';

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
```

### Controller

```php
<?php

namespace App\Http\Controllers;

use App\Http\Filters\ArticleFilter;
use App\Models\Article;

class ArticleController extends Controller
{
    public function index()
    {
        $filter = new ArticleFilter(request());
        
        $articles = Article::with(['author', 'tags'])
            ->filter($filter)
            ->paginate(20);
        
        return view('articles.index', compact('articles'));
    }
}
```

### View (Blade)

```html
@foreach($articles['data'] as $article)
    <article>
        <h2>{{ $article->title }}</h2>
        <p>By {{ $article->author->name }}</p>
    </article>
@endforeach

<!-- Pagination links automatically include all filters -->
<a href="{{ $articles['links']['prev'] }}">Previous</a>
<a href="{{ $articles['links']['next'] }}">Next</a>
```
