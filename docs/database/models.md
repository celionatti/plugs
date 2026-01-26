# Database: Models (ORM)

The Plugs ORM provides a beautiful, simple ActiveRecord implementation for working with your database. Each database table has a corresponding "Model" that is used to interact with that table.

## Defining Models

All models extend the `Plugs\Base\Model\PlugModel` class. Models are typically stored in the `app/Models` directory.

```php
namespace App\Models;

use Plugs\Base\Model\PlugModel;

class User extends PlugModel
{
    //
}
```

### Table Names

By default, the plural name of the class will be used as the table name unless another name is explicitly specified:

```php
class User extends PlugModel
{
    protected $table = 'my_users';
}
```

### Primary Keys

Plugs will also assume that each table has a primary key column named `id`. You may define a protected `$primaryKey` property to override this convention:

```php
class User extends PlugModel
{
    protected $primaryKey = 'user_id';
}
```

## Retrieving Models

Once you have created a model and its associated database table, you are ready to start retrieving data from your database.

```php
use App\Models\User;

foreach (User::all() as $user) {
    echo $user->name;
}
```

### Adding Additional Constraints

```php
$users = User::where('active', 1)
               ->orderBy('name', 'desc')
               ->take(10)
               ->get();
```

## Inserting & Updating Models

### Inserts

To create a new record in the database, instantiate a new model instance and set attributes on the model. Then, call the `save` method:

```php
$user = new User;
$user->name = 'George';
$user->save();
```

### Updates

The `save` method may also be used to update models that already exist in the database:

```php
$user = User::find(1);
$user->name = 'John';
$user->save();
```

### Mass Assignment

You may also use the `create` method to save a new model in a single line. The inserted model instance will be returned to you by the method:

```php
$user = User::create(['name' => 'John']);
```

> [!IMPORTANT]
> To use mass assignment, you must specify either a `fillable` or `guarded` property on your model class.

## Deleting Models

To delete a model, call the `delete` method on a model instance:

```php
$user = User::find(1);
$user->delete();
```

### Soft Deleting

If you wish to provide "soft delete" functionality, set the `$softDelete` property to `true` on your model:

```php
class User extends PlugModel
{
    protected $softDelete = true;
}
```

When you call the `delete` method on a model, the `deleted_at` column will be set to the current date and time. You may use `restore()` to un-delete a model.

## Accessors & Mutators

### Accessors

An accessor transforms a model attribute value when it is accessed. To define an accessor, create a `get{Attribute}Attribute` method on your model where `{Attribute}` is the "studly" case name of the column you wish to access.

In this example, we'll define an accessor for the `first_name` attribute. The accessor will automatically be called by the ORM when attempting to retrieve the value of the `first_name` attribute:

```php
class User extends PlugModel
{
    public function getFirstNameAttribute($value)
    {
        return ucfirst($value);
    }
}
```

### Mutators

A mutator transforms a model attribute value when it is set. To define a mutator, create a `set{Attribute}Attribute` method on your model where `{Attribute}` is the "studly" case name of the column you wish to access.

```php
class User extends PlugModel
{
    public function setFirstNameAttribute($value)
    {
        $this->attributes['first_name'] = strtolower($value);
    }
}
```

## Eager Loading

When accessing relationships as properties, the relationship data is "lazy loaded". This means the relationship data is not actually loaded until you first access the property. However, Plugs can "eager load" relationships at the time you query the parent model. Eager loading alleviates the N+1 query problem.

To eager load a relationship, use the `with` method:

```php
$books = Book::with('author')->get();

foreach ($books as $book) {
    echo $book->author->name;
}
```

### Eager Loading Multiple Relationships

```php
$books = Book::with('author', 'publisher')->get();
```

### Nested Eager Loading

To eager load nested relationships, you may use "dot" syntax. For example, let's eager load all of the book's authors and all of the author's contacts:

```php
$books = Book::with('author.contacts')->get();
```

## Relationships

Relationships are defined as methods on your model classes.

### One To One

```php
public function profile()
{
    return $this->hasOne(Profile::class);
}
```

### One To Many

```php
public function posts()
{
    return $this->hasMany(Post::class);
}
```

### Belongs To (Inverse)

```php
public function user()
{
    return $this->belongsTo(User::class);
}
```

### Many To Many

```php
public function roles()
{
    return $this->belongsToMany(Role::class);
}
```

### Has Many Through

```php
public function posts()
{
    return $this->hasManyThrough(Post::class, User::class);
}
```

### Has One Through

```php
public function owner()
{
    return $this->hasOneThrough(User::class, Account::class);
}
```

### Polymorphic Relationships

#### One To Many (Polymorphic)

```php
class Post extends PlugModel
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

class Comment extends PlugModel
{
    public function commentable()
    {
        return $this->morphTo();
    }
}
```

## Relationship Proxies & Chaining

Relationship methods in Plugs models return **Relationship Proxies** (e.g., `HasManyProxy`, `HasOneProxy`). These proxies act as a wrapper around the Query Builder, allowing you to chain additional constraints before executing the query.

### Fluent Chaining

You can chain any Query Builder method onto a relationship:

```php
// Get only published posts for a user
$publishedPosts = $user->posts()->where('status', 'published')->latest()->get();

// Find a specific comment on a post
$comment = $post->comments()->find(5);
```

### Relationship Persistence

Proxies also provide convenient methods for creating and saving related models:

```php
// Create a new post for a user
$post = $user->posts()->create([
    'title' => 'My New Post',
    'content' => '...'
]);

// Associate a profile with a user
$profile->user()->associate($user)->save();

// Dissociate a relationship
$profile->user()->dissociate()->save();
```

### Many-to-Many Syncing

The `BelongsToManyProxy` provides powerful methods for managing pivot table records:

```php
// Sync roles: only these IDs will remain in the pivot table
$user->roles()->sync([1, 2, 3]);

// Attach a role without removing existing ones
$user->roles()->attach(4);

// Detach a specific role
$user->roles()->detach(2);

// Toggle a role: attach if missing, detach if present
$user->roles()->toggle(5);
```

## Serialization

When building APIs, you often need to convert your models and relationships to arrays or JSON.

### Converting To Arrays

To convert a model and its loaded relationships to an array, you should use the `toArray` method. This method is recursive, so all attributes and all relations (including the relations of relations) will be converted to arrays:

```php
$user = User::with('posts')->find(1);
return $user->toArray();
```

### Serialization Visibility

Sometimes you may wish to limit the attributes, such as passwords, that are included in your model's array or JSON representation. To do so, add a `$hidden` property to your model:

```php
class User extends PlugModel
{
    protected $hidden = ['password'];
}
```

Alternatively, you may use the `visible` property to define a "white list" of attributes that should be included in your model's array and JSON representation:

```php
class User extends PlugModel
{
    protected $visible = ['first_name', 'last_name'];
}
```

### Appending Values To JSON

Occasionally, when converting models to an array or JSON, you may wish to add attributes that do not have a corresponding column in your database. To do so, first define an [accessor](#accessors) for the value:

```php
public function getIsAdminAttribute()
{
    return $this->attributes['admin'] === 'yes';
}
```

After creating the accessor, add the attribute name to the `appends` property on the model:

```php
class User extends PlugModel
{
    protected $appends = ['is_admin'];
}
```
