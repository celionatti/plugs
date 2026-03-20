# Asset Management

Plugs provides a streamlined workflow for managing your frontend assets, offering both a high-performance **Vite** integration and a lightweight built-in **Asset Manager**.

---

## 1. Vite Integration (Recommended)

Vite is the default and recommended tool for managing CSS and JavaScript. It provides a lightning-fast development experience with Hot Module Replacement (HMR).

### Installation & Setup
Initialize your dependencies and ensure `vite.config.mjs` is correctly configured:

```bash
npm install
```

### Usage in Views
Inject your assets using the `@vite` directive or the `<vite />` tag:

```html
<!-- Native Directive -->
@vite(['resources/css/app.css', 'resources/js/app.js'])

<!-- Modern Tag Syntax -->
<vite entries="['resources/css/app.css', 'resources/js/app.js']" />
```

### Build Workflow
- **Development**: `npm run dev` (Enables HMR and live reloading).
- **Production**: `npm run build` (Compiles and versions assets in `public/build`).

---

## 2. Built-in Asset Manager

For simpler projects that don't require compilation, you can use the built-in asset helper.

### The `asset()` Helper
Generate a versioned URL from the `public/` directory:

```html
<link rel="stylesheet" href="{{ asset('css/styles.css') }}">
<img src="@asset('img/logo.png')" alt="Logo">
```

### Automatic Cache Busting
The `asset()` helper automatically appends a version hash (e.g., `?v=a1b2c3`) based on the file's modification time, ensuring users always see the latest version of your assets.

---

## 3. Preloading & Pre-fetching

Optimize your page load speed by preloading critical assets:

```html
@push('head')
    <link rel="preload" href="@asset('css/critical.css')" as="style">
@endpush
```

---

## Next Steps
Deep dive into [Advanced View Features](./advanced.md) for streaming and optimization.
