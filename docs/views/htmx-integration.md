# HTMX Integration

The Plugs View system provides first-class support for HTMX and Turbo, enabling partial page updates without full reloads.

## Overview

HTMX allows you to update parts of your page by making AJAX requests and swapping HTML fragments. The View system includes built-in support for:

- **Fragments** - Define template sections that can be rendered independently
- **Teleport** - Move content to different locations in the DOM
- **Smart Rendering** - Automatically detect HTMX requests
- **Fragment Extraction** - Return only the requested fragment

---

## Fragments

### Defining Fragments

Use `@fragment` to mark sections that can be updated independently:

```blade
{{-- views/dashboard.plug.php --}}
@extends('layouts.app')

@section('content')
    <h1>Dashboard</h1>
    
    @fragment('stats')
        <div id="stats" class="stats-grid">
            @foreach($stats as $stat)
                <div class="stat-card">
                    <span class="stat-value">{{ $stat->value }}</span>
                    <span class="stat-label">{{ $stat->label }}</span>
                </div>
            @endforeach
        </div>
    @endfragment
    
    @fragment('activity')
        <div id="activity" class="activity-feed">
            @foreach($activities as $activity)
                <div class="activity-item">{{ $activity->description }}</div>
            @endforeach
        </div>
    @endfragment
@endsection
```

### Rendering Fragments

**In Controller:**

```php
use Plugs\View\FragmentRenderer;

public function refreshStats()
{
    // Check if this is an HTMX request
    if (FragmentRenderer::isHtmxRequest()) {
        // Return only the stats fragment
        return $this->viewEngine
            ->renderFragment('dashboard', 'stats', ['stats' => $this->getStats()]);
    }
    
    // Full page render
    return view('dashboard', [...]);
}
```

**Using View Object:**

```php
return view('dashboard', $data)->fragment('stats');
```

---

## Smart Rendering

The `renderSmart()` method automatically detects HTMX/Turbo requests:

```php
// Automatically returns fragment if HTMX request, full page otherwise
return view('dashboard', $data)->renderSmart();

// Or via engine
return $viewEngine->renderSmart('dashboard', $data);
```

Detection is based on these headers:
- `HX-Request` - HTMX request
- `HX-Target` - Target element ID (used as fragment name)
- `Turbo-Frame` - Turbo Frame ID

---

## HTMX Examples

### Update on Click

```blade
<button 
    hx-get="/api/stats" 
    hx-target="#stats"
    hx-swap="outerHTML"
>
    Refresh Stats
</button>

@fragment('stats')
    <div id="stats">
        {{-- Stats content --}}
    </div>
@endfragment
```

### Polling

```blade
@fragment('notifications')
    <div 
        id="notifications" 
        hx-get="/api/notifications" 
        hx-trigger="every 30s"
        hx-swap="innerHTML"
    >
        {{-- Notification list --}}
    </div>
@endfragment
```

### Form Submission

```blade
<form 
    hx-post="/users" 
    hx-target="#user-list"
    hx-swap="beforeend"
>
    @csrf
    <input name="name" required>
    <button type="submit">Add User</button>
</form>

@fragment('user-list')
    <ul id="user-list">
        @foreach($users as $user)
            <li>{{ $user->name }}</li>
        @endforeach
    </ul>
@endfragment
```

```php
// Controller
public function store(Request $request)
{
    $user = User::create($request->all());
    
    if (FragmentRenderer::isHtmxRequest()) {
        // Return just the new user row
        return view('users._row', ['user' => $user]);
    }
    
    return redirect('/users');
}
```

### Infinite Scroll

```blade
@fragment('posts')
    <div id="posts">
        @foreach($posts as $post)
            <article class="post">{{ $post->title }}</article>
        @endforeach
        
        @if($hasMore)
            <div 
                hx-get="/posts?page={{ $nextPage }}" 
                hx-trigger="revealed"
                hx-swap="outerHTML"
            >
                Loading more...
            </div>
        @endif
    </div>
@endfragment
```

---

## Teleport

Move content to a different location in the DOM:

```blade
{{-- In your view --}}
@teleport('#modals')
    <div class="modal" id="confirm-modal">
        <h3>Confirm Action</h3>
        <p>Are you sure?</p>
        <button>Yes</button>
        <button>No</button>
    </div>
@endteleport

{{-- At end of layout --}}
<div id="modals"></div>

<?php echo $view->getTeleportScripts(); ?>
```

The teleport content is moved via JavaScript after the page loads.

---

## FragmentRenderer API

### Static Methods

```php
use Plugs\View\FragmentRenderer;

// Check request types
FragmentRenderer::isHtmxRequest();      // bool
FragmentRenderer::isTurboFrameRequest(); // bool
FragmentRenderer::isPartialRequest();    // Either HTMX or Turbo

// Get request info
FragmentRenderer::getHtmxTarget();      // Target element ID
FragmentRenderer::getHtmxTrigger();     // Trigger element ID
FragmentRenderer::getTurboFrameId();    // Turbo Frame ID
FragmentRenderer::getRequestedFragment(); // Auto-detected fragment name

// Extract from HTML
$fragment = FragmentRenderer::extractFromHtml($html, 'sidebar');
```

### Instance Methods

```php
$renderer = new FragmentRenderer();

// Fragment capture
$renderer->startFragment('sidebar');
// ... content ...
$renderer->endFragment();

// Teleport
$renderer->startTeleport('#modals');
// ... content ...
$renderer->endTeleport('#modals');

// Retrieve
$content = $renderer->getFragment('sidebar');
$fragments = $renderer->getFragments();
$teleports = $renderer->getTeleports();
$scripts = $renderer->renderTeleportScripts();
```

---

## Best Practices

### 1. Use Semantic IDs

Match fragment names with element IDs:

```blade
@fragment('user-profile')
    <div id="user-profile">...</div>
@endfragment
```

### 2. Handle Both Full and Partial Requests

```php
public function show($id)
{
    $user = User::find($id);
    $view = view('users.show', ['user' => $user]);
    
    // Smart render handles both cases
    return $view->renderSmart();
}
```

### 3. Use Loading States

```blade
<button 
    hx-get="/data"
    hx-indicator="#loading"
>
    Load Data
</button>
<span id="loading" class="htmx-indicator">Loading...</span>
```

### 4. Progressive Enhancement

Always ensure the page works without JavaScript:

```blade
<a href="/users/1" hx-get="/users/1" hx-target="#content">
    View User
</a>
```
