# Plugs CSS Engine

Plugs includes a built-in **utility-first CSS engine** — a zero-dependency alternative to Tailwind CSS. Write utility classes directly in your templates, then compile them into a single optimized stylesheet.

---

## Quick Start

### 1. Add the Directive

Include the compiled stylesheet in your layout using the `@plugcss` directive:

```html
<head>
    <meta charset="UTF-8">
    <title>My App</title>
    @plugcss
</head>
```

This outputs a cache-busted `<link>` tag pointing to `/build/plugs.css`.

### 2. Write Utility Classes

Use familiar utility classes in your templates:

```html
<div class="flex items-center justify-between p-4 bg-blue-500 text-white rounded-lg shadow-md">
    <h2 class="text-xl font-bold">Welcome</h2>
    <button class="px-4 py-2 bg-white text-blue-500 rounded hover:bg-blue-50 transition">
        Get Started
    </button>
</div>
```

### 3. Build the CSS

Run the build command to scan your templates and generate the stylesheet:

```bash
php theplugs css:build
```

For development, use watch mode to rebuild automatically on file changes:

```bash
php theplugs css:build --watch
```

---

## How It Works

The engine follows a three-phase pipeline:

1. **Scan** — The `ClassExtractor` reads all `.plug.php`, `.php`, and `.html` templates and extracts class names from `class="..."` attributes, `@class(...)` directives, and `:class="..."` bindings.

2. **Generate** — The `UtilityGenerator` maps each class name (e.g., `bg-red-500`, `p-4`, `text-lg`) to its corresponding CSS rules.

3. **Compile** — The `CssCompiler` assembles all rules, wraps responsive and state variants in the appropriate `@media` queries and pseudo-selectors, optionally minifies, and writes the output file.

---

```php
return [
    'enabled'          => true,
    'output'           => 'public/build/plugs.css',
    'minify'           => true,
    'preflight'        => true,           // Include CSS reset
    'dark_mode'        => 'media',        // 'media' or 'class'
    'scan_paths'       => [
        'resources/views',
        'modules',
        'app/Components',
    ],
    'scan_extensions'  => ['.plug.php', '.php', '.html'],
    'safelist'         => [],             // Always include these classes
    'blocklist'        => [],             // Never include these classes
    'breakpoints'      => [
        'sm' => '640px', 'md' => '768px', 'lg' => '1024px',
        'xl' => '1280px', '2xl' => '1536px',
    ],
    'colors'           => [],             // Custom colors (see below)
];
```

### Framework Defaults & Environment Variables

If the `config/css.php` file is missing, the framework falls back to the values defined in `Plugs\Config\DefaultConfig`. You can override many of these settings directly via your `.env` file without modifying the PHP configuration:

| Setting | Environment Variable | Default Value |
|---|---|---|
| **Engine Enabled** | `CSS_ENABLED` | `true` |
| **Output Path** | `CSS_OUTPUT` | `public/build/plugs.css` |
| **Minification** | `CSS_MINIFY` | `true` (in production) |
| **CSS Reset** | `CSS_PREFLIGHT` | `true` |
| **Dark Mode** | `CSS_DARK_MODE` | `media` |
| **Scan Paths** | `CSS_SCAN_PATHS` | `resources/views,modules,app/Components` |
| **Scan Extensions** | `CSS_SCAN_EXTENSIONS` | `.plug.php,.php,.html` |

> [!TIP]
> Using environment variables is the recommended way to toggle the CSS engine on/off or change the output path across different environments (e.g., local vs. CI).

### Custom Colors

Add your own brand colors to `config/css.php`:

```php
'colors' => [
    'brand' => [
        50  => '#f0f9ff',
        100 => '#e0f2fe',
        500 => '#0ea5e9',
        700 => '#0369a1',
        900 => '#0c4a6e',
    ],
],
```

Then use them like any other color: `bg-brand-500`, `text-brand-700`, `hover:border-brand-200`.

### Dark Mode

Two strategies are supported:

- **`media`** (default): Uses `@media (prefers-color-scheme: dark)` — respects the user's OS setting.
- **`class`**: Uses a `.dark` class on the root element — gives you full control via JavaScript.

```php
'dark_mode' => 'class',
```

```html
<!-- Toggle dark mode with JS -->
<html class="dark">
    <body class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
```

---

## CLI Commands

### `css:build`

Scan templates and compile the CSS output.

```bash
php theplugs css:build              # Standard build
php theplugs css:build --watch      # Watch mode (auto-rebuild on changes)
php theplugs css:build --minify     # Force minification
php theplugs css:build --no-minify  # Force no minification
php theplugs css:build --verbose    # List all generated classes
```

Alias: `php theplugs css`

### `css:clear`

Remove the generated CSS file.

```bash
php theplugs css:clear
```

---

## Utility Class Reference

### Spacing

Spacing uses a consistent scale where each unit = `0.25rem` (4px).

| Class | CSS | Example |
|---|---|---|
| `p-{n}` | `padding: {n × 0.25}rem` | `p-4` → `padding: 1rem` |
| `px-{n}`, `py-{n}` | Horizontal / Vertical padding | `px-6` → `padding-left: 1.5rem; padding-right: 1.5rem` |
| `pt-{n}`, `pr-{n}`, `pb-{n}`, `pl-{n}` | Individual sides | `mt-8` → `margin-top: 2rem` |
| `m-{n}`, `mx-{n}`, `my-{n}`, `mt-{n}` ... | Margin variants | `mx-auto` → `margin-left: auto; margin-right: auto` |
| `gap-{n}` | Flex/Grid gap | `gap-4` → `gap: 1rem` |
| `space-x-{n}`, `space-y-{n}` | Space between children | `space-y-2` |

**Negative values:** Prefix with `-` for negative spacing: `-mt-4`, `-ml-2`, `-translate-x-4`.

Available scale: `0, px, 0.5, 1, 1.5, 2, 2.5, 3, 3.5, 4, 5, 6, 7, 8, 9, 10, 11, 12, 14, 16, 20, 24, 28, 32, 36, 40, 44, 48, 52, 56, 60, 64, 72, 80, 96`

---

### Typography

```html
<!-- Font size -->
<p class="text-sm">Small</p>
<h1 class="text-4xl font-bold">Heading</h1>

<!-- Weight -->
<span class="font-light">Light</span>
<strong class="font-extrabold">Extra Bold</strong>

<!-- Style & decoration -->
<em class="italic">Italic</em>
<span class="underline">Underlined</span>
<span class="line-through">Strikethrough</span>

<!-- Alignment -->
<p class="text-center">Centered</p>
<p class="text-justify">Justified</p>

<!-- Transform -->
<span class="uppercase">uppercased</span>
<span class="capitalize">capitalized words</span>

<!-- Truncation -->
<p class="truncate">This text will be truncated with an ellipsis...</p>

<!-- Font family -->
<code class="font-mono">monospaced</code>
```

Sizes: `text-xs`, `text-sm`, `text-base`, `text-lg`, `text-xl`, `text-2xl` through `text-9xl`

Weights: `font-thin` (100) through `font-black` (900)

---

### Colors

The engine ships with **22 named colors × 11 shades** (50–950), using the modern OKLCH color space with automatic hex fallbacks for older browsers.

```html
<!-- Text colors -->
<p class="text-red-500">Error message</p>
<p class="text-green-600">Success message</p>

<!-- Background colors -->
<div class="bg-blue-100">Light blue background</div>
<div class="bg-gray-900">Dark background</div>

<!-- Border colors -->
<input class="border border-gray-300 focus:border-blue-500">

<!-- With opacity -->
<div class="bg-red-500/80">80% opaque red</div>

<!-- Special colors -->
<div class="bg-white text-black">...</div>
<div class="bg-transparent">...</div>
```

**Available colors:** `slate`, `gray`, `zinc`, `neutral`, `stone`, `red`, `orange`, `amber`, `yellow`, `lime`, `green`, `emerald`, `teal`, `cyan`, `sky`, `blue`, `indigo`, `violet`, `purple`, `fuchsia`, `pink`, `rose`

**Shades:** `50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950`

**Color fallback strategy:**
```css
/* Generated output for bg-red-500 */
.bg-red-500 {
    background-color: #ef4444;              /* Hex fallback */
    background-color: oklch(0.637 0.237 25.331); /* Modern OKLCH */
}
```

---

### Layout

```html
<!-- Flexbox -->
<div class="flex items-center justify-between gap-4">
    <div class="flex-1">Grows</div>
    <div class="flex-none">Fixed</div>
</div>

<!-- Grid -->
<div class="grid grid-cols-3 gap-6">
    <div class="col-span-2">Wide</div>
    <div>Normal</div>
</div>

<!-- Display -->
<span class="block">Block element</span>
<div class="hidden">Hidden element</div>
<div class="inline-flex">Inline flex</div>
```

---

### Sizing

```html
<div class="w-full">Full width</div>
<div class="w-1/2">Half width</div>
<div class="h-screen">Full viewport height</div>
<div class="max-w-lg mx-auto">Centered container</div>
<div class="size-12">12×12 square (width + height)</div>
<img class="w-[200px] h-[150px]" />  <!-- Arbitrary values -->
```

---

### Borders & Radius

```html
<div class="border border-gray-300 rounded-lg">Rounded card</div>
<div class="border-2 border-dashed border-blue-500">Dashed border</div>
<img class="rounded-full" />  <!-- Circle -->
<div class="divide-y divide-gray-200">  <!-- Dividers between children -->
    <div>Item 1</div>
    <div>Item 2</div>
</div>
```

---

### Effects

```html
<div class="shadow-lg">Large shadow</div>
<div class="shadow-inner">Inner shadow</div>
<div class="opacity-50">Half transparent</div>
<div class="blur-sm">Blurred</div>
<div class="backdrop-blur-md">Backdrop blur</div>
```

---

### Transitions & Animations

```html
<button class="transition duration-300 ease-in-out hover:scale-105 hover:shadow-lg">
    Animated Button
</button>

<div class="transition-colors duration-200 hover:bg-blue-600">
    Color transition
</div>
```

---

### Transforms

```html
<div class="scale-110">Scaled up 10%</div>
<div class="rotate-45">Rotated 45°</div>
<div class="-rotate-12">Rotated -12°</div>
<div class="translate-x-4">Moved right 1rem</div>
<div class="-translate-y-2">Moved up 0.5rem</div>
```

---

### Positioning

```html
<div class="relative">
    <div class="absolute top-0 right-0 z-10">Badge</div>
</div>

<nav class="sticky top-0 z-50">Sticky navbar</nav>
<div class="fixed bottom-4 right-4">Floating button</div>
```

---

## Responsive Design

Use breakpoint prefixes to apply styles at specific screen widths (mobile-first):

| Prefix | Min Width | Equivalent |
|---|---|---|
| `sm:` | 640px | `@media (min-width: 640px)` |
| `md:` | 768px | `@media (min-width: 768px)` |
| `lg:` | 1024px | `@media (min-width: 1024px)` |
| `xl:` | 1280px | `@media (min-width: 1280px)` |
| `2xl:` | 1536px | `@media (min-width: 1536px)` |

```html
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <!-- 1 column on mobile, 2 on tablet, 3 on desktop -->
</div>

<div class="p-4 md:p-8 lg:p-12">
    <!-- Responsive padding -->
</div>

<div class="text-sm md:text-base lg:text-lg">
    <!-- Responsive font size -->
</div>
```

---

## State Variants

Apply styles conditionally based on element state:

| Variant | Selector | Usage |
|---|---|---|
| `hover:` | `:hover` | `hover:bg-blue-600` |
| `focus:` | `:focus` | `focus:ring-2` |
| `active:` | `:active` | `active:scale-95` |
| `disabled:` | `:disabled` | `disabled:opacity-50` |
| `first:` | `:first-child` | `first:mt-0` |
| `last:` | `:last-child` | `last:mb-0` |
| `odd:` | `:nth-child(odd)` | `odd:bg-gray-50` |
| `even:` | `:nth-child(even)` | `even:bg-gray-100` |
| `focus-within:` | `:focus-within` | `focus-within:ring-2` |
| `placeholder:` | `::placeholder` | `placeholder:text-gray-400` |
| `dark:` | Dark mode | `dark:bg-gray-900` |
| `group-hover:` | `.group:hover` | See below |

### Group Hover

Style a child when a parent is hovered:

```html
<div class="group cursor-pointer">
    <h3 class="group-hover:text-blue-500 transition-colors">Title</h3>
    <p class="group-hover:opacity-100 opacity-70 transition-opacity">Description</p>
</div>
```

---

## Arbitrary Values

For one-off values not in the default scale, use bracket syntax:

```html
<div class="w-[200px]">Exact width</div>
<div class="h-[calc(100vh-64px)]">Computed height</div>
<div class="bg-[#1a1a2e]">Custom hex color</div>
<div class="text-[1.35rem]">Custom font size</div>
<div class="p-[10px]">Custom padding</div>
<div class="gap-[30px]">Custom gap</div>
<div class="max-w-[1200px]">Custom max-width</div>
<div class="top-[50%]">Custom positioning</div>
```

---

## Preflight (CSS Reset)

By default, the engine includes a modern CSS reset called **Preflight** (based on `modern-normalize`). It provides:

- `box-sizing: border-box` on all elements
- Margin reset on headings, paragraphs, lists
- Sensible defaults for images (block display, `max-width: 100%`)
- Form element normalization
- Typography baseline

To disable it:

```php
// config/css.php
'preflight' => false,
```

---

## Safelist & Blocklist

### Safelist
Force-include classes that aren't in your templates (e.g., dynamically generated classes):

```php
'safelist' => [
    'bg-red-500',
    'text-green-600',
    'hover:bg-blue-700',
    'sm:grid-cols-4',
],
```

### Blocklist
Prevent specific classes from being generated:

```php
'blocklist' => [
    'container',    // Skip if you use your own container class
],
```

---

## Production Workflow

```bash
# Build minified CSS for production
php theplugs css:build --minify

# Clear cached CSS
php theplugs css:clear
```

Add `css:build` to your deployment pipeline alongside `view:cache` and `optimize`:

```bash
php theplugs optimize
php theplugs view:cache
php theplugs css:build
```

---

## Next Steps

- Combine with [Vite Integration](./asset-management.md) for JavaScript bundling
- Explore [Components](./components.md) for reusable UI elements
- Review [Themes](./themes.md) for layout and visual identity
