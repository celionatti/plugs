# Route Model Binding

Route model binding provides a convenient way to automatically inject model instances directly into your routes. Instead of manually finding records by ID, the framework will automatically retrieve the model for you.

## Implicit Binding

When type-hinting a model in your controller method, the framework will automatically resolve the model instance:

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;

class UserController extends Controller
{
    /**
     * Show the user profile.
     * The $user model is automatically resolved from the {user} route parameter.
     */
    public function show(User $user)
    {
        return view('users.show', compact('user'));
    }
}
```

### Route Definition

```php
$router->get('/users/{user}', [UserController::class, 'show']);
```

When you visit `/users/123`, the framework will automatically fetch `User::findOrFail(123)` and inject it into your controller.

## Customizing the Key

By default, the framework uses the model's primary key (usually `id`). To use a different column, define the `getRouteKeyName` method on your model:

```php
<?php

namespace App\Models;

use Plugs\Base\Model\PlugModel;

class Post extends PlugModel
{
    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
```

Now `/posts/my-awesome-article` will resolve using the `slug` column instead of `id`.

## Model Not Found

If the model is not found, a `ModelNotFoundException` is thrown, which automatically converts to a 404 HTTP response.

### Customizing the Not Found Behavior

You can customize this by catching the exception in your error handler or by using `findOrFail` with custom logic in your controller.

## Multiple Model Parameters

You can bind multiple models in a single route:

```php
$router->get('/users/{user}/posts/{post}', [PostController::class, 'show']);
```

```php
public function show(User $user, Post $post)
{
    // Both models are automatically resolved
    return view('posts.show', compact('user', 'post'));
}
```

## Scoped Bindings

For nested resources, you may want to ensure the child model belongs to the parent. Define a `scopeByParent` method or use a relationship check in your controller:

```php
public function show(User $user, Post $post)
{
    // Ensure the post belongs to the user
    if ($post->user_id !== $user->id) {
        abort(404);
    }
    
    return view('posts.show', compact('user', 'post'));
}
```

## Explicit Binding

For more control, you can explicitly register bindings in your route service provider or middleware:

```php
// In a service provider or middleware
Route::bind('user', function ($value) {
    return User::where('username', $value)->firstOrFail();
});
```

## Soft Deleted Models

By default, soft deleted models are not retrieved. To include them, you can customize the resolver:

```php
public function getRouteKeyName(): string
{
    return 'id';
}

public static function resolveRouteBinding($value)
{
    return static::withTrashed()->findOrFail($value);
}
```

## Complete Example

### Model

```php
<?php

namespace App\Models;

use Plugs\Base\Model\PlugModel;

class Article extends PlugModel
{
    protected $table = 'articles';

    /**
     * Use slug for route binding instead of ID
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
```

### Routes

```php
$router->get('/articles/{article}', [ArticleController::class, 'show']);
$router->get('/articles/{article}/edit', [ArticleController::class, 'edit']);
$router->put('/articles/{article}', [ArticleController::class, 'update']);
$router->delete('/articles/{article}', [ArticleController::class, 'destroy']);
```

### Controller

```php
<?php

namespace App\Http\Controllers;

use App\Models\Article;

class ArticleController extends Controller
{
    public function show(Article $article)
    {
        // Article is automatically resolved by slug
        return view('articles.show', compact('article'));
    }

    public function edit(Article $article)
    {
        return view('articles.edit', compact('article'));
    }

    public function update(Article $article)
    {
        $article->update(request()->validated());
        
        return redirect("/articles/{$article->slug}");
    }

    public function destroy(Article $article)
    {
        $article->delete();
        
        return redirect('/articles');
    }
}
```
