# AI Agent Optimization

Plugs includes built-in middleware to optimize your application's responses for AI agents (crawlers, LLMs, and search bots).

## AIOptimizeMiddleware

This middleware identifies requests from known AI agents and dynamically alters the response to be more "AI-friendly."

### Key Features

#### 1. Token Reduction (HTML Stripping)

When an AI agent is detected, the middleware automatically strips:

- `<script>` tags
- `<style>` tags
- HTML comments

This significantly reduces the total payload size, saving costs for the AI crawler and ensuring the model focuses on the core content.

#### 2. Text-Only Responses

If an AI agent provides an `Accept: text/plain` header, the middleware will replace the entire HTML response with a clean, Markdown-like text representation using `strip_tags()`.

#### 3. Optimized Meta Tags

Adds `X-AI-Optimized: true` and `X-Robots-Tag: index, follow` headers to signal that the content is already pre-processed for AI consumption.

---

## Configuration

The middleware identifies AI agents based on User-Agent strings and specialized headers. You can customize the behavior in `src/Http/Middleware/AIOptimizeMiddleware.php`.

### Enabling Optimization

Add the middleware to your `web` group in `Plugs.php` or your Router groups:

```php
$router->group(['middleware' => ['ai_optimize']], function($router) {
    $router->get('/blog/{slug}', [BlogController::class, 'show']);
});
```

> [!TIP]
> This is especially useful for document-heavy sites or documentation pages where you want AI crawlers to index your content efficiently without processing complex CSS/JS.
