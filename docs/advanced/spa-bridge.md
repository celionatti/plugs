# SPA BRIDGE

The **SPA Bridge** is a progressive enhancement layer that turns your multi-page PHP application into a Single Page Application with minimal effort.

---

![SPA Navigation Flow](file:///C:/Users/celionatti/.gemini/antigravity/brain/a247115d-000b-4e87-b889-f91296d843e5/spa_flow_diagram_1769081120436.png)

## How it Works

The SPA Bridge works by:
1.  **Intercepting Clicks**: It listens for clicks on links with the `data-spa="true"` attribute.
2.  **AJAX Loading**: Instead of a full page reload, it fetches the content via the Fetch API.
3.  **DOM Swapping**: It replaces the content of your main application container (default: `#app-content`) with the new HTML.
4.  **History Management**: It updates the browser's URL and handles back/forward buttons using the History API.

---

## Global Configuration

The SPA Bridge is automatically initialized if the script is included in your layout. You can customize its behavior by passing options to the constructor:

```javascript
// Example custom initialization in your global.js
window.plugsSPA = new PlugsSPA({
    contentSelector: '#main-content', // Target container
    loaderClass: 'is-loading',        // Class added to body during load
    prefetch: true                    // Enable/disable prefetching on hover
});
```

---

## Usage Directives

### Links
Turn any link into an SPA navigation:

```html
<a href="/about" data-spa="true">About Us</a>
```

### Forms
Submit forms via AJAX and update the page content:

```html
<form action="/contact" method="POST" data-spa="true">
    @csrf
    <input type="text" name="name">
    <button type="submit">Submit</button>
</form>
```

### Targeted Updates
You can specify a different target container for a specific link or form using `data-spa-target`:

```html
<!-- Load only a modal content -->
<a href="/login-form" data-spa="true" data-spa-target="#modal-body">Open Login</a>
```

---

## Performance: Prefetching

The SPA Bridge includes built-in prefetching. When a user hovers over a link with `data-spa="true"`, the bridge starts loading the content in the background. This makes the eventual click feel nearly instantaneous.

---

## Layout Detection & Full Reloads

The SPA Bridge is smart enough to know when a full page reload is necessary. If the server returns a response that uses a different layout than the current one (detected via `<meta name="plugs-layout">`), the bridge will automatically perform a full window redirect to ensure all assets are correctly loaded.

---

## Visual Feedback

While navigating, the bridge:
1.  Adds the `spa-loading` class to the `<body>`.
2.  Updates a global `#spa-progress-bar`.
3.  Fades the target content area to `0.5` opacity.

You can style these elements to match your application's brand.
