# View Basics

Learn how to create, render, and share data with Plugs views.

## File Structure

Views are stored in `resources/` (or a configured path) with the `.plug.php` extension.
The default structure looks like this:

```
resources/
├── layouts/
├── components/
├── partials/
├── themes/
│   └── modern/
│       └── layouts/
└── welcome.plug.php
```

## 🎨 Themes

Plugs supports a powerful theming system. You can switch the look of your application by changing the `APP_THEME` environment variable.

### How it Works

1.  **Set the Theme**: Set `APP_THEME=modern` in your `.env` file.
2.  **Theme Lookup**: The framework first looks for the view in `resources/themes/modern/`.
3.  **Fallback**: If not found in the theme folder, it falls back to the default `resources/` folder.

**Example**:
If you request `view('home')` with `APP_THEME=dark`:

- Check: `resources/themes/dark/home.plug.php`
- Fallback: `resources/home.plug.php`

To disable theming, set `APP_THEME=default` or leave it empty.

## ⚙️ Configurable Paths

You can change the default view directory by setting `VIEW_PATH` in your `.env` file.

```env
VIEW_PATH=/path/to/your/custom/views
```

## Rendering Views

### From Controllers

Use the global `view()` helper or the `ViewManager` API:

```php
// Using the helper
return view('welcome', ['name' => 'John Doe']);

// Using the ViewManager instance (e.g. injected or from container)
return $viewManager->make('welcome', ['name' => 'John Doe']);
```

### Fluent Data Binding

Plugs supports **three modes** of passing data to views, letting you choose the right level of explicitness.

#### 1. Manual Mode (Default — Safe)

The standard, explicit approach. You pass a key-value array:

```php
return view('profile', [
    'user' => $user,
    'posts' => $posts,
]);
```

Or use `with()` with a key and value:

```php
return view('profile')
    ->with('user', $user)
    ->withData(['stats' => $stats]);
```

#### 2. Easy Mode — Selective Collection

Pass the **names** of variables to collect. Works with public controller properties **or** local variables:

```php
// From controller properties
class ProfileController extends Controller
{
    public $user;
    public $posts;

    public function show(int $id)
    {
        $this->user = User::find($id);
        $this->posts = Post::where('user_id', $id)->get();

        return view('profile')->with('user', 'posts');
    }
}
```

```php
// From local variables — pass get_defined_vars() as the last argument
public function show(int $id)
{
    $user = User::find($id);
    $posts = Post::where('user_id', $id)->get();

    return view('profile')->with('user', 'posts', get_defined_vars());
}
```

#### 3. Lazy Mode — Automatic Collection

Let the framework collect **everything** automatically:

```php
// From public controller properties
class ProfileController extends Controller
{
    public $user;
    public $posts;

    public function show(int $id)
    {
        $this->user = User::find($id);
        $this->posts = Post::where('user_id', $id)->get();

        // Collects all public properties ($user, $posts)
        return view('profile')->auto();
    }
}
```

```php
// From local variables — pass get_defined_vars()
public function show(int $id)
{
    $user = User::find($id);
    $posts = Post::all();

    // Collects $user, $posts (and $id) — filtered automatically
    return view('profile')->auto(get_defined_vars());
}
```

> [!NOTE]
> **Why `get_defined_vars()`?** PHP does not allow a function to read another function's local variables. By calling `get_defined_vars()` inside your controller method, you pass a snapshot of your local scope to the view. The framework then filters out framework internals, superglobals, and unsafe values.

#### View-Side Type Safety: `@needs`

Views can **declare their required variables** using the `@needs` directive. If the controller fails to provide any of them, the framework throws a clear error instead of a silent `undefined variable` notice.

```blade
@needs user posts

<h1>{{ $user->name }}</h1>
@foreach($posts as $post)
    <p>{{ $post->title }}</p>
@endforeach
```

> [!IMPORTANT]
> `@needs` is checked at **runtime** when the view renders. If any listed variable is missing, a `ViewException` is thrown with error code `PLUGS-VIEW-006`.

Syntax supports both space-separated and comma-separated names, with or without `$`:

```blade
@needs user, posts
@needs $user $posts
```

### Advanced Rendering

```php
// Check if a view exists
if ($view->exists('emails.welcome')) { ... }

// Render a view and get the string
$html = $view->render('template', $data);
```

---

## Shared Data

Share data across all views globally.

```php
// In a service provider or middleware
$view->share('company_name', 'Acme Corp');
```

---

## ⚡ Async Data Resolution

Plugs views can resolve `Promises` or `Fibers` in parallel before rendering.

```php
return view('dashboard', [
    'users' => $client->getAsync('/users'),
    'posts' => $client->getAsync('/posts'),
]);
```

The view will wait for both requests to complete simultaneously before rendering, providing `$users` and `$posts` as resolved data.

---

## 🏗️ Path Resolution

Plugs supports several extensions and naming conventions:

- `user.profile` -> `resources/user/profile.plug.php`
- Falls back to `.php` or `.html` if `.plug.php` is missing.
