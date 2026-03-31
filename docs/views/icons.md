# Native SVG Icons

Plugs Framework features a native SVG icon system that allows you to manage and render local icons without external dependencies like Font Awesome or CDNs.

---

## 1. Getting Started

The framework looks for icons in the `resources/icons` directory by default. 

1. Create the directory if it doesn't exist: `mkdir resources/icons`
2. Drop any `.svg` files into this folder.
3. Use the `@icon` directive in your templates.

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

## Next Steps
Learn about [Flash Notifications](../basics/notifications.md) for premium user feedback.
