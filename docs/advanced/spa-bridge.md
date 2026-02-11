# SPA Bridge Documentation (v3.0)

The **Plugs SPA Bridge** is a lightweight progressive enhancement layer that turns your multi-page PHP application into a reactive Single Page Application. It provides smooth transitions, reactive backend-driven components, and a declarative API via HTML attributes.

---

## 🚀 Getting Started

### Installation

Generate the bridge asset using the CLI:

```bash
php theplugs make:spa-asset
```

### Global Initialization

Include the script in your layout `<head>`:

```html
<script src="/plugs/plugs-spa.js"></script>
<script>
  // Optional custom initialization
  window.Plugs = new PlugsSPA({
    contentSelector: "#app-content", // Main container
    prefetch: true, // Prefetch links on hover
    viewportPrefetch: true, // Prefetch as they enter viewport
    persistCache: true, // Persistence across refreshes
    cacheMaxSize: 50, // Cache up to 50 pages
    cacheTTL: 300000, // Cache expiry (5 mins)
  });
</script>
```

---

## 📖 Public API (JavaScript)

The bridge is exposed globally as `window.Plugs`.

### `Plugs.view(controller)`

Attaches logic to the current view.

- **Pattern 1 (Functional)**:
  ```javascript
  Plugs.view(() => {
    console.log("Mounted");
    return () => console.log("Unmounted"); // Cleanup
  });
  ```
- **Pattern 2 (Object)**:
  ```javascript
  Plugs.view({
      mount() { ... },
      unmount() { ... }
  });
  ```

### `Plugs.navigate(url, [pushState=true], [target=null])`

Programmatically navigate to a URL.

```javascript
Plugs.navigate("/dashboard");
```

### `Plugs.load(url, targetSelector)`

Load content from a URL directly into a specific element without changing the browser URL.

```javascript
Plugs.load("/api/notifications", "#notif-ui");
```

### `Plugs.prefetch(url)`

Manually trigger a prefetch of a URL into the SPA cache.

---

## 🔗 SPA Navigation (Attributes)

Enhance standard HTML elements for SPA behavior.

| Attribute                    | Usage             | Description                                                  |
| :--------------------------- | :---------------- | :----------------------------------------------------------- |
| `data-spa="true"`            | `<a>`, `<form>`   | Enables SPA handling for this element.                       |
| `data-spa-target="selector"` | `<a>`, `<form>`   | Redirects the response HTML into a specific container.       |
| `p-method="METHOD"`          | `<a>`             | Perform non-GET requests (e.g., `DELETE`, `POST`) on a link. |
| `p-confirm="message"`        | `<a>`, `<button>` | Shows a confirmation dialog before proceeding.               |
| `data-spa-skeleton="type"`   | Target Div        | Shows a placeholder (`card`, `list`, `table`) while loading. |

### Example: Delete Link

```html
<a href="/post/1" data-spa="true" p-method="DELETE" p-confirm="Delete post?">
  Delete
</a>
```

---

## ⚡ Reactive Components (Attributes)

Elements with `data-plug-component` become reactive. All events inside trigger a backend request to `/plugs/component/action`.

### Component Definition

```html
<div data-plug-component="UserList" id="users-container">
  <!-- Content -->
</div>
```

### Event Handlers

| Attribute                | Description                                           |
| :----------------------- | :---------------------------------------------------- |
| `p-click="action"`       | Trigger on click.                                     |
| `p-change="action"`      | Trigger on input/select change.                       |
| `p-submit="action"`      | Trigger on form submission.                           |
| `p-blur="action"`        | Trigger on focus loss.                                |
| `p-keyup="action"`       | Trigger on keyup.                                     |
| `p-keyup.enter="action"` | Trigger only when Enter is pressed.                   |
| `p-intersect="action"`   | Trigger when the element becomes visible (lazy-load). |

### Interaction Modifiers

- **`p-debounce="ms"`**: Delays the event (e.g., `p-keyup` search).
- **`p-loading="selector"`**: Shows/hides a specific loading element during the request.
- **`p-confirm="message"`**: Prevents the action unless confirmed.

### Lifecycle & Automation

- **`p-init="action"`**: Executes an action immediately when the component is mounted.
- **`p-poll="ms"`**: Automatically triggers an action on an interval.
- **`p-poll-action="name"`**: The action to trigger during polling (default: `refresh`).
- **`p-outside="action"`**: Triggers an action when clicking anywhere _outside_ the component.

---

## 🎨 Styles & Transitions

### View Transitions

If supported by the browser, Plugs uses the **View Transitions API**.

```css
::view-transition-old(root),
::view-transition-new(root) {
  animation-duration: 0.3s;
}
```

### Body Classes

During a page load, the body receives a class (`spa-loading` by default) for global styling.

---

## 🛠️ Backend Integration

### Headers Sent by Bridge

- `X-Plugs-SPA`: Always `true`.
- `X-Plugs-Section`: The ID of the target container (if partial load).
- `X-Requested-With`: `XMLHttpRequest`.

### Response Headers

- `X-Plugs-Flash`: A JSON string for toast notifications.
  - `header('X-Plugs-Flash: ' . json_encode(['type' => 'success', 'message' => 'Saved!']));`

### Flash Event

Listen for flash messages in your global JS:

```javascript
window.addEventListener("plugs:flash", (e) => {
  alert(e.detail.message); // Replace with your toast UI
});
```

---

## 🧩 Complete Component Example

```html
<div
  data-plug-component="Search"
  p-init="loadRecent"
  p-outside="closeResults"
  p-loading=".spinner"
>
  <input type="text" p-keyup.enter="search" placeholder="Hit Enter to search" />

  <div class="spinner" style="display:none">Searching...</div>

  <div id="results">
    <!-- Backend renders this -->
  </div>
</div>
```

    // 1. Get Payload
    $input = json_decode(file_get_contents('php://input'), true);

    $component = $input['component']; // "UserSearch"
    $action = $input['action'];       // "search"

    // 2. Handle Action
    if ($component === 'UserSearch' && $action === 'search') {

        // Fetch Filtered Data
        $users = User::where('name', 'LIKE', "%$query%")->get();

        // 3. Render and Return
        // Important: Return the same HTML structure as your initial component
        $html = view('partials/user-search', ['users' => $users]);

        header('Content-Type: application/json');
        echo json_encode(['html' => $html]);
        exit;
    }

}

````

---

## View Transitions

If the browser supports the **View Transitions API**, the SPA Bridge automatically uses it for smooth cross-fades between pages.

To customize the animation, use CSS:

```css
::view-transition-old(root),
::view-transition-new(root) {
  animation-duration: 0.5s;
}
````

---

## Flash Messages

The bridge automatically listens for the `X-Plugs-Flash` header in responses.

**Backend (PHP):**

```php
header('X-Plugs-Flash: ' . json_encode(['type' => 'success', 'message' => 'Saved!']));
```

**Frontend:**

```javascript
window.addEventListener("plugs:flash", (e) => {
  const { type, message } = e.detail;
  // Show toast notification
  alert(message);
});
```

---

## Global Configuration

```javascript
window.plugsSPA = new PlugsSPA({
  contentSelector: "#app-content", // Target container
  loaderClass: "spa-loading", // Class added to body during load
  prefetch: true, // Enable/disable prefetching on hover
  viewportPrefetch: true, // Enable/disable viewport-based prefetching
  persistCache: true, // Enable/disable session-based persistent cache
});
```
