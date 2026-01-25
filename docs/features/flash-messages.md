# Flash Messages

The Plugs framework features a powerful, premium flash messaging system built on top of `Plugs\Utils\FlashMessage`. It handles one-time alerts with a modern UI, automatic cleanup, and support for themes and animations.

## How it Works

Flash messages are stored in the user's session for exactly one request. When the next page loads, they are rendered and then automatically removed. This is perfect for success notifications after form submissions.

---

## 🛠️ Setting Messages (Controllers)

There are two primary ways to set flash messages in your controllers.

### 1. Fluent Redirects (Recommended)
You can chain flash messages directly onto your redirect responses.

```php
// Simple success message
return redirect('/dashboard')->withSuccess('Profile updated successfully!');

// Success message with a custom title
return redirect('/dashboard')->withSuccess('User role changed.', 'Admin Task');

// Other message types
return back()->withError('Invalid credentials.', 'Auth Failed');
return back()->withWarning('Disk space low.', 'System');
return back()->withInfo('New version available.', 'Update');
```

### 2. The `flash()` Helper
For situations where you aren't performing a redirect immediately (e.g., rendering a view directly), use the global helper.

```php
flash('success', 'Your message here', 'Optional Title');
```

---

## 🎨 Rendering Messages (Views)

The system is designed to be "plug-and-play" with a single component.

### 1. The Easy Way: `<x-flash />`
Simply add this tag to your main layout file (usually `resources/views/layouts/app.plug.php`), preferably just before the closing `</body>` tag.

```html
    <!-- ... Rest of your layout ... -->
    <x-flash />
</body>
</html>
```

This will automatically render all pending messages using the premium **OKLCH-powered** design with glassmorphism and animations.

### 2. View Directives
For custom logic, you can use the `@session` and `@flash` blade-style directives.

```html
@session('success')
    <div class="custom-alert">
        <strong>Yay!</strong> @flash('success')
    </div>
@endsession
```

> [!NOTE]
> `@session('key')` uses **Non-destructive Peeking**. It checks if the message exists without clearing it, allowing `<x-flash />` to still render it later if needed.

---

## ✨ Features

### Modern Color System
The system uses **OKLCH** color values (`oklch()`). This ensures that colors look consistent and vibrant across all monitors and automatically provides high-contrast results in both Light and Dark mode.

### Automatic Cleanup
You never have to worry about cleaning up old messages.
- The framework clears old form input automatically after a GET request.
- The flash component handles DOM removal via JavaScript after 8 seconds.

### Animations
Messages use a staggered "Bounce-In" effect from the side and a "Gravity-Out" effect when dismissed. If multiple messages are present, they will dismiss one by one with a small delay for a smooth experience.

---

## ⚙️ Advanced Configuration (FlashMessage.php)

You can customize the behavior globally by modifying the `$renderOptions` in `src/Utils/FlashMessage.php`:

| Option | Default | Description |
|--------|---------|-------------|
| `auto_dismiss` | `true` | Automatically hide messages after a delay. |
| `dismiss_delay`| `8000` | Milliseconds before auto-dismissing. |
| `animation` | `'plugs-bounce'` | Entrance animation class. |
| `show_icon` | `true` | Toggle the display of type icons. |
| `position` | `'fixed'` | `'fixed'` for overlays, `'static'` for inline. |
| `include_styles`| `true` | Whether to inject the embedded CSS. |
