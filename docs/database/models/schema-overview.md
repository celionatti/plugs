# Typed Model Schema

The Typed Model Schema system lets you replace the traditional `$fillable` + `$casts` + `$rules` triplet with a single `$schema` declaration. Your model becomes a **self-validating data contract** — fillable fields, type casts, validation rules, hidden attributes, and defaults are all **derived automatically** from the schema.

---

## Quick Start

```php
namespace App\Models;

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

> [!IMPORTANT]
> The `$schema` array **must** be populated inside the constructor **before** calling `parent::__construct()`. This is because the parent constructor resolves the schema and applies defaults during boot.

---

## What Schema Gives You Automatically

| Concern          | Traditional Way                           | Schema Way                                     |
| ---------------- | ----------------------------------------- | ---------------------------------------------- |
| Mass assignment  | `$fillable = ['name', 'email', ...]`      | Auto-derived — non-guarded fields are fillable |
| Type casting     | `$casts = ['age' => 'integer']`           | Auto-derived from field types                  |
| Validation rules | `$rules = ['email' => 'required\|email']` | Auto-derived from field constraints            |
| Hidden fields    | `$hidden = ['password']`                  | `PasswordField` is hidden by default           |
| Default values   | Manual in constructor or mutator          | `->default(value)` on the field                |

---

## How It Works Internally

The schema system is powered by three components:

### 1. `HasSchema` Trait

Used by `PlugModel` automatically. On first access it:

1. Reads the `$schema` property from your model.
2. Wraps it in a **`SchemaDefinition`** object (cached per class).
3. Supplies fillable, guarded, casts, rules, hidden, and defaults to the rest of the model.

### 2. `SchemaDefinition` Class

An immutable container that holds the resolved schema. It is created once per model class and cached in `static::$schemaDefinitions`.

```text
Plugs\Database\Schema\SchemaDefinition
├── getFields()            → array<string, Field>
├── getField(name)         → ?Field
├── hasField(name)         → bool
├── getFillable()          → string[]
├── getGuarded()           → string[]
├── getCasts()             → array<string, string>
├── getValidationRules()   → array<string, string>
├── getHidden()            → string[]
├── getDefaults()          → array<string, mixed>
├── getAttributeNames()    → string[]
└── getTableName()         → ?string
```

### 3. Field Classes

Each field type (`StringField`, `EmailField`, etc.) extends the abstract `Field` base class. Fields expose a **fluent API** for declaring constraints and derive their cast type and validation rules.

See the full [Field Reference →](schema-fields.md)

---

## Schema Resolution Priority

When a model has **both** a `$schema` and explicit properties (`$fillable`, `$casts`, `$rules`), the framework follows this precedence:

| Property    | Resolution                                                                                 |
| ----------- | ------------------------------------------------------------------------------------------ |
| `$fillable` | If explicitly set (non-empty), it takes precedence over schema-derived fillable.           |
| `$casts`    | Schema casts are merged; explicit `$casts` entries override schema casts for the same key. |
| `$rules`    | Schema rules are used as baseline; explicit `$rules` override per-field.                   |
| `$hidden`   | Schema hidden is **merged** with explicit `$hidden` (union of both lists).                 |

### Example: Overriding a Schema Rule

```php
class User extends PlugModel
{
    protected array $schema = [];

    // This rule overrides the schema-derived email rule
    protected array $rules = [
        'email' => 'required|email|unique:users,email',
    ];

    public function __construct(array|object $attributes = [], bool $exists = false)
    {
        $this->schema = [
            'name'  => StringField::make()->required(),
            'email' => EmailField::make()->required(),  // overridden by $rules above
        ];

        parent::__construct($attributes, $exists);
    }
}
```

---

## Schema Defaults

Fields with `->default(value)` will have their default value applied to the model's attributes during construction — **only** if that attribute has not already been set.

```php
'is_active' => BooleanField::make()->default(true),
```

The `applySchemaDefaults()` method runs during `__construct()`:

```php
$user = new User();
$user->is_active;  // true — default was applied
```

---

## Schema Hidden Fields

Fields marked with `->hidden()` (or inherently hidden like `PasswordField`) are automatically merged with any explicit `$hidden` array, so both sources contribute.

```php
'api_key' => StringField::make()->guarded()->hidden(),
```

---

## Guarded Fields

Marking a field as `->guarded()` makes it **not mass-assignable**. You can still set it directly:

```php
$model->forceFill(['api_key' => 'secret']);
// or
$model->api_key = 'secret';
```

---

## Inspecting the Resolved Schema

Access the `SchemaDefinition` at runtime for debugging, tooling, or dynamic forms:

```php
$user   = new User();
$schema = $user->getSchemaDefinition();

$schema->getFillable();         // ['name', 'email', 'age', 'is_active']
$schema->getCasts();            // ['age' => 'integer', 'is_active' => 'boolean', ...]
$schema->getValidationRules();  // ['name' => 'required|string|min:2|max:100', ...]
$schema->getHidden();           // ['password']
$schema->getDefaults();         // ['is_active' => true]
$schema->getField('email');     // EmailField instance
$schema->hasField('name');      // true
$schema->getAttributeNames();   // ['name', 'email', 'age', 'password', 'is_active']
```

---

## Generating Schema Models

Use the `--schema` flag with `make:model`:

```bash
php theplugs make:model Post --schema --migration
```

This generates a model with a `$schema` property and constructor instead of separate `$fillable` / `$casts` arrays.

---

## Backward Compatibility

The schema system is **completely opt-in**. Models that do **not** declare a `$schema` property continue to work exactly as before using `$fillable`, `$casts`, and `$rules`.

---

## Next Steps

- [Schema Field Reference](schema-fields.md) — every field type, its methods, and examples.
- [Model Overview](overview.md) — CRUD, transactions, caching.
- [Validation](../security.md) — validator details.
