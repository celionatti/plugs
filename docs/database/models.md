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
