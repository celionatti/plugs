# Views & Templating

The Plugs View Engine is a high-performance templating system that compiles to native PHP. It features a modern HTML-style syntax, context-aware security, and built-in async support.

## 🚀 Getting Started

If you're new to Plugs, start with the basics of rendering and data binding.

- **[View Basics](basics.md)**: Files, rendering, and sharing data.
- **[Modern Tag Syntax (V5)](tags.md)**: **Recommended.** Clean HTML-style syntax for layouts, loops, and forms.
- **[Directives Reference](directives.md)**: Traditional `@` syntax reference.
- **[New Features Reference](reference.md)**: Quick reference for `@csp`, `@id`, `@stream`, `@once`, and `@skeleton`.

---

## 🏗️ Building UI

- **[Components](components.md)**: Reusable PascalCase UI elements with props and slots.
- **[Async Components](async-components.md)**: **New.** On-demand loading of URLs and components.
- **[Fetch Components](fetch-components.md)**: **New.** Automated API states with client-side templates.
- **[Live Components](live-components.md)**: **New.** Reactive PHP components with shorthand events.
- **[Hybrid Reactivity](reactivity.md)**: **New.** Alpine-style client state, events, and transitions.
- **[HTMX Integration](htmx-integration.md)**: Build fast, reactive interfaces with fragments and OOB swaps.
- **[Themes & Customization](themes.md)**: Override views and components for white-labeling or deep customization.
- **[Caching](caching.md)**: High-performance fragment and view caching.

---

## 💡 Key Features

- **PascalCase Components**: `<MyComponent />` style tags for better readability.
- **Context-Aware Escaping**: Automatic XSS protection that understands HTML vs. Script vs. Attributes.
- **Async Resolution**: Resolve promises in parallel directly from your view data.
- **HTMX Native**: Built-in support for fragments, teleports, and SPA navigation.
