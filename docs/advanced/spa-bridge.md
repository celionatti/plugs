# SPA Bridge (v2)

The **SPA Bridge** is a progressive enhancement layer that turns your multi-page PHP application into a Single Page Application with minimal effort. It includes lifecycle hooks, reactive components, and smooth transitions.

---

![SPA Navigation Flow](../assets/spa_flow.png)

## Core Features

1.  **Intercepting Clicks**: Listens for `data-spa="true"` links and loads content via Fetch.
2.  **View Controllers**: Attach JavaScript logic using inline scripts.
3.  **View Transitions**: Native browser support for smooth navigation animations.
4.  **Reactive Components**: Backend-driven UI components with extensive event support.

---

## Inline View Controllers

With **Inline View Controllers**, you can define `mount` and `unmount` logic directly in your PHP view files. This keeps your JavaScript co-located with your PHP logic.

### Usage

Use the `Plugs.view()` helper in a script tag within your content area.

#### 1. Functional Style (Recommended)
This pattern, inspired by modern hooks, runs the function immediately on mount and returns a cleanup function for unmount.

```php
<!-- In resources/views/dashboard.php -->
<div class="dashboard">
    <h1>Hello, <?php echo $user->name; ?></h1>
    <div id="chart"></div>
</div>

<script>
Plugs.view(() => {
    // MOUNT LOGIC
    console.log("Dashboard mounted via SPA!");
    const chart = new Chart('#chart', { ... });
    const userId = <?php echo $user->id; ?>;

    // UNMOUNT LOGIC (Cleanup)
    return () => {
        console.log("Leaving Dashboard...");
        chart.destroy();
    };
});
</script>
```

#### 2. Object Style
Alternatively, pass an object with distinct `mount` and `unmount` methods.

```javascript
Plugs.view({
    mount() {
        this.timer = setInterval(refresh, 1000);
    },
    unmount() {
        clearInterval(this.timer);
    }
});
```

> [!IMPORTANT]
> Ensure `plugs-spa.js` is loaded in your `<head>` or at the top of your `<body>` so that `Plugs` is defined when the inline script runs.

---

## Reactive Component Events

Elements inside a `[data-plug-component]` can trigger backend actions using `p-*` attributes.

### Available Events

| Attribute | Description | Example |
| :--- | :--- | :--- |
| `p-click` | Fired when clicked. | `<button p-click="increment">` |
| `p-change` | Fired on input change. | `<input p-change="search">` |
| `p-submit` | Fired on form submit. | `<form p-submit="save">` |
| `p-blur` | Fired when focus is lost. | `<input p-blur="validate">` |
| `p-keyup` | Fired on keyup. | `<input p-keyup="filter">` |
| `p-intersect` | Fired when visible. | `<div p-intersect="loadMore">` |

### Modifiers

- `p-debounce="ms"`: Delays the event (useful for `p-keyup`).

### Example: Real-time Search

```html
<div data-plug-component="UserSearch">
    <input type="text" 
           p-keyup="search" 
           p-debounce="300" 
           placeholder="Search users...">
    
    <div id="results">
        <!-- Results rendered by backend -->
        <?php foreach($users ?? [] as $user): ?>
            <div class="user-row"><?= $user->name ?></div>
        <?php endforeach; ?>
    </div>
</div>
```

**Backend Implementation (PHP):**

The bridge sends a JSON POST request to `/plugs/component/action`.

```php
// In your ComponentController
public function handleAction() 
{
    // 1. Get Payload
    $input = json_decode(file_get_contents('php://input'), true);
    
    $component = $input['component']; // "UserSearch"
    $action = $input['action'];       // "search"
    $query = $input['payload'];       // The input value

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
```

---

## View Transitions

If the browser supports the **View Transitions API**, the SPA Bridge automatically uses it for smooth cross-fades between pages.

To customize the animation, use CSS:

```css
::view-transition-old(root),
::view-transition-new(root) {
  animation-duration: 0.5s;
}
```

---

## Flash Messages

The bridge automatically listens for the `X-Plugs-Flash` header in responses.

**Backend (PHP):**
```php
header('X-Plugs-Flash: ' . json_encode(['type' => 'success', 'message' => 'Saved!']));
```

**Frontend:**
```javascript
window.addEventListener('plugs:flash', (e) => {
    const { type, message } = e.detail;
    // Show toast notification
    alert(message); 
});
```

---

## Global Configuration

```javascript
window.plugsSPA = new PlugsSPA({
    contentSelector: '#app-content', // Target container
    loaderClass: 'spa-loading',      // Class added to body during load
    prefetch: true                   // Enable/disable prefetching on hover
});
```
