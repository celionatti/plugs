# Built-in Components

Plugs ships with a library of **15 ready-to-use UI components** that you can use immediately in any view — no file creation required. They cover common UI patterns like alerts, buttons, cards, form inputs, modals, and more.

> [!TIP]
> **Zero setup!** Built-in components work out of the box. Just use `<x-alert>`, `<x-card>`, `<x-button>`, etc. in any view and they render instantly.

---

## How It Works

### Resolution Order

When you reference a component, Plugs resolves it in this order:

1. **Your components** — `resources/views/components/` (always checked first)
2. **Theme components** — `resources/views/themes/{theme}/components/`
3. **Framework built-ins** — the shipped defaults inside the framework

This means you can **override any built-in** by simply creating a file with the same name in your project's `resources/views/components/` directory. Your version always wins.

### Class Resolution

For class-backed components, the same priority applies:

1. `App\Components\*` — your project classes
2. `Plugs\View\Components\*` — framework-provided classes

### Component Syntax

Plugs supports **two syntaxes** for using components:

| Syntax | Example | How It Works |
|---|---|---|
| **PascalCase** (no prefix) | `<Alert type="success">` | Any tag starting with an uppercase letter is treated as a component |
| **`x-` prefix** (lowercase) | `<x-alert type="success">` | The `x-` prefix explicitly marks a tag as a component |

Both syntaxes are fully equivalent and produce the same output.

```html
{{-- These are identical --}}
<Alert type="success" dismissible="true">Saved!</Alert>
<x-alert type="success" dismissible="true">Saved!</x-alert>

{{-- PascalCase works for all built-ins --}}
<Card shadow="lg">Content</Card>
<Button variant="primary">Click</Button>
<Modal id="my-modal" title="Confirm">Are you sure?</Modal>
<Spinner size="lg" />

{{-- Self-closing works with both syntaxes --}}
<Avatar alt="John Doe" />
<x-avatar alt="John Doe" />
```

> [!CAUTION]
> **Lowercase tags without the `x-` prefix do NOT work.** A tag like `<alert>` is treated as regular HTML, not as a component. You must either capitalize it (`<Alert>`) or add the prefix (`<x-alert>`).

```html
{{-- ✅ These work --}}
<Alert type="info">Message</Alert>
<x-alert type="info">Message</x-alert>

{{-- ❌ This does NOT work — treated as plain HTML --}}
<alert type="info">Message</alert>
```

---

## Component Base Class

When building your own class-backed components, you can now extend `Plugs\View\Component` — a lightweight base class designed for standard, server-side components. Unlike `ReactiveComponent`, it has **no JavaScript bridge or state serialization** — it's purely for preparing data before rendering.

```php
namespace App\Components;

use Plugs\View\Component;

class PricingCard extends Component
{
    public string $plan = 'free';
    public float $price = 0;
    public array $features = [];

    public function __construct(
        string $plan = 'free',
        float $price = 0,
        array $features = [],
    ) {
        $this->plan = $plan;
        $this->price = $price;
        $this->features = $features;
    }

    public function render(): string
    {
        return 'pricing-card'; // resolves to components/pricing-card.plug.php
    }
}
```

| Base Class | When to Use |
|---|---|
| *(none — view-only)* | Simple UI pieces with no logic |
| `Plugs\View\Component` | Components that need computed data or validation |
| `Plugs\View\ReactiveComponent` | Interactive components with client-side state sync |

---

## Available Built-in Components

### Quick Reference

| Component | Tag | Key Props |
|---|---|---|
| [Alert](#alert) | `<x-alert>` | `type`, `dismissible` |
| [Badge](#badge) | `<x-badge>` | `type`, `pill`, `size` |
| [Button](#button) | `<x-button>` | `variant`, `size`, `href`, `disabled` |
| [Card](#card) | `<x-card>` | `shadow`, `padding`, `bordered` |
| [Modal](#modal) | `<x-modal>` | `id`, `title`, `size`, `closable` |
| [Dropdown](#dropdown) | `<x-dropdown>` | `label`, `align` |
| [Avatar](#avatar) | `<x-avatar>` | `src`, `alt`, `size`, `fallback` |
| [Input](#input) | `<x-input>` | `name`, `label`, `type`, `error` |
| [Textarea](#textarea) | `<x-textarea>` | `name`, `label`, `rows`, `error` |
| [Select](#select) | `<x-select>` | `name`, `label`, `options`, `error` |
| [Checkbox](#checkbox) | `<x-checkbox>` | `name`, `label`, `checked` |
| [Table](#table) | `<x-table>` | `striped`, `hoverable`, `bordered` |
| [Pagination](#pagination) | `<x-pagination>` | `currentPage`, `totalPages`, `baseUrl` |
| [Spinner](#spinner) | `<x-spinner>` | `size`, `color` |
| [Toast](#toast) | `<x-toast>` | `type`, `message`, `position`, `duration` |

---

## UI Elements

### Alert

A dismissible notification panel with color-coded types.

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `type` | string | `info` | `success`, `danger`, `warning`, `info` |
| `dismissible` | bool | `false` | Show a close button |

**Examples:**

```html
{{-- Basic info alert --}}
<x-alert type="info">This is an informational message.</x-alert>

{{-- Dismissible success alert --}}
<x-alert type="success" dismissible="true">
    Your changes have been saved successfully!
</x-alert>

{{-- Danger alert with HTML content --}}
<x-alert type="danger">
    <strong>Error:</strong> Unable to connect to the database.
</x-alert>

{{-- Warning alert --}}
<x-alert type="warning" dismissible="true">
    Your session will expire in 5 minutes.
</x-alert>
```

---

### Badge

An inline label/status indicator.

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `type` | string | `primary` | `primary`, `secondary`, `success`, `danger`, `warning`, `info` |
| `pill` | bool | `false` | Fully-rounded pill shape |
| `size` | string | `md` | `sm`, `md`, `lg` |

**Examples:**

```html
{{-- Status badges --}}
<x-badge type="success">Active</x-badge>
<x-badge type="danger" pill="true">Urgent</x-badge>
<x-badge type="warning" size="sm">Pending</x-badge>

{{-- Use in a heading --}}
<h2>Users <x-badge type="info">142</x-badge></h2>
```

---

### Button

A styled button supporting multiple variants, sizes, and automatic `<a>`/`<button>` tag switching.

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `variant` | string | `primary` | `primary`, `secondary`, `danger`, `success`, `warning`, `outline`, `ghost` |
| `size` | string | `md` | `sm`, `md`, `lg` |
| `type` | string | `button` | HTML button type (`button`, `submit`, `reset`) |
| `href` | string | `null` | If set, renders as `<a>` instead of `<button>` |
| `disabled` | bool | `false` | Disabled state |

**Examples:**

```html
{{-- Standard buttons --}}
<x-button variant="primary">Save Changes</x-button>
<x-button variant="danger" size="sm">Delete</x-button>
<x-button variant="outline">Cancel</x-button>
<x-button variant="ghost">Skip</x-button>

{{-- Submit button --}}
<x-button variant="success" type="submit" size="lg">
    Create Account
</x-button>

{{-- Link that looks like a button --}}
<x-button href="/docs" variant="secondary">
    Read the Docs
</x-button>

{{-- Disabled state --}}
<x-button variant="primary" disabled="true">Processing...</x-button>
```

---

### Card

A content wrapper with optional header and footer slots.

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `shadow` | string | `md` | `none`, `sm`, `md`, `lg` |
| `padding` | string | `md` | `sm`, `md`, `lg` |
| `bordered` | bool | `true` | Show border |

**Slots:** `header`, `footer`

**Examples:**

```html
{{-- Simple card --}}
<x-card>
    <p>This is a simple card with default styling.</p>
</x-card>

{{-- Card with header and footer --}}
<x-card shadow="lg">
    <slot:header>User Profile</slot:header>

    <p>Name: John Doe</p>
    <p>Email: john@example.com</p>

    <slot:footer>
        <x-button variant="primary" size="sm">Edit Profile</x-button>
    </slot:footer>
</x-card>

{{-- Minimal card --}}
<x-card shadow="none" bordered="false" padding="sm">
    <p>Compact content area.</p>
</x-card>
```

---

### Modal

A dialog/modal popup with backdrop, animation, and click-outside-to-close.

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `id` | string | auto-generated | Unique ID for toggling |
| `title` | string | `''` | Modal title text |
| `size` | string | `md` | `sm`, `md`, `lg`, `xl` |
| `closable` | bool | `true` | Show close button |

**Slots:** `header`, `footer`

**Opening a modal:** Set the element's `display` to `flex` via JavaScript:

```javascript
document.getElementById('my-modal').style.display = 'flex';
```

**Examples:**

```html
{{-- Define the modal --}}
<x-modal id="confirm-modal" title="Confirm Action">
    <p>Are you sure you want to delete this item?</p>

    <slot:footer>
        <x-button variant="ghost" onclick="document.getElementById('confirm-modal').style.display='none'">
            Cancel
        </x-button>
        <x-button variant="danger">Delete</x-button>
    </slot:footer>
</x-modal>

{{-- Trigger button --}}
<x-button variant="danger" onclick="document.getElementById('confirm-modal').style.display='flex'">
    Delete Item
</x-button>

{{-- Large modal with custom header --}}
<x-modal id="edit-modal" size="lg">
    <slot:header>
        <div style="display:flex;align-items:center;gap:8px">
            <span>✏️</span> Edit Record
        </div>
    </slot:header>

    <form>
        <x-input name="title" label="Title" />
        <x-textarea name="body" label="Content" rows="6" />
    </form>

    <slot:footer>
        <x-button variant="primary" type="submit">Save</x-button>
    </slot:footer>
</x-modal>
```

---

### Dropdown

A toggle dropdown menu with click-outside-to-close behavior.

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `label` | string | `Menu` | Button label text |
| `align` | string | `left` | `left`, `right` |

**Examples:**

```html
{{-- Basic dropdown --}}
<x-dropdown label="Actions">
    <a href="/edit">Edit</a>
    <a href="/duplicate">Duplicate</a>
    <a href="/delete">Delete</a>
</x-dropdown>

{{-- Right-aligned dropdown --}}
<x-dropdown label="Account" align="right">
    <a href="/profile">Profile</a>
    <a href="/settings">Settings</a>
    <a href="/logout">Logout</a>
</x-dropdown>
```

---

### Avatar

A user avatar with image support, fallback initials, and graceful degradation when images fail to load.

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `src` | string | `''` | Image URL |
| `alt` | string | `''` | Alt text (also used to generate initials) |
| `size` | string | `md` | `sm` (32px), `md` (40px), `lg` (52px), `xl` (72px) |
| `fallback` | string | auto | Custom fallback initials |

**Examples:**

```html
{{-- Avatar with image --}}
<x-avatar src="/img/users/john.jpg" alt="John Doe" />

{{-- Fallback to initials when no image --}}
<x-avatar alt="Jane Smith" size="lg" />

{{-- Custom initials --}}
<x-avatar fallback="AD" size="xl" />

{{-- Gracefully handles broken images --}}
<x-avatar src="/broken-path.jpg" alt="Bob Builder" />
```

> [!TIP]
> When `src` is provided but the image fails to load, the avatar automatically switches to showing initials derived from the `alt` text.

---

## Form Controls

All form components share a consistent API and styling: label with optional required indicator, error message display, and focus ring animation.

### Input

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `name` | string | `''` | Input name attribute |
| `label` | string | `''` | Label text |
| `type` | string | `text` | HTML input type |
| `error` | string | `''` | Error message |
| `placeholder` | string | `''` | Placeholder text |
| `value` | string | `''` | Default value |
| `required` | bool | `false` | Mark as required |
| `disabled` | bool | `false` | Disable the input |

**Examples:**

```html
{{-- Basic input --}}
<x-input name="email" label="Email Address" type="email" placeholder="you@example.com" required="true" />

{{-- Input with error --}}
<x-input name="username" label="Username" value="jo" error="Username must be at least 3 characters" />

{{-- Password input --}}
<x-input name="password" label="Password" type="password" required="true" />

{{-- Disabled input --}}
<x-input name="id" label="User ID" value="12345" disabled="true" />
```

---

### Textarea

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `name` | string | `''` | Textarea name |
| `label` | string | `''` | Label text |
| `rows` | int | `4` | Number of rows |
| `error` | string | `''` | Error message |
| `placeholder` | string | `''` | Placeholder |
| `value` | string | `''` | Default value |
| `required` | bool | `false` | Mark as required |
| `disabled` | bool | `false` | Disable |

**Examples:**

```html
<x-textarea name="bio" label="Biography" rows="5" placeholder="Tell us about yourself..." />

<x-textarea name="notes" label="Notes" error="Notes are required" required="true" />
```

---

### Select

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `name` | string | `''` | Select name |
| `label` | string | `''` | Label text |
| `options` | array | `[]` | Key-value pairs for options |
| `selected` | string | `''` | Currently selected value |
| `placeholder` | string | `— Select —` | Default disabled option |
| `error` | string | `''` | Error message |
| `required` | bool | `false` | Mark as required |
| `disabled` | bool | `false` | Disable |

**Examples:**

```html
{{-- Static options --}}
<x-select
    name="role"
    label="User Role"
    :options="['admin' => 'Administrator', 'editor' => 'Editor', 'viewer' => 'Viewer']"
    selected="editor"
    required="true"
/>

{{-- Dynamic options from controller --}}
<x-select name="category" label="Category" :options="$categories" :selected="$post->category_id" />

{{-- With error --}}
<x-select name="country" label="Country" :options="$countries" error="Please select a country" />
```

---

### Checkbox

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `name` | string | `''` | Checkbox name |
| `label` | string | `''` | Label text |
| `checked` | bool | `false` | Checked state |
| `value` | string | `1` | Value when checked |
| `disabled` | bool | `false` | Disable |
| `error` | string | `''` | Error message |

**Examples:**

```html
<x-checkbox name="terms" label="I accept the Terms and Conditions" required="true" />
<x-checkbox name="newsletter" label="Subscribe to newsletter" checked="true" />
<x-checkbox name="premium" label="Premium account" disabled="true" />
```

---

## Data Display

### Table

A responsive table wrapper with optional striped rows, hover effect, and borders.

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `striped` | bool | `false` | Alternate row backgrounds |
| `hoverable` | bool | `true` | Highlight on hover |
| `bordered` | bool | `false` | Add cell borders |
| `compact` | bool | `false` | Reduced padding |

**Examples:**

```html
<x-table striped="true">
    <thead>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($users as $user)
        <tr>
            <td>{{ $user->name }}</td>
            <td>{{ $user->email }}</td>
            <td>{{ $user->role }}</td>
            <td><x-badge type="success">Active</x-badge></td>
        </tr>
        @endforeach
    </tbody>
</x-table>
```

---

### Pagination

Page navigation with smart range calculation, ellipsis truncation, and accessible markup.

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `currentPage` | int | `1` | Current active page |
| `totalPages` | int | `1` | Total number of pages |
| `baseUrl` | string | `''` | Base URL for page links |
| `maxVisible` | int | `7` | Maximum page links to show |

**Examples:**

```html
{{-- Basic pagination --}}
<x-pagination :currentPage="$page" :totalPages="$totalPages" baseUrl="/posts" />

{{-- With query string --}}
<x-pagination :currentPage="$page" :totalPages="$total" baseUrl="/search?q=php" />

{{-- Show more pages --}}
<x-pagination :currentPage="$page" :totalPages="$total" baseUrl="/articles" maxVisible="11" />
```

> [!TIP]
> The pagination component automatically generates `?page=N` query parameters appended to your `baseUrl`. If your `baseUrl` already contains query parameters, it uses `&page=N` instead.

---

## Feedback

### Spinner

A CSS-only loading spinner.

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `size` | string | `md` | `sm` (20px), `md` (32px), `lg` (48px) |
| `color` | string | `#6366f1` | Spinner color |

**Examples:**

```html
{{-- Default spinner --}}
<x-spinner />

{{-- Large red spinner --}}
<x-spinner size="lg" color="#ef4444" />

{{-- Inline with text --}}
<div style="display:flex;align-items:center;gap:8px">
    <x-spinner size="sm" /> Loading results...
</div>
```

---

### Toast

A positioned notification that auto-dismisses after a set duration.

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `type` | string | `info` | `success`, `error`, `warning`, `info` |
| `message` | string | `''` | Notification text |
| `position` | string | `top-right` | `top-right`, `top-left`, `bottom-right`, `bottom-left`, `top-center`, `bottom-center` |
| `duration` | int | `5000` | Time in milliseconds before auto-dismiss (0 to disable) |

**Examples:**

```html
{{-- Success toast --}}
<x-toast type="success" message="Changes saved successfully!" />

{{-- Error toast at top-center --}}
<x-toast type="error" message="Failed to upload file." position="top-center" duration="8000" />

{{-- Persistent toast (no auto-dismiss) --}}
<x-toast type="warning" message="Your session is about to expire." duration="0" />
```

> [!IMPORTANT]
> Toasts are rendered at a fixed position on screen. They appear as soon as the page loads. For dynamic toast triggering (e.g., after a form submission), pair them with server-side flash data or use HTMX partials.

---

## Overriding Built-in Components

To override any built-in component, create a file with the same name in your project:

```text
resources/views/components/
├── alert.plug.php       ← overrides built-in alert
├── button.plug.php      ← overrides built-in button
└── card.plug.php        ← overrides built-in card
```

You can also override just one and keep using the rest. The framework built-ins remain as fallbacks for any component you don't override.

### Override with a Class

To override the class logic too, create a class in `app/Components/`:

```php
namespace App\Components;

use Plugs\View\Component;

class Alert extends Component
{
    public string $type = 'info';
    public bool $dismissible = false;
    public string $icon = '';

    public function __construct(string $type = 'info', bool $dismissible = false)
    {
        $this->type = $type;
        $this->dismissible = $dismissible;
        $this->icon = match($type) {
            'success' => '✅',
            'danger'  => '❌',
            'warning' => '⚠️',
            default   => 'ℹ️',
        };
    }

    public function render(): string
    {
        return 'alert';
    }
}
```

---

## Composing Components

Built-in components work together naturally. Here's an example of a complete form built entirely from built-in components:

```html
<x-card shadow="lg">
    <slot:header>Create New User</slot:header>

    <form method="POST" action="/users">
        @csrf

        <x-input name="name" label="Full Name" required="true" placeholder="John Doe" />
        <x-input name="email" label="Email" type="email" required="true" />
        <x-input name="password" label="Password" type="password" required="true" />

        <x-select
            name="role"
            label="Role"
            :options="['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer']"
            required="true"
        />

        <x-textarea name="bio" label="Bio" rows="3" placeholder="Tell us about yourself..." />
        <x-checkbox name="terms" label="I accept the Terms of Service" required="true" />

        <div style="display:flex;gap:10px;margin-top:20px">
            <x-button variant="primary" type="submit">Create User</x-button>
            <x-button variant="ghost" href="/users">Cancel</x-button>
        </div>
    </form>

    <slot:footer>
        <small>All fields marked with * are required.</small>
    </slot:footer>
</x-card>
```
