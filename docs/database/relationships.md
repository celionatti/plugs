# Relationships

The `PlugModel` provides an expressive, fluent Eloquent-inspired API for interacting with the relationships between your models. Relationships in the Plugs ORM are defined as methods on your model classes.

## Defining Relationships

Since building these methods returns a Relationship Proxy (which acts like a query builder), you can method-chain additional constraints onto your relationships before they are executed.

### One To One

A one-to-one relationship is a very basic type of database relation. For example, a `User` model might be associated with one `Profile` model.

```php
namespace App\Models;

use Plugs\Base\Model\PlugModel;

class User extends PlugModel
{
    /**
     * Get the profile associated with the user.
     */
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
}
```

The first argument passed to the `hasOne` method is the name of the related model class. Once the relationship is defined, we may retrieve the related record using dynamic properties or by querying the relationship method directly.

```php
$profile = User::find(1)->profile;
```

#### Defining The Inverse Of A Relationship

To define the inverse of a `hasOne` relationship, you should define a relationship method on the child model which calls the `belongsTo` method:

```php
class Profile extends PlugModel
{
    /**
     * Get the user that owns the profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

### One To Many

A one-to-many relationship defines a relationship where a single model is the parent to one or more child models. For example, a blog post may have an infinite number of comments.

```php
namespace App\Models;

use Plugs\Base\Model\PlugModel;

class Post extends PlugModel
{
    /**
     * Get the comments for the blog post.
     */
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
```

#### Defining The Inverse Of A One To Many Relationship

Using the `belongsTo` method, you can define the inverse of a `hasMany` relationship:

```php
class Comment extends PlugModel
{
    /**
     * Get the post that owns the comment.
     */
    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
```

### Many To Many

Many-to-many relations require an intermediate table (often called a pivot table). For example, a user may have many roles, where the roles are also shared by other users.

```php
namespace App\Models;

use Plugs\Base\Model\PlugModel;

class User extends PlugModel
{
    /**
     * The roles that belong to the user.
     */
    public function roles()
    {
        // 1st param: The related model
        // 2nd param: The pivot table name (optional)
        // 3rd param: Foreign pivot key (optional)
        // 4th param: Related pivot key (optional)
        return $this->belongsToMany(Role::class);
    }
}
```

### Has One Through

The "has one through" relationship links models through a single intermediate relation. For example, if a `Supplier` has one `Account`, and an `Account` has one `AccountHistory`, the `Supplier` may access the `AccountHistory` _through_ the `Account`.

```php
namespace App\Models;

use Plugs\Base\Model\PlugModel;

class Supplier extends PlugModel
{
    /**
     * Get the supplier's account history.
     */
    public function accountHistory()
    {
        // 1st param: Final Model you want to access
        // 2nd param: Intermediate Model you are passing through
        return $this->hasOneThrough(AccountHistory::class, Account::class);
    }
}
```

### Has Many Through

The "has many through" relationship provides a convenient shortcut for accessing distant relations via an intermediate relation. For example, if a `Project` model has many `Environment` models, and each `Environment` has many `Deployment` models, you may access all deployments for a project through the environments.

```php
namespace App\Models;

use Plugs\Base\Model\PlugModel;

class Project extends PlugModel
{
    /**
     * Get all of the deployments for the project.
     */
    public function deployments()
    {
        return $this->hasManyThrough(Deployment::class, Environment::class);
    }
}
```

---

## Polymorphic Relationships

A polymorphic relationship allows the child model to belong to more than one type of model using a single association.

### One To One (Polymorphic)

```php
class Image extends PlugModel
{
    public function imageable()
    {
        return $this->morphTo();
    }
}

class User extends PlugModel
{
    public function image()
    {
        // Parameter: Relationship Name
        return $this->morphOne(Image::class, 'imageable');
    }
}
```

### One To Many (Polymorphic)

```php
class Comment extends PlugModel
{
    public function commentable()
    {
        return $this->morphTo();
    }
}

class Post extends PlugModel
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
```

---

## Querying Relationships and Method Chaining

Because relationship methods in `PlugModel` return Relationship Proxy objects (e.g., `HasManyProxy`, `HasOneThroughProxy`), they behave exactly like the underlying Query Builder.

This means you can chain any `WHERE`, `ORDER BY`, or other query building methods directly onto the relationship method definition to return a **constrained relationship**.

### Chaining on Definition

You can define base constraints directly inside the relationship definition on the Model class:

```php
class Supplier extends PlugModel
{
    /**
     * Get the latest active account history.
     */
    public function latestActiveAccountHistory()
    {
        return $this->hasOneThrough(AccountHistory::class, Account::class)
                    ->where('status', 'active')
                    ->orderBy('created_at', 'DESC');
    }
}
```

### Chaining on Execution

You can also dynamically chain query builder constraints onto relationships when calling them as a method anywhere in your application:

```php
$user = User::find(1);

// Get post comments that were created today
$commentsToday = $user->comments()
                      ->where('created_at', '>=', date('Y-m-d'))
                      ->get();
```

> [!CAUTION]
> Remember: Accessing a relationship as a **method** (`$user->comments()`) returns the Query Builder Proxy so you can add constraints. Accessing the relationship as a **property** (`$user->comments`) immediately executes the standard query and returns the Collection/Model!

---

## Eager Loading

When accessing relationships as properties, the relationship data is "lazy loaded", meaning the relationship data is not actually loaded until you first access the property. However, you can "eager load" relationships at the time you query the parent model to prevent the N+1 query problem.

```php
// Prevent N+1 queries by eager loading all posts and their authors
$posts = Post::with('author')->get();

// Eager loading multiple relationships
$posts = Post::with(['author', 'comments'])->get();
```

### Lazy Eager Loading

Sometimes you may need to eager load a relationship after the parent model has already been retrieved. For example, this may be useful if you need to dynamically decide whether to load related models:

```php
$books = Book::all();

if ($loadAuthors) {
    $books->load('author');
}
```
