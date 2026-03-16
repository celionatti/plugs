# Internal Framework Assets

Plugs comes with built-in scripts and styles that are necessary for certain framework features (like SPA navigation, lazy loading, and rich text editors). To simplify development, these assets are internalized within the framework and served automatically.

## 🚀 Automatic Injection

By default, the framework automatically injects the necessary core scripts into your HTML response if it detects a `</body>` tag and the request is a full page render (not an HTMX partial or a specialized component).

The scripts currently auto-injected are:
- `plugs-spa.js`: Handles Single Page Application transitions, navigation, and **Hybrid Reactivity** (state, events, and transitions).
- `plugs-lazy.js`: Manages lazy loading of images and components.

## 🛠️ Framework Scripts Directive

If you need manual control over where your framework scripts are placed, or if you've suppressed auto-injection, you can use the `@frameworkScripts` directive:

```html
<head>
    <!-- Other meta tags -->
    @frameworkScripts
</head>
```

This directive will output the following script tags:
```html
<script src="/plugs/plugs-spa.js" defer></script>
<script src="/plugs/plugs-lazy.js" defer></script>
```

## 📦 Internal Assets Routing

The framework serves internal assets through dedicated routes. You can reference them directly if needed:

| Asset Path | Description |
|------------|-------------|
| `/plugs/plugs-spa.js` | SPA Bridge & Reactivity Engine |
| `/plugs/plugs-lazy.js` | Lazy loading utility |
| `/plugs/plugs-editor.js` | Rich Text Editor assets |
| `/plugs/plugs-editor.css` | Rich Text Editor styles |

These assets are served directly from the framework's `src/Resources/assets` directory, so you don't need to manually publish them to your `public` folder.

## 🚫 Advanced: Controlling Injection

The framework is smart about when to inject scripts. It will skip injection in the following cases:
- HTMX partial requests (requests with `HX-Request: true`).
- Requests where the view has called `$view->layout(false)`.
- When rendering individual components.
- If the script tag is already detected in the rendered content.
