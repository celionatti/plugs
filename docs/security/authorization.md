# Authorization (RBAC)

The Plugs framework provides a flexible and powerful Role-Based Access Control (RBAC) system. It allows you to manage user permissions and roles without forcing a specific database schema or implementation style.

## Introduction

Authorization in Plugs is built around two main concepts:

1.  **Gates**: Simple, closure-based authorization logic.
2.  **RBAC (Roles & Permissions)**: A structured way to assign permissions to roles, and roles to users.

## The Authorizable Trait

To enable authorization features on your `User` model, you should implement the `Plugs\Security\Authorization\Authorizable` interface and use the `Plugs\Security\Authorization\Traits\HasRolesAndPermissions` trait.

```php
namespace App\Models;

use Plugs\Security\Auth\Authenticatable;
use Plugs\Security\Authorization\Authorizable;
use Plugs\Security\Authorization\Traits\HasRolesAndPermissions;

class User implements Authenticatable, Authorizable
{
    use HasRolesAndPermissions;

    // ...
}
```

## Defining Abilities (Gates)

Gates are closures that determine if a user is authorized to perform a given action. They are typically defined in a service provider.

```php
use Plugs\Facades\Auth;

gate()->define('update-post', function ($user, $post) {
    return $user->id === $post->user_id;
});
```

### Super Admin Bypass

If you want to grant all permissions to a specific role (like 'admin'), you can use the `before` method:

```php
gate()->before(function ($user, $ability) {
    if ($user->hasRole('admin')) {
        return true;
    }
});
```

## Policies

Policies are classes that organize authorization logic around a particular model or resource.

### Creating a Policy

```php
namespace App\Policies;

use App\Models\User;
use App\Models\Post;
use Plugs\Security\Authorization\Policy;

class PostPolicy extends Policy
{
    public function update(User $user, Post $post)
    {
        return $user->id === $post->user_id;
    }
}
```

### Registering Policies

Register your policies in a service provider:

```php
gate()->policy(Post::class, PostPolicy::class);
```

### Using Policies

Once a policy is registered, you can check it using the same `can` or `@can` syntax. The system will automatically detect the model and use the corresponding policy method:

```php
if ($user->can('update', $post)) {
    // ...
}
```

## Checking Permissions

### Fluent Model API

The `HasRolesAndPermissions` trait provides a fluent `can()` method on your user model:

```php
if ($user->can('edit.post')) {
    // ...
}

// Or with models (Policies)
if ($user->can('update', $post)) {
    // ...
}
```

### In Controllers

You can check permissions manually using the `gate()` helper or the `Auth` facade:

```php
if (gate()->allows('update-post', $post)) {
    // The user can update the post...
}

if (Auth::user()->hasPermission('edit.post')) {
    // ...
}
```

### In Views (Directives)

Plugs provides several directives for checking authorization in your templates:

```blade
@can('update-post', $post)
    <!-- The user can update the post -->
@elsecan('view-post', $post)
    <!-- The user can only view the post -->
@endcan

@role('admin')
    <!-- The user has the admin role -->
@endrole
```

### In Views (HTML Tags)

For a cleaner, more modern look, you can use HTML-style tag directives:

```html
<can :ability="'edit.post'">
  <button>Edit</button>
</can>

<role :name="'admin'">
  <admin-panel />
</role>
```

Available tags include: `<can>`, `<cannot>`, `<elsecan>`, `<role>`, `<hasrole>`, `<hasanyrole>`, `<hasallroles>`.

## Middleware

To protect your routes, you can use the `Plugs\Http\Middleware\Authorize` middleware.

```php
// Now supported with colon syntax
$router->get('/admin', [AdminController::class, 'index'])
       ->middleware('can:access.admin');
```

The middleware will automatically throw a `403 Forbidden` exception if the user is not authorized.

## Customizing RBAC

Because the system is interface-based, you can customize how roles and permissions are stored. Simply implement the `Role` and `Permission` interfaces and override the `getRoles()` and `getPermissions()` methods in your `User` model to fetch data from your database or external service.
