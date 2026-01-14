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
