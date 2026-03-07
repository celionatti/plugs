# Localization

The Plugs framework provides a convenient way to retrieve strings in various languages, allowing you to easily support multiple languages within your application.

## Language Files

Language strings are stored in files within the `resources/lang` directory. In this directory, there should be a subdirectory for each language supported by the application.

```text
/resources
    /lang
        /en
            messages.php
        /fr
            messages.php
```

Each language file should return an array of keyed strings:

```php
// resources/lang/en/messages.php
return [
    'welcome' => 'Welcome to our application!',
    'greet' => 'Hello, :name',
];
```

## Retrieving Translation Strings

You may retrieve translation strings using the `__` helper function. The first argument is the file name and the key separated by a dot:

```php
echo __('messages.welcome');
```

### Parameter Replacement

If the translation string contains placeholders, you can pass an array of replacements as the second argument:

```php
echo __('messages.greet', ['name' => 'Celio']); // Output: Hello, Celio
```

The helper will automatically handle different cases if you use them in your placeholders:

- `:name` -> `Celio`
- `:Name` -> `Celio`
- `:NAME` -> `CELIO`

## Determining The Current Locale

The current locale is determined by the `app.locale` configuration option. You can change the locale at runtime using the `translator` instance:

```php
use Plugs\Container\Container;

$translator = Container::getInstance()->make('translator');
$translator->setLocale('fr');
```

### Localization Middleware

Plugs includes a `LocalizationMiddleware` that can automatically detect and set the locale based on:

1. `lang` query parameter (e.g., `?lang=fr`)
2. Session `locale` key
3. `locale` cookie
4. `Accept-Language` HTTP header

To use it, add the following to your middleware pipeline in `bootstrap/boot.php` or a Service Provider:

```php
$app->pipe(new \Plugs\Http\Middleware\LocalizationMiddleware($container->make('translator')));
```

## Generating Translation Files

Use the `make:lang` command to quickly scaffold translation files for a locale:

```bash
php theplugs make:lang en
php theplugs make:lang fr
php theplugs make:lang es
```

This creates three translation groups by default: `messages`, `validation`, and `auth` — pre-filled with common translations for the specified locale.

**Alias:** `g:lang`

### Supported Locales (Pre-Filled)

The following locales come with built-in translations:

| Code | Language   |
| ---- | ---------- |
| `en` | English    |
| `fr` | French     |
| `es` | Spanish    |
| `de` | German     |
| `pt` | Portuguese |
| `zh` | Chinese    |
| `ar` | Arabic     |

Any other locale code will generate English-based stubs ready for translation.

### Options

- `--force, -f` — Overwrite existing translation files.
- `--groups` — Comma-separated list of groups to create (default: `messages,validation,auth`).

```bash
# Only generate specific groups
php theplugs make:lang fr --groups=messages,validation

# Overwrite existing files
php theplugs make:lang en --force

# Create a custom group (generates an empty stub)
php theplugs make:lang en --groups=emails,notifications
```

## Fallback Locale

If a translation key is not found for the current locale, the translator will automatically fall back to the `fallback_locale` defined in `config/app.php` (default: `en`).

```php
// config/app.php
return [
    'locale' => 'fr',
    'fallback_locale' => 'en',
];
```

## Using Translations in Views

You can use the `__()` helper directly in your Plug templates:

```php
<h1><?= __('messages.welcome') ?></h1>
<p><?= __('messages.greet', ['name' => $user->name]) ?></p>
```
