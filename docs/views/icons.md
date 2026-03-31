# Native SVG Icons

Plugs Framework features a native SVG icon system that allows you to manage and render local icons without external dependencies like Font Awesome or CDNs.

## 0. Quick Setup

The easiest way to get started is to use the built-in icon generator to create your icons directory and populate it with a set of modern, professional icons.

```bash
# Initialize the default icon set (Heroicons-based)
php plugs make:icon --init
```

---

## 1. Getting Started

The framework looks for icons in the `resources/icons` directory by default. 

### Creating Icons

You can manually drop `.svg` files into `resources/icons` or use the CLI command:

```bash
# Create a new blank icon skeleton
php plugs make:icon my-new-icon

# Create an icon with specific SVG content
php plugs make:icon bell --svg='<svg>...</svg>'
```

### Basic Usage

```html
<!-- Renders resources/icons/user.svg -->
@icon('user')

<!-- With CSS classes -->
@icon('home', ['class' => 'w-6 h-6 text-blue-500'])
```

---

## 2. Dynamic Attribute Merging

The `@icon` directive automatically merges the attributes you provide into the root `<svg>` tag of the file. This supports:
- `class`
- `style`
- `id`
- `stroke-width`
- Any data attributes (`data-*`)

### Example: Complex Styling

```html
@icon('settings', [
    'class' => 'animate-spin opacity-50',
    'id' => 'settings-icon',
    'stroke-width' => '1.5'
])
```

---

## 3. Best Practices

- **SVG Cleaning**: Ensure your SVG files are "clean" (contain only the path data and relevant viewbox).
- **CurrentColor**: Set your SVG `stroke` or `fill` to `currentColor` so they automatically inherit the parent's text color.
- **Icon Reuse**: Since `@icon` is resolved at compile time, it's extremely efficient, but try not to use hundreds of icons on a single page to keep your HTML output size manageable.

---

## 4. Default Icon List

When you run `php plugs make:icon --init`, the followings icons are available:

- `home`, `user`, `settings`, `trash`, `edit`, `plus`, `minus`, `check`, `x-mark`
- `search`, `bell`, `chevron-down`, `chevron-up`, `arrow-left`, `arrow-right`
- `logout`, `login`, `eye`, `eye-slash`, `menu`

---

## Next Steps
Learn about [Flash Notifications](../basics/notifications.md) for premium user feedback.
