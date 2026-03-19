# Theme Module Overrides

The **Theme Module Overrides** feature allows you to centrally manage the visual appearance of all application modules (e.g., Auth, Admin, Blog) from within your active theme. This enables a unified design system across namespaced views without requiring modifications to the module's internal code.

---

## 🏗️ Technical Implementation

When a namespaced view is requested (using the `Namespace::view` syntax), the `PlugViewEngine` uses a hierarchical lookup system to find the correct file.

### Lookup Order

1.  **Central Theme Override**: 
    `resources/views/themes/{theme_name}/modules/{namespace}/{view}.plug.php`
2.  **Module-Specific Theme Theme**: 
    `{module_path}/Views/themes/{theme_name}/{view}.plug.php`
3.  **Default Module View**: 
    `{module_path}/Views/{view}.plug.php`

---

## 🚀 Usage Example

Imagine you have an `Auth` module with a `login` view, and you want to apply the `nebula` theme's space aesthetic to it.

### 1. Identify the Namespace
The `Auth` module typically registers the `auth` namespace.

### 2. Create the Override File
Create the following directory structure and file in your theme:

```text
resources/views/themes/nebula/modules/auth/login.plug.php
```

### 3. Implement the Themed View
Inside the override file, you can use your theme's layout and components:

```php
<layout name="layouts.app">
    <div class="glass-panel text-center">
        <h1 class="text-gradient-nebula">Access Terminal</h1>
        <form action="/login" method="POST">
            @csrf
            <!-- Your themed form fields -->
            <button type="submit" class="bg-gradient-nebula">Confirm Access</button>
        </form>
    </div>
</layout>
```

---

## 🎯 Benefits

- **Global Consistency**: Ensure every page, even those from third-party modules, matches your branding precisely.
- **Zero-Modification**: The module's original code remains untouched, making updates easier.
- **Granular Control**: Override only the views you need; other views will fall back to their module defaults.

---

## 💡 Developer Tips

### Discovery
If you are unsure where a module's views live, you can find them in the `modules/` directory or by checking the output of the Plugs debugger.

### Component Overrides
This feature also applies to namespaced components. To override a component located at `auth::components.input`, place your override at:
`resources/views/themes/{theme_name}/modules/auth/components/input.plug.php`
