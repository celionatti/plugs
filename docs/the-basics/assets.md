# Asset Management

The Plugs framework provides a modern workflow for managing your application's frontend assets. You can choose between the powerful **Vite** integration for modern apps or the built-in **AssetManager** for simpler projects.

## ⚡ Vite Integration

Vite is the recommended tool for managing CSS and JavaScript in Plugs. It provides a lightning-fast development experience with Hot Module Replacement (HMR).

### Installation

If your project doesn't have a `package.json`, create one or run:

```bash
npm install
```

Ensure your `vite.config.mjs` is configured to point to your entry points:

```javascript
/* vite.config.mjs */
export default defineConfig({
  build: {
    outDir: "public/build",
    manifest: "manifest.json",
    rollupOptions: {
      input: ["resources/js/app.js", "resources/css/app.css"],
    },
  },
});
```

### Usage in Views

You can use the native `@vite` directive or the `<vite />` HTML tag:

```html
<!-- Single Entry -->
<vite entry="resources/js/app.js" />

<!-- Multiple Entries -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

### Development (Hot Reload)

Run the development server to enable HMR and automatic page reloads:

```bash
npm run dev
```

The framework will automatically detect the Vite server and serve assets from it. If you edit a `.plug.php` template, the page will refresh automatically.

### Production Build

For production, compile your assets into the `public/build` directory:

```bash
npm run build
```

This generates versioned files and a `manifest.json`. The framework uses this manifest to resolve the correct URLs.

### Deployment (Shared Hosting)

On shared hosting where you cannot run Node.js:

1. Run `npm run build` on your **local machine**.
2. Upload the `public/build` directory along with your PHP files.
3. The framework will automatically read from the manifest on the server.

---

## 📦 Static Assets

For simple assets that don't need compilation, you can use the `@asset` directive or the `asset()` helper.

### Usage

```html
<link rel="stylesheet" href="{{ asset('assets/css/global.css') }}" />
<img src="@asset('images/logo.svg')" alt="Logo" />
```

Assets linked this way are served directly from the `public` directory. The framework adds a version hash (`?v=...`) based on the file's content to ensure cache busting.
