# Helper Directives Usage Guide

## Integration Steps

### 1. Add Helper Methods to ViewCompiler

Copy all the methods from `ViewCompilerHelpers` into your `ViewCompiler` class.

### 2. Update compileNonComponentContent()

Add this line after `compileJson()`:

```php
private function compileNonComponentContent(string $content): string
{
    // ... existing code ...
    
    // 9. Utilities
    $content = $this->compileJson($content);
    $content = $this->compileHelperDirectives($content); // ADD THIS LINE
    $content = $this->compileErrorDirectives($content);
    
    // ... rest of the code ...
}
```

---

## 📅 Date & Time Directives

### @date

```html
<!-- Format: Y-m-d -->
<p>Published: @date($post->created_at, 'Y-m-d')</p>
<!-- Output: Published: 2024-01-15 -->

<!-- Format: m/d/Y -->
<p>Date: @date($post->created_at, 'm/d/Y')</p>
<!-- Output: Date: 01/15/2024 -->
```

### @time

```html
<!-- Default format: H:i:s -->
<p>Time: @time($post->created_at)</p>
<!-- Output: Time: 14:30:45 -->

<!-- Custom format -->
<p>Time: @time($post->created_at, 'g:i A')</p>
<!-- Output: Time: 2:30 PM -->
```

### @datetime

```html
<!-- Default format: Y-m-d H:i:s -->
<p>Created: @datetime($post->created_at)</p>
<!-- Output: Created: 2024-01-15 14:30:45 -->

<!-- Custom format -->
<p>Posted: @datetime($post->created_at, 'M j, Y \a\t g:i A')</p>
<!-- Output: Posted: Jan 15, 2024 at 2:30 PM -->
```

### @humanDate

```html
<p>@humanDate($post->created_at)</p>
<!-- Output: January 15, 2024 -->
```

### @diffForHumans

```html
<span class="text-muted">@diffForHumans($comment->created_at)</span>
<!-- Output: 2 hours ago -->
<!-- Output: 3 days ago -->
<!-- Output: 1 week ago -->
```

---

## 💰 Number & Currency Directives

### @number

```html
<!-- No decimals -->
<p>Views: @number($post->views)</p>
<!-- Output: Views: 1,234,567 -->

<!-- With decimals -->
<p>Rating: @number($product->rating, 2)</p>
<!-- Output: Rating: 4.75 -->
```

### @currency

```html
<!-- Default: USD -->
<p>Price: @currency($product->price)</p>
<!-- Output: Price: $99.99 -->

<!-- Custom currency -->
<p>Price: @currency($product->price, 'EUR')</p>
<!-- Output: Price: €99.99 -->

<p>Price: @currency($product->price, 'NGN')</p>
<!-- Output: Price: ₦99.99 -->

<p>Price: @currency($product->price, 'GBP')</p>
<!-- Output: Price: £99.99 -->
```

### @percent

```html
<!-- Default: 2 decimals -->
<p>Discount: @percent($discount)</p>
<!-- Output: Discount: 25.50% -->

<!-- Custom decimals -->
<p>Complete: @percent($progress, 0)</p>
<!-- Output: Complete: 75% -->
```

---

## 📝 String Manipulation Directives

### @upper / @lower

```html
<h1>@upper($title)</h1>
<!-- Output: WELCOME TO MY BLOG -->

<p>@lower($category->name)</p>
<!-- Output: technology -->
```

### @title

```html
<h2>@title($post->title)</h2>
<!-- Input: "hello world from php" -->
<!-- Output: Hello World From Php -->
```

### @ucfirst

```html
<p>@ucfirst($sentence)</p>
<!-- Input: "hello world" -->
<!-- Output: Hello world -->
```

### @slug

```html
<input type="hidden" name="slug" value="@slug($category->name)">
<!-- Input: "Hello World & Tech!" -->
<!-- Output: hello-world-tech -->
```

### @truncate

```html
<!-- Truncate to 50 characters -->
<p>@truncate($post->content, 50)</p>
<!-- Output: Lorem ipsum dolor sit amet, consectetur adip... -->

<!-- Custom ending -->
<p>@truncate($post->content, 50, ' [Read more]')</p>
<!-- Output: Lorem ipsum dolor sit amet, consectetur [Read more] -->
```

### @excerpt

```html
<!-- Word-aware truncation (default: 150 chars) -->
<p>@excerpt($post->content)</p>

<!-- Custom length -->
<p>@excerpt($post->content, 100)</p>
<!-- Truncates at word boundary, strips HTML tags -->
```

---

## 📊 Array & Collection Directives

### @count

```html
<span class="badge">@count($comments) Comments</span>
<!-- Output: 42 Comments -->

@if(@count($items) > 0)
    <p>Found @count($items) items</p>
@endif
```

### @join / @implode

```html
<p>Tags: @join($post->tags, ', ')</p>
<!-- Output: Tags: php, laravel, web development -->

<p>Authors: @join($authors, ' & ')</p>
<!-- Output: Authors: John & Jane & Bob -->
```

---

## 🔧 Utility Directives

### @default

```html
<p>Bio: @default($user->bio, 'No bio available')</p>
<!-- Shows bio if exists, otherwise shows default text -->

<img src="@default($user->avatar, '/images/default-avatar.png')">
```

### @route (if you have a route() helper)

```html
<a href="@route('post.show', ['id' => $post->id])">Read More</a>
<!-- Output: <a href="/posts/123">Read More</a> -->

<form action="@route('post.update', ['id' => $post->id])" method="POST">
```

### @asset

```html
<link rel="stylesheet" href="@asset('css/style.css')">
<!-- Output: <link rel="stylesheet" href="http://example.com/css/style.css"> -->

<script src="@asset('js/app.js')"></script>
<!-- Output: <script src="http://example.com/js/app.js"></script> -->
```

### @url

```html
<a href="@url('about')">About Us</a>
<!-- Output: <a href="http://example.com/about">About Us</a> -->

<meta property="og:url" content="@url('posts/' . $post->slug)">
```

### @config

```html
<title>@config('app.name')</title>
<!-- Output: <title>My Application</title> -->

<p>Environment: @config('app.env', 'production')</p>
```

### @env

```html
<p>Version: @env('APP_VERSION', '1.0.0')</p>
<!-- Gets from $_ENV or getenv() -->

@if(@env('APP_DEBUG', 'false') === 'true')
    <div class="debug-bar">Debug Mode</div>
@endif
```

---

## 🎨 Real-World Examples

### Blog Post Card

```html
<div class="card">
    <div class="card-body">
        <h2 class="card-title">@title($post->title)</h2>
        
        <p class="text-muted">
            <small>
                By @ucfirst($post->author->name) 
                • @diffForHumans($post->created_at)
                • @count($post->comments) comments
            </small>
        </p>
        
        <p class="card-text">@excerpt($post->content, 200)</p>
        
        <div class="d-flex justify-content-between align-items-center">
            <div class="badge-group">
                @foreach($post->tags as $tag)
                <span class="badge bg-primary">@lower($tag)</span>
                @endforeach
            </div>
            
            <span class="text-muted">@number($post->views) views</span>
        </div>
        
        <a href="@url('posts/' . $post->slug)" class="btn btn-primary">
            Read More
        </a>
    </div>
</div>
```

### Product Card

```html
<div class="product-card">
    <h3>@title($product->name)</h3>
    
    <div class="price">
        @if($product->discount > 0)
            <span class="original-price text-decoration-line-through">
                @currency($product->original_price)
            </span>
            <span class="sale-price text-danger">
                @currency($product->price)
            </span>
            <span class="badge bg-success">
                @percent($product->discount, 0) OFF
            </span>
        @else
            <span class="price">@currency($product->price)</span>
        @endif
    </div>
    
    <p>@truncate($product->description, 100)</p>
    
    <div class="meta">
        <span>⭐ @number($product->rating, 1)/5.0</span>
        <span>(@count($product->reviews) reviews)</span>
        <span>@number($product->stock) in stock</span>
    </div>
</div>
```

### User Profile

```html
<div class="profile">
    <h1>@title($user->name)</h1>
    
    <p class="bio">
        @default($user->bio, 'This user hasn\'t written a bio yet.')
    </p>
    
    <div class="stats">
        <div>
            <strong>@number($user->followers)</strong>
            <span>Followers</span>
        </div>
        <div>
            <strong>@number($user->following)</strong>
            <span>Following</span>
        </div>
        <div>
            <strong>@count($user->posts)</strong>
            <span>Posts</span>
        </div>
    </div>
    
    <p class="text-muted">
        Member since @humanDate($user->created_at)
    </p>
    
    <p class="text-muted">
        Last seen @diffForHumans($user->last_seen_at)
    </p>
</div>
```

### Analytics Dashboard

```html
<div class="dashboard">
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Revenue</h3>
            <p class="value">@currency($stats->revenue)</p>
            <p class="change text-success">
                +@percent($stats->revenue_growth) from last month
            </p>
        </div>
        
        <div class="stat-card">
            <h3>Users</h3>
            <p class="value">@number($stats->users)</p>
            <p class="info">@number($stats->active_users) active today</p>
        </div>
        
        <div class="stat-card">
            <h3>Conversion Rate</h3>
            <p class="value">@percent($stats->conversion_rate, 2)</p>
        </div>
    </div>
    
    <p class="updated-at">
        Last updated: @datetime($stats->updated_at, 'M j, Y g:i A')
    </p>
</div>
```

---

## 🎯 Tips & Best Practices

1. **Chain directives carefully**: Some directives output escaped HTML, so order matters
2. **Use @truncate for previews**: Better UX than long text blocks
3. **@diffForHumans for recent dates**: Great for comments, posts, notifications
4. **@humanDate for old dates**: Better for birthdays, historical dates
5. **Always provide defaults**: Use @default() for optional fields
6. **Currency consistency**: Define a default currency in config
7. **Performance**: These compile to pure PHP, so they're fast!

---

## 🔮 Custom Directives

You can still add your own custom directives:

```php
// In your bootstrap or controller
$viewEngine->directive('icon', function ($expression) {
    return "<?php echo '<i class=\"bi bi-{$expression}\"></i>'; ?>";
});
```

Use in views:

```html
<button>@icon('heart') Like</button>
<!-- Output: <button><i class="bi bi-heart"></i> Like</button> -->
```
