# Typed Model Schema

Plugs models support an **Intelligent Typed Schema** system that replaces the repetitive `$fillable` + `$casts` + `$rules` pattern with a single `$schema` declaration. Your model becomes a **self-validating data contract**.

## Quick Start

```php
use Plugs\Base\Model\PlugModel;
use Plugs\Database\Schema\Fields\StringField;
use Plugs\Database\Schema\Fields\EmailField;
use Plugs\Database\Schema\Fields\IntegerField;
use Plugs\Database\Schema\Fields\PasswordField;
use Plugs\Database\Schema\Fields\BooleanField;

class User extends PlugModel
{
    protected $table = 'users';
    protected array $schema = [];

    public function __construct(array|object $attributes = [], bool $exists = false)
    {
        $this->schema = [
            'name'      => StringField::make()->required()->min(2)->max(100),
            'email'     => EmailField::make()->required()->unique(),
            'age'       => IntegerField::make()->min(18),
            'password'  => PasswordField::make()->required()->min(8)->strong(),
            'is_active' => BooleanField::make()->default(true),
        ];

        parent::__construct($attributes, $exists);
    }
}
```

**What this gives you automatically:**

| Feature         | Old Way                                   | Schema Way                          |
| --------------- | ----------------------------------------- | ----------------------------------- |
| Mass assignment | `$fillable = ['name', 'email', ...]`      | Auto-derived from schema fields     |
| Type casting    | `$casts = ['age' => 'integer']`           | Auto-derived from field types       |
| Validation      | `$rules = ['email' => 'required\|email']` | Auto-derived from field constraints |
| Hidden fields   | `$hidden = ['password']`                  | `PasswordField` is auto-hidden      |
| Default values  | Manual in constructor                     | `->default(true)` on the field      |

---

## Available Field Types

### StringField

```php
StringField::make()
    ->required()
    ->min(2)        // min length
    ->max(255)      // max length
    ->between(2, 255)
    ->unique()
    ->nullable()
```

### IntegerField

```php
IntegerField::make()
    ->required()
    ->min(0)        // min value
    ->max(999)      // max value
    ->between(0, 999)
    ->unsigned()    // shorthand for min(0)
    ->default(0)
```

### FloatField

```php
FloatField::make()
    ->min(0.0)
    ->max(100.0)
    ->nullable()
```

### BooleanField

```php
BooleanField::make()
    ->default(true)
```

### EmailField

```php
EmailField::make()
    ->required()
    ->unique()
    ->max(255)
```

### PasswordField

```php
PasswordField::make()      // Automatically hidden from toArray/JSON
    ->required()
    ->min(8)
    ->strong()             // Requires uppercase, lowercase, number, special char
```

### TextField

```php
TextField::make()
    ->nullable()
    ->min(10)
    ->max(65535)
```

### DateTimeField

```php
DateTimeField::make()
    ->nullable()
    ->format('Y-m-d')
    ->before('2030-01-01')
    ->after('2020-01-01')
```

### JsonField

```php
JsonField::make()
    ->nullable()
    ->minItems(1)
    ->maxItems(50)
```

### EnumField

```php
// Raw values
EnumField::make()->values(['draft', 'published', 'archived'])->default('draft')

// PHP 8.1 Backed Enum
EnumField::make()->enumClass(Status::class)
```

### UrlField

```php
UrlField::make()->nullable()->max(2048)
```

### UuidField

```php
UuidField::make()->required()
```

### SlugField

```php
SlugField::make()->required()->max(100)
```

### PhoneField

```php
PhoneField::make()->nullable()
```

---

## Fluent API (All Fields)

Every field type inherits these methods:

| Method                      | Description                       |
| --------------------------- | --------------------------------- |
| `->required()`              | Marks as required                 |
| `->nullable()`              | Marks as nullable                 |
| `->unique(?table, ?column)` | Adds unique constraint            |
| `->guarded()`               | Prevents mass assignment          |
| `->hidden()`                | Hides from serialization          |
| `->default(value)`          | Sets default value                |
| `->rule('custom_rule')`     | Appends arbitrary validation rule |

---

## Guarded Fields

Mark fields that should **not** be mass-assignable:

```php
'api_key' => StringField::make()->guarded()->hidden(),
'role'    => StringField::make()->default('user'),
```

Guarded fields can still be set via `$model->forceFill(...)` or directly.

---

## Mixing Schema with Explicit Rules

If you need **conditional or dynamic validation** that a schema can't express, declare both `$schema` and `$rules`. Explicit rules override schema-derived rules for the same field:

```php
class User extends PlugModel
{
    protected array $schema = [];

    // This overrides the schema-derived email rule
    protected array $rules = [
        'email' => 'required|email|unique:users,email',
    ];

    public function __construct(/*...*/)
    {
        $this->schema = [
            'name'  => StringField::make()->required(),
            'email' => EmailField::make()->required(), // overridden by $rules above
        ];
        parent::__construct(...);
    }
}
```

---

## Backward Compatibility

The schema system is **fully opt-in**. Models that do **not** declare `protected array $schema` continue to work exactly as before with `$fillable`, `$casts`, and `$rules`.

---

## Generating Schema Models

Use the `--schema` flag with `make:model`:

```bash
php theplugs make:model Post --schema --migration
```

This generates a model with a `$schema` property and constructor instead of `$fillable`/`$casts` arrays.

---

## SchemaDefinition API

Access the resolved schema programmatically:

```php
$model = new User();
$schema = $model->getSchemaDefinition();

$schema->getFillable();         // ['name', 'email', 'age', ...]
$schema->getCasts();            // ['age' => 'integer', ...]
$schema->getValidationRules();  // ['name' => 'required|string|min:2', ...]
$schema->getHidden();           // ['password']
$schema->getDefaults();         // ['is_active' => true]
$schema->getField('email');     // EmailField instance
$schema->hasField('name');      // true
$schema->getAttributeNames();   // ['name', 'email', 'age', ...]
```
