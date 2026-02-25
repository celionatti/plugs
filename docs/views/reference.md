# New Directives & Tags Reference

This guide provides a quick reference for the latest directives and tags added to the Plugs View system.

## 🛡️ Security Directives

### `@csp` / `<csp />`

Automatically generates a `Content-Security-Policy` meta tag with secure defaults.

- **Directive:** `@csp`
- **Tag:** `<csp />`
- **Purpose:** Prevents XSS by restricting where scripts and other resources can be loaded from.

### `@id` / `<id />`

Safely generates and escapes a value for use as an HTML `id` attribute.

- **Directive:** `<div id="@id($username)">...</div>`
- **Tag:** `<id :value="$username" />`
- **Purpose:** Sanitizes strings by removing characters that are illegal in HTML IDs or dangerous in CSS selectors (only allows `a-z`, `0-9`, `_`, `-`).
- **When to use:** Use this whenever you are generating dynamic IDs based on user input or database records to ensure the resulting ID is valid and safe.

### `@sanitize`

Hardened HTML sanitization that strips dangerous event handlers (e.g., `onclick`) and unsafe protocols (e.g., `javascript:`).

- **Usage:** `@sanitize($userBio)`
- **Purpose:** Provides a safe way to render user-provided HTML without risking XSS.

---

## 🚀 Performance & Streaming

### `@stream` / `<stream />`

Renders a view and flushes it to the browser in chunks.

- **Directive:** `@stream('partials.large-list', ['items' => $items])`
- **Tag:** `<stream view="partials.large-list" :items="$items" />`
- **Purpose:** Improves perceived performance for large pages by allowing the browser to start rendering the page before it's fully generated.

### `@once` / `<once>`

Ensures a block of code is only rendered one time, even if the template is included multiple times.

- **Directive:** `@once('key') ... @endonce`
- **Tag:** `<once key="key">...</once>`
- **Purpose:** Perfect for including one-off scripts, styles, or meta tags that shouldn't be duplicated in the final HTML.

---

## ✨ UI & Utilities

### `@skeleton` / `<skeleton />`

Renders a CSS-animated skeleton loader placeholder.

- **Directive:** `@skeleton('avatar', '50px')`
- **Tag:** `<skeleton type="avatar" width="50px" />`
- **Presets:** `text`, `avatar`, `image`, `button`. Add `-dark` suffix for dark mode variants (e.g., `text-dark`).
- **Purpose:** Provides a better user experience during data loading states.

### `@error` / `<error>`

Streamlined way to display validation error messages.

- **Directive:** `@error('email') ... @enderror`
- **Tag:** `<error field="email">...</error>`
- **Variable:** Inside the block, a `$message` variable is automatically available.
- **Purpose:** Reduces boilerplate when displaying form validation feedback.
