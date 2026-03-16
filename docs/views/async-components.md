# Async Components

Async Components allow you to load parts of your page on-demand, either by fetching a URL or rendering a server-side component asynchronously. This is perfect for improving initial page load times or loading data-heavy sections without blocking the main thread.

## URL Loading

You can use the `<async>` tag with a `src` attribute to fetch HTML content from any endpoint.

```html
<async src="/api/stats">
    <div class="loading-state">
        <span class="spinner"></span> Loading statistics...
    </div>
</async>
```

When the page loads, the framework will:
1. Display the initial content (the loading state).
2. Fetch the content from `/api/stats`.
3. Replace the inner content with the response using smooth DOM morphing.

## Component Loading

You can also load server-side components asynchronously using the `component` attribute.

```html
<async component="UserStats" :user-id="$user->id">
    <p>Loading user data...</p>
</async>
```

### Security
All data passed to an async component (like `:user-id`) is **encrypted** on the server before being sent to the client. This prevents users from tampering with the component's initial state.

## Benefits
- **Non-blocking**: The main page renders instantly while heavy components load in the background.
- **Progressive Enhancement**: Provide fallback content that remains visible if the user has JavaScript disabled or the request fails.
- **Zero Configuration**: No need to write manual AJAX calls or state management.
