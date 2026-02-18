# Modern Tag Syntax (V5)

Plugs V5 introduces a modern, HTML-friendly tag syntax for control structures and view logic. This syntax integrates perfectly with IDE highlighting and results in cleaner, more readable templates.

## 🏗️ Layout & Inheritance

Use tags to define your page structure without needing `@` directives.

### Defining A Layout

Anything inside the `<layout>` tag that isn't a named slot is automatically captured as the `content` section.

```html
<layout name="layouts.default">
  <slot:title>Home Page</slot:title>

  <div class="welcome">
    <h1>Welcome to Plugs V5</h1>
    <p>
      This content is automatically injected into the layout's content yield.
    </p>
  </div>
</layout>
```

### Slots & Yields

| Tag                   | Attribute | Description                          |
| :-------------------- | :-------- | :----------------------------------- |
| `<slot name="..." />` | `name`    | Defines a section (Classic syntax).  |
| `<slot:name />`       | -         | V5 shorthand for defining a section. |
| `<yield:name />`      | `default` | Output a section or a default value. |

---

### 🛡️ Forms & Security

Clean HTML tags for security tokens and method spoofing.

```html
<form action="/update" method="POST">
  <csrf />
  <method type="PUT" />

  <input type="text" name="name" />
  <button type="submit">Update</button>
</form>
```

| Tag                         | Description                                                    |
| :-------------------------- | :------------------------------------------------------------- |
| **`<csrf />`**              | Renders the hidden CSRF input field.                           |
| **`<method type="..." />`** | Renders the hidden `_method` field (`PUT`, `PATCH`, `DELETE`). |

---

### 🚦 Control Structures

Semantic tags for flow control. Note that attributes for PHP expressions MUST start with a colon (`:`).

#### Conditionals

```html
<if :condition="$user->isAdmin()">
  <p>Logged in as Administrator</p>
  <elseif :condition="$user->isEditor()" />
  <p>Logged in as Editor</p>
  <else />
  <p>Standard User</p>
</if>

<unless :condition="$maintenanceMode">
  <p>System is Live!</p>
</unless>
```

#### Loops

The `<loop>` tag provides a `$loop` variable just like `@foreach`.

```html
<loop :items="$users" as="$user">
  <div class="user">
    <span class="rank">{{ $loop->iteration }}</span>
    {{ $user->name }}
  </div>
</loop>

<for :init="$i = 0" :condition="$i < 5" :step="$i++">
  <i class="bi bi-star-fill"></i>
</for>
```

---

### 📦 Directives & Stacks

| Tag                          | Attribute       | Description                               |
| :--------------------------- | :-------------- | :---------------------------------------- |
| **`<include view="..." />`** | `view`, `:data` | Include a partial view.                   |
| **`<push:name>`**            | -               | Push content to a stack (scripts/styles). |
| **`<stack:name />`**         | -               | Render a stack.                           |
| **`<fragment name="...">`**  | `name`          | Define a renderable HTMX/Turbo fragment.  |
| **`<teleport to="...">`**    | `to`            | Move content to a different DOM element.  |

---

### 🧪 Error Handling

Easily render validation errors.

```html
<div class="form-group">
  <input type="email" name="email" />
  <error field="email">
    <span class="text-danger">{{ $message }}</span>
  </error>
</div>
```
