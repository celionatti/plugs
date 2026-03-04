# Schema Field Reference

Every field type in the Plugs Schema system extends the abstract `Plugs\Database\Schema\Fields\Field` base class. Fields are instantiated with the static `::make()` factory and configured with a **fluent API**.

```php
use Plugs\Database\Schema\Fields\StringField;

'name' => StringField::make()->required()->min(2)->max(100),
```

---

## Common Fluent Methods (All Fields)

These methods are inherited by every field type:

| Method                      | Description                                             |
| --------------------------- | ------------------------------------------------------- |
| `->required()`              | Adds the `required` validation rule                     |
| `->nullable()`              | Adds the `nullable` validation rule                     |
| `->unique(?table, ?column)` | Adds a `unique` constraint (table/column auto-detected) |
| `->guarded()`               | Prevents mass assignment via `fill()`                   |
| `->hidden()`                | Excludes from `toArray()` / `toJson()` / JSON output    |
| `->default(mixed $value)`   | Sets a default value applied during construction        |
| `->rule(string $rule)`      | Appends an arbitrary validation rule string             |

### Example — Using Common Methods

```php
'api_key' => StringField::make()
    ->guarded()          // can't be mass-assigned
    ->hidden()           // won't appear in JSON
    ->default('none')    // default value
    ->rule('alpha_num'), // custom validation rule
```

---

## Field Types

### StringField

General-purpose string attribute.

| Method                | Description                | Cast     |
| --------------------- | -------------------------- | -------- |
| `->min(int)`          | Minimum character length   | `string` |
| `->max(int)`          | Maximum character length   |          |
| `->between(int, int)` | Length between min and max |          |

```php
use Plugs\Database\Schema\Fields\StringField;

'name' => StringField::make()->required()->min(2)->max(255),
```

**Validation rules produced:** `required|string|min:2|max:255`
**Cast type:** `string`

---

### IntegerField

Numeric integer attribute.

| Method                | Description              | Cast      |
| --------------------- | ------------------------ | --------- |
| `->min(int)`          | Minimum value            | `integer` |
| `->max(int)`          | Maximum value            |           |
| `->between(int, int)` | Value between min/max    |           |
| `->unsigned()`        | Shorthand for `->min(0)` |           |

```php
use Plugs\Database\Schema\Fields\IntegerField;

'age'   => IntegerField::make()->min(18)->max(120),
'votes' => IntegerField::make()->unsigned()->default(0),
```

**Validation rules produced:** `integer|min:18|max:120`
**Cast type:** `integer`

---

### FloatField

Floating-point numeric attribute.

| Method         | Description   | Cast    |
| -------------- | ------------- | ------- |
| `->min(float)` | Minimum value | `float` |
| `->max(float)` | Maximum value |         |

```php
use Plugs\Database\Schema\Fields\FloatField;

'price' => FloatField::make()->min(0.0)->max(99999.99),
```

**Validation rules produced:** `numeric|min:0|max:99999.99`
**Cast type:** `float`

---

### BooleanField

Boolean attribute.

```php
use Plugs\Database\Schema\Fields\BooleanField;

'is_active' => BooleanField::make()->default(true),
```

**Validation rules produced:** `boolean`
**Cast type:** `boolean`

---

### EmailField

Email address attribute. Adds `email` validation automatically.

| Method       | Description    | Cast     |
| ------------ | -------------- | -------- |
| `->max(int)` | Maximum length | `string` |

```php
use Plugs\Database\Schema\Fields\EmailField;

'email' => EmailField::make()->required()->unique()->max(255),
```

**Validation rules produced:** `required|email|max:255|unique:users,email`
**Cast type:** `string`

> [!TIP]
> When you call `->unique()` without arguments, the table name and column name are resolved automatically from the model's table and the field name.

---

### PasswordField

Password attribute. **Automatically hidden** from serialisation (`toArray()`, `toJson()`).

| Method       | Description                                                  | Cast     |
| ------------ | ------------------------------------------------------------ | -------- |
| `->min(int)` | Minimum password length                                      | `string` |
| `->strong()` | Requires uppercase, lowercase, number, and special character |          |

```php
use Plugs\Database\Schema\Fields\PasswordField;

'password' => PasswordField::make()->required()->min(8)->strong(),
```

**Validation rules produced:** `required|string|min:8|strong_password`
**Cast type:** `string`

> [!NOTE]
> You do **not** need to chain `->hidden()` on `PasswordField` — it is hidden by default.

---

### TextField

Long-form text attribute (maps to `TEXT` columns).

| Method       | Description              | Cast     |
| ------------ | ------------------------ | -------- |
| `->min(int)` | Minimum character length | `string` |
| `->max(int)` | Maximum character length |          |

```php
use Plugs\Database\Schema\Fields\TextField;

'body' => TextField::make()->nullable()->min(10)->max(65535),
```

**Validation rules produced:** `nullable|string|min:10|max:65535`
**Cast type:** `string`

---

### DateTimeField

Date / datetime attribute.

| Method             | Description                   | Cast                              |
| ------------------ | ----------------------------- | --------------------------------- |
| `->format(string)` | Expected datetime format      | `datetime` or `datetime:{format}` |
| `->before(string)` | Must be before the given date |                                   |
| `->after(string)`  | Must be after the given date  |                                   |

```php
use Plugs\Database\Schema\Fields\DateTimeField;

'published_at' => DateTimeField::make()
    ->nullable()
    ->format('Y-m-d')
    ->after('2020-01-01')
    ->before('2030-01-01'),
```

**Validation rules produced:** `nullable|date|date_format:Y-m-d|after:2020-01-01|before:2030-01-01`
**Cast type:** `datetime:Y-m-d`

---

### JsonField

JSON attribute (stored as a JSON string, cast to an array).

| Method            | Description              | Cast   |
| ----------------- | ------------------------ | ------ |
| `->minItems(int)` | Minimum array item count | `json` |
| `->maxItems(int)` | Maximum array item count |        |

```php
use Plugs\Database\Schema\Fields\JsonField;

'metadata' => JsonField::make()->nullable()->minItems(1)->maxItems(50),
```

**Validation rules produced:** `nullable|json|min_items:1|max_items:50`
**Cast type:** `json`

---

### EnumField

Enum attribute — works with raw string arrays **or** PHP 8.1+ backed enums.

| Method                | Description           | Cast                |
| --------------------- | --------------------- | ------------------- |
| `->values(array)`     | Allowed string values | `string`            |
| `->enumClass(string)` | PHP backed enum class | The enum class name |

#### Raw Values

```php
use Plugs\Database\Schema\Fields\EnumField;

'status' => EnumField::make()
    ->values(['draft', 'published', 'archived'])
    ->default('draft'),
```

**Validation rules produced:** `in:draft,published,archived`
**Cast type:** `string`

#### Backed Enum

```php
enum Status: string {
    case Draft     = 'draft';
    case Published = 'published';
    case Archived  = 'archived';
}

'status' => EnumField::make()->enumClass(Status::class),
```

**Validation rules produced:** `in:draft,published,archived` (auto-extracted from enum cases)
**Cast type:** `App\Enums\Status` (the model casts to the enum instance)

---

### UrlField

URL attribute. Adds `url` validation automatically.

| Method       | Description    | Cast     |
| ------------ | -------------- | -------- |
| `->max(int)` | Maximum length | `string` |

```php
use Plugs\Database\Schema\Fields\UrlField;

'website' => UrlField::make()->nullable()->max(2048),
```

**Validation rules produced:** `nullable|url|max:2048`
**Cast type:** `string`

---

### UuidField

UUID attribute. Adds `uuid` validation automatically.

```php
use Plugs\Database\Schema\Fields\UuidField;

'uuid' => UuidField::make()->required(),
```

**Validation rules produced:** `required|uuid`
**Cast type:** `string`

---

### SlugField

URL-friendly slug attribute. Adds `slug` validation automatically.

| Method       | Description    | Cast     |
| ------------ | -------------- | -------- |
| `->max(int)` | Maximum length | `string` |

```php
use Plugs\Database\Schema\Fields\SlugField;

'slug' => SlugField::make()->required()->max(100),
```

**Validation rules produced:** `required|slug|max:100`
**Cast type:** `string`

---

### PhoneField

Phone number attribute. Adds `phone` validation automatically.

```php
use Plugs\Database\Schema\Fields\PhoneField;

'phone' => PhoneField::make()->nullable(),
```

**Validation rules produced:** `nullable|phone`
**Cast type:** `string`

---

## Creating Custom Fields

Extend the `Field` base class:

```php
namespace App\Schema\Fields;

use Plugs\Database\Schema\Fields\Field;

class CurrencyField extends Field
{
    protected int $decimals = 2;

    public function decimals(int $decimals): static
    {
        $this->decimals = $decimals;

        return $this;
    }

    public function getCastType(): string
    {
        return 'float';
    }

    protected function getTypeRules(): array
    {
        return ['numeric', 'min:0'];
    }
}
```

Usage:

```php
'price' => CurrencyField::make()->required()->decimals(2),
```

---

## Full Real-World Example

```php
namespace App\Models;

use Plugs\Base\Model\PlugModel;
use Plugs\Database\Schema\Fields\{
    StringField, EmailField, PasswordField,
    IntegerField, BooleanField, DateTimeField,
    EnumField, JsonField, SlugField, PhoneField
};

class Employee extends PlugModel
{
    protected $table = 'employees';
    protected array $schema = [];

    public function __construct(array|object $attributes = [], bool $exists = false)
    {
        $this->schema = [
            'first_name'   => StringField::make()->required()->min(2)->max(50),
            'last_name'    => StringField::make()->required()->min(2)->max(50),
            'email'        => EmailField::make()->required()->unique(),
            'phone'        => PhoneField::make()->nullable(),
            'password'     => PasswordField::make()->required()->min(8)->strong(),
            'slug'         => SlugField::make()->required()->max(100),
            'age'          => IntegerField::make()->min(18)->max(65),
            'is_active'    => BooleanField::make()->default(true),
            'role'         => EnumField::make()->values(['admin', 'manager', 'staff'])->default('staff'),
            'preferences'  => JsonField::make()->nullable(),
            'hired_at'     => DateTimeField::make()->format('Y-m-d')->after('2020-01-01'),
            'api_key'      => StringField::make()->guarded()->hidden(),
        ];

        parent::__construct($attributes, $exists);
    }
}
```

This single schema declaration replaces what would otherwise be separate `$fillable`, `$casts`, `$rules`, `$hidden`, and constructor defaults.
