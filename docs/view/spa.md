# SPA Bridge & Fragment Loading

The Plugs Framework provides a lightweight Single Page Application (SPA) bridge that allows for seamless page transitions and partial content updates without full browser reloads.

## Table of Contents
1. [Overview](#overview)
2. [Getting Started](#getting-started)
3. [Link Interception](#link-interception)
4. [Fragment Loading](#fragment-loading)
5. [Manual Navigation](#manual-navigation)
6. [Loading States](#loading-states)
7. [Technical Details](#technical-details)

---

## Overview

The SPA bridge works by intercepting internal link clicks and fetching the requested page via the Fetch API. On the server, the `ViewEngine` detects this request and returns only the necessary content (skipping the parent layout).

## Getting Started

The SPA bridge is enabled by including the `plugs-spa.js` script in your layout and identifying the main content area with `id="app-content"`.

```html
<!-- In resources/views/layouts/app.plug.php -->
<main id="app-content">
    @yield('content')
</main>

<script src="/assets/js/plugs-spa.js"></script>
```

## Link Interception

By default, any internal `<a>` tag will be intercepted by the SPA bridge.

### Opting Out
To force a full page reload for a specific link, use `data-spa="false"` or `data-spa-ignore`:

```html
<a href="/logout" data-spa="false">Logout</a>
<a href="/external" data-spa-ignore>External Link</a>
```

## Fragment Loading

Fragment loading allows you to update only a specific part of your page (e.g., a sidebar, a modal, or a notification counter).

### Using HTML Attributes
Add `data-spa-target` to a link to specify which element should be updated:

```html
<!-- The link -->
<a href="/sidebar-update" data-spa-target="#sidebar">
    Refresh Sidebar
</a>

<!-- The target -->
<div id="sidebar">
    @yield('sidebar')
</div>
```

When clicked, the server will return ONLY the content of `@section('sidebar')`, and the bridge will swap it into the `#sidebar` element.

## Form Interception

The SPA bridge automatically intercepts form submissions to prevent full page reloads.

### Basic Usage
Any standard HTML form will be handled by SPA automatically.

```html
<form action="/contact" method="POST">
    @csrf
    <input type="text" name="name">
    <button type="submit">Send Message</button>
</form>
```

### Partial Form Targets
You can submit a form and have the result loaded into a specific element instead of the main content area using `data-spa-target`.

```html
<!-- Submit a subscription and update just the status message -->
<form action="/subscribe" method="POST" data-spa-target="#status-message">
    @csrf
    <input type="email" name="email">
    <button type="submit">Subscribe</button>
</form>

<div id="status-message"></div>
```

### Handling Redirects
If your controller returns a JSON response with a `redirect` key, the SPA bridge will automatically navigate to that URL.

```php
// In your Controller
return response()->json([
    'success' => true,
    'redirect' => '/dashboard'
]);
```

### Opting Out
To submit a form traditionally (full reload), use `data-spa="false"`:

```html
<form action="/upload" method="POST" enctype="multipart/form-data" data-spa="false">
    ...
</form>
```

## Manual Navigation

You can trigger SPA navigation manually through JavaScript using the global `window.plugsSPA` instance.

### `plugsSPA.navigate(url, [pushState], [targetSelector])`
- `url`: The URL to fetch.
- `pushState`: (Boolean) Whether to update the browser URL bar (default `true`).
- `targetSelector`: (String) The CSS selector of the element to update (default `#app-content`).

### `plugsSPA.load(url, targetSelector)`
A shorthand for loading a fragment without updating the browser URL:

```javascript
plugsSPA.load('/api/notifications', '#notif-count');
```

## Loading States

The bridge adds a `.spa-loading` class to the `<body>` during navigation. You can use this to add visual feedback:

```css
body.spa-loading {
    cursor: wait;
}

body.spa-loading #app-content {
    opacity: 0.5;
    transition: opacity 0.3s;
}
```

## Technical Details

### Request Headers
The bridge sends the following headers with every request:
- `X-Plugs-SPA: true`
- `X-Requested-With: XMLHttpRequest`
- `X-Plugs-Section: [section-name]` (Only when `data-spa-target` is used)

### Server Handling
The `SPAMiddleware` detects these headers and configures the `ViewEngine` automatically:
1. Sets `suppressLayout(true)` to skip parent rendering.
2. If `X-Plugs-Section` is present, it tells the engine to return ONLY that specific section.

### Script Execution
Scripts within the fetched HTML are automatically executed when the content is injected into the DOM.

## Production Features

The SPA Bridge includes several features designed for a premium user experience and production reliability:

### 1. Progress Bar
A slim, blue progress bar automatically appearing at the top of the viewport during navigation to provide immediate visual feedback.

### 2. Form Interception
Forms are automatically intercepted and submitted via AJAX if they don't have `data-spa="false"`. This supports seamless form submissions without page reloads.

### 3. Link Prefetching & Caching
The bridge prefetches internal links on hover (after a 100ms delay) and stores the content in an in-memory cache. This makes many page transitions feel nearly instant.

### 4. Title & Meta Sync
The bridge automatically extracts `<title>` tags from the server's partial response and updates the browser tab accordingly.

### 5. Smart Script Execution
When new content is injected, the bridge re-executes all `<script>` tags found within that content to ensure interactivity works as expected.

## Advanced Usage

### Ignoring Links or Forms
To prevent SPA from handling a specific link or form, use the `data-spa="false"` or `data-spa-ignore` attribute:

```html
<a href="/login" data-spa="false">Traditional Login</a>
<form action="/upload" data-spa="false">...</form>
```

### Technical Notes for Developers
- **ViewCompiler Fix**: The framework now supports nested parentheses in conditional directives (e.g., `@if(isset($var))`), which is critical for complex SPA views.
- **Header Synchronization**: The server responds with only the requested section when `X-Plugs-SPA` and `X-Plugs-Section` are present, but also prepends the `<title>` tag for synchronization.

## Verification Results

| Feature | Status | Verification Method |
| :--- | :--- | :--- |
| **Progress Bar** | ✅ Active | Browser Visual Confirmation |
| **Form Interception** | ✅ Active | Tested via `test-prod-spa` POST |
| **Title Sync** | ✅ Active | Dynamic tab update on navigation |
| **Link Prefetching** | ✅ Active | Background fetch on hover |
| **Nested Directives** | ✅ Fixed | `@if(isset(...))` compiles correctly |

![Final Production SPA Walkthrough](file:///C:/Users/celionatti/.gemini/antigravity/brain/4d1bf2c1-c304-4a34-9fbb-747754d5066a/spa_production_features_final_v2_1768357777190.webp)
