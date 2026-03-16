# Fetch Components

Fetch Components provide a high-level way to handle API interactions directly in your views. They come with built-in state management for loading and success states, along with a lightweight client-side template engine.

## Basic Usage

The `<fetch>` tag simplifies fetching JSON data and rendering it without writing a single line of custom JavaScript.

```html
<fetch url="/api/products">
    <loading>
        <div class="alert alert-info">
            Searching for amazing products...
        </div>
    </loading>

    <success>
        <div class="product-grid">
            @for product in products
                <div class="product-card">
                    <h4>{{ product.name }}</h4>
                    <p>{{ product.description }}</p>
                    <span class="price">${{ product.price }}</span>
                </div>
            @endfor
        </div>
    </success>
</fetch>
```

## How it Works

1. **Initial State**: The framework renders the `<loading>` block first.
2. **Data Fetching**: The `plugs-spa.js` script fetches data from the provided URL using the `fetch` API.
3. **Template Rendering**: Once the JSON arrives, the framework renders the content inside the `<success>` block using the local data.
4. **DOM Update**: The results are morphed into the DOM, replacing the loading state.

## Client-Side Template DSL

The success block supports a simple, directive-like syntax:

| Syntax | Description |
|--------|-------------|
| `{{ var }}` | Interpolates a variable from the response |
| `@for item in list` | Iterates over an array in the response |
| `@if(condition)` | Conditionally renders content based on a value |

## Key Features

- **Automatic Transitions**: Seamlessly switches between loading and success states.
- **Client-Side Templating**: No need to rely on heavy JS frameworks for simple data display.
- **Efficient Updates**: Uses DOM morphing to ensure updates are fast and preserve element state.
