# Models

Models are the heart of your application, representing your data and business logic. They interact with your database tables and provide an intuitive API for querying and manipulating data.

## Generating Models

The `make:model` command is a powerful tool for generating models and their related files.

```bash
php theplugs make:model Product
```

### Options

- `--migration` (`-m`): Create a migration file.
- `--controller` (`-c`): Create a controller.
- `--resource` (`-r`): Create a resource controller.
- `--factory` (`-f`): Create a factory.
- `--seed` (`-s`): Create a seeder.
- `--all` (`-a`): Create migration, factory, seeder, and controller.
- `--pivot`: Generate a pivot model (inherits from `Pivot`).
- `--soft-deletes`: Add `SoftDeletes` trait.
- `--fillable=name,price`: Define fillable attributes.
- `--hidden=password`: Define hidden attributes.
- `--casts=is_active:boolean`: Define attribute casting.

Example:

```bash
php theplugs make:model Product --all --fillable=name,price,description --casts=is_active:boolean
```

## Model Features

### Inheritance

All models extend the `Plugs\Base\Model\PlugModel` class, which provides Eloquent-like functionality.

```php
<?php

namespace App\Models;

use Plugs\Base\Model\PlugModel;
use Plugs\Database\Traits\SoftDeletes;

class Product extends PlugModel
{
    use SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'name',
        'price',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];
}
```

### Relationships

Define relationships methods to link models.

```php
public function category()
{
    return $this->belongsTo(Category::class);
}

public function reviews()
{
    return $this->hasMany(Review::class);
}
```

## Scopes

Query scopes allow you to define common sets of constraints that you may easily reuse throughout your application.

```php
public function scopeActive($query)
{
    return $query->where('is_active', true);
}
```

Usage:

```php
$activeProducts = Product::active()->get();
```
