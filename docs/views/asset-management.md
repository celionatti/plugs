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

### Unified Build Workflow
Vite now acts as the primary orchestrator for the **Plugs CSS Engine**. You no longer need to run separate watchers.

- **Development**: `npm run dev`
    - Starts the Vite server (with HMR).
    - Automatically builds Plugs Utility CSS on every template change.
- **Production**: `npm run build`
    - Compiles, versions, and minifies all JS and CSS assets.
    - Performs a final production-grade Plugs CSS build.

---

## 2. Production Optimization

The framework provides built-in tools for maximizing asset performance in production environments.

### Asset Pre-compression
Generating **Gzip** and **Brotli** variants of your assets allows your web server (like Nginx) to serve them directly without on-the-fly compression, saving CPU time and reducing load times.

Run this command after your Vite build:
```bash
php theplugs assets:compress
```

- **Features**: Scans `public/build` for `.js`, `.css`, `.svg`, and `.html` files.
- **Output**: Generates `.gz` and `.br` variants for each file.

---

## 3. Built-in Asset Manager

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
