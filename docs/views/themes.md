# Themes & Customization

The Plugs framework supports a powerful theme system that allows you to override any view or component without modifying the core application files. This is perfect for building modular skins or providing white-label solutions for different environments.

## 🏗️ How it Works

The `PlugViewEngine` uses a hierarchical lookup system. When a theme is active, it will first check the theme's directory for a file. If it doesn't find it there, it falls back to the default view location.

- **Theme Location**: `resources/views/themes/{theme_name}/`
- **Default Location**: `resources/views/`

### File Priority

1. `resources/views/themes/{theme_name}/{view}.plug.php`
2. `resources/views/{view}.plug.php`

---

## 🚀 Setting Up a Theme

### 0. CLI Scaffolding (Recommended)

The fastest way to create a new theme is using the `theplugs` CLI tool:

```bash
# Create a new theme named 'carbon'
php theplugs make:theme carbon

# Install the premium Nebula (Space) theme
php theplugs theme:nebula
```

### 1. Configuration

You can set the default theme in your `config/app.php` or `config/view.php` file:

```php
// config/app.php or config/view.php
return [
    // ...
    'theme' => 'carbon', // Set your theme name here
];
```

By default, the theme is set to `'default'`, which skips the theme lookup and uses the root `resources/views` directory directly.

### 2. Directory Structure

Create a directory for your theme inside `resources/views/themes/`. For example, if your theme is named `carbon`:

```text
resources/
└── views/
    ├── themes/
    │   └── carbon/
    │       ├── layouts/
    │       │   └── app.plug.php  <-- Overrides default layout
    │       └── welcome.plug.php   <-- Overrides default welcome page
    └── welcome.plug.php           <-- Original/Default file
```

---

## 🧩 Theming Components

Themes also apply to components. If you use a component like `<Button />`, the engine will look for it in the theme first:

1. `resources/views/themes/{theme_name}/components/button.plug.php`
2. `resources/views/components/button.plug.php`

This allows you to customize the look and feel of your entire UI by only overriding specific components in your theme.

---

## 🧩 Theme Module Overrides

The Plugs framework allows themes to override views from modules (namespaces) globally. This ensures a consistent look across the entire application, even for views provided by external or internal modules (like `Auth` or `Admin`).

### Module Lookup Priority

When a namespaced view is requested (e.g., `auth::login`), the engine checks:

1.  **Central Theme Override**: `resources/views/themes/{theme_name}/modules/{namespace}/{view}.plug.php`
2.  **Module Theme Override**: `{module_path}/Views/themes/{theme_name}/{view}.plug.php`
3.  **Default Module View**: `{module_path}/Views/{view}.plug.php`

### Example Structure

To override the `login` view of the `Auth` module in the `nebula` theme:

```text
resources/
└── views/
    └── themes/
        └── nebula/
            └── modules/
                └── auth/
                    └── login.plug.php  <-- Nebula version of login
```

---

## 💡 Pro Tips

### Dynamic Theme Switching

You can switch themes dynamically at runtime (e.g., based on the current user or domain) using the `View` facade or by resolving the `ViewEngineInterface` from the container:

```php
use Plugs\View\ViewEngineInterface;

$engine = app(ViewEngineInterface::class);
$engine->setTheme('dark-mode');
```

### Environment Overrides

You can also use `.env` to set your theme, making it easy to use different themes for different environments:

```env
APP_THEME=carbon
```

(Ensure your `config/view.php` uses `env('APP_THEME', 'default')`)
